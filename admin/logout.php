<?php
require_once '../config/database.php';

// Log activity before destroying session
if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    $admin_name = $_SESSION['admin_name'];

    // Log the logout activity
    $sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
            VALUES ('admin', :user_id, 'logout', :description)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'user_id' => $admin_id,
        'description' => "Admin {$admin_name} logout dari sistem"
    ]);
}

// Destroy all session data
session_start();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
