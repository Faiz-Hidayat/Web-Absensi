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

// Get student information
$sql = "SELECT * FROM siswa WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->execute(['id' => $id]);
$siswa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$siswa) {
    header("Location: index.php?error=not_found");
    exit();
}

// Get attendance statistics for this student
$sql = "SELECT status, COUNT(*) as count FROM absensi WHERE siswa_id = :siswa_id GROUP BY status";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $id]);
$absensi_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get most recent attendances
$sql = "SELECT id, tanggal, jam_masuk, status, approval_status FROM absensi WHERE siswa_id = :siswa_id ORDER BY tanggal DESC, created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->execute(['siswa_id' => $id]);
$recent_absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate attendance percentages
$total_absensi = array_sum($absensi_stats);
$percentages = [];

if ($total_absensi > 0) {
    foreach ($absensi_stats as $status => $count) {
        $percentages[$status] = round(($count / $total_absensi) * 100);
    }
}

// Get default percentages for categories that aren't logged yet
$status_defaults = ['Hadir', 'Sakit', 'Izin', 'Terlambat', 'Alpha'];
foreach ($status_defaults as $status) {
    if (!isset($absensi_stats[$status])) {
        $absensi_stats[$status] = 0;
        $percentages[$status] = 0;
    }
}

// Status colors for the charts and badges
$status_colors = [
    'Hadir' => 'green',
    'Sakit' => 'yellow',
    'Izin' => 'purple',
    'Terlambat' => 'orange',
    'Alpha' => 'red'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Siswa - SMKN 40 Jakarta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Animations */
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

        /* Fix for mobile browsers */
        @supports (-webkit-touch-callout: none) {
            .min-h-screen {
                min-height: -webkit-fill-available;
            }
        }

        /* Improved chart container for mobile */
        .chart-container {
            position: relative;
            margin: 0 auto;
        }

        /* Touch feedback */
        .touch-effect {
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .touch-effect:active {
            transform: scale(0.97);
            opacity: 0.9;
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
            <a href="../absensi/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-calendar-check"></i>
                <span>Absensi</span>
            </a>
            <a href="index.php" class="flex items-center gap-3 text-white/90 p-3 rounded-lg menu-active">
                <i class="fas fa-users text-purple-500"></i>
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
            <div class="max-w-7xl mx-auto">
                <!-- Header with back button - enhanced for mobile -->
                <div class="flex items-center mb-6">
                    <a href="index.php" class="mr-3 p-2 rounded-full hover:bg-gray-800/60 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Detail Siswa</h1>
                        <p class="text-sm md:text-base text-gray-400">Informasi lengkap data siswa</p>
                    </div>
                </div>

                <?php if (isset($_GET['updated'])): ?>
                    <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4 mb-6 flex items-center animate-fade-in">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <p class="text-sm md:text-base">Data siswa berhasil diperbarui.</p>
                    </div>
                <?php endif; ?>

                <!-- Responsive Grid Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 mb-6">
                    <!-- Student Profile Card - Mobile optimized -->
                    <div class="glass-effect rounded-xl overflow-hidden">
                        <!-- Header banner with proper mobile height -->
                        <div class="h-24 sm:h-32 bg-gradient-to-r from-purple-600/30 to-blue-600/30 relative"></div>

                        <!-- Profile content with adjusted spacing -->
                        <div class="px-4 sm:px-6 pb-5 sm:pb-6 -mt-12 sm:-mt-14 relative">
                            <!-- Profile picture with responsive size -->
                            <img src="../../<?= $siswa['foto_profil'] ?>"
                                alt="<?= htmlspecialchars($siswa['nama_lengkap']) ?>"
                                class="w-24 h-24 sm:w-28 sm:h-28 rounded-xl object-cover border-4 border-gray-900 shadow-lg">

                            <!-- Student info with responsive spacing -->
                            <h3 class="text-lg sm:text-xl font-bold mt-3 sm:mt-4"><?= htmlspecialchars($siswa['nama_lengkap']) ?></h3>
                            <p class="text-sm text-gray-400 mb-3 sm:mb-4"><?= $siswa['nis'] ?> • Kelas <?= $siswa['kelas'] ?> <?= $siswa['jurusan'] ?></p>

                            <!-- Contact info -->
                            <div class="space-y-2 sm:space-y-3">
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-envelope w-5 text-center text-purple-500"></i>
                                    <span class="text-sm break-all"><?= htmlspecialchars($siswa['email']) ?></span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-clock w-5 text-center text-purple-500"></i>
                                    <span class="text-sm">Terdaftar: <?= date('d/m/Y', strtotime($siswa['created_at'])) ?></span>
                                </div>
                            </div>

                            <!-- Action buttons with better touch targets -->
                            <div class="flex mt-4 sm:mt-5 gap-3">
                                <a href="edit.php?id=<?= $id ?>"
                                    class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-center py-2.5 sm:py-2 rounded-lg text-sm font-medium transition-colors touch-effect">
                                    <i class="fas fa-edit mr-2"></i>Edit
                                </a>
                                <button onclick="confirmDelete(<?= $id ?>)"
                                    class="flex-1 bg-red-600 hover:bg-red-700 text-center py-2.5 sm:py-2 rounded-lg text-sm font-medium transition-colors touch-effect">
                                    <i class="fas fa-trash mr-2"></i>Hapus
                                </button>
                            </div>

                            <!-- View all attendances link -->
                            <a href="../absensi/?search=<?= urlencode($siswa['nis']) ?>"
                                class="block mt-3 bg-gray-800 hover:bg-gray-700 text-center py-2.5 sm:py-2 rounded-lg text-sm font-medium transition-colors touch-effect">
                                <i class="fas fa-history mr-2"></i>Lihat Semua Riwayat Absensi
                            </a>
                        </div>
                    </div>

                    <!-- Attendance Donut Chart - Mobile optimized -->
                    <div class="glass-effect rounded-xl p-4 sm:p-6 overflow-hidden h-fit">
                        <h3 class="font-semibold mb-4 text-base sm:text-lg">Statistik Kehadiran</h3>

                        <!-- Responsive layout for chart and legends -->
                        <div class="flex flex-col sm:flex-row justify-around items-center gap-4 sm:gap-0">
                            <!-- Chart container with proper sizing -->
                            <div class="chart-container" style="height: 120px; width: 120px;">
                                <canvas id="donutChart"></canvas>
                            </div>

                            <!-- Legend with proper spacing -->
                            <div class="space-y-2 sm:space-y-3 w-full sm:w-auto">
                                <?php foreach ($status_defaults as $status): ?>
                                    <div class="flex items-center gap-2 justify-between sm:justify-start">
                                        <div class="flex items-center gap-2">
                                            <span class="w-3 h-3 rounded-full inline-block bg-<?= $status_colors[$status] ?>-500"></span>
                                            <span class="text-sm"><?= $status ?>:</span>
                                        </div>
                                        <span class="text-sm font-medium"><?= $absensi_stats[$status] ?> (<?= $percentages[$status] ?>%)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Mobile-only total attendances -->
                        <div class="mt-4 pt-3 border-t border-gray-700 text-center sm:hidden">
                            <p class="text-sm text-gray-400">Total Kehadiran:</p>
                            <p class="text-lg font-medium"><?= $total_absensi ?> hari</p>
                        </div>
                    </div>

                    <!-- Recent Attendances - Mobile optimized -->
                    <div class="glass-effect rounded-xl p-4 sm:p-6 overflow-hidden">
                        <h3 class="font-semibold mb-3 sm:mb-4 text-base sm:text-lg">Absensi Terbaru</h3>

                        <?php if (count($recent_absensi) > 0): ?>
                            <div class="space-y-2 sm:space-y-3">
                                <?php foreach ($recent_absensi as $absensi): ?>
                                    <a href="../absensi/detail.php?id=<?= $absensi['id'] ?>"
                                        class="block p-3 rounded-lg bg-gray-800/50 hover:bg-gray-800 transition-colors touch-effect">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="text-sm font-medium"><?= date('d/m/Y', strtotime($absensi['tanggal'])) ?></p>
                                                <p class="text-xs text-gray-400 mt-0.5">
                                                    <?= $absensi['jam_masuk'] !== '00:00:00' ? date('H:i', strtotime($absensi['jam_masuk'])) : '-' ?>
                                                </p>
                                            </div>
                                            <div class="flex flex-col xs:flex-row items-end xs:items-center gap-1 xs:gap-2">
                                                <span class="px-2 py-1 text-xs rounded-full bg-<?= $status_colors[$absensi['status']] ?>-500/10 text-<?= $status_colors[$absensi['status']] ?>-500">
                                                    <?= $absensi['status'] ?>
                                                </span>
                                                <?php
                                                $approval_color = 'gray';
                                                if ($absensi['approval_status'] === 'Approved') $approval_color = 'green';
                                                if ($absensi['approval_status'] === 'Rejected') $approval_color = 'red';
                                                if ($absensi['approval_status'] === 'Pending') $approval_color = 'yellow';
                                                ?>
                                                <span class="px-2 py-1 text-xs rounded-full bg-<?= $approval_color ?>-500/10 text-<?= $approval_color ?>-500">
                                                    <?= $absensi['approval_status'] ?>
                                                </span>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-calendar-day text-3xl mb-2"></i>
                                <p>Belum ada data absensi</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Back button on mobile only -->
                <div class="mt-6 flex justify-center lg:hidden">
                    <a href="index.php" class="px-4 py-2.5 bg-gray-700 hover:bg-gray-600 rounded-lg flex items-center justify-center text-sm transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar Siswa
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete Confirmation Modal - Mobile optimized -->
    <div id="deleteModal" class="fixed inset-0 flex items-center justify-center z-[100] hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideDeleteModal()"></div>
        <div class="glass-effect rounded-lg p-5 sm:p-8 w-11/12 max-w-md relative z-10 mx-4 animate-fade-in">
            <h3 class="text-lg sm:text-xl font-semibold mb-3 sm:mb-4">Konfirmasi Hapus</h3>
            <p class="text-gray-300 mb-4 sm:mb-6 text-sm sm:text-base">Apakah Anda yakin ingin menghapus data siswa ini? Semua data absensi terkait juga akan dihapus. Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex justify-end gap-3 sm:gap-4">
                <button onclick="hideDeleteModal()" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm">
                    Batal
                </button>
                <form method="POST" action="delete.php">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm">
                        Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        // Chart initialization with responsive options
        const donutData = {
            labels: <?= json_encode($status_defaults) ?>,
            datasets: [{
                data: [
                    <?= $percentages['Hadir'] ?>,
                    <?= $percentages['Sakit'] ?>,
                    <?= $percentages['Izin'] ?>,
                    <?= $percentages['Terlambat'] ?>,
                    <?= $percentages['Alpha'] ?>
                ],
                backgroundColor: [
                    'rgb(16, 185, 129)', // Green for Hadir
                    'rgb(234, 179, 8)', // Yellow for Sakit
                    'rgb(139, 92, 246)', // Purple for Izin
                    'rgb(249, 115, 22)', // Orange for Terlambat
                    'rgb(239, 68, 68)' // Red for Alpha
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        };

        const ctx = document.getElementById('donutChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: donutData,
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
                            // More mobile-friendly tooltips
                            title: function(context) {
                                return context[0].label;
                            },
                            label: function(context) {
                                return ` ${context.parsed}% (${Object.values(<?= json_encode($absensi_stats) ?>)[context.dataIndex]} hari)`;
                            }
                        }
                    }
                },
                // Better animation for mobile
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 1000
                }
            }
        });

        // Delete confirmation
        function confirmDelete(id) {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

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
                // Close delete modal if open
                if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                    hideDeleteModal();
                    return;
                }

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

        // Enhance touch interactions
        document.addEventListener('DOMContentLoaded', function() {
            if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                // Add visual feedback for touch interactions
                document.querySelectorAll('.touch-effect').forEach(el => {
                    el.addEventListener('touchstart', function() {
                        this.style.transform = 'scale(0.97)';
                        this.style.opacity = '0.9';
                    }, {
                        passive: true
                    });

                    el.addEventListener('touchend', function() {
                        this.style.transform = 'scale(1)';
                        this.style.opacity = '1';
                    }, {
                        passive: true
                    });
                });
            }

            // Check for very small screens and adjust chart size if needed
            if (window.innerWidth < 340) {
                const chartContainer = document.querySelector('.chart-container');
                if (chartContainer) {
                    chartContainer.style.width = '100px';
                    chartContainer.style.height = '100px';
                }
            }
        });
    </script>
</body>

</html>