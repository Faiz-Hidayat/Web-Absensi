<?php
require_once '../../config/database.php';

// Check if student is logged in
if (!isset($_SESSION['siswa_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get student data
$siswa_id = $_SESSION['siswa_id'];

// Initialize filter variables with defaults
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Calculate date range for the selected month
$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));

// Build SQL query with filters
$sql = "SELECT a.*, DATE_FORMAT(a.tanggal, '%d %b %Y') as formatted_date,
        DATE_FORMAT(a.jam_masuk, '%H:%i') as formatted_time
        FROM absensi a
        WHERE a.siswa_id = :siswa_id ";

// Add approval status filter to include only approved records
$sql .= "AND a.approval_status = 'Approved' ";

// Add date range filter
$sql .= "AND a.tanggal BETWEEN :start_date AND :end_date ";

// Add status filter if selected
if (!empty($status)) {
    $sql .= "AND a.status = :status ";
}

// Order by date descending (newest first)
$sql .= "ORDER BY a.tanggal DESC, a.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':siswa_id', $siswa_id);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);

if (!empty($status)) {
    $stmt->bindParam(':status', $status);
}

$stmt->execute();
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance summary for the selected period
$summary_sql = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'Hadir' AND approval_status = 'Approved' THEN 1 END) as hadir,
    COUNT(CASE WHEN status = 'Sakit' AND approval_status = 'Approved' THEN 1 END) as sakit,
    COUNT(CASE WHEN status = 'Izin' AND approval_status = 'Approved' THEN 1 END) as izin,
    COUNT(CASE WHEN status = 'Terlambat' AND approval_status = 'Approved' THEN 1 END) as terlambat,
    COUNT(CASE WHEN status = 'Alpha' AND approval_status = 'Approved' THEN 1 END) as alpha
    FROM absensi
    WHERE siswa_id = :siswa_id
    AND tanggal BETWEEN :start_date AND :end_date
    AND approval_status = 'Approved'";

$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bindParam(':siswa_id', $siswa_id);
$summary_stmt->bindParam(':start_date', $start_date);
$summary_stmt->bindParam(':end_date', $end_date);
$summary_stmt->execute();
$attendance_summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate percentage
$total_days = $attendance_summary['total'];
$present_days = $attendance_summary['hadir'] + $attendance_summary['terlambat'];
$attendance_percentage = $total_days > 0 ? round(($present_days / $total_days) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Absensi - SMKN 40 Jakarta</title>
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

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 1rem;
        }

        .status-hadir {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-sakit {
            background-color: rgba(234, 179, 8, 0.1);
            color: #EAB308;
            border: 1px solid rgba(234, 179, 8, 0.3);
        }

        .status-izin {
            background-color: rgba(139, 92, 246, 0.1);
            color: #8B5CF6;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .status-terlambat {
            background-color: rgba(249, 115, 22, 0.1);
            color: #F97316;
            border: 1px solid rgba(249, 115, 22, 0.3);
        }

        .status-alpha {
            background-color: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .approval-pending {
            background-color: rgba(234, 179, 8, 0.1);
            color: #EAB308;
            border: 1px solid rgba(234, 179, 8, 0.3);
        }

        .approval-approved {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .approval-rejected {
            background-color: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Custom scrollbar for Statistik & Insight section */
        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(31, 41, 55, 0.2);
            border-radius: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(139, 92, 246, 0.5);
            border-radius: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(139, 92, 246, 0.7);
        }

        /* For Firefox */
        .custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: rgba(139, 92, 246, 0.5) rgba(31, 41, 55, 0.2);
        }

        /* Mobile responsive enhancements */
        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }

        .mobile-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease-in-out;
        }

        /* Responsive table adjustments */
        @media (max-width: 640px) {

            .table-compact th,
            .table-compact td {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
                font-size: 0.75rem;
            }

            .filter-container {
                flex-direction: column;
            }

            .filter-container>div {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            /* Calendar day size adjustment */
            #calendarGrid>div {
                height: 2rem;
            }

            #calendarGrid .text-xs {
                font-size: 0.65rem;
            }

            /* Status badges for small screens */
            .status-badge-sm {
                padding: 0.1rem 0.3rem;
                font-size: 0.65rem;
            }

            /* Image modal for mobile */
            #imageModal img {
                max-height: 80vh;
            }
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

        <nav class="p-4 space-y-2">
            <a href="../dashboard/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="index.php" class="flex items-center gap-3 text-white/90 p-3 rounded-lg menu-active">
                <i class="fas fa-history text-purple-500"></i>
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

        <div class="p-4 lg:p-8">
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <header class="mb-6 lg:mb-8">
                    <h1 class="text-xl lg:text-2xl font-bold">Riwayat Absensi</h1>
                    <p class="text-gray-400 text-sm lg:text-base mt-1">Lihat dan filter riwayat kehadiran Anda</p>
                </header>

                <!-- Summary Cards - Make responsive with grid -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 lg:gap-4 mb-6">
                    <!-- Total Attendance Card -->
                    <div class="glass-effect rounded-lg p-4">
                        <h3 class="text-xs uppercase text-gray-400 font-medium mb-1">Total</h3>
                        <p class="text-2xl font-bold"><?= $attendance_summary['total'] ?></p>
                        <div class="mt-2 text-xs">
                            <span class="text-gray-400">Periode: <?= date('M Y', strtotime($start_date)) ?></span>
                        </div>
                    </div>

                    <!-- Present Card -->
                    <div class="glass-effect rounded-lg p-4 border-l-4 border-green-500">
                        <h3 class="text-xs uppercase text-gray-400 font-medium mb-1">Hadir</h3>
                        <p class="text-2xl font-bold"><?= $attendance_summary['hadir'] ?></p>
                        <div class="mt-2 flex items-center">
                            <div class="w-full bg-gray-700 rounded-full h-1.5">
                                <div class="bg-green-500 h-1.5 rounded-full" style="width: <?= $attendance_summary['total'] > 0 ? ($attendance_summary['hadir'] / $attendance_summary['total'] * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Sick Card -->
                    <div class="glass-effect rounded-lg p-4 border-l-4 border-yellow-500">
                        <h3 class="text-xs uppercase text-gray-400 font-medium mb-1">Sakit</h3>
                        <p class="text-2xl font-bold"><?= $attendance_summary['sakit'] ?></p>
                        <div class="mt-2 flex items-center">
                            <div class="w-full bg-gray-700 rounded-full h-1.5">
                                <div class="bg-yellow-500 h-1.5 rounded-full" style="width: <?= $attendance_summary['total'] > 0 ? ($attendance_summary['sakit'] / $attendance_summary['total'] * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Permission Card -->
                    <div class="glass-effect rounded-lg p-4 border-l-4 border-purple-500">
                        <h3 class="text-xs uppercase text-gray-400 font-medium mb-1">Izin</h3>
                        <p class="text-2xl font-bold"><?= $attendance_summary['izin'] ?></p>
                        <div class="mt-2 flex items-center">
                            <div class="w-full bg-gray-700 rounded-full h-1.5">
                                <div class="bg-purple-500 h-1.5 rounded-full" style="width: <?= $attendance_summary['total'] > 0 ? ($attendance_summary['izin'] / $attendance_summary['total'] * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Late Card -->
                    <div class="glass-effect rounded-lg p-4 border-l-4 border-orange-500">
                        <h3 class="text-xs uppercase text-gray-400 font-medium mb-1">Terlambat</h3>
                        <p class="text-2xl font-bold"><?= $attendance_summary['terlambat'] ?></p>
                        <div class="mt-2 flex items-center">
                            <div class="w-full bg-gray-700 rounded-full h-1.5">
                                <div class="bg-orange-500 h-1.5 rounded-full" style="width: <?= $attendance_summary['total'] > 0 ? ($attendance_summary['terlambat'] / $attendance_summary['total'] * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Alpha Card -->
                    <div class="glass-effect rounded-lg p-4 border-l-4 border-red-500">
                        <h3 class="text-xs uppercase text-gray-400 font-medium mb-1">Alpha</h3>
                        <p class="text-2xl font-bold"><?= $attendance_summary['alpha'] ?></p>
                        <div class="mt-2 flex items-center">
                            <div class="w-full bg-gray-700 rounded-full h-1.5">
                                <div class="bg-red-500 h-1.5 rounded-full" style="width: <?= $attendance_summary['total'] > 0 ? ($attendance_summary['alpha'] / $attendance_summary['total'] * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Section - Enhanced for mobile -->
                <div class="glass-effect p-4 rounded-lg mb-6">
                    <form action="" method="GET" class="flex flex-wrap items-end gap-4 filter-container">
                        <div class="w-full sm:w-auto">
                            <label for="month" class="block text-xs text-gray-400 mb-1">Bulan</label>
                            <select id="month" name="month" class="w-full sm:w-auto bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 focus:outline-none focus:border-purple-500">
                                <?php for ($i = 1; $i <= 12; $i++) : ?>
                                    <option value="<?= sprintf("%02d", $i) ?>" <?= $month == sprintf("%02d", $i) ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="w-full sm:w-auto">
                            <label for="year" class="block text-xs text-gray-400 mb-1">Tahun</label>
                            <select id="year" name="year" class="w-full sm:w-auto bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 focus:outline-none focus:border-purple-500">
                                <?php
                                $currentYear = date('Y');
                                for ($i = $currentYear; $i >= $currentYear - 2; $i--) : ?>
                                    <option value="<?= $i ?>" <?= $year == $i ? 'selected' : '' ?>>
                                        <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="w-full sm:w-auto">
                            <label for="status" class="block text-xs text-gray-400 mb-1">Status</label>
                            <select id="status" name="status" class="w-full sm:w-auto bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 focus:outline-none focus:border-purple-500">
                                <option value="">Semua Status</option>
                                <option value="Hadir" <?= $status == 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                                <option value="Sakit" <?= $status == 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                                <option value="Izin" <?= $status == 'Izin' ? 'selected' : '' ?>>Izin</option>
                                <option value="Terlambat" <?= $status == 'Terlambat' ? 'selected' : '' ?>>Terlambat</option>
                                <option value="Alpha" <?= $status == 'Alpha' ? 'selected' : '' ?>>Alpha</option>
                            </select>
                        </div>

                        <div class="w-full sm:w-auto ml-auto mt-2 sm:mt-0">
                            <button type="submit" class="w-full sm:w-auto bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center gap-2">
                                <i class="fas fa-filter"></i>
                                <span>Filter</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Attendance History Table - Make scrollable for mobile -->
                <div class="glass-effect rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-900/40 to-indigo-900/40 p-4 border-b border-gray-800">
                        <h3 class="font-semibold flex items-center">
                            <i class="fas fa-list text-purple-500 mr-2"></i>
                            Data Absensi
                        </h3>
                    </div>

                    <?php if (count($attendance_records) > 0) : ?>
                        <div class="overflow-x-auto no-scrollbar">
                            <table class="w-full table-compact">
                                <thead>
                                    <tr class="border-b border-gray-800 bg-gray-900/30">
                                        <th class="text-left p-4 text-sm font-medium text-gray-300">Tanggal</th>
                                        <th class="text-left p-4 text-sm font-medium text-gray-300">Jam Masuk</th>
                                        <th class="text-left p-4 text-sm font-medium text-gray-300">Status</th>
                                        <th class="text-left p-4 text-sm font-medium text-gray-300">Persetujuan</th>
                                        <th class="text-left p-4 text-sm font-medium text-gray-300">Keterangan</th>
                                        <th class="text-left p-4 text-sm font-medium text-gray-300">Bukti</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record) : ?>
                                        <tr class="border-b border-gray-800 hover:bg-gray-900/20">
                                            <td class="p-4 text-sm">
                                                <?= $record['formatted_date'] ?>
                                            </td>
                                            <td class="p-4 text-sm">
                                                <?= $record['formatted_time'] ?: '—' ?>
                                            </td>
                                            <td class="p-4 text-sm">
                                                <span class="status-badge status-<?= strtolower($record['status']) ?>">
                                                    <?php
                                                    // Icon based on status
                                                    $icon = match ($record['status']) {
                                                        'Hadir' => 'fa-check',
                                                        'Sakit' => 'fa-hospital',
                                                        'Izin' => 'fa-clipboard-list',
                                                        'Terlambat' => 'fa-clock',
                                                        'Alpha' => 'fa-times',
                                                        default => 'fa-question'
                                                    };
                                                    ?>
                                                    <i class="fas <?= $icon ?> mr-1"></i>
                                                    <?= $record['status'] ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-sm">
                                                <span class="status-badge approval-<?= strtolower($record['approval_status']) ?>">
                                                    <?php
                                                    // Icon based on approval status
                                                    $approvalIcon = match ($record['approval_status']) {
                                                        'Approved' => 'fa-check',
                                                        'Rejected' => 'fa-times',
                                                        'Pending' => 'fa-clock',
                                                        default => 'fa-question'
                                                    };
                                                    ?>
                                                    <i class="fas <?= $approvalIcon ?> mr-1"></i>
                                                    <?= $record['approval_status'] ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-sm">
                                                <?= !empty($record['keterangan']) ? htmlspecialchars($record['keterangan']) : '—' ?>
                                            </td>
                                            <td class="p-4 text-sm">
                                                <?php if (!empty($record['bukti_foto'])) : ?>
                                                    <a href="#" onclick="showImagePreview('../../<?= $record['bukti_foto'] ?>')" class="text-purple-400 hover:text-purple-300 flex items-center gap-1">
                                                        <i class="fas fa-image"></i>
                                                        <span>Lihat</span>
                                                    </a>
                                                <?php else : ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="p-8 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-800/50 mb-4">
                                <i class="fas fa-calendar-times text-2xl text-gray-500"></i>
                            </div>
                            <p class="text-gray-400">Tidak ada data absensi untuk periode yang dipilih</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Responsive grid for Calendar and Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <!-- Attendance Calendar View -->
                    <div class="glass-effect rounded-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-purple-900/40 to-indigo-900/40 p-4 border-b border-gray-800">
                            <h3 class="font-semibold flex items-center">
                                <i class="fas fa-calendar-alt text-purple-500 mr-2"></i>
                                Kalender Absensi
                            </h3>
                        </div>
                        <div class="p-4">
                            <div id="calendarContainer" class="mb-4">
                                <!-- Calendar will be generated by JavaScript -->
                                <div id="calendarHeader" class="flex justify-between items-center mb-4">
                                    <h4 class="text-lg font-medium"><?= date('F Y', strtotime($start_date)) ?></h4>
                                </div>
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
                                    <!-- Calendar days will be filled by JavaScript -->
                                </div>
                            </div>

                            <!-- Calendar Legend -->
                            <div class="grid grid-cols-3 gap-2 pt-3 border-t border-gray-800 mt-3">
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
                                    <span class="text-xs">Tidak Ada Data</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Insights/Stats -->
                    <div class="glass-effect rounded-lg overflow-hidden flex flex-col">
                        <div class="bg-gradient-to-r from-purple-900/40 to-indigo-900/40 p-4 border-b border-gray-800">
                            <h3 class="font-semibold flex items-center">
                                <i class="fas fa-lightbulb text-purple-500 mr-2"></i>
                                Statistik & Insight
                            </h3>
                        </div>
                        <div class="p-4 overflow-y-auto custom-scrollbar" style="max-height: 460px;">
                            <!-- Overall Attendance Percentage -->
                            <div class="mb-4">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm text-gray-400">Tingkat Kehadiran Bulan Ini</span>
                                    <span class="font-medium text-white"><?= $attendance_percentage ?>%</span>
                                </div>
                                <div class="h-2.5 bg-gray-700 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-purple-600 to-indigo-500 rounded-full"
                                        style="width: <?= $attendance_percentage ?>%"></div>
                                </div>
                                <div class="mt-2 text-xs">
                                    <?php if ($attendance_percentage >= 90): ?>
                                        <span class="text-green-400">Sangat baik! Pertahankan kehadiranmu.</span>
                                    <?php elseif ($attendance_percentage >= 75): ?>
                                        <span class="text-yellow-400">Baik. Tingkatkan kehadiranmu untuk hasil yang lebih baik.</span>
                                    <?php else: ?>
                                        <span class="text-red-400">Perlu ditingkatkan. Kehadiran mempengaruhi nilai akhir.</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Day of Week Attendance Pattern -->
                            <div class="mb-4 pb-4 border-b border-gray-800">
                                <h4 class="text-sm font-medium text-white mb-2">Pola Kehadiran</h4>
                                <div style="height: 200px;"> <!-- Fixed height for chart container -->
                                    <canvas id="attendancePatternChart"></canvas>
                                </div>
                            </div>

                            <!-- Quick Stats -->
                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 rounded-md bg-purple-500/20 flex items-center justify-center mr-3">
                                            <i class="fas fa-medal text-purple-400"></i>
                                        </div>
                                        <span class="text-sm">Status Terbanyak</span>
                                    </div>
                                    <?php
                                    $statuses = ['hadir', 'sakit', 'izin', 'terlambat', 'alpha'];
                                    $max_status = 'hadir';
                                    $max_count = 0;
                                    foreach ($statuses as $s) {
                                        if ($attendance_summary[$s] > $max_count) {
                                            $max_count = $attendance_summary[$s];
                                            $max_status = $s;
                                        }
                                    }

                                    $status_colors = [
                                        'hadir' => 'text-green-400',
                                        'sakit' => 'text-yellow-400',
                                        'izin' => 'text-purple-400',
                                        'terlambat' => 'text-orange-400',
                                        'alpha' => 'text-red-400'
                                    ];

                                    $status_label = ucfirst($max_status);
                                    $color_class = $status_colors[$max_status];
                                    ?>
                                    <span class="font-medium <?= $color_class ?>"><?= $status_label ?></span>
                                </div>

                                <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 rounded-md bg-purple-500/20 flex items-center justify-center mr-3">
                                            <i class="fas fa-history text-purple-400"></i>
                                        </div>
                                        <span class="text-sm">Absensi Terakhir</span>
                                    </div>
                                    <?php
                                    $last_attendance = null;
                                    if (count($attendance_records) > 0) {
                                        $last_attendance = $attendance_records[0]; // Most recent record
                                    }
                                    ?>
                                    <span class="font-medium">
                                        <?= $last_attendance ? date('d/m/Y', strtotime($last_attendance['tanggal'])) : '-' ?>
                                    </span>
                                </div>

                                <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 rounded-md bg-purple-500/20 flex items-center justify-center mr-3">
                                            <i class="fas fa-clock text-purple-400"></i>
                                        </div>
                                        <span class="text-sm">Rata-rata Jam Masuk</span>
                                    </div>
                                    <?php
                                    // Calculate average entry time (only for records with jam_masuk)
                                    $total_seconds = 0;
                                    $count_with_time = 0;
                                    foreach ($attendance_records as $record) {
                                        if (!empty($record['jam_masuk'])) {
                                            $time_parts = explode(':', $record['jam_masuk']);
                                            $seconds = $time_parts[0] * 3600 + $time_parts[1] * 60 + $time_parts[2];
                                            $total_seconds += $seconds;
                                            $count_with_time++;
                                        }
                                    }

                                    $avg_time = '-';
                                    if ($count_with_time > 0) {
                                        $avg_seconds = $total_seconds / $count_with_time;
                                        $avg_hours = floor($avg_seconds / 3600);
                                        $avg_minutes = floor(($avg_seconds % 3600) / 60);
                                        $avg_time = sprintf('%02d:%02d', $avg_hours, $avg_minutes);
                                    }
                                    ?>
                                    <span class="font-medium"><?= $avg_time ?></span>
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

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
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

        // Update the script to include the calendar and pattern chart
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize attendance calendar
            generateAttendanceCalendar();

            // Initialize pattern chart
            generatePatternChart();

            // Function to generate the attendance calendar
            function generateAttendanceCalendar() {
                const monthYear = '<?= $year . '-' . $month ?>';
                const [year, month] = monthYear.split('-').map(Number);

                // Get attendance data from PHP
                const attendanceData = <?= json_encode($attendance_records) ?>;

                // Map attendance data by day
                const attendanceMap = {};
                attendanceData.forEach(record => {
                    const day = new Date(record.tanggal).getDate();
                    attendanceMap[day] = record.status;
                });

                // Get first day of month (0 = Sunday, 1 = Monday, etc.)
                const firstDay = new Date(year, month - 1, 1).getDay();

                // Get number of days in month
                const daysInMonth = new Date(year, month, 0).getDate();

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
                    const currentDate = new Date(year, month - 1, day);
                    const isToday = currentDate.toDateString() === new Date().toDateString();

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
                        }
                        cell.appendChild(statusDot);
                    } else {
                        const statusDot = document.createElement('span');
                        statusDot.className = 'h-2 w-2 rounded-full bg-gray-600 mt-1';
                        cell.appendChild(statusDot);
                    }

                    // Style today's cell
                    if (isToday) {
                        cell.classList.add('ring-2', 'ring-purple-500');
                    }

                    calendarGrid.appendChild(cell);
                }
            }

            // Function to generate the attendance pattern chart
            function generatePatternChart() {
                // Calculate attendance by day of week
                const daysOfWeek = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const attendanceData = <?= json_encode($attendance_records) ?>;

                // Initialize counts
                const presentByDay = [0, 0, 0, 0, 0, 0, 0];
                const absentByDay = [0, 0, 0, 0, 0, 0, 0];

                // Count attendance by day of week
                attendanceData.forEach(record => {
                    const date = new Date(record.tanggal);
                    const dayOfWeek = date.getDay(); // 0 = Sunday, 6 = Saturday

                    if (record.status === 'Hadir' || record.status === 'Terlambat') {
                        presentByDay[dayOfWeek]++;
                    } else {
                        absentByDay[dayOfWeek]++;
                    }
                });

                // Create the chart
                const ctx = document.getElementById('attendancePatternChart').getContext('2d');

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: daysOfWeek,
                        datasets: [{
                                label: 'Hadir',
                                data: presentByDay,
                                backgroundColor: '#10B981',
                                barThickness: 10,
                            },
                            {
                                label: 'Tidak Hadir',
                                data: absentByDay,
                                backgroundColor: '#EF4444',
                                barThickness: 10,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'end',
                                labels: {
                                    boxWidth: 12,
                                    usePointStyle: true,
                                    color: '#E5E7EB',
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.9)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: 'rgba(147, 51, 234, 0.3)',
                                borderWidth: 1
                            },
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#9CA3AF',
                                    font: {
                                        size: 10
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)'
                                },
                                ticks: {
                                    precision: 0,
                                    color: '#9CA3AF',
                                    font: {
                                        size: 10
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });

        // Add mobile sidebar toggle function
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

        // Update time for mobile view
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

        // Make sure modals close when pressing escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImagePreview();

                // Also close sidebar on mobile
                if (window.innerWidth < 1024) { // lg breakpoint in Tailwind
                    const sidebar = document.getElementById('sidebar');
                    if (!sidebar.classList.contains('-translate-x-full')) {
                        toggleSidebar();
                    }
                }
            }
        });

        // Add mobile time updater
        setInterval(updateMobileTime, 60000); // Update every minute
        updateMobileTime(); // Initial call

        // Responsive adjustments for the calendar
        function adjustCalendarForMobile() {
            if (window.innerWidth < 640) { // sm breakpoint
                document.querySelectorAll('#calendarGrid > div').forEach(cell => {
                    const dayNumber = cell.querySelector('span:first-child');
                    if (dayNumber) {
                        dayNumber.classList.add('text-[10px]');
                    }

                    const statusDot = cell.querySelector('span:nth-child(2)');
                    if (statusDot) {
                        statusDot.classList.add('h-1.5', 'w-1.5');
                    }
                });
            }
        }

        // Adjust chart for responsive displays
        function adjustChartForMobile() {
            const chart = window.attendancePatternChart;
            if (chart && window.innerWidth < 640) {
                chart.options.plugins.legend.labels.boxWidth = 8;
                chart.options.plugins.legend.labels.font = {
                    size: 9
                };
                chart.update();
            }
        }

        // Run responsive adjustments on resize and load
        window.addEventListener('resize', function() {
            adjustCalendarForMobile();
            adjustChartForMobile();
        });

        document.addEventListener('DOMContentLoaded', function() {
            // ... existing DOMContentLoaded code ...

            // Run responsive adjustments
            adjustCalendarForMobile();
            adjustChartForMobile();

            // Add responsive behavior to the image preview
            document.getElementById('imageModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeImagePreview();
                }
            });
        });
    </script>
</body>

</html>