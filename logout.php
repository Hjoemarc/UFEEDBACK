<?php
session_start();
require_once 'db_connection.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $device  = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';

    $stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, ip_address, device_info) VALUES (?, 'LOGOUT', ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sss", $user_id, $ip, $device);
        $stmt->execute();
        $stmt->close();
    }
}

session_destroy();
header("Location: login.php");
exit();
?>