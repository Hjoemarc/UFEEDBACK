<?php
session_start();
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Auth check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}
if (($_SESSION['internal_user_type'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

require_once 'db_connection.php';
mysqli_report(MYSQLI_REPORT_OFF);

$admin_id = $_SESSION['user_id'] ?? '';
if ($admin_id === '') {
    echo json_encode(['ok' => false, 'error' => 'No session user']);
    exit;
}

$action = $_GET['action'] ?? '';

// ============================================================
// MARK ONE READ
// ============================================================
if ($action === 'mark_read') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok' => false]); exit; }

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1
                            WHERE id = ? AND recipient_type = 'Admin' AND recipient_id = ?");
    $stmt->bind_param("is", $id, $admin_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true]);
    exit;
}

// ============================================================
// MARK ALL READ
// ============================================================
if ($action === 'mark_all_read') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1
                            WHERE recipient_type = 'Admin' AND recipient_id = ? AND is_read = 0");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true]);
    exit;
}

// ============================================================
// FETCH (default) — unread count + latest 8 notifications
// ============================================================
$unread_count = 0;
$count_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications
                              WHERE recipient_type = 'Admin' AND recipient_id = ? AND is_read = 0");
$count_stmt->bind_param("s", $admin_id);
$count_stmt->execute();
$row = $count_stmt->get_result()->fetch_assoc();
$unread_count = (int)($row['c'] ?? 0);
$count_stmt->close();

$items = [];
$list_stmt = $conn->prepare("SELECT id, title, message, is_read, created_at
                             FROM notifications
                             WHERE recipient_type = 'Admin' AND recipient_id = ?
                             ORDER BY created_at DESC
                             LIMIT 8");
$list_stmt->bind_param("s", $admin_id);
$list_stmt->execute();
$res = $list_stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $items[] = [
        'id'         => (int)$r['id'],
        'title'      => $r['title'],
        'message'    => $r['message'],
        'is_read'    => (int)$r['is_read'],
        'created_at' => $r['created_at'],
    ];
}
$list_stmt->close();

echo json_encode([
    'ok'            => true,
    'unread_count'  => $unread_count,
    'notifications' => $items,
]);