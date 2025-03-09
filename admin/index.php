<?php
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

header("Location: dashboard/");
exit();
