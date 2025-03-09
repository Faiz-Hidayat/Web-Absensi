<?php
require_once '../../config/database.php';

// Check if student is logged in
if (!isset($_SESSION['siswa_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get student data
$siswa_id = $_SESSION['siswa_id'];
$today = date('Y-m-d');

// Get student attendance summary - ADD APPROVAL STATUS FILTER
$sql = "SELECT 
            COUNT(CASE WHEN status = 'Hadir' AND approval_status = 'Approved' THEN 1 END) as hadir,
            COUNT(CASE WHEN status = 'Sakit' AND approval_status = 'Approved' THEN 1 END) as sakit,
            COUNT(CASE WHEN status = 'Izin' AND approval_status = 'Approved' THEN 1 END) as izin,
            COUNT(CASE WHEN status = 'Terlambat' AND approval_status = 'Approved' THEN 1 END) as terlambat,
            COUNT(CASE WHEN status = 'Alpha' AND approval_status = 'Approved' THEN 1 END) as alpha
        FROM absensi 
        WHERE siswa_id = :siswa_id";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $siswa_id]);
$attendance_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if student has submitted attendance today (modified to exclude rejected submissions)
$sql = "SELECT * FROM absensi 
        WHERE siswa_id = :siswa_id 
        AND tanggal = :today 
        AND approval_status != 'Rejected'";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $siswa_id, 'today' => $today]);
$today_attendance = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if student has a rejected submission for today
$sql = "SELECT * FROM absensi 
        WHERE siswa_id = :siswa_id 
        AND tanggal = :today 
        AND approval_status = 'Rejected'";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $siswa_id, 'today' => $today]);
$rejected_attendance = $stmt->fetch(PDO::FETCH_ASSOC);

// Get attendance history for the current month - ADD APPROVAL STATUS FILTER
$sql = "SELECT 
            a.*, 
            DATE_FORMAT(a.tanggal, '%d') as day,
            DATE_FORMAT(a.tanggal, '%a') as day_name
        FROM absensi a
        WHERE a.siswa_id = :siswa_id 
        AND MONTH(a.tanggal) = MONTH(CURRENT_DATE())
        AND YEAR(a.tanggal) = YEAR(CURRENT_DATE())
        AND a.approval_status = 'Approved'
        ORDER BY a.tanggal DESC";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $siswa_id]);
$attendance_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending approval requests
$sql = "SELECT * FROM absensi 
        WHERE siswa_id = :siswa_id 
        AND approval_status = 'Pending'
        ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $siswa_id]);
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate attendance percentage - ONLY COUNT APPROVED RECORDS
$total_days = count($attendance_history);
$present_days = $attendance_summary['hadir'] + $attendance_summary['terlambat'];
$attendance_percentage = $total_days > 0 ? round(($present_days / $total_days) * 100) : 0;

// Process attendance submission
$submission_message = '';
$submission_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $status = $_POST['status'];
    $keterangan = isset($_POST['keterangan']) ? $_POST['keterangan'] : '';

    // Check if attendance already submitted (exclude rejected ones)
    if ($today_attendance) {
        $submission_status = 'error';
        $submission_message = 'Anda sudah melakukan absensi hari ini.';
    } else {
        // If resubmitting after rejection, delete the rejected submission first
        if ($rejected_attendance) {
            $delete_sql = "DELETE FROM absensi WHERE id = :id";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->execute(['id' => $rejected_attendance['id']]);
        }

        // Handle file upload if needed
        $bukti_foto = '';
        if (isset($_FILES['bukti_foto']) && $_FILES['bukti_foto']['error'] === 0) {
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($_FILES['bukti_foto']['type'], $allowed_types)) {
                $submission_status = 'error';
                $submission_message = 'Format file tidak didukung. Gunakan JPG atau PNG.';
            } else {
                // Process valid upload
                $upload_dir = '../../uploads/bukti/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_extension = pathinfo($_FILES['bukti_foto']['name'], PATHINFO_EXTENSION);
                $filename = 'bukti_' . $siswa_id . '_' . date('Ymd_His') . '.' . $file_extension;
                $target_file = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['bukti_foto']['tmp_name'], $target_file)) {
                    $bukti_foto = 'uploads/bukti/' . $filename;
                } else {
                    $submission_status = 'error';
                    $submission_message = 'Gagal mengunggah file bukti.';
                }
            }
        }

        // If no error, save attendance
        if ($submission_status !== 'error') {
            try {
                $conn->beginTransaction();

                // Determine approval status - all submissions require approval now
                $approval_status = 'Pending';

                // Set jam_masuk only for Hadir status
                if ($status === 'Hadir') {
                    $jam_masuk = date('H:i:s');

                    // For Hadir status, ensure camera image was captured
                    if (empty($_POST['camera_image_data'])) {
                        throw new Exception("Bukti foto kehadiran wajib diambil melalui kamera");
                    }

                    // Process the camera image data (base64)
                    $bukti_foto = '';
                    $img_data = $_POST['camera_image_data'];

                    // Extract base64 data
                    if (preg_match('/^data:image\/(\w+);base64,/', $img_data, $type)) {
                        $img_data = substr($img_data, strpos($img_data, ',') + 1);
                        $type = strtolower($type[1]); // jpg, png, etc

                        if (!in_array($type, ['jpg', 'jpeg', 'png'])) {
                            throw new Exception("Format gambar tidak valid");
                        }

                        $img_data = str_replace(' ', '+', $img_data);
                        $img_data = base64_decode($img_data);

                        if ($img_data === false) {
                            throw new Exception("Gagal memproses gambar");
                        }

                        // Save image file
                        $upload_dir = '../../uploads/bukti/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }

                        $filename = 'bukti_' . $siswa_id . '_' . date('Ymd_His') . '.' . $type;
                        $target_file = $upload_dir . $filename;

                        if (file_put_contents($target_file, $img_data) === false) {
                            throw new Exception("Gagal menyimpan gambar");
                        }

                        $bukti_foto = 'uploads/bukti/' . $filename;
                    } else {
                        throw new Exception("Format gambar tidak valid");
                    }
                } else {
                    $jam_masuk = null; // No entry time for absences

                    // Process normal file upload for other statuses
                    // ...existing file upload code...
                }

                // Insert the attendance record
                $sql = "INSERT INTO absensi (siswa_id, tanggal, jam_masuk, status, keterangan, bukti_foto, approval_status, created_at)
                        VALUES (:siswa_id, :tanggal, :jam_masuk, :status, :keterangan, :bukti_foto, :approval_status, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    'siswa_id' => $siswa_id,
                    'tanggal' => $today,
                    'jam_masuk' => $jam_masuk,
                    'status' => $status,
                    'keterangan' => $keterangan,
                    'bukti_foto' => $bukti_foto,
                    'approval_status' => $approval_status
                ]);

                // Add activity log
                $log_sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description)
                            VALUES ('siswa', :user_id, 'absensi', :description)";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->execute([
                    'user_id' => $siswa_id,
                    'description' => "Siswa {$_SESSION['siswa_name']} mengajukan absensi sebagai {$status}"
                ]);

                $conn->commit();

                $submission_status = 'success';
                $submission_message = 'Absensi berhasil dikirim dan menunggu persetujuan.';

                // Refresh the page to show the updated attendance
                header("Location: index.php?status=success&message=" . urlencode($submission_message));
                exit();
            } catch (Exception $e) {
                $conn->rollBack();
                $submission_status = 'error';
                $submission_message = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

// Handle cancellation of pending requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $request_id = $_POST['request_id'];

    try {
        $conn->beginTransaction();

        // First verify that this request belongs to the current student
        $check_sql = "SELECT * FROM absensi WHERE id = :id AND siswa_id = :siswa_id AND approval_status = 'Pending'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([
            'id' => $request_id,
            'siswa_id' => $siswa_id
        ]);

        if ($check_stmt->rowCount() > 0) {
            // Delete the pending request
            $delete_sql = "DELETE FROM absensi WHERE id = :id";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->execute(['id' => $request_id]);

            // Add log entry
            $log_sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description)
                       VALUES ('siswa', :user_id, 'delete', :description)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->execute([
                'user_id' => $siswa_id,
                'description' => "Siswa {$_SESSION['siswa_name']} membatalkan pengajuan absensi"
            ]);

            $conn->commit();

            // Refresh page to show updated list
            header("Location: index.php?status=success&message=Permintaan berhasil dibatalkan");
            exit();
        } else {
            throw new Exception("Permintaan tidak ditemukan atau bukan milik Anda");
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $submission_status = 'error';
        $submission_message = 'Gagal membatalkan permintaan: ' . $e->getMessage();
    }
}

// Check for URL parameters (for redirects)
if (isset($_GET['status']) && isset($_GET['message'])) {
    $submission_status = $_GET['status'];
    $submission_message = $_GET['message'];
}

// Get attendance history by status for pie chart - ADD APPROVAL STATUS FILTER
$sql = "SELECT status, COUNT(*) as count FROM absensi 
        WHERE siswa_id = :siswa_id 
        AND approval_status = 'Approved'
        GROUP BY status";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $siswa_id]);
$attendance_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format the data for the chart
$chart_data = [
    'labels' => [],
    'data' => [],
    'colors' => []
];

$status_colors = [
    'Hadir' => '#10B981',
    'Sakit' => '#EAB308',
    'Izin' => '#8B5CF6',
    'Terlambat' => '#F97316',
    'Alpha' => '#EF4444'
];

foreach ($attendance_stats as $stat) {
    $chart_data['labels'][] = $stat['status'];
    $chart_data['data'][] = (int)$stat['count'];
    $chart_data['colors'][] = $status_colors[$stat['status']] ?? '#9CA3AF';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - SMKN 40 Jakarta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .glass-effect {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(147, 51, 234, 0.3);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(147, 51, 234, 0.2), rgba(147, 51, 234, 0.05));
            border-left: 4px solid #9333ea;
        }

        /* Mobile menu active */
        .mobile-menu-active {
            background: rgba(147, 51, 234, 0.2);
            border-bottom: 2px solid #9333ea;
        }

        body {
            background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 100%);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.3s ease-out forwards;
        }

        .status-hadir {
            --status-color: #10B981;
        }

        .status-sakit {
            --status-color: #EAB308;
        }

        .status-izin {
            --status-color: #8B5CF6;
        }

        .status-terlambat {
            --status-color: #F97316;
        }

        .status-alpha {
            --status-color: #EF4444;
        }

        .status-badge {
            background-color: rgba(var(--tw-color-primary-500), 0.1);
            color: var(--status-color);
            border: 1px solid rgba(var(--tw-color-primary-500), 0.2);
        }

        /* Sidebar transition */
        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }

        /* Mobile overlay */
        .mobile-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease-in-out;
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        /* Hide scrollbar for IE, Edge and Firefox */
        .no-scrollbar {
            -ms-overflow-style: none;
            /* IE and Edge */
            scrollbar-width: none;
            /* Firefox */
        }

        /* Modal responsive adjustments */
        @media (max-width: 640px) {
            #imageModal img {
                max-height: 80vh;
            }

            #confirmationModal {
                padding: 1rem;
                margin: 0 1rem;
            }
        }

        /* Better focus styles for accessibility */
        button:focus,
        a:focus {
            outline: 2px solid rgba(147, 51, 234, 0.5);
            outline-offset: 2px;
        }

        /* Smooth transitions for all interactive elements */
        button,
        a,
        input,
        select,
        textarea {
            transition: all 0.2s ease;
        }
    </style>
</head>

<body class="min-h-screen text-white bg-fixed">
    <!-- Mobile Overlay - only visible when sidebar is open on mobile -->
    <div id="mobile-overlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Side Navigation -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass-effect border-r border-purple-900/30 z-50 sidebar-transition -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-4 lg:p-6 border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logo-smk40.png" alt="SMKN 40" class="h-8 lg:h-10 w-auto">
                <div>
                    <h1 class="font-semibold text-sm lg:text-base">SMKN 40</h1>
                    <p class="text-xs text-gray-400">Sistem Absensi</p>
                </div>
            </div>
            <!-- Close sidebar button - only visible on mobile -->
            <button class="text-gray-400 hover:text-white lg:hidden" onclick="toggleSidebar()">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="p-4 border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <img src="../../<?= $_SESSION['siswa_photo'] ?: 'assets/default/photo-profile.png' ?>" alt="Profile" class="h-10 w-10 rounded-full object-cover border-2 border-purple-500/50">
                <div>
                    <h2 class="font-medium text-sm"><?= $_SESSION['siswa_name'] ?></h2>
                    <p class="text-xs text-gray-400"><?= $_SESSION['siswa_kelas'] ?> <?= $_SESSION['siswa_jurusan'] ?></p>
                </div>
            </div>
        </div>

        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height: calc(100vh - 160px);">
            <a href="index.php" class="flex items-center gap-3 text-white/90 p-3 rounded-lg menu-active">
                <i class="fas fa-home text-purple-500"></i>
                <span>Dashboard</span>
            </a>
            <a href="../riwayat/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-history"></i>
                <span>Riwayat Absensi</span>
            </a>
            <a href="../profil/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-user"></i>
                <span>Profil</span>
            </a>

            <div class="pt-4 mt-auto">
                <a href="../logout.php" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-red-500/10 hover:text-red-500 transition-colors">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 transition-all duration-300">
        <!-- Mobile Header -->
        <div class="lg:hidden bg-gray-900/60 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-1">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logo-smk40.png" alt="SMKN 40" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium"></span>
                <img src="../../<?= $_SESSION['siswa_photo'] ?: 'assets/default/photo-profile.png' ?>" alt="Profile" class="h-8 w-8 rounded-full object-cover border border-purple-500/50">
            </div>
        </div>

        <!-- Content Body -->
        <div class="p-4 lg:p-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header with greeting and date -->
                <header class="mb-6 lg:mb-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 class="text-xl lg:text-2xl font-bold">Selamat Datang, <?= explode(' ', $_SESSION['siswa_name'])[0] ?>!</h1>
                            <p class="text-gray-400 text-sm lg:text-base mt-1"><?= date('l, d F Y') ?></p>
                        </div>

                        <!-- Current Time - Desktop Only -->
                        <div class="hidden lg:flex glass-effect rounded-lg px-4 py-2 items-center gap-2 mt-4 md:mt-0">
                            <i class="fas fa-clock text-purple-400"></i>
                            <span id="current-time" class="font-medium"></span>
                        </div>
                    </div>
                </header>

                <?php if ($submission_message): ?>
                    <div class="mb-6 p-4 rounded-lg border <?= $submission_status === 'success' ? 'bg-green-500/10 border-green-500/30 text-green-500' : 'bg-red-500/10 border-red-500/30 text-red-500' ?>">
                        <div class="flex items-center">
                            <i class="fas <?= $submission_status === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                            <p class="text-sm"><?= $submission_message ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6 mb-6 lg:mb-8">
                    <!-- Today's Attendance Form -->
                    <div class="glass-effect rounded-xl p-4 lg:p-6 lg:col-span-2">
                        <h3 class="font-semibold text-base lg:text-lg mb-4 flex items-center">
                            <i class="fas fa-clipboard-check text-purple-500 mr-2"></i>
                            Absensi Hari Ini
                        </h3>

                        <?php if ($today_attendance): ?>
                            <!-- Already submitted attendance -->
                            <div class="p-3 lg:p-4 border border-gray-700/50 rounded-lg bg-gray-800/50">
                                <div class="flex flex-col sm:flex-row justify-between items-start gap-3">
                                    <div>
                                        <div class="mb-3 lg:mb-4 flex items-center flex-wrap gap-2">
                                            <span class="text-sm font-medium mr-2">Status:</span>
                                            <span class="px-3 py-1 rounded-full text-xs font-medium
                                            <?php
                                            switch ($today_attendance['status']) {
                                                case 'Hadir':
                                                    echo 'bg-green-500/10 text-green-500 border border-green-500/30';
                                                    break;
                                                case 'Sakit':
                                                    echo 'bg-yellow-500/10 text-yellow-500 border border-yellow-500/30';
                                                    break;
                                                case 'Izin':
                                                    echo 'bg-purple-500/10 text-purple-500 border border-purple-500/30';
                                                    break;
                                                case 'Terlambat':
                                                    echo 'bg-orange-500/10 text-orange-500 border border-orange-500/30';
                                                    break;
                                                case 'Alpha':
                                                    echo 'bg-red-500/10 text-red-500 border border-red-500/30';
                                                    break;
                                                default:
                                                    echo 'bg-gray-500/10 text-gray-500 border border-gray-500/30';
                                            }
                                            ?>">
                                                <?= $today_attendance['status'] ?>
                                            </span>
                                        </div>

                                        <div class="mb-3 lg:mb-4">
                                            <span class="text-sm font-medium">Approval Status:</span>
                                            <span class="px-3 py-1 rounded-full text-xs font-medium ml-2
                                            <?php
                                            switch ($today_attendance['approval_status']) {
                                                case 'Approved':
                                                    echo 'bg-green-500/10 text-green-500 border border-green-500/30';
                                                    break;
                                                case 'Rejected':
                                                    echo 'bg-red-500/10 text-red-500 border border-red-500/30';
                                                    break;
                                                default:
                                                    echo 'bg-yellow-500/10 text-yellow-500 border border-yellow-500/30';
                                            }
                                            ?>">
                                                <?= $today_attendance['approval_status'] ?>
                                            </span>
                                        </div>

                                        <?php if ($today_attendance['jam_masuk']): ?>
                                            <div class="mb-3 lg:mb-4">
                                                <span class="text-sm font-medium">Waktu Absen:</span>
                                                <span class="text-gray-300 ml-2"><?= date('H:i', strtotime($today_attendance['jam_masuk'])) ?> WIB</span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($today_attendance['keterangan']): ?>
                                            <div class="mt-4">
                                                <span class="text-sm font-medium block mb-1">Keterangan:</span>
                                                <p class="text-gray-400 text-sm bg-gray-800/50 p-3 rounded-lg border border-gray-700/50">
                                                    <?= htmlspecialchars($today_attendance['keterangan']) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($today_attendance['bukti_foto']): ?>
                                        <div class="sm:ml-4 shrink-0">
                                            <span class="text-sm font-medium block mb-1">Bukti:</span>
                                            <img src="../../<?= $today_attendance['bukti_foto'] ?>" alt="Bukti"
                                                class="w-24 h-24 object-cover rounded-lg border border-gray-700/50"
                                                onclick="showImagePreview('../../<?= $today_attendance['bukti_foto'] ?>')">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($rejected_attendance): ?>
                            <!-- Rejected attendance notification -->
                            <div class="p-3 lg:p-4 border border-red-500/30 rounded-lg bg-red-500/10 mb-4">
                                <div class="flex items-center text-red-500">
                                    <i class="fas fa-exclamation-circle text-xl mr-3"></i>
                                    <div>
                                        <p class="font-medium">Pengajuan absensi Anda ditolak</p>
                                        <p class="text-xs mt-1">Silakan ajukan ulang absensi Anda dengan informasi yang benar</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Resubmission form (same as the normal submission form but with pre-filled values) -->
                            <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                                <!-- Camera container for Hadir status -->
                                <div id="camera-container" class="hidden mb-4 p-3 lg:p-4 border border-gray-700/50 rounded-lg bg-gray-800/50">
                                    <div class="mb-3">
                                        <h4 class="text-sm font-medium text-gray-300 mb-2">Ambil Foto Kehadiran <span class="text-red-400">*</span></h4>
                                        <p class="text-xs text-gray-400 mb-2">Silakan posisikan wajah Anda dengan jelas di dalam frame</p>
                                    </div>

                                    <!-- Camera elements with responsive adjustments -->
                                    <div class="relative mb-3 rounded-lg overflow-hidden bg-black">
                                        <video id="camera-preview" class="w-full h-40 sm:h-48 md:h-56 lg:h-64 object-cover rounded-lg"></video>
                                        <canvas id="camera-canvas" class="hidden"></canvas>
                                        <div id="camera-overlay" class="hidden absolute inset-0 bg-black flex items-center justify-center">
                                            <img id="camera-result" class="max-h-full max-w-full rounded-lg" src="" alt="Captured photo">
                                        </div>

                                        <!-- Camera loading indicator -->
                                        <div id="camera-loading" class="absolute inset-0 flex items-center justify-center bg-black/70">
                                            <div class="text-center">
                                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-purple-500"></div>
                                                <p class="text-xs mt-2 text-gray-300">Memuat kamera...</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Camera controls with better responsive layout -->
                                    <div class="grid grid-cols-2 sm:flex sm:flex-wrap sm:justify-between gap-2">
                                        <button type="button" id="switch-camera-btn" class="px-2 lg:px-3 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-xs lg:text-sm text-white flex items-center justify-center">
                                            <i class="fas fa-sync mr-1 lg:mr-2"></i> Ganti Kamera
                                        </button>
                                        <button type="button" id="capture-btn" class="px-2 lg:px-3 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-xs lg:text-sm text-white flex items-center justify-center">
                                            <i class="fas fa-camera mr-1 lg:mr-2"></i> Ambil Foto
                                        </button>
                                        <button type="button" id="retake-btn" class="hidden col-span-2 sm:col-span-1 px-2 lg:px-3 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-xs lg:text-sm text-white flex items-center justify-center">
                                            <i class="fas fa-redo mr-1 lg:mr-2"></i> Ulangi Foto
                                        </button>
                                    </div>

                                    <!-- Hidden input to store the image data -->
                                    <input type="hidden" name="camera_image_data" id="camera-image-data">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Status Kehadiran</label>
                                    <div class="grid grid-cols-3 gap-2 lg:gap-3">
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="Hadir" class="hidden peer" required
                                                <?= ($rejected_attendance['status'] === 'Hadir') ? 'checked' : '' ?>>
                                            <div class="p-2 lg:p-3 border border-gray-700 rounded-lg peer-checked:border-green-500 peer-checked:bg-green-500/10 text-center transition-colors">
                                                <i class="fas fa-check text-green-500 mb-1"></i>
                                                <p class="text-xs lg:text-sm font-medium">Hadir</p>
                                            </div>
                                        </label>

                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="Sakit" class="hidden peer"
                                                <?= ($rejected_attendance['status'] === 'Sakit') ? 'checked' : '' ?>>
                                            <div class="p-2 lg:p-3 border border-gray-700 rounded-lg peer-checked:border-yellow-500 peer-checked:bg-yellow-500/10 text-center transition-colors">
                                                <i class="fas fa-hospital text-yellow-500 mb-1"></i>
                                                <p class="text-xs lg:text-sm font-medium">Sakit</p>
                                            </div>
                                        </label>

                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="Izin" class="hidden peer"
                                                <?= ($rejected_attendance['status'] === 'Izin') ? 'checked' : '' ?>>
                                            <div class="p-2 lg:p-3 border border-gray-700 rounded-lg peer-checked:border-purple-500 peer-checked:bg-purple-500/10 text-center transition-colors">
                                                <i class="fas fa-clipboard-list text-purple-500 mb-1"></i>
                                                <p class="text-xs lg:text-sm font-medium">Izin</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div id="additionalFields" class="hidden space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Keterangan</label>
                                        <textarea name="keterangan" rows="3"
                                            class="w-full px-3 lg:px-4 py-2 rounded-lg bg-gray-800/50 border border-gray-700 text-white
                                            focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500"
                                            placeholder="Masukkan alasan ketidakhadiran..."><?= htmlspecialchars($rejected_attendance['keterangan'] ?? '') ?></textarea>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Bukti (opsional)</label>
                                        <input type="file" name="bukti_foto" accept="image/*"
                                            class="w-full px-3 lg:px-4 py-2 rounded-lg bg-gray-800/50 border border-gray-700 text-white
                                            focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 text-sm">
                                        <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG, max 2MB.</p>

                                        <?php if (!empty($rejected_attendance['bukti_foto'])): ?>
                                            <div class="mt-2 flex items-center gap-2">
                                                <span class="text-sm text-gray-400">Bukti sebelumnya:</span>
                                                <a href="#" onclick="showImagePreview('../../<?= $rejected_attendance['bukti_foto'] ?>')" class="text-purple-400 hover:text-purple-300 text-sm flex items-center">
                                                    <i class="fas fa-image mr-1"></i> Lihat
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="pt-2">
                                    <button type="submit" name="submit_attendance"
                                        class="px-4 lg:px-6 py-2 lg:py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium 
                                        text-white transition-colors flex items-center justify-center w-full sm:w-auto text-sm">
                                        <i class="fas fa-paper-plane mr-2"></i> Kirim Ulang Absensi
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- Attendance submission form -->
                            <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                                <!-- Add this before the form for camera UI -->
                                <div id="camera-container" class="hidden mb-4 p-3 lg:p-4 border border-gray-700/50 rounded-lg bg-gray-800/50">
                                    <div class="mb-3">
                                        <h4 class="text-sm font-medium text-gray-300 mb-2">Ambil Foto Kehadiran <span class="text-red-400">*</span></h4>
                                        <p class="text-xs text-gray-400 mb-2">Silakan posisikan wajah Anda dengan jelas di dalam frame</p>
                                    </div>

                                    <!-- Camera elements -->
                                    <div class="relative mb-3 rounded-lg overflow-hidden bg-black">
                                        <video id="camera-preview" class="w-full h-48 lg:h-64 object-cover rounded-lg"></video>
                                        <canvas id="camera-canvas" class="hidden"></canvas>
                                        <div id="camera-overlay" class="hidden absolute inset-0 bg-black flex items-center justify-center">
                                            <img id="camera-result" class="max-h-full rounded-lg" src="" alt="Captured photo">
                                        </div>

                                        <!-- Camera loading indicator -->
                                        <div id="camera-loading" class="absolute inset-0 flex items-center justify-center bg-black/70">
                                            <div class="text-center">
                                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-purple-500"></div>
                                                <p class="text-xs mt-2 text-gray-300">Memuat kamera...</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Camera controls -->
                                    <div class="flex flex-wrap justify-between gap-2">
                                        <button type="button" id="switch-camera-btn" class="px-2 lg:px-3 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-xs lg:text-sm text-white flex items-center">
                                            <i class="fas fa-sync mr-1 lg:mr-2"></i> Ganti Kamera
                                        </button>
                                        <button type="button" id="capture-btn" class="px-2 lg:px-3 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-xs lg:text-sm text-white flex items-center">
                                            <i class="fas fa-camera mr-1 lg:mr-2"></i> Ambil Foto
                                        </button>
                                        <button type="button" id="retake-btn" class="hidden px-2 lg:px-3 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-xs lg:text-sm text-white flex items-center">
                                            <i class="fas fa-redo mr-1 lg:mr-2"></i> Ulangi Foto
                                        </button>
                                    </div>

                                    <!-- Hidden input to store the image data -->
                                    <input type="hidden" name="camera_image_data" id="camera-image-data">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Status Kehadiran</label>
                                    <div class="grid grid-cols-3 gap-2 lg:gap-3">
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="Hadir" class="hidden peer" required checked>
                                            <div class="p-2 lg:p-3 border border-gray-700 rounded-lg peer-checked:border-green-500 peer-checked:bg-green-500/10 text-center transition-colors">
                                                <i class="fas fa-check text-green-500 mb-1"></i>
                                                <p class="text-xs lg:text-sm font-medium">Hadir</p>
                                            </div>
                                        </label>

                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="Sakit" class="hidden peer">
                                            <div class="p-2 lg:p-3 border border-gray-700 rounded-lg peer-checked:border-yellow-500 peer-checked:bg-yellow-500/10 text-center transition-colors">
                                                <i class="fas fa-hospital text-yellow-500 mb-1"></i>
                                                <p class="text-xs lg:text-sm font-medium">Sakit</p>
                                            </div>
                                        </label>

                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="Izin" class="hidden peer">
                                            <div class="p-2 lg:p-3 border border-gray-700 rounded-lg peer-checked:border-purple-500 peer-checked:bg-purple-500/10 text-center transition-colors">
                                                <i class="fas fa-clipboard-list text-purple-500 mb-1"></i>
                                                <p class="text-xs lg:text-sm font-medium">Izin</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div id="additionalFields" class="hidden space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Keterangan</label>
                                        <textarea name="keterangan" rows="3"
                                            class="w-full px-3 lg:px-4 py-2 rounded-lg bg-gray-800/50 border border-gray-700 text-white
                                            focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500"
                                            placeholder="Masukkan alasan ketidakhadiran..."></textarea>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">Bukti (opsional)</label>
                                        <input type="file" name="bukti_foto" accept="image/*"
                                            class="w-full px-3 lg:px-4 py-2 rounded-lg bg-gray-800/50 border border-gray-700 text-white
                                            focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 text-sm">
                                        <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG, max 2MB.</p>
                                    </div>
                                </div>

                                <div>
                                    <button type="submit" name="submit_attendance"
                                        class="px-4 lg:px-6 py-2 lg:py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium 
                                        text-white transition-colors flex items-center justify-center w-full sm:w-auto text-sm">
                                        <i class="fas fa-paper-plane mr-2"></i> Kirim Absensi
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Attendance Summary Stats -->
                    <div class="glass-effect rounded-xl p-4 lg:p-6">
                        <h3 class="font-semibold text-base lg:text-lg mb-4 flex items-center">
                            <i class="fas fa-chart-pie text-purple-500 mr-2"></i>
                            Ringkasan Kehadiran
                        </h3>

                        <div class="mb-6">
                            <!-- Attendance Percentage -->
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-400">Persentase Kehadiran</span>
                                <span class="text-sm font-medium"><?= $attendance_percentage ?>%</span>
                            </div>
                            <div class="w-full bg-gray-800/60 rounded-full h-2">
                                <div class="bg-gradient-to-r from-purple-500 to-indigo-500 h-2 rounded-full"
                                    style="width: <?= $attendance_percentage ?>%"></div>
                            </div>
                        </div>

                        <!-- Chart -->
                        <div class="aspect-square mb-4">
                            <canvas id="attendanceChart"></canvas>
                        </div>

                        <!-- Stats Legend -->
                        <div class="grid grid-cols-2 gap-3 text-xs lg:text-sm">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-green-500 mr-2"></span>
                                    <span>Hadir</span>
                                </div>
                                <span class="font-medium"><?= $attendance_summary['hadir'] ?></span>
                            </div>

                            <div class="flex justify-between items-center">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-yellow-500 mr-2"></span>
                                    <span>Sakit</span>
                                </div>
                                <span class="font-medium"><?= $attendance_summary['sakit'] ?></span>
                            </div>

                            <div class="flex justify-between items-center">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-purple-500 mr-2"></span>
                                    <span>Izin</span>
                                </div>
                                <span class="font-medium"><?= $attendance_summary['izin'] ?></span>
                            </div>

                            <div class="flex justify-between items-center">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-orange-500 mr-2"></span>
                                    <span>Terlambat</span>
                                </div>
                                <span class="font-medium"><?= $attendance_summary['terlambat'] ?></span>
                            </div>

                            <div class="flex justify-between items-center col-span-2">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-red-500 mr-2"></span>
                                    <span>Alpha</span>
                                </div>
                                <span class="font-medium"><?= $attendance_summary['alpha'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Requests and Calendar -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
                    <!-- Pending Approval Requests -->
                    <div class="glass-effect rounded-xl overflow-hidden">
                        <div class="bg-gradient-to-r from-purple-900/40 to-indigo-900/40 p-4 border-b border-gray-800">
                            <h3 class="font-semibold flex items-center text-base">
                                <i class="fas fa-clock text-purple-500 mr-2"></i>
                                Permintaan Menunggu Persetujuan
                            </h3>
                        </div>

                        <?php if (count($pending_requests) > 0): ?>
                            <div class="divide-y divide-gray-800 max-h-[400px] overflow-y-auto">
                                <?php foreach ($pending_requests as $request): ?>
                                    <div class="p-3 lg:p-4 hover:bg-gray-800/30 transition-colors">
                                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                                            <div>
                                                <!-- Request Type Badge -->
                                                <span class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center
                                                <?php
                                                switch ($request['status']) {
                                                    case 'Sakit':
                                                        echo 'bg-yellow-500/10 text-yellow-500 border border-yellow-500/30';
                                                        break;
                                                    case 'Izin':
                                                        echo 'bg-purple-500/10 text-purple-500 border border-purple-500/30';
                                                        break;
                                                    default:
                                                        echo 'bg-gray-500/10 text-gray-500 border border-gray-500/30';
                                                }
                                                ?>">
                                                    <i class="fas <?= $request['status'] === 'Sakit' ? 'fa-hospital' : 'fa-clipboard-list' ?> mr-1"></i>
                                                    <?= $request['status'] ?>
                                                </span>

                                                <!-- Date and Time -->
                                                <div class="mt-2 text-xs text-gray-400">
                                                    <span class="inline-block mr-3">
                                                        <i class="far fa-calendar-alt mr-1"></i>
                                                        <?= date('d M Y', strtotime($request['tanggal'])) ?>
                                                    </span>
                                                    <?php if ($request['created_at']): ?>
                                                        <span class="inline-block">
                                                            <i class="far fa-clock mr-1"></i>
                                                            <?= date('H:i', strtotime($request['created_at'])) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Display thumbnail if evidence exists -->
                                            <?php if ($request['bukti_foto']): ?>
                                                <div class="shrink-0">
                                                    <img src="../../<?= $request['bukti_foto'] ?>"
                                                        alt="Bukti"
                                                        class="w-12 h-12 object-cover rounded-md border border-gray-700"
                                                        onclick="showImagePreview('../../<?= $request['bukti_foto'] ?>')">
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Reason if any -->
                                        <?php if ($request['keterangan']): ?>
                                            <div class="mt-2 text-sm text-gray-300">
                                                <div class="text-xs text-gray-400 mb-1">Keterangan:</div>
                                                <p class="text-xs"><?= htmlspecialchars($request['keterangan']) ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Pending Badge -->
                                        <div class="mt-3 flex items-center justify-between flex-wrap gap-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-500/10 text-yellow-500 border border-yellow-500/30">
                                                <i class="fas fa-hourglass-half mr-1.5"></i>
                                                Menunggu Persetujuan
                                            </span>

                                            <!-- Cancel submission -->
                                            <button type="button" onclick="showConfirmationModal(<?= $request['id'] ?>)" class="text-red-400 hover:text-red-300 transition-colors text-sm flex items-center">
                                                <i class="fas fa-trash-alt mr-1"></i>
                                                Batalkan
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-8 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-800/50 mb-4">
                                    <i class="fas fa-check-circle text-2xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-400">Tidak ada permintaan yang tertunda.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Attendance Calendar -->
                    <div class="glass-effect rounded-xl overflow-hidden">
                        <div class="bg-gradient-to-r from-purple-900/40 to-indigo-900/40 p-4 border-b border-gray-800">
                            <h3 class="font-semibold flex items-center text-base">
                                <i class="fas fa-calendar-alt text-purple-500 mr-2"></i>
                                Kehadiran Bulan Ini
                            </h3>
                        </div>

                        <div class="p-4">
                            <!-- Calendar Grid -->
                            <div class="grid grid-cols-7 gap-1 text-center mb-2">
                                <div class="text-xs font-medium text-gray-400">Min</div>
                                <div class="text-xs font-medium text-gray-400">Sen</div>
                                <div class="text-xs font-medium text-gray-400">Sel</div>
                                <div class="text-xs font-medium text-gray-400">Rab</div>
                                <div class="text-xs font-medium text-gray-400">Kam</div>
                                <div class="text-xs font-medium text-gray-400">Jum</div>
                                <div class="text-xs font-medium text-gray-400">Sab</div>
                            </div>

                            <div id="calendarGrid" class="grid grid-cols-7 gap-1">
                                <!-- Calendar will be generated by JavaScript -->
                            </div>

                            <!-- Calendar Legend -->
                            <div class="p-4 border-t border-gray-800 grid grid-cols-3 gap-2">
                                <div class="flex items-center">
                                    <span class="h-3 w-3 rounded-full bg-green-500 mr-2"></span>
                                    <span class="text-xs">Hadir</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="h-3 w-3 rounded-full bg-yellow-500 mr-2"></span>
                                    <span class="text-xs">Sakit</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="h-3 w-3 rounded-full bg-purple-500 mr-2"></span>
                                    <span class="text-xs">Izin</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="h-3 w-3 rounded-full bg-orange-500 mr-2"></span>
                                    <span class="text-xs">Terlambat</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="h-3 w-3 rounded-full bg-red-500 mr-2"></span>
                                    <span class="text-xs">Alpha</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="h-3 w-3 rounded-full bg-gray-600 mr-2"></span>
                                    <span class="text-xs">Belum Absen</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Image Preview Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black/80 z-50 hidden flex items-center justify-center p-4">
        <div class="relative max-w-xl w-full">
            <button onclick="closeImagePreview()" class="absolute -top-10 right-0 text-white hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
            </button>
            <img id="previewImage" src="" alt="Preview" class="w-full rounded-lg">
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
        <div class="glass-effect rounded-xl max-w-md w-full p-6 border border-purple-500/30">
            <h3 class="text-lg font-semibold mb-4 text-white">Konfirmasi</h3>
            <p class="text-gray-300 mb-6">Apakah Anda yakin ingin membatalkan pengajuan absensi ini?</p>
            <form method="POST" id="cancelForm">
                <input type="hidden" name="request_id" id="requestIdInput">
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeConfirmationModal()"
                        class="px-4 py-2 bg-gray-600/50 hover:bg-gray-600 rounded-lg text-white transition-colors">
                        <i class="fas fa-times mr-2"></i> Batal
                    </button>
                    <button type="submit" name="cancel_request"
                        class="px-4 py-2 bg-red-500/80 hover:bg-red-600 rounded-lg text-white transition-colors">
                        <i class="fas fa-trash-alt mr-2"></i> Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Update current time every second
            function updateTime() {
                const timeElement = document.getElementById('current-time');
                const now = new Date();
                const timeString = now.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                });
                timeElement.textContent = timeString + ' WIB';
            }
            setInterval(updateTime, 1000);
            updateTime(); // Initial call

            // Show/hide additional fields based on attendance status
            document.querySelectorAll('input[name="status"]').forEach(input => {
                input.addEventListener('change', function() {
                    const additionalFields = document.getElementById('additionalFields');
                    if (this.value === 'Hadir') {
                        additionalFields.classList.add('hidden');
                    } else {
                        additionalFields.classList.remove('hidden');
                    }
                });
            });

            // Image preview functionality
            function showImagePreview(src) {
                const modal = document.getElementById('imageModal');
                const previewImage = document.getElementById('previewImage');
                previewImage.src = src;
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeImagePreview() {
                const modal = document.getElementById('imageModal');
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }

            // Close modal when clicking outside the image
            document.getElementById('imageModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeImagePreview();
                }
            });

            // Initialize attendance chart
            const ctx = document.getElementById('attendanceChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($chart_data['labels']) ?>,
                    datasets: [{
                        data: <?= json_encode($chart_data['data']) ?>,
                        backgroundColor: <?= json_encode($chart_data['colors']) ?>,
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(17, 24, 39, 0.9)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            padding: 10,
                            borderColor: 'rgba(147, 51, 234, 0.3)',
                            borderWidth: 1,
                            displayColors: true,
                            usePointStyle: true,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Generate calendar
            function generateCalendar() {
                const date = new Date();
                const year = date.getFullYear();
                const month = date.getMonth();

                const attendanceData = <?= json_encode($attendance_history) ?>;

                // Map attendance data by day
                const attendanceMap = {};
                attendanceData.forEach(record => {
                    const day = new Date(record.tanggal).getDate();
                    attendanceMap[day] = record.status;
                });

                // Get first day of month
                const firstDay = new Date(year, month, 1).getDay();
                // Get number of days in month
                const daysInMonth = new Date(year, month + 1, 0).getDate();

                // Get calendar grid
                const calendarGrid = document.getElementById('calendarGrid');
                calendarGrid.innerHTML = '';

                // Add empty cells for days before the first day of month
                for (let i = 0; i < firstDay; i++) {
                    const emptyCell = document.createElement('div');
                    emptyCell.className = 'h-10 rounded-md';
                    calendarGrid.appendChild(emptyCell);
                }

                // Add cells for each day
                for (let day = 1; day <= daysInMonth; day++) {
                    const cell = document.createElement('div');

                    // Check if date is in the future
                    const currentDate = new Date(year, month, day);
                    const isToday = currentDate.toDateString() === new Date().toDateString();
                    const isPast = currentDate < new Date().setHours(0, 0, 0, 0);

                    // Basic cell styling
                    cell.className = 'h-10 flex flex-col items-center justify-center rounded-md relative';

                    // Day number
                    const dayNumber = document.createElement('span');
                    dayNumber.className = 'text-xs ' + (isToday ? 'font-bold' : '');
                    dayNumber.textContent = day;
                    cell.appendChild(dayNumber);

                    // Status dot
                    if (attendanceMap[day]) {
                        const statusDot = document.createElement('span');
                        switch (attendanceMap[day]) {
                            case 'Hadir':
                                statusDot.className = 'h-2 w-2 rounded-full bg-green-500 mt-1';
                                cell.title = 'Hadir';
                                break;
                            case 'Sakit':
                                statusDot.className = 'h-2 w-2 rounded-full bg-yellow-500 mt-1';
                                cell.title = 'Sakit';
                                break;
                            case 'Izin':
                                statusDot.className = 'h-2 w-2 rounded-full bg-purple-500 mt-1';
                                cell.title = 'Izin';
                                break;
                            case 'Terlambat':
                                statusDot.className = 'h-2 w-2 rounded-full bg-orange-500 mt-1';
                                cell.title = 'Terlambat';
                                break;
                            case 'Alpha':
                                statusDot.className = 'h-2 w-2 rounded-full bg-red-500 mt-1';
                                cell.title = 'Alpha';
                                break;
                            default:
                                statusDot.className = 'h-2 w-2 rounded-full bg-gray-600 mt-1';
                                cell.title = 'Tidak hadir';
                        }
                        cell.appendChild(statusDot);
                    } else if (isPast && !attendanceMap[day]) {
                        cell.classList.add('bg-gray-800/30');
                        const statusDot = document.createElement('span');
                        statusDot.className = 'h-2 w-2 rounded-full bg-gray-600 mt-1';
                        cell.title = 'Tidak hadir';
                        cell.appendChild(statusDot);
                    }

                    // Style today
                    if (isToday) {
                        cell.classList.add('ring-2', 'ring-purple-500');
                        dayNumber.classList.add('text-purple-400');
                    }

                    // Add hover effect
                    cell.classList.add('hover:bg-gray-800/50', 'transition-colors');

                    calendarGrid.appendChild(cell);
                }
            }

            generateCalendar();
        });

        // Add these functions to handle the modal
        function showConfirmationModal(requestId) {
            const modal = document.getElementById('confirmationModal');
            const requestIdInput = document.getElementById('requestIdInput');
            requestIdInput.value = requestId;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeConfirmationModal() {
            const modal = document.getElementById('confirmationModal');
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('confirmationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfirmationModal();
            }
        });

        // Add this to your existing script section
        let stream = null;
        let facingMode = "user"; // Default to front camera
        const cameraPreview = document.getElementById('camera-preview');
        const cameraCanvas = document.getElementById('camera-canvas');
        const cameraResult = document.getElementById('camera-result');
        const cameraOverlay = document.getElementById('camera-overlay');
        const captureBtn = document.getElementById('capture-btn');
        const retakeBtn = document.getElementById('retake-btn');
        const switchCameraBtn = document.getElementById('switch-camera-btn');
        const cameraImageData = document.getElementById('camera-image-data');
        const cameraContainer = document.getElementById('camera-container');

        // Modified status change handler to start camera for "Hadir"
        document.querySelectorAll('input[name="status"]').forEach(input => {
            input.addEventListener('change', function() {
                const additionalFields = document.getElementById('additionalFields');
                const cameraContainer = document.getElementById('camera-container');

                if (this.value === 'Hadir') {
                    additionalFields.classList.add('hidden');
                    cameraContainer.classList.remove('hidden');
                    startCamera(); // Start camera when Hadir is selected
                } else {
                    additionalFields.classList.remove('hidden');
                    cameraContainer.classList.add('hidden');
                    stopCamera(); // Stop camera when other statuses are selected
                }
            });
        });

        // Check if "Hadir" is already selected on page load
        window.addEventListener('DOMContentLoaded', function() {
            const hadirRadio = document.querySelector('input[name="status"][value="Hadir"]:checked');
            if (hadirRadio && hadirRadio.checked) {
                const cameraContainer = document.getElementById('camera-container');
                if (cameraContainer) {
                    cameraContainer.classList.remove('hidden');
                    startCamera();
                }
            }
        });

        // Start camera function
        function startCamera() {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                // Stop any existing stream
                if (stream) {
                    stopCamera();
                }

                // Show loading indicator
                const loadingIndicator = document.getElementById('camera-loading');
                if (loadingIndicator) loadingIndicator.classList.remove('hidden');

                // Reset camera UI
                cameraOverlay.classList.add('hidden');
                captureBtn.classList.remove('hidden');
                retakeBtn.classList.add('hidden');

                navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: facingMode,
                            width: {
                                ideal: 1280
                            },
                            height: {
                                ideal: 720
                            }
                        },
                        audio: false
                    })
                    .then(function(mediaStream) {
                        stream = mediaStream;
                        cameraPreview.srcObject = stream;
                        cameraPreview.play().then(() => {
                            // Hide loading indicator when camera starts
                            if (loadingIndicator) loadingIndicator.classList.add('hidden');
                        });
                    })
                    .catch(function(error) {
                        console.error('Camera error:', error);
                        if (loadingIndicator) loadingIndicator.classList.add('hidden');

                        // Show user-friendly error message
                        let errorMessage = 'Tidak dapat mengakses kamera. ';

                        if (error.name === 'NotAllowedError') {
                            errorMessage += 'Harap berikan izin kamera di browser Anda.';
                        } else if (error.name === 'NotFoundError') {
                            errorMessage += 'Kamera tidak ditemukan di perangkat Anda.';
                        } else if (error.name === 'NotReadableError') {
                            errorMessage += 'Kamera sedang digunakan aplikasi lain.';
                        } else {
                            errorMessage += 'Terjadi kesalahan teknis.';
                        }

                        alert(errorMessage);
                    });
            } else {
                alert('Browser anda tidak mendukung kamera. Gunakan browser terbaru seperti Chrome atau Firefox.');
            }
        }

        // Stop camera function
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => {
                    track.stop();
                });
                stream = null;
            }
        }

        // Capture photo
        captureBtn.addEventListener('click', function() {
            if (stream) {
                // Set canvas dimensions to match video
                cameraCanvas.width = cameraPreview.videoWidth;
                cameraCanvas.height = cameraPreview.videoHeight;

                // Draw video frame to canvas
                const context = cameraCanvas.getContext('2d');
                context.drawImage(cameraPreview, 0, 0, cameraCanvas.width, cameraCanvas.height);

                // Get image data and show preview
                const imageData = cameraCanvas.toDataURL('image/jpeg');
                cameraResult.src = imageData;
                cameraImageData.value = imageData; // Store in hidden input

                // Show captured image and retake button
                cameraOverlay.classList.remove('hidden');
                captureBtn.classList.add('hidden');
                retakeBtn.classList.remove('hidden');
            }
        });

        // Retake photo
        retakeBtn.addEventListener('click', function() {
            cameraOverlay.classList.add('hidden');
            captureBtn.classList.remove('hidden');
            retakeBtn.classList.add('hidden');
            cameraImageData.value = '';
        });

        // Switch camera
        switchCameraBtn.addEventListener('click', function() {
            facingMode = facingMode === "user" ? "environment" : "user";
            startCamera();
        });

        // Form submission validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const hadirRadio = document.querySelector('input[name="status"][value="Hadir"]:checked');

            if (hadirRadio && !cameraImageData.value) {
                e.preventDefault();
                alert('Anda harus mengambil foto untuk absensi hadir.');
            }
        });

        // Stop camera when page is unloaded
        window.addEventListener('beforeunload', stopCamera);

        // Add sidebar toggle function (mobile responsive)
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');

            if (sidebar.classList.contains('-translate-x-full')) {
                // Open sidebar
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            } else {
                // Close sidebar
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        // Update time for mobile view too
        function updateMobileTime() {
            const mobileTimeElement = document.getElementById('current-time-mobile');
            if (mobileTimeElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                });
                mobileTimeElement.textContent = timeString;
            }
        }

        // Add mobile time updater
        setInterval(updateMobileTime, 60000); // Update every minute
        updateMobileTime(); // Initial call

        // Make sure modals close when pressing escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImagePreview();
                closeConfirmationModal();

                // Also close sidebar on mobile
                if (window.innerWidth < 1024) { // lg breakpoint in Tailwind
                    const sidebar = document.getElementById('sidebar');
                    if (!sidebar.classList.contains('-translate-x-full')) {
                        toggleSidebar();
                    }
                }
            }
        });

        // Adjust camera UI for smaller screens
        function adjustCameraUI() {
            const cameraPreview = document.getElementById('camera-preview');
            if (cameraPreview) {
                // Set height based on screen size
                if (window.innerWidth < 768) { // md breakpoint
                    cameraPreview.style.maxHeight = '200px';
                } else {
                    cameraPreview.style.maxHeight = '300px';
                }
            }
        }

        // Run on page load and window resize
        window.addEventListener('resize', adjustCameraUI);
        window.addEventListener('DOMContentLoaded', function() {
            adjustCameraUI();
        });

        // Update the calendar based on screen size
        function adjustCalendar() {
            // Regenerate the calendar to adjust sizes
            generateCalendar();

            // Make day indicators smaller on mobile
            if (window.innerWidth < 640) { // sm breakpoint
                document.querySelectorAll('#calendarGrid > div').forEach(cell => {
                    const dayNumber = cell.querySelector('span:first-child');
                    if (dayNumber) {
                        dayNumber.classList.add('text-[10px]');
                    }
                });
            }
        }

        // Add resize listener for calendar adjustments
        window.addEventListener('resize', adjustCalendar);

        // Image preview functionality 
        function showImagePreview(src) {
            const modal = document.getElementById('imageModal');
            const previewImage = document.getElementById('previewImage');
            previewImage.src = src;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeImagePreview() {
            const modal = document.getElementById('imageModal');
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside the image
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImagePreview();
            }
        });
    </script>
</body>

</html>