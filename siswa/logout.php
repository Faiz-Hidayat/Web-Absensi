<?php
require_once '../config/database.php';

if (isset($_SESSION['siswa_id'])) {
    // Log the logout activity
    $log_sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
               VALUES ('siswa', :user_id, 'logout', :description)";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->execute([
        'user_id' => $_SESSION['siswa_id'],
        'description' => "Siswa {$_SESSION['siswa_name']} logout dari sistem"
    ]);
}

// Destroy the session
session_start();
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
