<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Default filter values
$kelas_filter = $_GET['kelas'] ?? '';
$jurusan_filter = $_GET['jurusan'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Sorting parameters
$sort_column = $_GET['sort'] ?? 'nis';
$sort_order = $_GET['order'] ?? 'ASC';

// Validate sort column to prevent SQL injection
$valid_columns = ['nis', 'nama_lengkap', 'kelas', 'jurusan', 'email', 'created_at'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'nis';

// Base query for count
$count_sql = "SELECT COUNT(*) as total FROM siswa WHERE 1=1";
$params = [];

// Base query for data
$sql = "SELECT * FROM siswa WHERE 1=1";

// Apply filters
if ($kelas_filter) {
    $sql .= " AND kelas = :kelas";
    $count_sql .= " AND kelas = :kelas";
    $params['kelas'] = $kelas_filter;
}

if ($jurusan_filter) {
    $sql .= " AND jurusan = :jurusan";
    $count_sql .= " AND jurusan = :jurusan";
    $params['jurusan'] = $jurusan_filter;
}

if ($search) {
    $sql .= " AND (nama_lengkap LIKE :search OR nis LIKE :search OR email LIKE :search)";
    $count_sql .= " AND (nama_lengkap LIKE :search OR nis LIKE :search OR email LIKE :search)";
    $params['search'] = "%$search%";
}

// Add sorting
$sql .= " ORDER BY $sort_column " . ($sort_order === 'ASC' ? 'ASC' : 'DESC');

// Get total count for pagination
$count_stmt = $conn->prepare($count_sql);
foreach ($params as $key => $value) {
    $count_stmt->bindValue(':' . $key, $value);
}
$count_stmt->execute();
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

// Add pagination
$sql .= " LIMIT :offset, :limit";
$params['offset'] = $offset;
$params['limit'] = $items_per_page;

// Execute query
$stmt = $conn->prepare($sql);

// Bind all parameters except pagination ones
foreach ($params as $key => $value) {
    if ($key != 'offset' && $key != 'limit') {
        $stmt->bindValue(':' . $key, $value);
    }
}

// Bind pagination parameters as integers
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', (int)$items_per_page, PDO::PARAM_INT);

// Execute the query
$stmt->execute();
$siswa_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count students by class and major
$sql = "SELECT COUNT(*) as count, kelas, jurusan 
        FROM siswa 
        GROUP BY kelas, jurusan 
        ORDER BY kelas ASC, jurusan ASC";
$stats = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

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
        'message' => 'Data siswa berhasil dihapus'
    ];
} elseif (isset($_GET['delete']) && $_GET['delete'] == 'error') {
    $notification = [
        'type' => 'error',
        'message' => 'Gagal menghapus data siswa: ' . ($_GET['message'] ?? 'Terjadi kesalahan')
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa - SMKN 40 Jakarta</title>
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

                <!-- Header - Made responsive -->
                <header class="flex flex-wrap justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold">Data Siswa</h1>
                        <p class="text-gray-400 text-sm md:text-base">Kelola data siswa SMKN 40 Jakarta</p>
                    </div>
                    <div class="hidden sm:block">
                        <a href="add.php" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg flex items-center gap-2 text-sm font-medium transition-colors">
                            <i class="fas fa-user-plus"></i> Tambah Siswa
                        </a>
                    </div>
                </header>

                <!-- Stats Grid - Made responsive -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
                    <?php
                    $class_colors = [
                        '10' => ['bg' => 'bg-blue-500/10', 'text' => 'text-blue-500', 'border' => 'border-blue-500/30', 'icon' => 'fa-user-graduate'],
                        '11' => ['bg' => 'bg-green-500/10', 'text' => 'text-green-500', 'border' => 'border-green-500/30', 'icon' => 'fa-user-tie'],
                        '12' => ['bg' => 'bg-orange-500/10', 'text' => 'text-orange-500', 'border' => 'border-orange-500/30', 'icon' => 'fa-user-tag']
                    ];

                    $major_icons = [
                        'RPL' => 'fa-laptop-code',
                        'DKV' => 'fa-paint-brush',
                        'AK' => 'fa-calculator',
                        'BR' => 'fa-shopping-bag',
                        'MP' => 'fa-briefcase'
                    ];

                    $total_students = 0;
                    foreach ($stats as $stat) {
                        $total_students += $stat['count'];
                    }
                    ?>

                    <!-- Total Students -->
                    <div class="glass-effect rounded-lg p-4 flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-purple-500/10 flex items-center justify-center mr-3">
                            <i class="fas fa-users text-purple-500"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Total Siswa</p>
                            <p class="text-xl font-bold"><?= $total_students ?></p>
                        </div>
                    </div>

                    <?php foreach ($stats as $stat): ?>
                        <?php
                        $colors = $class_colors[$stat['kelas']] ?? [
                            'bg' => 'bg-gray-500/10',
                            'text' => 'text-gray-500',
                            'border' => 'border-gray-500/30',
                            'icon' => 'fa-user'
                        ];
                        $icon = $major_icons[$stat['jurusan']] ?? 'fa-user';
                        ?>
                        <div class="glass-effect rounded-lg p-4 hover:bg-gray-800/50 transition-colors cursor-pointer"
                            onclick="window.location='index.php?kelas=<?= $stat['kelas'] ?>&jurusan=<?= $stat['jurusan'] ?>'">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs text-gray-400">Kelas <?= $stat['kelas'] ?> <?= $stat['jurusan'] ?></p>
                                    <p class="text-xl font-bold mt-1"><?= $stat['count'] ?></p>
                                </div>
                                <div class="h-10 w-10 rounded-lg <?= $colors['bg'] ?> flex items-center justify-center">
                                    <i class="fas <?= $icon ?> <?= $colors['text'] ?>"></i>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Filter & Search - Made responsive -->
                <div class="glass-effect rounded-xl p-4 md:p-6 mb-6">
                    <div class="flex flex-wrap justify-between items-center mb-4">
                        <h3 class="font-medium text-lg mb-2 md:mb-0">Filter & Pencarian</h3>
                        <?php if (!empty($_GET) && isset($_GET['search']) || isset($_GET['kelas']) || isset($_GET['jurusan'])): ?>
                            <a href="index.php" class="text-sm flex items-center gap-1 text-purple-400 hover:text-purple-300">
                                <i class="fas fa-times-circle"></i> Reset Filter
                            </a>
                        <?php endif; ?>
                    </div>

                    <form method="GET" id="filterForm">
                        <!-- Preserve sort parameters -->
                        <input type="hidden" name="sort" value="<?= $sort_column ?>">
                        <input type="hidden" name="order" value="<?= $sort_order ?>">

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4">
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
                                <label class="text-xs text-gray-400 block mb-1">Pencarian</label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                        placeholder="Nama, NIS, atau Email..."
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

                <!-- Data Table - Made responsive with horizontal scroll -->
                <div class="glass-effect rounded-xl overflow-hidden">
                    <?php if (count($siswa_list) > 0): ?>
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
                                            <a href="<?= buildSortUrl('jurusan') ?>" class="flex items-center gap-1 hover:text-white">
                                                Jurusan
                                                <?php echo getSortIcon('jurusan', $sort_column, $sort_order); ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium hidden md:table-cell">
                                            <a href="<?= buildSortUrl('email') ?>" class="flex items-center gap-1 hover:text-white">
                                                Email
                                                <?php echo getSortIcon('email', $sort_column, $sort_order); ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-xs font-medium text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    <?php foreach ($siswa_list as $siswa): ?>
                                        <tr class="hover:bg-purple-900/5 transition-colors animate-fade-in">
                                            <td class="px-6 py-4">
                                                <?= htmlspecialchars($siswa['nis']) ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <img src="../../<?= $siswa['foto_profil'] ?: 'assets/default/avatar.png' ?>"
                                                        alt="<?= htmlspecialchars($siswa['nama_lengkap']) ?>"
                                                        class="w-8 h-8 rounded-full object-cover mr-3">
                                                    <span><?= htmlspecialchars($siswa['nama_lengkap']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4"><?= $siswa['kelas'] ?></td>
                                            <td class="px-6 py-4"><?= $siswa['jurusan'] ?></td>
                                            <td class="px-6 py-4 max-w-[200px] truncate hidden md:table-cell">
                                                <?= htmlspecialchars($siswa['email']) ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex justify-center space-x-2">
                                                    <a href="detail.php?id=<?= $siswa['id'] ?>"
                                                        class="text-blue-400 hover:text-blue-300 p-1.5 rounded-full hover:bg-blue-500/10"
                                                        title="Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?= $siswa['id'] ?>"
                                                        class="text-yellow-400 hover:text-yellow-300 p-1.5 rounded-full hover:bg-yellow-500/10"
                                                        title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="confirmDelete(<?= $siswa['id'] ?>)"
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
                            <i class="fas fa-users text-5xl text-gray-600 mb-4"></i>
                            <p class="text-gray-400">Tidak ada data siswa yang ditemukan</p>
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
                        <i class="fas fa-user-plus text-lg"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete Confirmation Modal - Made responsive -->
    <div id="deleteModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideDeleteModal()"></div>
        <div class="glass-effect rounded-lg p-6 w-11/12 max-w-md relative z-10">
            <h3 class="text-xl font-semibold mb-4">Konfirmasi Hapus</h3>
            <p class="text-gray-300 mb-6">Apakah Anda yakin ingin menghapus data siswa ini? Semua data absensi terkait juga akan dihapus. Tindakan ini tidak dapat dibatalkan.</p>
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
        document.querySelectorAll('select[name]').forEach(element => {
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

        // Better touch experience for filters and buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Larger touch targets for mobile
            if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
                document.querySelectorAll('select, input, button, a.px-2, a.px-3').forEach(el => {
                    el.classList.add('touch-target');
                });
            }

            // Auto-submit select filters after a small delay on mobile
            if (window.innerWidth < 768) {
                const selectFilters = document.querySelectorAll('select[name="kelas"], select[name="jurusan"]');
                selectFilters.forEach(select => {
                    select.addEventListener('change', function() {
                        // Only auto-submit if the value has changed
                        if (this.dataset.changed) {
                            document.getElementById('filterForm').submit();
                        }
                        this.dataset.changed = "true";
                    });
                });
            }

            // Make mobile table rows tappable to open details on small screens
            if (window.innerWidth < 640) {
                document.querySelectorAll('tbody tr').forEach(row => {
                    row.addEventListener('click', function(e) {
                        // Don't trigger if clicked on a button or link
                        if (e.target.closest('a') || e.target.closest('button')) {
                            return;
                        }

                        // Get student ID from the delete button's onclick handler
                        const deleteButton = this.querySelector('button[onclick^="confirmDelete"]');
                        if (deleteButton) {
                            const idMatch = deleteButton.getAttribute('onclick').match(/confirmDelete\((\d+)\)/);
                            if (idMatch && idMatch[1]) {
                                window.location.href = `detail.php?id=${idMatch[1]}`;
                            }
                        }
                    });

                    // Add visual feedback for tappable rows
                    row.classList.add('cursor-pointer');
                });
            }

            // Add swipe gestures for pagination on mobile
            if (window.innerWidth < 768) {
                let touchStartX = 0;
                let touchEndX = 0;

                const handleSwipe = () => {
                    const swipeThreshold = 100;
                    const prevPageLink = document.querySelector('a[href*="page=' + (parseInt(<?= $page ?>) - 1) + '"]');
                    const nextPageLink = document.querySelector('a[href*="page=' + (parseInt(<?= $page ?>) + 1) + '"]');

                    if (touchEndX < touchStartX - swipeThreshold && nextPageLink) {
                        // Swipe left -> Next page
                        window.location.href = nextPageLink.href;
                    }

                    if (touchEndX > touchStartX + swipeThreshold && prevPageLink) {
                        // Swipe right -> Previous page
                        window.location.href = prevPageLink.href;
                    }
                };

                document.querySelector('.table-container').addEventListener('touchstart', e => {
                    touchStartX = e.changedTouches[0].screenX;
                });

                document.querySelector('.table-container').addEventListener('touchend', e => {
                    touchEndX = e.changedTouches[0].screenX;
                    handleSwipe();
                });
            }
        });
    </script>
</body>

</html>