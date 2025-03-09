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

        // Get student details for activity log
        $sql = "SELECT nama_lengkap, nis FROM siswa WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        $siswa = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($siswa) {
            // Delete all attendance records first (the ON DELETE CASCADE should handle this automatically,
            // but we'll do it explicitly to ensure proper logging)
            $sql = "DELETE FROM absensi WHERE siswa_id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['id' => $id]);

            // Delete the student
            $sql = "DELETE FROM siswa WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['id' => $id]);

            // Log activity
            $description = "Admin menghapus data siswa: " . $siswa['nama_lengkap'] . " (" . $siswa['nis'] . ")";

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
            throw new Exception("Data siswa tidak ditemukan");
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
