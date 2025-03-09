<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check for required ID
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'];
$error = '';
$success = false;

// Get absensi data
$sql = "SELECT a.*, s.nama_lengkap, s.nis 
        FROM absensi a
        JOIN siswa s ON a.siswa_id = s.id
        WHERE a.id = :id";
$stmt = $conn->prepare($sql);
$stmt->execute(['id' => $id]);
$absensi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$absensi) {
    header("Location: index.php?error=not_found");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $status = $_POST['status'];
        $tanggal = $_POST['tanggal'];
        $jam_masuk = $_POST['jam_masuk'] ?: NULL;
        $keterangan = $_POST['keterangan'];
        $approval_status = $_POST['approval_status'];

        // Begin transaction
        $conn->beginTransaction();

        // Update absensi record
        $sql = "UPDATE absensi SET 
                status = :status, 
                tanggal = :tanggal, 
                jam_masuk = :jam_masuk, 
                keterangan = :keterangan,
                approval_status = :approval_status
                WHERE id = :id";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'status' => $status,
            'tanggal' => $tanggal,
            'jam_masuk' => $jam_masuk,
            'keterangan' => $keterangan,
            'approval_status' => $approval_status,
            'id' => $id
        ]);

        // Log activity
        $sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                VALUES ('admin', :admin_id, 'update', :description)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'admin_id' => $_SESSION['admin_id'],
            'description' => "Admin mengedit absensi " . $absensi['nama_lengkap'] . " tanggal " . date('d/m/Y', strtotime($tanggal))
        ]);

        // Handle file upload if any
        if (isset($_FILES['bukti_file']) && $_FILES['bukti_file']['error'] == 0) {
            $target_dir = "../../uploads/files/";
            $file_extension = pathinfo($_FILES['bukti_file']['name'], PATHINFO_EXTENSION);
            $new_filename = 'file_' . time() . '_' . $absensi['siswa_id'] . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;

            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            if (move_uploaded_file($_FILES['bukti_file']['tmp_name'], $target_file)) {
                // Update database with new file path
                $sql = "UPDATE absensi SET bukti_file = :file WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    'file' => 'uploads/files/' . $new_filename,
                    'id' => $id
                ]);
            }
        }

        $conn->commit();
        $success = true;

        // Refresh data after update
        $stmt = $conn->prepare("SELECT a.*, s.nama_lengkap, s.nis 
                                FROM absensi a
                                JOIN siswa s ON a.siswa_id = s.id
                                WHERE a.id = :id");
        $stmt->execute(['id' => $id]);
        $absensi = $stmt->fetch(PDO::FETCH_ASSOC);

        header("Location: detail.php?id=$id&updated=true");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Get all students for the dropdown
$sql = "SELECT id, nama_lengkap, nis FROM siswa ORDER BY nama_lengkap";
$siswa_list = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Absensi - SMKN 40 Jakarta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(147, 51, 234, 0.3);
        }

        body {
            background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 100%);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(147, 51, 234, 0.2), rgba(147, 51, 234, 0.05));
            border-left: 4px solid #9333ea;
        }

        /* Mobile responsive styles */
        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }

        .mobile-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease-in-out;
        }

        /* Hide scrollbar */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Form animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease forwards;
        }

        /* Improved touch targets for mobile */
        @media (max-width: 640px) {
            .touch-target {
                min-height: 44px;
            }

            select,
            input[type="date"],
            input[type="time"] {
                font-size: 16px;
                /* Prevents iOS zoom on focus */
            }
        }

        /* Fix for iOS full height */
        @supports (-webkit-touch-callout: none) {
            .min-h-screen {
                min-height: -webkit-fill-available;
            }
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

        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height: calc(100vh - 76px);">
            <a href="../dashboard/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="index.php" class="flex items-center gap-3 text-white/90 p-3 rounded-lg menu-active">
                <i class="fas fa-calendar-check text-purple-500"></i>
                <span>Absensi</span>
            </a>
            <a href="../siswa/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-users"></i>
                <span>Data Siswa</span>
            </a>
            <a href="../laporan/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-file-alt"></i>
                <span>Laporan</span>
            </a>
            <a href="../profil/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-user-cog"></i>
                <span>Profil</span>
            </a>
            <a href="../logout.php" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-red-500/10 hover:text-red-500 transition-colors mt-10">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 transition-all duration-300">
        <!-- Mobile Header -->
        <div class="lg:hidden bg-gray-900/60 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-2 -ml-2 rounded-lg hover:bg-gray-800/60" aria-label="Menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logo-smk40.png" alt="SMKN 40" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php
                // Use admin photo from session if available
                $photo_path = $_SESSION['admin_photo'] ?? 'assets/default/avatar.png';
                ?>
                <img src="../../<?= $photo_path ?>" alt="Profile" class="h-8 w-8 rounded-full object-cover border border-purple-500/50">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header with back button - enhanced for mobile -->
                <div class="flex items-center mb-6">
                    <a href="detail.php?id=<?= $id ?>" class="mr-3 p-2 rounded-full hover:bg-gray-800/60 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Edit Absensi</h1>
                        <p class="text-sm md:text-base text-gray-400">Ubah data kehadiran siswa</p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="bg-green-500/10 border border-green-500/30 text-green-500 rounded-lg p-4 mb-6 flex items-start animate-fade-in">
                        <i class="fas fa-check-circle mt-0.5 mr-3"></i>
                        <div>
                            <p class="font-medium">Data berhasil diperbarui</p>
                            <p class="text-sm text-green-500/80 mt-1">Perubahan telah disimpan ke sistem.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500/30 text-red-500 rounded-lg p-4 mb-6 flex items-start animate-fade-in">
                        <i class="fas fa-exclamation-circle mt-0.5 mr-3"></i>
                        <div>
                            <p class="font-medium">Gagal menyimpan perubahan</p>
                            <p class="text-sm text-red-500/80 mt-1"><?= $error ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Edit Form - Mobile responsive -->
                <div class="glass-effect rounded-xl p-4 md:p-6 mb-6 animate-fade-in">
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Student Info - Improved for mobile -->
                        <div class="mb-6 p-4 bg-gray-800/50 rounded-lg">
                            <p class="text-sm text-gray-400 mb-1">Data Siswa:</p>
                            <p class="text-base md:text-lg font-medium break-words"><?= htmlspecialchars($absensi['nama_lengkap']) ?> (<?= htmlspecialchars($absensi['nis']) ?>)</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 mb-6">
                            <!-- Date - Mobile optimized -->
                            <div>
                                <label for="tanggal" class="block text-sm text-gray-400 mb-2">Tanggal</label>
                                <input type="date" id="tanggal" name="tanggal" value="<?= $absensi['tanggal'] ?>" required
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-3 md:py-2 text-white focus:outline-none focus:border-purple-500 touch-target">
                            </div>

                            <!-- Time - Mobile optimized -->
                            <div>
                                <label for="jam_masuk" class="block text-sm text-gray-400 mb-2">Jam Masuk</label>
                                <input type="time" id="jam_masuk" name="jam_masuk" value="<?= $absensi['jam_masuk'] !== '00:00:00' ? $absensi['jam_masuk'] : '' ?>"
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-3 md:py-2 text-white focus:outline-none focus:border-purple-500 touch-target">
                                <p class="text-xs text-gray-400 mt-1">Biarkan kosong untuk absensi sakit/izin/alpha</p>
                            </div>

                            <!-- Status dropdown - Mobile optimized -->
                            <div>
                                <label for="status" class="block text-sm text-gray-400 mb-2">Status</label>
                                <select id="status" name="status" required onchange="toggleTimeVisibility()"
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-3 md:py-2 text-white focus:outline-none focus:border-purple-500 touch-target">
                                    <option value="Hadir" <?= $absensi['status'] == 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                                    <option value="Sakit" <?= $absensi['status'] == 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                                    <option value="Izin" <?= $absensi['status'] == 'Izin' ? 'selected' : '' ?>>Izin</option>
                                    <option value="Terlambat" <?= $absensi['status'] == 'Terlambat' ? 'selected' : '' ?>>Terlambat</option>
                                    <option value="Alpha" <?= $absensi['status'] == 'Alpha' ? 'selected' : '' ?>>Alpha</option>
                                </select>
                            </div>

                            <!-- Approval Status dropdown - Mobile optimized -->
                            <div>
                                <label for="approval_status" class="block text-sm text-gray-400 mb-2">Status Persetujuan</label>
                                <select id="approval_status" name="approval_status" required
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-3 md:py-2 text-white focus:outline-none focus:border-purple-500 touch-target">
                                    <option value="Pending" <?= $absensi['approval_status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Approved" <?= $absensi['approval_status'] == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="Rejected" <?= $absensi['approval_status'] == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>
                        </div>

                        <!-- Description - Mobile optimized -->
                        <div class="mb-6">
                            <label for="keterangan" class="block text-sm text-gray-400 mb-2">Keterangan</label>
                            <textarea id="keterangan" name="keterangan" rows="3"
                                class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-3 text-white focus:outline-none focus:border-purple-500 touch-target"><?= htmlspecialchars($absensi['keterangan']) ?></textarea>
                        </div>

                        <!-- File Upload - Improved for mobile -->
                        <div class="mb-6">
                            <label for="bukti_file" class="block text-sm text-gray-400 mb-2">Ganti File Bukti (Opsional)</label>
                            <div class="relative">
                                <input type="file" id="bukti_file" name="bukti_file" class="hidden"
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                <input type="text" id="file_name" readonly placeholder="Pilih file..."
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-3 md:py-2 text-white cursor-pointer touch-target"
                                    onclick="document.getElementById('bukti_file').click()">
                                <button type="button" onclick="document.getElementById('bukti_file').click()"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 px-3 py-1 bg-purple-600/80 hover:bg-purple-600 rounded text-sm">
                                    Browse
                                </button>
                            </div>
                            <?php if ($absensi['bukti_file']): ?>
                                <div class="flex items-center mt-2">
                                    <span class="text-xs text-gray-400 mr-2">File saat ini:</span>
                                    <a href="../../<?= htmlspecialchars($absensi['bukti_file']) ?>" target="_blank"
                                        class="text-xs text-blue-400 hover:text-blue-300 flex items-center">
                                        <i class="fas fa-file-alt mr-1"></i>
                                        <span class="truncate max-w-[200px]">Lihat File</span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Submit Button - Full width on mobile -->
                        <div class="flex justify-end">
                            <button type="submit" class="w-full md:w-auto px-6 py-3 md:py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors font-medium">
                                <i class="fas fa-save mr-2"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Back button - only visible on mobile -->
                <div class="mt-6 flex justify-center lg:hidden">
                    <a href="detail.php?id=<?= $id ?>" class="px-4 py-2.5 bg-gray-700 hover:bg-gray-600 rounded-lg flex items-center justify-center text-sm transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Detail Absensi
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        // Toggle time input visibility based on selected status
        function toggleTimeVisibility() {
            const status = document.getElementById('status').value;
            const timeInput = document.getElementById('jam_masuk');
            const timeLabel = timeInput.previousElementSibling;

            if (status === 'Hadir' || status === 'Terlambat') {
                timeInput.removeAttribute('disabled');
                timeInput.classList.remove('opacity-50');
                timeLabel.classList.remove('opacity-50');
            } else {
                timeInput.setAttribute('disabled', 'disabled');
                timeInput.classList.add('opacity-50');
                timeLabel.classList.add('opacity-50');
                timeInput.value = '';
            }
        }

        // Initialize time visibility on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleTimeVisibility();

            // Handle file input display
            document.getElementById('bukti_file').addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'Tidak ada file dipilih';
                document.getElementById('file_name').value = fileName;
            });

            // Better handling for date/time inputs on mobile
            if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                // Add better touch handling
                const inputs = document.querySelectorAll('input, select, textarea, button');
                inputs.forEach(input => {
                    input.classList.add('touch-target');
                });
            }
        });

        // Mobile sidebar toggle function
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

        // Add mobile time updater
        setInterval(updateMobileTime, 60000); // Update every minute
        updateMobileTime(); // Initial call

        // Make sure sidebar closes when pressing escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close sidebar on mobile
                if (window.innerWidth < 1024) {
                    const sidebar = document.getElementById('sidebar');
                    if (!sidebar.classList.contains('-translate-x-full')) {
                        toggleSidebar();
                    }
                }
            }
        });

        // Fix viewport height issues on mobile browsers
        function setMobileHeight() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }

        window.addEventListener('resize', setMobileHeight);
        setMobileHeight();
    </script>
</body>

</html>