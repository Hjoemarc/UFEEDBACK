<?php
// admin_notifications.php — Notification Center

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<div style='background:#fee2e2;color:#991b1b;padding:24px;margin:20px;font-family:sans-serif;border-radius:8px;border:1px solid #f87171;'>";
        echo "<h3 style='margin-top:0;'>PHP Fatal Error Caught</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($error['file']) . " on line <strong>" . $error['line'] . "</strong></p>";
        echo "</div>";
    }
});

session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php"); exit();
}
if (($_SESSION['internal_user_type'] ?? '') !== 'admin') {
    header("Location: student_dashboard.php"); exit();
}

require_once 'db_connection.php';
mysqli_report(MYSQLI_REPORT_OFF);

$admin_id = $_SESSION['user_id'] ?? '';
$toast_message = '';
$toast_type    = '';

// ---------------------------------------------------------------------------
// POST ACTIONS
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_id = ?");
            $stmt->bind_param("is", $id, $admin_id);
            $stmt->execute();
            $stmt->close();
            $toast_message = "Notification marked as read.";
            $toast_type = 'success';
        }
    } 
    
    elseif ($action === 'mark_unread') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 0 WHERE id = ? AND recipient_id = ?");
            $stmt->bind_param("is", $id, $admin_id);
            $stmt->execute();
            $stmt->close();
            $toast_message = "Notification marked as unread.";
            $toast_type = 'success';
        }
    } 
    
    elseif ($action === 'delete_notif') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND recipient_id = ?");
            $stmt->bind_param("is", $id, $admin_id);
            $stmt->execute();
            $stmt->close();
            $toast_message = "Notification deleted.";
            $toast_type = 'success';
        }
    } 
    
    elseif ($action === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND is_read = 0");
        $stmt->bind_param("s", $admin_id);
        $stmt->execute();
        $stmt->close();
        $toast_message = "All notifications marked as read.";
        $toast_type = 'success';
    } 
    
    elseif ($action === 'clear_read') {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE recipient_id = ? AND is_read = 1");
        $stmt->bind_param("s", $admin_id);
        $stmt->execute();
        $stmt->close();
        $toast_message = "Cleared all read notifications.";
        $toast_type = 'success';
    }
}

// ---------------------------------------------------------------------------
// FILTERS & STATS
// ---------------------------------------------------------------------------
$filter = $_GET['filter'] ?? 'all';

$stats = ['total' => 0, 'unread' => 0];
$r = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN is_read=0 THEN 1 ELSE 0 END) AS unread FROM notifications WHERE recipient_id = '" . $conn->real_escape_string($admin_id) . "'");
if ($r) {
    $row = $r->fetch_assoc();
    $stats['total'] = (int)$row['total'];
    $stats['unread'] = (int)$row['unread'];
}

// ---------------------------------------------------------------------------
// FETCH NOTIFICATIONS
// ---------------------------------------------------------------------------
$where = "recipient_id = '" . $conn->real_escape_string($admin_id) . "'";
if ($filter === 'unread') $where .= " AND is_read = 0";
elseif ($filter === 'read') $where .= " AND is_read = 1";

$notifications = [];
$n_res = $conn->query("
    SELECT id, title, message, is_read, created_at 
    FROM notifications 
    WHERE $where 
    ORDER BY created_at DESC
    LIMIT 100
");
if ($n_res instanceof mysqli_result) {
    while ($row = $n_res->fetch_assoc()) $notifications[] = $row;
}

// Page metadata
$page_title    = 'Notification Center';
$page_crumb    = 'System & Security / Notifications';
$page_subtitle = 'Manage system alerts, feedback updates, and administrative messages';
$unread_notifications = $stats['unread']; // For sidebar badge sync
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> | ZPPSU CSM</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --maroon: #7A0000; --maroon-dark: #580000;
            --bg: #F8FAFC; --surface: #FFFFFF;
            --border: #E2E8F0; --border-strong: #CBD5E1;
            --text-heading: #0F172A; --text-body: #475569; --text-muted: #94A3B8;
            --success: #10B981; --danger: #EF4444; --info: #2563EB;
            --sidebar-width: 272px; --header-height: 72px;
        }
        html, body { width: 100%; height: 100%; margin: 0; padding: 0; overflow-x: hidden; }
        * { box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; -webkit-text-size-adjust: 100%; }
        body { background-color: var(--bg); color: var(--text-body); display: flex; min-height: 100vh; }

        .admin-main-wrap {
            margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width));
            min-width: 0; display: flex; flex-direction: column; min-height: 100vh; overflow-x: hidden;
            transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1), width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .admin-container {
            width: 100%; max-width: 100%;
            padding: calc(var(--header-height) + 20px) 24px 20px;
            flex: 1; display: flex; flex-direction: column;
        }
        .fixed-header-deck {
            position: sticky; top: var(--header-height); z-index: 800;
            background: var(--bg); padding-top: 4px; padding-bottom: 12px;
        }

        /* KPI */
        .kpi-grid {
            display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px; margin-bottom: 12px;
        }
        .kpi-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; padding: 16px 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; gap: 8px; min-width: 0;
        }
        .kpi-card::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
            background: linear-gradient(180deg, var(--maroon), var(--maroon-dark));
        }
        .kpi-card.total::before    { background: linear-gradient(180deg,#2563EB,#1D4ED8); }
        .kpi-card.unread::before   { background: linear-gradient(180deg,#EF4444,#B91C1C); }

        .kpi-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .kpi-label { font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.7px; }
        .kpi-icon { width: 28px; height: 28px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .kpi-icon svg { width: 15px; height: 15px; }
        .kpi-icon.blue { background: #EFF6FF; color: #1D4ED8; }
        .kpi-icon.red { background: #FEF2F2; color: #B91C1C; }

        .kpi-value { font-size: 26px; font-weight: 800; color: var(--text-heading); line-height: 1; letter-spacing: -0.7px; display: flex; align-items: baseline; gap: 4px; }
        .kpi-sub { font-size: 11.5px; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 5px; }
        .kpi-sub .dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0; }

        /* TOOLBAR */
        .toolbar {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px 20px; margin-bottom: 10px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .toolbar-title h2 { font-size: 19px; font-weight: 800; color: var(--text-heading); margin: 0; }
        .toolbar-title p { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }
        .toolbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

        .btn-secondary {
            background: #FFFFFF; color: var(--text-heading); border: 1.5px solid var(--border);
            padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 700;
            cursor: pointer; transition: background 0.18s; font-family: inherit;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-secondary:hover { background: #F1F5F9; border-color: var(--border-strong); }
        .btn-danger { color: var(--danger); border-color: #FECACA; background: #FEF2F2; }
        .btn-danger:hover { background: #FEE2E2; border-color: #FCA5A5; }

        /* FILTER PANEL */
        .filter-panel {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 10px 14px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
        }
        .filter-controls { display: flex; align-items: center; gap: 10px; flex: 1; flex-wrap: wrap; }
        .input-live-search, .select-live-filter {
            padding: 9px 12px; border: 1.5px solid var(--border); border-radius: 8px;
            font-size: 12.5px; background: #F8FAFC; color: var(--text-heading);
            outline: none; transition: border-color 0.18s, background 0.18s; font-family: inherit;
        }
        .input-live-search { min-width: 250px; flex: 1; }
        .input-live-search:focus, .select-live-filter:focus { border-color: var(--maroon); background: #FFFFFF; }

        /* TABLE */
        .panel-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px; flex: 1;
            display: flex; flex-direction: column; min-height: 0;
        }
        .table-scroll-wrap {
            flex: 1; min-height: 400px; max-height: calc(100vh - 340px);
            overflow-y: auto; overflow-x: auto;
            border: 1px solid var(--border); border-radius: 8px;
            -webkit-overflow-scrolling: touch;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 800px; }
        .table-scroll-wrap thead th {
            position: sticky; top: 0; background-color: #F1F5F9; z-index: 10;
            box-shadow: 0 1px 0 var(--border);
        }
        th {
            background-color: #F1F5F9; color: var(--text-muted); font-weight: 700;
            padding: 11px 14px; border-bottom: 1px solid var(--border);
            text-transform: uppercase; font-size: 10.5px; text-align: left; letter-spacing: 0.4px;
        }
        td { padding: 12px 14px; border-bottom: 1px solid var(--border); color: var(--text-body); vertical-align: middle; }
        tbody tr:hover td { background-color: #FAFAFA; }
        tbody tr.unread-row td { background-color: #FEFDFB; }
        tbody tr.unread-row:hover td { background-color: #FFFDF9; }

        /* Badges & Pills */
        .badge-type {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11px; font-weight: 700;
            padding: 4px 10px; border-radius: 6px;
        }
        .badge-unread { background: #FEF2F2; color: var(--maroon); border: 1px solid #FECACA; }
        .badge-read   { background: #F1F5F9; color: var(--text-muted); border: 1px solid #E2E8F0; }
        
        .notif-title { font-weight: 700; color: var(--text-heading); display: block; font-size: 13.5px; margin-bottom: 4px; }
        .notif-message { font-size: 12.5px; color: var(--text-body); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        /* Actions */
        .action-btns { display: flex; align-items: center; gap: 6px; justify-content: flex-end; }
        .btn-table-action {
            padding: 6px 12px; border-radius: 6px; font-size: 11.5px;
            font-weight: 600; cursor: pointer; border: 1px solid transparent;
            transition: filter 0.18s; font-family: inherit; display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-table-action:hover { filter: brightness(0.95); }
        .btn-edit { background: #EFF6FF; color: #2563EB; border-color: #BFDBFE; }
        .btn-delete { background: #FEF2F2; color: var(--danger); border-color: #FECACA; }
        .btn-mark { background: #F8FAFC; color: var(--text-heading); border-color: var(--border); }
        .btn-mark:hover { background: #F1F5F9; }

        /* TOAST */
        .toast-container { position: fixed; top: calc(var(--header-height) + 16px); right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; max-width: 420px; pointer-events: none; }
        .toast { pointer-events: auto; background: #FFFFFF; border-radius: 8px; padding: 12px 16px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); border-left: 4px solid var(--success); font-size: 13px; font-weight: 600; color: var(--text-heading); display: flex; align-items: center; justify-content: space-between; gap: 10px; animation: toastSlideIn 0.3s forwards; }
        .toast-error { border-left-color: var(--danger); }
        .toast-close { background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 18px; line-height: 1; }
        @keyframes toastSlideIn { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }

        /* Empty State */
        .empty-state { text-align: center; color: var(--text-muted); padding: 60px 20px; }
        .empty-state svg { width: 44px; height: 44px; color: #CBD5E1; margin-bottom: 10px; }
        .empty-state h4 { color: var(--text-heading); font-size: 15px; margin: 0 0 4px; }
        .empty-state p { font-size: 12.5px; margin: 0; }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .admin-main-wrap { margin-left: 0; width: 100%; }
            .kpi-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
        }
        @media (max-width: 768px) {
            .admin-container { padding: calc(var(--header-height) + 12px) 14px 18px; }
            .toolbar { flex-direction: column; align-items: stretch; padding: 14px 16px; }
            .toolbar-actions { width: 100%; }
            .toolbar-actions .btn-secondary { flex: 1; justify-content: center; }
            .filter-controls { width: 100%; }
            .input-live-search, .select-live-filter { width: 100%; min-width: 0; }
            .table-scroll-wrap { max-height: calc(100vh - 380px); }
        }
        @media (max-width: 640px) {
            .kpi-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .kpi-card { padding: 12px 14px; border-radius: 12px; }
            .kpi-value { font-size: 20px; }
            .kpi-label { font-size: 10px; }
        }
        @media (max-width: 480px) {
            :root { --header-height: 66px; }
            .admin-container { padding: calc(var(--header-height) + 10px) 10px 16px; }
            .toolbar-title h2 { font-size: 17px; }
            .toolbar-title p { font-size: 11.5px; }
            .toast-container { top: calc(var(--header-height) + 10px); right: 10px; left: 10px; max-width: none; }
        }
    </style>
</head>
<body>

    <div class="toast-container" id="toastContainer"></div>

    <?php
    $sidebar_path = __DIR__ . '/admin_sidebar.php';
    if (file_exists($sidebar_path)) require_once $sidebar_path;
    $header_path = __DIR__ . '/admin_header.php';
    if (file_exists($header_path)) require_once $header_path;
    ?>

    <div class="admin-main-wrap">
        <main class="admin-container">

            <!-- ============= KPI ============= -->
            <section class="kpi-grid">
                <div class="kpi-card total">
                    <div class="kpi-head">
                        <span class="kpi-label">Total Messages</span>
                        <span class="kpi-icon blue">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                    </div>
                    <div class="kpi-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="kpi-sub">
                        <span class="dot" style="background:var(--info);"></span>
                        All time notifications
                    </div>
                </div>

                <div class="kpi-card unread">
                    <div class="kpi-head">
                        <span class="kpi-label">Unread Alerts</span>
                        <span class="kpi-icon red">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </span>
                    </div>
                    <div class="kpi-value"><?php echo number_format($stats['unread']); ?></div>
                    <div class="kpi-sub">
                        <span class="dot" style="background:var(--danger);"></span>
                        Requires your attention
                    </div>
                </div>
            </section>

            <div class="fixed-header-deck">
                <section class="toolbar">
                    <div class="toolbar-title">
                        <h2>Notification Center</h2>
                        <p>Manage system alerts, feedback updates, and administrative messages</p>
                    </div>
                    <div class="toolbar-actions">
                        <?php if ($stats['unread'] > 0): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="btn-secondary">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    Mark All Read
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if ($stats['total'] > 0): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete all read notifications?');">
                                <input type="hidden" name="action" value="clear_read">
                                <button type="submit" class="btn-secondary btn-danger">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                    Clear Read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="filter-panel">
                    <div class="filter-controls">
                        <select id="liveFilterSelect" class="select-live-filter" onchange="window.location.href='?filter='+this.value">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Notifications</option>
                            <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Unread Only</option>
                            <option value="read" <?php echo $filter === 'read' ? 'selected' : ''; ?>>Read Only</option>
                        </select>
                        <input type="text" id="liveSearchInput" class="input-live-search" placeholder="Search notifications..." autocomplete="off">
                    </div>
                    <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 600;" id="searchCountBadge">
                        Showing <?php echo count($notifications); ?> notification(s)
                    </div>
                </section>
            </div>

            <!-- ============= TABLE ============= -->
            <section class="panel-card">
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 100px;">Status</th>
                                <th>Notification Details</th>
                                <th style="width: 160px;">Received</th>
                                <th style="width: 180px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="notifTableBody">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $n): ?>
                                    <tr class="notif-row <?php echo $n['is_read'] ? '' : 'unread-row'; ?>" 
                                        data-search="<?php echo strtolower(htmlspecialchars($n['title'] . ' ' . $n['message'])); ?>">
                                        
                                        <td>
                                            <?php if ($n['is_read']): ?>
                                                <span class="badge-type badge-read">Read</span>
                                            <?php else: ?>
                                                <span class="badge-type badge-unread">Unread</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="notif-title"><?php echo htmlspecialchars($n['title']); ?></span>
                                            <div class="notif-message"><?php echo nl2br(htmlspecialchars($n['message'])); ?></div>
                                        </td>

                                        <td>
                                            <span style="font-size:12px; font-weight:600; color:var(--text-body);">
                                                <?php echo date('M j, Y', strtotime($n['created_at'])); ?>
                                            </span>
                                            <small style="display:block; color:var(--text-muted); font-size:11px;">
                                                <?php echo date('g:i A', strtotime($n['created_at'])); ?>
                                            </small>
                                        </td>

                                        <td style="text-align: right;">
                                            <div class="action-btns">
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="<?php echo $n['is_read'] ? 'mark_unread' : 'mark_read'; ?>">
                                                    <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                                                    <button type="submit" class="btn-table-action btn-mark" title="<?php echo $n['is_read'] ? 'Mark as Unread' : 'Mark as Read'; ?>">
                                                        <?php if ($n['is_read']): ?>
                                                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76"/></svg>
                                                        <?php else: ?>
                                                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                        <?php endif; ?>
                                                        <?php echo $n['is_read'] ? 'Unread' : 'Read'; ?>
                                                    </button>
                                                </form>

                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this notification?');">
                                                    <input type="hidden" name="action" value="delete_notif">
                                                    <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                                                    <button type="submit" class="btn-table-action btn-delete">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                            </svg>
                                            <h4>You're all caught up</h4>
                                            <p>No notifications to display at this time.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr id="noResultsRow" style="display: none;">
                                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    No notifications match your search.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

    <script>
        /* ============================================================
           TOAST
           ============================================================ */
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            const iconSvg = type === 'success'
                ? `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>`
                : `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`;
            toast.innerHTML = `
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="color:${type === 'success' ? 'var(--success)' : 'var(--danger)'}; display:flex;">${iconSvg}</div>
                    <span>${message}</span>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            `;
            container.appendChild(toast);
            setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4000);
        }

        <?php if (!empty($toast_message)): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showToast(<?php echo json_encode($toast_message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($toast_type); ?>);
            });
        <?php endif; ?>

        /* ============================================================
           LIVE TABLE FILTER
           ============================================================ */
        const liveSearchInput = document.getElementById('liveSearchInput');
        const rows = document.querySelectorAll('#notifTableBody tr.notif-row');
        const badge = document.getElementById('searchCountBadge');
        const noResults = document.getElementById('noResultsRow');

        function runLiveFilter() {
            const term = liveSearchInput.value.toLowerCase().trim();
            let matches = 0;

            rows.forEach(row => {
                const rSearch = row.getAttribute('data-search') || '';
                if (term === '' || rSearch.includes(term)) {
                    row.style.display = '';
                    matches++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noResults) noResults.style.display = (matches === 0 && rows.length > 0) ? '' : 'none';
            if (badge) badge.textContent = `Showing ${matches} notification(s)`;
        }

        if (liveSearchInput) liveSearchInput.addEventListener('input', runLiveFilter);
    </script>
</body>
</html>