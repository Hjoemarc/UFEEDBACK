<?php
// admin_suspicious.php — Suspicious Activity Monitor

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Manila');

// Auth guard — admin only
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
// 1. SELF-HEALING TABLE
// ---------------------------------------------------------------------------
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS suspicious_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            reason TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ip (ip_address),
            KEY idx_user (username),
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

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) $from_date = $default_from;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date))   $to_date   = $default_to;
if ($from_date > $to_date) { $from_date = $default_from; $to_date = $default_to; }

$date_where = " AND created_at >= '{$from_date} 00:00:00' AND created_at <= '{$to_date} 23:59:59' ";

// ---------------------------------------------------------------------------
// 3. POST ACTIONS (Clear Old Logs)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_old_logs') {
    try {
        $del = $conn->prepare("DELETE FROM suspicious_activity WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        if ($del && $del->execute()) {
            $deleted_count = $del->affected_rows;
            $toast_message = "Successfully cleared {$deleted_count} alerts older than 30 days.";
            $toast_type = 'success';
        } else {
            $toast_message = "Failed to clear alerts: " . $conn->error;
            $toast_type = 'error';
        }
        if ($del) $del->close();
    } catch (Throwable $e) {
        $toast_message = "SQL Exception: " . $e->getMessage();
        $toast_type = 'error';
    }
}

// ---------------------------------------------------------------------------
// 4. CSV EXPORT (Respects date filters)
// ---------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ZPPSU_SuspiciousActivity_' . date('Y-m-d_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Timestamp', 'Username Attempted', 'IP Address', 'Reason / Event']);

    try {
        $exp_res = $conn->query("SELECT * FROM suspicious_activity WHERE 1=1 $date_where ORDER BY created_at DESC");
        if ($exp_res) {
            while ($row = $exp_res->fetch_assoc()) {
                fputcsv($output, [
                    $row['id'], $row['created_at'], $row['username'],
                    $row['ip_address'], $row['reason']
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
// 5. FETCH DATA & KPI STATS
// ---------------------------------------------------------------------------
$logs = [];
$kpi_total = 0;
$kpi_unique_ips = 0;
$kpi_top_target = 'N/A';

try {
    // Fetch Logs
    $logs_res = $conn->query("SELECT * FROM suspicious_activity WHERE 1=1 $date_where ORDER BY created_at DESC LIMIT 1000");
    if ($logs_res instanceof mysqli_result) {
        while ($r = $logs_res->fetch_assoc()) $logs[] = $r;
    }
    $kpi_total = count($logs);

    // KPI: Unique IPs
    $ip_res = $conn->query("SELECT COUNT(DISTINCT ip_address) AS c FROM suspicious_activity WHERE 1=1 $date_where");
    if ($ip_res) $kpi_unique_ips = (int)$ip_res->fetch_assoc()['c'];

    // KPI: Top Target Account
    $target_res = $conn->query("
        SELECT username, COUNT(*) AS c 
        FROM suspicious_activity 
        WHERE 1=1 $date_where 
        GROUP BY username 
        ORDER BY c DESC LIMIT 1
    ");
    if ($target_res && $tr = $target_res->fetch_assoc()) {
        $kpi_top_target = $tr['username'] . " (" . $tr['c'] . " hits)";
    }
} catch (Throwable $e) {
    die("<div style='padding:20px; background:#fee2e2; color:#991b1b; font-family:sans-serif;'><strong>Data Fetch Error:</strong> " . $e->getMessage() . "</div>");
}

// Page metadata
$page_title    = 'Suspicious Activity Monitor';
$page_crumb    = 'System & Security / Suspicious Activity';
$page_subtitle = 'Track failed logins, brute-force attempts, and unauthorized access patterns';
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

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 12px; }
        .kpi-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px;
            display: flex; align-items: center; gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .kpi-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .kpi-icon.red { background: #FEF2F2; color: var(--danger); }
        .kpi-icon.orange { background: #FFF7ED; color: var(--warning); }
        .kpi-icon.blue { background: #EFF6FF; color: var(--info); }
        .kpi-value { font-size: 20px; font-weight: 800; color: var(--text-heading); line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .kpi-label { font-size: 10.5px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.4px; margin-top: 4px; }

        /* Filter Panel */
        .filter-panel {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 10px 14px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
        }
        .filter-controls { display: flex; align-items: center; gap: 10px; flex: 1; flex-wrap: wrap; }
        
        .input-live-search, .date-input {
            padding: 8px 12px; border: 1.5px solid var(--border); border-radius: 6px;
            font-size: 12.5px; background: #F8FAFC; color: var(--text-heading);
            outline: none; font-family: inherit; transition: border-color 0.18s;
        }
        .input-live-search { min-width: 200px; flex: 1; }
        .input-live-search:focus, .date-input:focus {
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
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 800px; }
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

        .reason-badge {
            display: inline-block; font-size: 11px; font-weight: 700;
            padding: 3px 8px; border-radius: 4px;
            background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA;
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
            .kpi-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .toolbar { flex-direction: column; align-items: stretch; padding: 14px 16px; }
            .toolbar-actions { width: 100%; justify-content: stretch; }
            .toolbar-actions .btn-primary, .toolbar-actions .btn-danger-outline, .toolbar-actions form { flex: 1; }
            .toolbar-actions .btn-primary, .toolbar-actions .btn-danger-outline { justify-content: center; }
            
            .kpi-grid { grid-template-columns: 1fr; gap: 8px; }
            .kpi-card { padding: 12px; }
            .kpi-value { font-size: 18px; }
            
            .filter-panel { flex-direction: column; align-items: stretch; }
            .filter-controls { flex-direction: column; align-items: stretch; }
            .input-live-search, .date-input { width: 100%; min-width: 0; }
            
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
                        <h2>Suspicious Activity Monitor</h2>
                        <p>Track failed logins, brute-force attempts, and unauthorized access</p>
                    </div>

                    <div class="toolbar-actions">
                        <form method="POST" action="admin_suspicious.php" style="margin:0;" onsubmit="return confirm('Clear alerts older than 30 days? This cannot be undone.');">
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

                <!-- KPI CARDS -->
                <section class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon red">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3l-6.93-12a2 2 0 00-3.48 0L2.33 16a2 2 0 001.74 3z"/></svg>
                        </div>
                        <div>
                            <div class="kpi-value"><?php echo number_format($kpi_total); ?></div>
                            <div class="kpi-label">Total Alerts</div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon orange">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                        </div>
                        <div>
                            <div class="kpi-value"><?php echo number_format($kpi_unique_ips); ?></div>
                            <div class="kpi-label">Unique IP Addresses</div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon blue">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div>
                            <div class="kpi-value" style="font-size: 16px; margin-top: 2px;"><?php echo htmlspecialchars($kpi_top_target); ?></div>
                            <div class="kpi-label">Top Target Account</div>
                        </div>
                    </div>
                </section>

                <section class="filter-panel">
                    <form method="GET" action="admin_suspicious.php" class="filter-controls" id="filterForm">
                        <input type="text" id="liveSearchInput" class="input-live-search" placeholder="Search IP or username..." autocomplete="off">
                        
                        <div class="date-group">
                            <span>From</span>
                            <input type="date" name="from_date" class="date-input" value="<?php echo htmlspecialchars($from_date); ?>" max="<?php echo $default_to; ?>">
                            <span>To</span>
                            <input type="date" name="to_date" class="date-input" value="<?php echo htmlspecialchars($to_date); ?>" max="<?php echo $default_to; ?>">
                            <button type="submit" class="btn-primary" style="padding: 8px 12px; font-size: 12px;">Apply</button>
                        </div>
                    </form>
                    <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 600;" id="searchCountBadge">
                        Showing <?php echo count($logs); ?> alert(s)
                    </div>
                </section>
            </div>

            <section class="panel-card">
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 150px;">Timestamp</th>
                                <th style="width: 200px;">Username Attempted</th>
                                <th style="width: 150px;">IP Address</th>
                                <th>Reason / Trigger</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="log-row" 
                                        data-search="<?php echo strtolower(htmlspecialchars($log['username'] . ' ' . $log['ip_address'])); ?>">
                                        
                                        <td style="font-size: 11.5px; color: var(--text-muted); white-space: nowrap;">
                                            <?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?>
                                        </td>
                                        
                                        <td>
                                            <strong style="color: var(--text-heading); font-family: monospace; font-size: 13px;">
                                                <?php echo htmlspecialchars($log['username']); ?>
                                            </strong>
                                        </td>
                                        
                                        <td>
                                            <span style="font-family: monospace; font-size: 12px; font-weight: 600; background:#F1F5F9; padding:2px 6px; border-radius:4px;">
                                                <?php echo htmlspecialchars($log['ip_address']); ?>
                                            </span>
                                        </td>
                                        
                                        <td>
                                            <span class="reason-badge">
                                                <?php echo htmlspecialchars($log['reason']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="noDataRow"><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 30px;">No suspicious activity detected in this date range.</td></tr>
                            <?php endif; ?>
                            <tr id="noResultsRow" style="display: none;"><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 30px;">No alerts match your search settings.</td></tr>
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

        /* Live Search */
        const liveSearchInput = document.getElementById('liveSearchInput');
        const rows = document.querySelectorAll('#logsTableBody tr.log-row');
        const badge = document.getElementById('searchCountBadge');
        const noResults = document.getElementById('noResultsRow');

        function runLiveFilter() {
            const term = liveSearchInput.value.toLowerCase().trim();
            let matches = 0;

            rows.forEach(row => {
                const rSearch = (row.getAttribute('data-search') || '');
                if (term === '' || rSearch.includes(term)) {
                    row.style.display = ''; matches++;
                } else { 
                    row.style.display = 'none'; 
                }
            });

            if (noResults) noResults.style.display = (matches === 0 && rows.length > 0) ? '' : 'none';
            if (badge) badge.textContent = `Showing ${matches} alert(s)`;
        }

        if (liveSearchInput) liveSearchInput.addEventListener('input', runLiveFilter);
    </script>
</body>
</html>