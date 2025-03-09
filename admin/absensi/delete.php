<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];

    try {
        // Start transaction
        $conn->beginTransaction();

        // Get student ID and details for activity log
        $sql = "SELECT a.siswa_id, s.nama_lengkap, a.tanggal, a.status 
                FROM absensi a 
                JOIN siswa s ON a.siswa_id = s.id
                WHERE a.id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        $absensi = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($absensi) {
            // Delete the record
            $sql = "DELETE FROM absensi WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['id' => $id]);

            // Log activity
            $description = "Admin menghapus absensi " . $absensi['status'] . " untuk " .
                $absensi['nama_lengkap'] . " pada tanggal " .
                date('d/m/Y', strtotime($absensi['tanggal']));

            $sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                    VALUES ('admin', :admin_id, 'delete', :description)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'admin_id' => $_SESSION['admin_id'],
                'description' => $description
            ]);

            $conn->commit();

            // Redirect with success message
            header("Location: index.php?delete=success");
            exit();
        } else {
            throw new Exception("Data absensi tidak ditemukan");
        }
    } catch (Exception $e) {
        $conn->rollBack();

        // Redirect with error message
        header("Location: index.php?delete=error&message=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Invalid request
    header("Location: index.php");
    exit();
}
