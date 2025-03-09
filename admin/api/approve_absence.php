<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

// Get request body
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !isset($data['action'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing required parameters']));
}

$id = $data['id'];
$action = $data['action']; // 'approve' or 'reject'

try {
    // Start transaction
    $conn->beginTransaction();

    // Get the attendance record
    $sql = "SELECT a.id, a.siswa_id, a.status, s.nama_lengkap as siswa_name 
            FROM absensi a 
            JOIN siswa s ON a.siswa_id = s.id
            WHERE a.id = :id AND a.approval_status = 'Pending'";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['id' => $id]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attendance) {
        throw new Exception('Invalid attendance request or already processed');
    }

    // Update the approval status
    $approval_status = $action === 'approve' ? 'Approved' : 'Rejected';
    $sql = "UPDATE absensi SET approval_status = :status WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'status' => $approval_status,
        'id' => $id
    ]);

    // Log the activity
    $action_description = $action === 'approve' ? 'menyetujui' : 'menolak';
    $status = $attendance['status']; // Get the attendance status (Hadir, Sakit, etc.)
    $sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
            VALUES ('admin', :user_id, 'approval', :description)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'user_id' => $_SESSION['admin_id'],
        'description' => "Admin {$_SESSION['admin_name']} {$action_description} pengajuan {$status} dari siswa {$attendance['siswa_name']}"
    ]);

    // Get remaining pending approval requests
    $sql = "SELECT COUNT(*) as remaining FROM absensi WHERE approval_status = 'Pending'";
    $remaining = $conn->query($sql)->fetch(PDO::FETCH_ASSOC)['remaining'];

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => ucfirst($action === 'approve' ? 'Approved' : 'Rejected') . ' successfully',
        'remaining' => (int) $remaining
    ]);
} catch (Exception $e) {
    // Roll back transaction on error
    $conn->rollBack();

    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
