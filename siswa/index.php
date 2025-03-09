<?php
require_once '../config/database.php';

// If student is logged in, redirect to dashboard
if (isset($_SESSION['siswa_id'])) {
    header("Location: dashboard/");
    exit();
}

// Otherwise redirect to login page
header("Location: login.php");
exit();
