<?php
$servername = "sql110.infinityfree.com";
$username   = "if0_43170484";
$password   = "Joeanmark89";
$dbname     = "if0_43170484_ufeedback";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---------------------------------------------------------------------------
// FORCE PHILIPPINE TIMEZONE (UTC+8) ON THE MYSQL CONNECTION
// This ensures NOW(), CURRENT_TIMESTAMP, and DATE_ADD() all use Manila time
// so they match PHP's date_default_timezone_set('Asia/Manila').
// ---------------------------------------------------------------------------
$conn->query("SET time_zone = '+08:00'");

// ---------------------------------------------------------------------------
// FORCE UTF-8 ENCODING
// Prevents special characters (₱, é, ñ, etc.) from breaking or rendering as ????
// ---------------------------------------------------------------------------
$conn->set_charset("utf8mb4");

// Optional: Turn on strict error reporting (recommended for development)
// Uncomment the line below if you want PHP to throw exceptions on SQL errors.
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?>