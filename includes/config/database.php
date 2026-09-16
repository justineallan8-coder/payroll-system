<?php
// =============================================
// DATABASE CONNECTION
// =============================================

$host = "localhost";
$username = "root";
$password = "";
$database = "payroll_db";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set timezone
date_default_timezone_set('Africa/Dar_es_Salaam');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>