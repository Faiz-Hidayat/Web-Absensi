<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Default filter values
$date_filter = $_GET['date'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';
$kelas_filter = $_GET['kelas'] ?? '';
$jurusan_filter = $_GET['jurusan'] ?? '';
$approval_filter = $_GET['approval'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Base query for count
$count_sql = "SELECT COUNT(*) as total FROM absensi a
            JOIN siswa s ON a.siswa_id = s.id 
            WHERE 1=1";

// Base query for data
$sql = "SELECT a.id, a.tanggal, a.jam_masuk, a.status, a.approval_status, 
        s.nama_lengkap, s.nis, s.kelas, s.jurusan, s.foto_profil 
        FROM absensi a
        JOIN siswa s ON a.siswa_id = s.id 
        WHERE 1=1";

// If we're showing an analysis or report that should only include approved records
if (isset($show_analysis) && $show_analysis) {
    $sql .= " AND a.approval_status = 'Approved'";
}

$params = [];

// Apply filters
if ($date_filter) {
    $sql .= " AND a.tanggal = :date";
    $count_sql .= " AND a.tanggal = :date";
    $params['date'] = $date_filter;
}

if ($status_filter) {
    $sql .= " AND a.status = :status";
    $count_sql .= " AND a.status = :status";
    $params['status'] = $status_filter;
}

if ($kelas_filter) {
    $sql .= " AND s.kelas = :kelas";
    $count_sql .= " AND s.kelas = :kelas";
    $params['kelas'] = $kelas_filter;
}

if ($jurusan_filter) {
    $sql .= " AND s.jurusan = :jurusan";
    $count_sql .= " AND s.jurusan = :jurusan";
    $params['jurusan'] = $jurusan_filter;
}

if ($approval_filter) {
    $sql .= " AND a.approval_status = :approval";
    $count_sql .= " AND a.approval_status = :approval";
    $params['approval'] = $approval_filter;
}

if ($search) {
    $sql .= " AND (s.nama_lengkap LIKE :search OR s.nis LIKE :search)";
    $count_sql .= " AND (s.nama_lengkap LIKE :search OR s.nis LIKE :search)";
    $params['search'] = "%$search%";
}

// Add sort parameters to the query
$sort_column = $_GET['sort'] ?? 'tanggal';
$sort_order = $_GET['order'] ?? 'DESC';

// Validate sort column to prevent SQL injection
$valid_columns = ['nis', 'nama_lengkap', 'kelas', 'tanggal', 'jam_masuk', 'status', 'approval_status'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'tanggal';

// Add sorting prefix for joined tables
$sort_prefix = ($sort_column == 'nama_lengkap' || $sort_column == 'nis' || $sort_column == 'kelas') ? 's.' : 'a.';

// Add ORDER BY clause
$sql .= " ORDER BY " . $sort_prefix . "$sort_column " . ($sort_order === 'ASC' ? 'ASC' : 'DESC');

if ($sort_column != 'tanggal') {
    $sql .= ", a.tanggal DESC"; // Secondary sort by date always
}

// Add pagination LIMIT clause
$sql .= " LIMIT :offset, :limit";

// Get total count for pagination
$count_stmt = $conn->prepare($count_sql);
foreach ($params as $key => $value) {
    if ($key != 'offset' && $key != 'limit') {
        $count_stmt->bindValue(':' . $key, $value);
    }
}
$count_stmt->execute();
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

// Execute query
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    if ($key != 'offset' && $key != 'limit') {
        $stmt->bindValue(':' . $key, $value);
    }
}

// Bind the pagination parameters explicitly as integers
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', (int)$items_per_page, PDO::PARAM_INT);

// Execute the query
$stmt->execute();
$absensi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for today - ADD APPROVAL STATUS FILTER
$today = date('Y-m-d');
$status_counts = [
    'Hadir' => 0,
    'Sakit' => 0,
    'Izin' => 0,
    'Terlambat' => 0,
    'Alpha' => 0
];

// Update the SQL query to filter by approval_status
$sql = "SELECT status, COUNT(*) as count 
        FROM absensi 
        WHERE tanggal = :today 
        AND approval_status = 'Approved'
        GROUP BY status";
$stmt = $conn->prepare($sql);
$stmt->execute(['today' => $today]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    $status_counts[$row['status']] = $row['count'];
}

// Get pending counts
$sql = "SELECT COUNT(*) as count FROM absensi WHERE approval_status = 'Pending'";
$stmt = $conn->prepare($sql);
$stmt->execute();
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Helper functions for sorting
function buildSortUrl($column)
{
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = isset($_GET['sort']) && $_GET['sort'] === $column && $_GET['order'] === 'ASC' ? 'DESC' : 'ASC';
    return '?' . http_build_query($params);
}

function getSortIcon($column, $sort_column, $sort_order)
{
    if ($column !== $sort_column) {
        return '<i class="fas fa-sort text-gray-500 opacity-50"></i>';
    }

    return $sort_order === 'ASC'
        ? '<i class="fas fa-sort-up text-purple-500"></i>'
        : '<i class="fas fa-sort-down text-purple-500"></i>';
}

// Helper function to build pagination URLs
function buildPaginationUrl($page)
{
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Check for notification messages
$notification = null;
if (isset($_GET['delete']) && $_GET['delete'] == 'success') {
    $notification = [
        'type' => 'success',
        'message' => 'Data absensi berhasil dihapus'
    ];
} elseif (isset($_GET['delete']) && $_GET['delete'] == 'error') {
    $notification = [
        'type' => 'error',
        'message' => 'Gagal menghapus data absensi: ' . ($_GET['message'] ?? 'Terjadi kesalahan')
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Absensi - SMKN 40 Jakarta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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

        .status-hadir {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-sakit {
            background: rgba(234, 179, 8, 0.1);
            color: #EAB308;
            border: 1px solid rgba(234, 179, 8, 0.3);
        }

        .status-izin {
            background: rgba(139, 92, 246, 0.1);
            color: #8B5CF6;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .status-terlambat {
            background: rgba(249, 115, 22, 0.1);
            color: #F97316;
            border: 1px solid rgba(249, 115, 22, 0.3);
        }

        .status-alpha {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-pending {
            background: rgba(217, 119, 6, 0.1);
            color: #D97706;
            border: 1px solid rgba(217, 119, 6, 0.3);
        }

        .status-approved {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

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

        /* Responsive table styles */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Responsive pagination */
        @media (max-width: 640px) {
            .pagination-compact .page-number {
                display: none;
            }

            .pagination-compact .current-page {
                display: inline-flex;
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
            <div class="max-w-7xl mx-auto">
                <!-- Notification alert (if exists) -->
                <?php if ($notification): ?>
                    <div class="mb-6 animate-fade-in <?= $notification['type'] === 'success' ? 'bg-green-500/10 border-green-500/30 text-green-500' : 'bg-red-500/10 border-red-500/30 text-red-500' ?> rounded-lg p-4 border flex items-center">
                        <i class="fas <?= $notification['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                        <p class="text-sm"><?= htmlspecialchars($notification['message']) ?></p>
                        <button class="ml-auto text-gray-400 hover:text-gray-300" onclick="this.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <header class="flex flex-wrap justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Data Absensi</h1>
                        <p class="text-gray-400 text-sm md:text-base">Kelola data kehadiran siswa</p>
                    </div>
                    <div>
                        <a href="add.php" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg flex items-center gap-2 text-sm font-medium transition-colors">
                            <i class="fas fa-plus"></i> Tambah Absensi
                        </a>
                    </div>
                </header>

                <!-- Stats Cards - Now responsive -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-green-500/10 flex items-center justify-center mr-3">
                            <i class="fas fa-check text-green-500"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Hadir</p>
                            <p class="text-xl font-bold"><?= $status_counts['Hadir'] ?></p>
                        </div>
                    </div>
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-yellow-500/10 flex items-center justify-center mr-3">
                            <i class="fas fa-hospital text-yellow-500"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Sakit</p>
                            <p class="text-xl font-bold"><?= $status_counts['Sakit'] ?></p>
                        </div>
                    </div>
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-purple-500/10 flex items-center justify-center mr-3">
                            <i class="fas fa-envelope text-purple-500"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Izin</p>
                            <p class="text-xl font-bold"><?= $status_counts['Izin'] ?></p>
                        </div>
                    </div>
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-orange-500/10 flex items-center justify-center mr-3">
                            <i class="fas fa-clock text-orange-500"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Terlambat</p>
                            <p class="text-xl font-bold"><?= $status_counts['Terlambat'] ?></p>
                        </div>
                    </div>
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-red-500/10 flex items-center justify-center mr-3">
                            <i class="fas fa-times text-red-500"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Alpha</p>
                            <p class="text-xl font-bold"><?= $status_counts['Alpha'] ?></p>
                        </div>
                    </div>
                </div>

                <!-- Filter & Search - Made responsive -->
                <div class="glass-effect rounded-xl p-4 md:p-6 mb-6">
                    <div class="flex flex-wrap justify-between items-center mb-4">
                        <h3 class="font-medium text-lg mb-2 md:mb-0">Filter & Pencarian</h3>
                        <?php if (!empty($_GET) && isset($_GET['search']) || isset($_GET['date']) || isset($_GET['status']) || isset($_GET['kelas']) || isset($_GET['jurusan']) || isset($_GET['approval'])): ?>
                            <a href="index.php" class="text-sm flex items-center gap-1 text-purple-400 hover:text-purple-300">
                                <i class="fas fa-times-circle"></i> Reset Filter
                            </a>
                        <?php endif; ?>
                    </div>

                    <form method="GET" id="filterForm">
                        <!-- Preserve sort parameters -->
                        <input type="hidden" name="sort" value="<?= $sort_column ?>">
                        <input type="hidden" name="order" value="<?= $sort_order ?>">

                        <!-- Responsive grid for filters -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                            <div>
                                <label class="text-xs text-gray-400 block mb-1">Tanggal</label>
                                <div class="relative">
                                    <input type="date" name="date" value="<?= $date_filter ?>"
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-purple-500">
                                    <?php if ($date_filter): ?>
                                        <button type="button" onclick="clearField('date')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs text-gray-400 block mb-1">Status</label>
                                <div class="relative">
                                    <select name="status" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-purple-500">
                                        <option value="">Semua Status</option>
                                        <option value="Hadir" <?= $status_filter === 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                                        <option value="Sakit" <?= $status_filter === 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                                        <option value="Izin" <?= $status_filter === 'Izin' ? 'selected' : '' ?>>Izin</option>
                                        <option value="Terlambat" <?= $status_filter === 'Terlambat' ? 'selected' : '' ?>>Terlambat</option>
                                        <option value="Alpha" <?= $status_filter === 'Alpha' ? 'selected' : '' ?>>Alpha</option>
                                    </select>
                                    <?php if ($status_filter): ?>
                                        <button type="button" onclick="clearField('status')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs text-gray-400 block mb-1">Kelas</label>
                                <div class="relative">
                                    <select name="kelas" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-purple-500">
                                        <option value="">Semua Kelas</option>
                                        <option value="10" <?= $kelas_filter === '10' ? 'selected' : '' ?>>10</option>
                                        <option value="11" <?= $kelas_filter === '11' ? 'selected' : '' ?>>11</option>
                                        <option value="12" <?= $kelas_filter === '12' ? 'selected' : '' ?>>12</option>
                                    </select>
                                    <?php if ($kelas_filter): ?>
                                        <button type="button" onclick="clearField('kelas')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs text-gray-400 block mb-1">Jurusan</label>
                                <div class="relative">
                                    <select name="jurusan" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-purple-500">
                                        <option value="">Semua Jurusan</option>
                                        <option value="RPL" <?= $jurusan_filter === 'RPL' ? 'selected' : '' ?>>RPL</option>
                                        <option value="DKV" <?= $jurusan_filter === 'DKV' ? 'selected' : '' ?>>DKV</option>
                                        <option value="AK" <?= $jurusan_filter === 'AK' ? 'selected' : '' ?>>AK</option>
                                        <option value="BR" <?= $jurusan_filter === 'BR' ? 'selected' : '' ?>>BR</option>
                                        <option value="MP" <?= $jurusan_filter === 'MP' ? 'selected' : '' ?>>MP</option>
                                    </select>
                                    <?php if ($jurusan_filter): ?>
                                        <button type="button" onclick="clearField('jurusan')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs text-gray-400 block mb-1">Status Approval</label>
                                <div class="relative">
                                    <select name="approval" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-purple-500">
                                        <option value="">Semua Status</option>
                                        <option value="Pending" <?= $approval_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Approved" <?= $approval_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                                        <option value="Rejected" <?= $approval_filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                    </select>
                                    <?php if ($approval_filter): ?>
                                        <button type="button" onclick="clearField('approval')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs text-gray-400 block mb-1">Pencarian Siswa</label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                        placeholder="Nama atau NIS..."
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg pl-9 pr-9 py-2.5 text-sm text-white focus:outline-none focus:border-purple-500">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
                                    <?php if ($search): ?>
                                        <button type="button" onclick="clearField('search')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors text-sm">
                                <i class="fas fa-filter mr-2"></i>Terapkan Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Data Table - Made responsive with horizontal scroll on small screens -->
                <div class="glass-effect rounded-xl overflow-hidden">
                    <?php if (count($absensi_list) > 0): ?>
                        <div class="overflow-x-auto table-container">
                            <table class="w-full whitespace-nowrap">
                                <thead>
                                    <tr class="bg-gray-800/50 text-gray-300 text-left">
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('nis') ?>" class="flex items-center gap-1 hover:text-white">
                                                NIS
                                                <?php echo getSortIcon('nis', $sort_column, $sort_order); ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('nama_lengkap') ?>" class="flex items-center gap-1 hover:text-white">
                                                Nama
                                                <?php echo getSortIcon('nama_lengkap', $sort_column, $sort_order); ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('kelas') ?>" class="flex items-center gap-1 hover:text-white">
                                                Kelas
                                                <?php echo getSortIcon('kelas', $sort_column, $sort_order); ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('tanggal') ?>" class="flex items-center gap-1 hover:text-white">
                                                Tanggal
                                                <?php echo getSortIcon('tanggal', $sort_column, $sort_order); ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('jam_masuk') ?>" class="flex items-center gap-1 hover:text-white">
                                                Jam
                                                <?php echo getSortIcon('jam_masuk', $sort_column, $sort_order); ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('status') ?>" class="flex items-center gap-1 hover:text-white">
                                                Status
                                                <?php echo getSortIcon('status', $sort_column, $sort_order); ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium">
                                            <a href="<?= buildSortUrl('approval_status') ?>" class="flex items-center gap-1 hover:text-white">
                                                Approval
                                                <?php echo getSortIcon('approval_status', $sort_column, $sort_order); ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    <?php foreach ($absensi_list as $absensi): ?>
                                        <tr class="hover:bg-purple-900/5 transition-colors animate-fade-in">
                                            <td class="px-6 py-4">
                                                <?= htmlspecialchars($absensi['nis']) ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <img src="../../<?= $absensi['foto_profil'] ?: 'assets/default/avatar.png' ?>"
                                                        alt="Profile"
                                                        class="w-8 h-8 rounded-full object-cover mr-3">
                                                    <span><?= htmlspecialchars($absensi['nama_lengkap']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?= $absensi['kelas'] ?> <?= $absensi['jurusan'] ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?= date('d/m/Y', strtotime($absensi['tanggal'])) ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?= $absensi['jam_masuk'] !== '00:00:00' ? date('H:i', strtotime($absensi['jam_masuk'])) : '-' ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-3 py-1 rounded-full text-xs status-<?= strtolower($absensi['status']) ?>">
                                                    <?= $absensi['status'] ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-3 py-1 rounded-full text-xs status-<?= strtolower($absensi['approval_status']) ?>">
                                                    <?= $absensi['approval_status'] ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex justify-center space-x-2">
                                                    <a href="detail.php?id=<?= $absensi['id'] ?>"
                                                        class="text-blue-400 hover:text-blue-300 p-1.5 rounded-full hover:bg-blue-500/10"
                                                        title="Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?= $absensi['id'] ?>"
                                                        class="text-yellow-400 hover:text-yellow-300 p-1.5 rounded-full hover:bg-yellow-500/10"
                                                        title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="confirmDelete(<?= $absensi['id'] ?>)"
                                                        class="text-red-400 hover:text-red-300 p-1.5 rounded-full hover:bg-red-500/10"
                                                        title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Responsive Pagination -->
                        <div class="p-4 border-t border-gray-800 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <p class="text-sm text-gray-400 order-2 sm:order-1">
                                Menampilkan <?= min($offset + 1, $total_items) ?> - <?= min($offset + $items_per_page, $total_items) ?> dari <?= $total_items ?> data
                            </p>
                            <div class="flex space-x-1 order-1 sm:order-2 pagination-compact">
                                <?php if ($page > 1): ?>
                                    <a href="<?= buildPaginationUrl(1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px]">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <a href="<?= buildPaginationUrl($page - 1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px]">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                // Calculate the range of pages to show
                                $range = 2;
                                $start_page = max($page - $range, 1);
                                $end_page = min($page + $range, $total_pages);

                                // Display ellipses if needed
                                if ($start_page > 1) {
                                    echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center justify-center">...</span>';
                                }

                                // Display page numbers
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    $is_current = $i == $page;
                                    $class = $is_current
                                        ? 'px-2 sm:px-3 py-1.5 bg-purple-600 rounded text-white text-sm flex items-center justify-center min-w-[32px] current-page page-number'
                                        : 'px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px] page-number';

                                    echo '<a href="' . buildPaginationUrl($i) . '" class="' . $class . '">' . $i . '</a>';
                                }

                                // Display ellipses if needed
                                if ($end_page < $total_pages) {
                                    echo '<span class="px-2 sm:px-3 py-1.5 text-gray-500 flex items-center justify-center">...</span>';
                                }
                                ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= buildPaginationUrl($page + 1) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px]">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                    <a href="<?= buildPaginationUrl($total_pages) ?>" class="px-2 sm:px-3 py-1.5 bg-gray-800 rounded hover:bg-gray-700 text-sm flex items-center justify-center min-w-[32px]">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-10 text-center">
                            <i class="fas fa-calendar-day text-5xl text-gray-600 mb-4"></i>
                            <p class="text-gray-400">Tidak ada data absensi yang ditemukan</p>
                            <?php if (!empty($_GET)): ?>
                                <a href="index.php" class="mt-4 inline-block text-purple-400 hover:text-purple-500">
                                    <i class="fas fa-arrow-left mr-1"></i> Reset Filter
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile action button - Fixed at bottom -->
                <div class="fixed bottom-4 right-4 lg:hidden">
                    <a href="add.php" class="flex items-center justify-center w-14 h-14 bg-purple-600 hover:bg-purple-700 rounded-full shadow-lg transition-colors">
                        <i class="fas fa-plus text-lg"></i>
                    </a>
                </div>
            </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideDeleteModal()"></div>
        <div class="glass-effect rounded-lg p-6 w-11/12 max-w-md relative z-10">
            <h3 class="text-xl font-semibold mb-4">Konfirmasi Hapus</h3>
            <p class="text-gray-300 mb-6">Apakah Anda yakin ingin menghapus data absensi ini? Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex justify-end gap-4">
                <button onclick="hideDeleteModal()" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm">
                    Batal
                </button>
                <form id="deleteForm" method="POST" action="delete.php">
                    <input type="hidden" id="deleteId" name="id" value="">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm">
                        Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 1) ?>assets/js/diome.js"></script>
    <script>
        // Delete confirmation
        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Auto-submit filter changes
        document.querySelectorAll('select[name], input[type="date"]').forEach(element => {
            element.addEventListener('change', () => {
                document.getElementById('filterForm').submit();
            });
        });

        // Clear individual fields
        function clearField(fieldName) {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.value = '';
                document.getElementById('filterForm').submit();
            }
        }

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

        // Add mobile time updater
        setInterval(updateMobileTime, 60000); // Update every minute
        updateMobileTime(); // Initial call

        // Make sure sidebar closes when pressing escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const sidebar = document.getElementById('sidebar');
                const deleteModal = document.getElementById('deleteModal');

                // Close delete modal if open
                if (!deleteModal.classList.contains('hidden')) {
                    hideDeleteModal();
                    return;
                }

                // Close sidebar on mobile
                if (window.innerWidth < 1024 && !sidebar.classList.contains('-translate-x-full')) {
                    toggleSidebar();
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

        // Better touch experience for filter inputs
        if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
            document.querySelectorAll('select, input').forEach(element => {
                element.classList.add('mobile-touch-target');
            });
        }
    </script>
</body>

</html>