<?php
// admin_security_logs.php — Security & Access Logs

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Manila');

// Auth guard
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php"); exit();
}
if (($_SESSION['internal_user_type'] ?? '') !== 'admin') {
    header("Location: student_dashboard.php"); exit();
}

require_once 'db_connection.php';
mysqli_report(MYSQLI_REPORT_OFF);

$toast_message = '';
$toast_type = '';

// ---------------------------------------------------------------------------
// 1. SELF-HEALING: Ensure table exists
// ---------------------------------------------------------------------------
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS security_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(50) NULL,
            event_type VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NULL,
            device_info TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user (user_id),
            KEY idx_event (event_type),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB
    ");
} catch (Throwable $e) {
    die("<div style='padding:20px; background:#fee2e2; color:#991b1b; font-family:sans-serif;'><strong>Database Setup Error:</strong> " . $e->getMessage() . "</div>");
}

// ---------------------------------------------------------------------------
// 2. DATE FILTERS (Default: 1 month ago to today)
// ---------------------------------------------------------------------------
$default_from = date('Y-m-d', strtotime('-1 month'));
$default_to   = date('Y-m-d');

$from_date = $_GET['from_date'] ?? $default_from;
$to_date   = $_GET['to_date'] ?? $default_to;

// Validate dates to prevent SQL injection or errors
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) $from_date = $default_from;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date))   $to_date   = $default_to;
if ($from_date > $to_date) { $from_date = $default_from; $to_date = $default_to; }

$date_where = " AND s.created_at >= '{$from_date} 00:00:00' AND s.created_at <= '{$to_date} 23:59:59' ";

// ---------------------------------------------------------------------------
// 3. HANDLE POST ACTIONS (Clear Logs)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_old_logs') {
    try {
        $del = $conn->prepare("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        if ($del && $del->execute()) {
            $deleted_count = $del->affected_rows;
            $toast_message = "Successfully cleared {$deleted_count} logs older than 30 days.";
            $toast_type = 'success';
        } else {
            $toast_message = "Failed to clear logs: " . $conn->error;
            $toast_type = 'error';
        }
        if ($del) $del->close();
    } catch (Throwable $e) {
        $toast_message = "SQL Exception: " . $e->getMessage();
        $toast_type = 'error';
    }
}

// ---------------------------------------------------------------------------
// 4. HANDLE CSV EXPORT (Respects current date filter)
// ---------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ZPPSU_CSM_SecurityLogs_' . date('Y-m-d_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Log ID', 'Timestamp', 'Event Type', 'User ID', 'Account Name', 'Role', 'IP Address', 'Device/Browser Info']);

    try {
        $export_query = "
            SELECT 
                s.log_id, s.created_at, s.event_type, s.user_id, s.ip_address, s.device_info,
                COALESCE(u.full_name, 'Unknown / Unauthenticated') AS full_name,
                COALESCE(u.internal_user_type, 'N/A') AS role
            FROM security_logs s
            LEFT JOIN users u ON BINARY s.user_id = BINARY u.user_id
            WHERE 1=1 $date_where
            ORDER BY s.created_at DESC
        ";
        $exp_res = $conn->query($export_query);
        if ($exp_res) {
            while ($row = $exp_res->fetch_assoc()) {
                fputcsv($output, [
                    $row['log_id'], $row['created_at'], $row['event_type'],
                    $row['user_id'] ?: 'GUEST', $row['full_name'], $row['role'],
                    $row['ip_address'], $row['device_info']
                ]);
            }
        }
    } catch (Throwable $e) {
        fputcsv($output, ['Export Error', $e->getMessage()]);
    }
    fclose($output);
    exit();
}

// ---------------------------------------------------------------------------
// 5. FETCH LOGS & EVENT TYPES
// ---------------------------------------------------------------------------
$logs = [];
$event_types = [];

try {
    $logs_query = "
        SELECT 
            s.log_id, s.user_id, s.event_type, s.ip_address, s.device_info, s.created_at,
            u.full_name, u.internal_user_type
        FROM security_logs s
        LEFT JOIN users u ON BINARY s.user_id = BINARY u.user_id
        WHERE 1=1 $date_where
        ORDER BY s.created_at DESC
        LIMIT 1000
    ";
    
    $logs_res = $conn->query($logs_query);
    if ($logs_res && $logs_res instanceof mysqli_result) {
        while ($r = $logs_res->fetch_assoc()) $logs[] = $r;
    }

    // Get distinct event types for the filter dropdown (unfiltered by date to show all possible types)
    $event_res = $conn->query("SELECT DISTINCT event_type FROM security_logs ORDER BY event_type ASC");
    if ($event_res) {
        while ($er = $event_res->fetch_assoc()) $event_types[] = $er['event_type'];
    }
} catch (Throwable $e) {
    die("<div style='padding:20px; background:#fee2e2; color:#991b1b; font-family:sans-serif;'><strong>Data Fetch Error:</strong> " . $e->getMessage() . "</div>");
}

// Page metadata
$page_title    = 'Security & Access Logs';
$page_crumb    = 'System & Security / Access Logs';
$page_subtitle = 'Immutable audit trail of authentications, failed attempts, and critical events';
$unread_notifications = 0;
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
            --maroon: #7A0000; --maroon-dark: #580000; --gold: #F2A900;
            --bg: #F8FAFC; --surface: #FFFFFF;
            --border: #E2E8F0; --border-strong: #CBD5E1;
            --text-heading: #0F172A; --text-body: #475569; --text-muted: #94A3B8;
            --success: #10B981; --danger: #EF4444; --warning: #F59E0B; --info: #3B82F6;
            --sidebar-width: 272px; --header-height: 72px;
        }
        html, body { width: 100%; height: 100%; margin: 0; padding: 0; overflow-x: hidden; }
        * { box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; -webkit-text-size-adjust: 100%; }
        body { background-color: var(--bg); color: var(--text-body); display: flex; min-height: 100vh; }

        /* Full width layout */
        .admin-main-wrap {
            margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width));
            min-width: 0; display: flex; flex-direction: column; min-height: 100vh; overflow-x: hidden;
            transition: margin-left 0.35s cubic-bezier(0.4,0,0.2,1), width 0.35s cubic-bezier(0.4,0,0.2,1);
        }
        .admin-container {
            width: 100%; max-width: 100%;
            padding: calc(var(--header-height) + 20px) 24px 20px;
            flex: 1; display: flex; flex-direction: column;
        }

        /* Sticky Header Deck */
        .fixed-header-deck {
            position: -webkit-sticky; position: sticky;
            top: var(--header-height); z-index: 800;
            background: var(--bg); padding-top: 10px; padding-bottom: 12px;
            box-shadow: 0 8px 15px -10px rgba(0,0,0,0.08);
        }
        .toolbar {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px 20px; margin-bottom: 10px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .toolbar-title h2 { font-size: 19px; font-weight: 800; color: var(--text-heading); margin: 0; }
        .toolbar-title p { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }
        
        .toolbar-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .btn-primary {
            background: var(--maroon); color: #FFFFFF; border: none;
            padding: 9px 15px; border-radius: 6px; font-size: 12.5px; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
            white-space: nowrap; text-decoration: none; font-family: inherit;
        }
        .btn-primary:hover { background: var(--maroon-dark); }
        .btn-danger-outline {
            background: transparent; color: var(--danger); border: 1px solid #FECACA;
            padding: 8px 14px; border-radius: 6px; font-size: 12.5px; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
            white-space: nowrap; font-family: inherit;
        }
        .btn-danger-outline:hover { background: #FEF2F2; }

        /* Filter Panel */
        .filter-panel {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 10px 14px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
        }
        .filter-controls { display: flex; align-items: center; gap: 10px; flex: 1; flex-wrap: wrap; }
        
        .input-live-search, .select-live-filter, .date-input {
            padding: 8px 12px; border: 1.5px solid var(--border); border-radius: 6px;
            font-size: 12.5px; background: #F8FAFC; color: var(--text-heading);
            outline: none; font-family: inherit; transition: border-color 0.18s;
        }
        .input-live-search { min-width: 200px; flex: 1; }
        .input-live-search:focus, .select-live-filter:focus, .date-input:focus {
            border-color: var(--maroon); background: #FFFFFF; box-shadow: 0 0 0 3px rgba(122,0,0,0.06);
        }

        .date-group { display: flex; align-items: center; gap: 6px; }
        .date-group span { font-size: 12px; font-weight: 700; color: var(--text-muted); }
        .date-input { width: 140px; }

        /* Table */
        .panel-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px; flex: 1;
            display: flex; flex-direction: column; min-height: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .table-scroll-wrap {
            flex: 1; min-height: 400px; max-height: calc(100vh - 340px);
            overflow-y: auto; overflow-x: auto;
            border: 1px solid var(--border); border-radius: 8px;
            -webkit-overflow-scrolling: touch;
        }
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 900px; }
        .table-scroll-wrap thead th {
            position: sticky; top: 0; background-color: #F1F5F9; z-index: 10;
            box-shadow: 0 1px 0 var(--border);
        }
        th {
            background-color: #F1F5F9; color: var(--text-muted); font-weight: 700;
            padding: 11px 14px; border-bottom: 1px solid var(--border);
            text-transform: uppercase; font-size: 10.5px; text-align: left; letter-spacing: 0.4px;
            white-space: nowrap;
        }
        td { padding: 12px 14px; border-bottom: 1px solid var(--border); color: var(--text-body); vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background-color: #FAFBFC; }

        .badge-event {
            display: inline-block; font-size: 10.5px; font-weight: 800;
            padding: 3px 8px; border-radius: 4px; border: 1px solid transparent;
            text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap;
        }
        .event-success { background: #ECFDF5; color: #047857; border-color: #A7F3D0; }
        .event-fail    { background: #FEF2F2; color: #B91C1C; border-color: #FECACA; }
        .event-warn    { background: #FFFBEB; color: #B45309; border-color: #FDE68A; }
        .event-info    { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }

        .device-info-text {
            font-size: 11px; color: var(--text-muted);
            line-height: 1.4; max-width: 300px; word-wrap: break-word;
        }

        /* Toast */
        .toast-container {
            position: fixed; top: calc(var(--header-height) + 16px); right: 20px;
            z-index: 9999; display: flex; flex-direction: column; gap: 10px;
            max-width: 420px; pointer-events: none;
        }
        .toast {
            pointer-events: auto; background: #FFFFFF; border-radius: 8px;
            padding: 12px 16px; box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            border-left: 4px solid var(--success); font-size: 13px; font-weight: 600;
            color: var(--text-heading); display: flex; align-items: center;
            justify-content: space-between; gap: 10px; animation: toastSlideIn 0.3s forwards;
        }
        .toast-error { border-left-color: var(--danger); }
        .toast-close { background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 18px; line-height: 1; }
        @keyframes toastSlideIn { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }

        /* Responsive */
        @media (max-width: 1024px) {
            .admin-main-wrap { margin-left: 0; width: 100%; }
            .admin-container { padding: calc(var(--header-height) + 12px) 16px 20px; }
        }
        @media (max-width: 768px) {
            .toolbar { flex-direction: column; align-items: stretch; padding: 14px 16px; }
            .toolbar-actions { width: 100%; justify-content: stretch; }
            .toolbar-actions .btn-primary, .toolbar-actions .btn-danger-outline, .toolbar-actions form { flex: 1; }
            .toolbar-actions .btn-primary, .toolbar-actions .btn-danger-outline { justify-content: center; }
            
            .filter-panel { flex-direction: column; align-items: stretch; }
            .filter-controls { flex-direction: column; align-items: stretch; }
            .input-live-search, .select-live-filter, .date-input { width: 100%; min-width: 0; }
            
            .date-group { width: 100%; justify-content: space-between; }
            .date-input { flex: 1; }
            
            .table-scroll-wrap { max-height: calc(100vh - 400px); }
            #searchCountBadge { display: none !important; }
        }
        @media (max-width: 640px) {
            :root { --header-height: 66px; }
            .admin-container { padding: calc(var(--header-height) + 10px) 12px 16px; }
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

            <div class="fixed-header-deck">
                <section class="toolbar">
                    <div class="toolbar-title">
                        <h2>Security & Access Logs</h2>
                        <p>Immutable audit trail of authentications, failed attempts, and critical events</p>
                    </div>

                    <div class="toolbar-actions">
                        <form method="POST" action="admin_security_logs.php" style="margin:0;" onsubmit="return confirm('Clear logs older than 30 days? This action is permanent.');">
                            <input type="hidden" name="action" value="clear_old_logs">
                            <button type="submit" class="btn-danger-outline">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                Clear >30 Days
                            </button>
                        </form>
                        <a href="?export=csv&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>" class="btn-primary">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Export CSV
                        </a>
                    </div>
                </section>

                <section class="filter-panel">
                    <form method="GET" action="admin_security_logs.php" class="filter-controls" id="filterForm">
                        <input type="text" id="liveSearchInput" class="input-live-search" placeholder="Search IP, User ID, or Name..." autocomplete="off">
                        
                        <select id="liveEventSelect" class="select-live-filter">
                            <option value="">All Event Types</option>
                            <?php foreach ($event_types as $evt): ?>
                                <option value="<?php echo htmlspecialchars($evt); ?>"><?php echo htmlspecialchars($evt); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div class="date-group">
                            <span>From</span>
                            <input type="date" name="from_date" class="date-input" value="<?php echo htmlspecialchars($from_date); ?>" max="<?php echo $default_to; ?>">
                            <span>To</span>
                            <input type="date" name="to_date" class="date-input" value="<?php echo htmlspecialchars($to_date); ?>" max="<?php echo $default_to; ?>">
                            <button type="submit" class="btn-primary" style="padding: 8px 12px; font-size: 12px;">Apply</button>
                        </div>
                    </form>
                    <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 600;" id="searchCountBadge">
                        Showing <?php echo count($logs); ?> log(s)
                    </div>
                </section>
            </div>

            <section class="panel-card">
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 150px;">Timestamp</th>
                                <th style="width: 140px;">Event Action</th>
                                <th style="width: 250px;">User Identifier</th>
                                <th style="width: 140px;">IP Address</th>
                                <th>Device / Trace Details</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                    <?php 
                                        $evt = strtoupper($log['event_type']);
                                        $badge_class = 'event-info';
                                        if (strpos($evt, 'SUCCESS') !== false || strpos($evt, 'LOGIN') !== false && strpos($evt, 'FAIL') === false) {
                                            $badge_class = 'event-success';
                                        } elseif (strpos($evt, 'FAIL') !== false || strpos($evt, 'ERROR') !== false || strpos($evt, 'DENY') !== false) {
                                            $badge_class = 'event-fail';
                                        } elseif (strpos($evt, 'CHANGE') !== false || strpos($evt, 'UPDATE') !== false) {
                                            $badge_class = 'event-warn';
                                        }
                                        
                                        $user_display = $log['user_id'] ? htmlspecialchars($log['user_id']) : 'GUEST / SYSTEM';
                                        $name_display = $log['full_name'] ? htmlspecialchars($log['full_name']) : 'Unauthenticated Actor';
                                    ?>
                                    <tr class="log-row" 
                                        data-search="<?php echo strtolower(htmlspecialchars($log['user_id'] . ' ' . $log['full_name'] . ' ' . $log['ip_address'])); ?>" 
                                        data-event="<?php echo strtolower(htmlspecialchars($log['event_type'])); ?>">
                                        <td style="font-size: 11.5px; color: var(--text-muted); white-space: nowrap;">
                                            <?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?>
                                        </td>
                                        <td><span class="badge-event <?php echo $badge_class; ?>"><?php echo htmlspecialchars($log['event_type']); ?></span></td>
                                        <td>
                                            <strong style="color: var(--text-heading); display: block;"><?php echo $user_display; ?></strong>
                                            <small style="color: var(--text-muted); font-size: 11px;"><?php echo $name_display; ?></small>
                                        </td>
                                        <td><span style="font-family: monospace; font-size: 12px; font-weight: 600;"><?php echo htmlspecialchars($log['ip_address'] ?? 'Unknown IP'); ?></span></td>
                                        <td><div class="device-info-text"><?php echo htmlspecialchars($log['device_info'] ?? 'No trace data recorded.'); ?></div></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="noDataRow"><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">No security events recorded in this date range.</td></tr>
                            <?php endif; ?>
                            <tr id="noResultsRow" style="display: none;"><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">No logs match your search or filter settings.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            const iconSvg = type === 'success' 
                ? `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>`
                : `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`;

            toast.innerHTML = `<div style="display:flex; align-items:center; gap:10px;"><div style="color:${type === 'success' ? 'var(--success)' : 'var(--danger)'}; display:flex;">${iconSvg}</div><span>${message}</span></div><button class="toast-close" onclick="this.parentElement.remove()">&times;</button>`;
            container.appendChild(toast);
            setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4000);
        }

        <?php if (!empty($toast_message)): ?>
            document.addEventListener('DOMContentLoaded', () => { showToast("<?php echo addslashes($toast_message); ?>", "<?php echo $toast_type; ?>"); });
        <?php endif; ?>

        /* ============================================================
           CLIENT-SIDE LIVE FILTER (Search + Event Type)
           ============================================================ */
        const liveSearchInput = document.getElementById('liveSearchInput');
        const liveEventSelect = document.getElementById('liveEventSelect');
        const rows = document.querySelectorAll('#logsTableBody tr.log-row');
        const badge = document.getElementById('searchCountBadge');
        const noResults = document.getElementById('noResultsRow');

        function runLiveFilter() {
            const term = liveSearchInput.value.toLowerCase().trim();
            const evt = liveEventSelect.value.toLowerCase().trim();
            let matches = 0;

            rows.forEach(row => {
                const rSearch = (row.getAttribute('data-search') || '');
                const rEvent = (row.getAttribute('data-event') || '');
                if ((term === '' || rSearch.includes(term)) && (evt === '' || rEvent === evt)) {
                    row.style.display = ''; matches++;
                } else { 
                    row.style.display = 'none'; 
                }
            });

            if (noResults) noResults.style.display = (matches === 0 && rows.length > 0) ? '' : 'none';
            if (badge) badge.textContent = `Showing ${matches} log(s)`;
        }

        if (liveSearchInput) liveSearchInput.addEventListener('input', runLiveFilter);
        if (liveEventSelect) liveEventSelect.addEventListener('change', runLiveFilter);
    </script>
</body>
</html>