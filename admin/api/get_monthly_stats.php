<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

// Create an array for the full year (past 12 months)
$months = [];
for ($i = 0; $i < 12; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M', strtotime("-$i months"));
    $months[$month] = [
        'month' => $month_name,
        'year' => date('Y', strtotime("-$i months")),
        'percentage' => 0,
        'hadir' => 0,
        'sakit' => 0,
        'izin' => 0,
        'terlambat' => 0,
        'alpha' => 0,
        'total' => 0
    ];
}

// Get monthly attendance data - ADD APPROVAL STATUS FILTER
$sql = "SELECT 
            DATE_FORMAT(tanggal, '%Y-%m') as month,
            status,
            COUNT(*) as count
        FROM absensi
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        AND approval_status = 'Approved' 
        GROUP BY DATE_FORMAT(tanggal, '%Y-%m'), status
        ORDER BY month";

$stmt = $conn->query($sql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process the data
foreach ($results as $row) {
    $month = $row['month'];
    $status = strtolower($row['status']);
    $count = (int)$row['count'];

    if (isset($months[$month])) {
        $months[$month][$status] = $count;
        $months[$month]['total'] += $count;
    }
}

// Calculate attendance percentages
foreach ($months as &$month) {
    if ($month['total'] > 0) {
        $present = $month['hadir'] + $month['terlambat']; // Consider both present and late as attendance
        $month['percentage'] = round(($present / $month['total']) * 100);
    }
}

// Reverse array to get chronological order (oldest to newest)
$months = array_reverse($months);

// Convert to indexed array for JSON response
$response = array_values($months);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
