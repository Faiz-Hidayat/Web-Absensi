<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

// Initialize response data
$response = [
    'stats' => [],
    'weeklyStats' => [],
    'notifications' => [],
];

// Get today's statistics with approval filter
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Status categories
$statuses = ['hadir', 'sakit', 'izin', 'terlambat', 'alpha'];

// Initialize stats arrays
$today_stats = array_fill_keys($statuses, 0);
$yesterday_stats = array_fill_keys($statuses, 0);

// Get today's counts
$sql = "SELECT status, COUNT(*) as count FROM absensi 
        WHERE tanggal = :today 
        AND approval_status = 'Approved'
        GROUP BY status";
$stmt = $conn->prepare($sql);
$stmt->execute(['today' => $today]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status = strtolower($row['status']);
    if (isset($today_stats[$status])) {
        $today_stats[$status] = (int)$row['count'];
    }
}

// Get yesterday's counts
$sql = "SELECT status, COUNT(*) as count FROM absensi 
        WHERE tanggal = :yesterday 
        AND approval_status = 'Approved'
        GROUP BY status";
$stmt = $conn->prepare($sql);
$stmt->execute(['yesterday' => $yesterday]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status = strtolower($row['status']);
    if (isset($yesterday_stats[$status])) {
        $yesterday_stats[$status] = (int)$row['count'];
    }
}

// Calculate percentage changes
foreach ($statuses as $status) {
    $percentage_change = 0;

    if ($yesterday_stats[$status] > 0) {
        $percentage_change = round((($today_stats[$status] - $yesterday_stats[$status]) / $yesterday_stats[$status]) * 100);
    } else if ($today_stats[$status] > 0) {
        $percentage_change = 100; // If yesterday was 0 but today has data
    }

    $response['stats'][$status] = [
        'count' => $today_stats[$status],
        'yesterday' => $yesterday_stats[$status],
        'percentage_change' => $percentage_change
    ];
}

// Get weekly statistics
$sql = "SELECT 
            DATE(tanggal) as date,
            DATE_FORMAT(tanggal, '%d %b') as date_label,
            status,
            COUNT(*) as count
        FROM absensi
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND approval_status = 'Approved'
        GROUP BY DATE(tanggal), status
        ORDER BY date ASC";
$stmt = $conn->query($sql);
$response['weeklyStats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending notifications
$sql = "SELECT a.id, s.nama_lengkap, s.foto_profil, a.status, a.created_at, a.bukti_foto, a.keterangan
        FROM absensi a
        JOIN siswa s ON a.siswa_id = s.id
        WHERE a.approval_status = 'Pending'
        ORDER BY a.created_at DESC
        LIMIT 5";
$stmt = $conn->query($sql);
$response['notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return data as JSON
header('Content-Type: application/json');
echo json_encode($response);
