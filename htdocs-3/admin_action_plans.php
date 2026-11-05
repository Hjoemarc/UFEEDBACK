<?php
// admin_action_plans.php — Action Plan Tracker (operational management)

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

$toast_message = '';
$toast_type    = '';
$admin_id      = $_SESSION['user_id'] ?? '';

// Aging thresholds (days since creation)
const AGE_FRESH_MAX   = 3;   // 0-3d  → green
const AGE_AGING_MAX   = 7;   // 4-7d  → yellow
const AGE_OVERDUE_MAX = 14;  // 8-14d → orange
                              // 15+d  → red
function aging_bucket(int $d): string {
    if ($d <= AGE_FRESH_MAX)   return 'fresh';
    if ($d <= AGE_AGING_MAX)   return 'aging';
    if ($d <= AGE_OVERDUE_MAX) return 'overdue';
    return 'critical';
}
function aging_label(string $b): string {
    return match ($b) {
        'fresh'    => 'Fresh',
        'aging'    => 'Aging',
        'overdue'  => 'Overdue',
        'critical' => 'Critical',
        default    => 'Unknown',
    };
}

// ---------------------------------------------------------------------------
// POST ACTIONS
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -------- QUICK STATUS CHANGE (inline) --------
    if ($action === 'quick_status') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($id <= 0 || !in_array($status, ['Pending','In Progress','Resolved'], true)) {
            $toast_message = "Invalid status change.";
            $toast_type = 'error';
        } else {
            if ($status === 'Resolved') {
                $stmt = $conn->prepare("
                    UPDATE action_plans
                    SET status = ?, resolved_at = COALESCE(resolved_at, NOW())
                    WHERE id = ?
                ");
                $stmt->bind_param("si", $status, $id);
            } else {
                $stmt = $conn->prepare("
                    UPDATE action_plans
                    SET status = ?, resolved_at = NULL
                    WHERE id = ?
                ");
                $stmt->bind_param("si", $status, $id);
            }

            if ($stmt->execute()) {
                $toast_message = "Ticket #" . str_pad((string)$id, 4, '0', STR_PAD_LEFT) . " status changed to \"{$status}\".";
                $toast_type = 'success';
            } else {
                $toast_message = "Update failed: " . $conn->error;
                $toast_type = 'error';
            }
            $stmt->close();
        }
    }

    // -------- FULL UPDATE (from modal) --------
    elseif ($action === 'update_plan') {
        $id                  = (int)($_POST['id'] ?? 0);
        $assigned_staff_id   = trim($_POST['assigned_staff_id'] ?? '');
        $root_cause_analysis = trim($_POST['root_cause_analysis'] ?? '');
        $action_taken        = trim($_POST['action_taken'] ?? '');
        $status              = $_POST['status'] ?? 'Pending';

        if (!in_array($status, ['Pending','In Progress','Resolved'], true)) $status = 'Pending';
        if ($assigned_staff_id === '') $assigned_staff_id = null;

        if ($id <= 0 || $root_cause_analysis === '') {
            $toast_message = "Root-cause analysis is required.";
            $toast_type = 'error';
        } else {
            if ($status === 'Resolved') {
                $stmt = $conn->prepare("
                    UPDATE action_plans
                    SET assigned_staff_id = ?, root_cause_analysis = ?, action_taken = ?,
                        status = ?, resolved_at = COALESCE(resolved_at, NOW())
                    WHERE id = ?
                ");
                $stmt->bind_param("ssssi", $assigned_staff_id, $root_cause_analysis, $action_taken, $status, $id);
            } else {
                $stmt = $conn->prepare("
                    UPDATE action_plans
                    SET assigned_staff_id = ?, root_cause_analysis = ?, action_taken = ?,
                        status = ?, resolved_at = NULL
                    WHERE id = ?
                ");
                $stmt->bind_param("ssssi", $assigned_staff_id, $root_cause_analysis, $action_taken, $status, $id);
            }

            if ($stmt->execute()) {
                $toast_message = "Ticket #" . str_pad((string)$id, 4, '0', STR_PAD_LEFT) . " updated.";
                $toast_type = 'success';
            } else {
                $toast_message = "Update failed: " . $conn->error;
                $toast_type = 'error';
            }
            $stmt->close();
        }
    }

    // -------- DELETE --------
    elseif ($action === 'delete_plan') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $del = $conn->prepare("DELETE FROM action_plans WHERE id = ?");
            $del->bind_param("i", $id);
            if ($del->execute()) {
                $toast_message = "Ticket #" . str_pad((string)$id, 4, '0', STR_PAD_LEFT) . " deleted.";
                $toast_type = 'success';
            } else {
                $toast_message = "Delete failed: " . $conn->error;
                $toast_type = 'error';
            }
            $del->close();
        }
    }
}

// ---------------------------------------------------------------------------
// KPI STATS
// ---------------------------------------------------------------------------
$stats = [
    'open'             => 0,
    'overdue'          => 0,
    'avg_resolve_days' => null,
    'resolved_30d'     => 0,
];

$r = $conn->query("SELECT COUNT(*) AS c FROM action_plans WHERE status != 'Resolved'");
if ($r) $stats['open'] = (int)$r->fetch_assoc()['c'];

$r = $conn->query("
    SELECT COUNT(*) AS c FROM action_plans
    WHERE status != 'Resolved'
      AND DATEDIFF(NOW(), created_at) > " . AGE_AGING_MAX . "
");
if ($r) $stats['overdue'] = (int)$r->fetch_assoc()['c'];

$r = $conn->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
    FROM action_plans
    WHERE status = 'Resolved' AND resolved_at IS NOT NULL
");
if ($r) {
    $row = $r->fetch_assoc();
    if ($row['avg_hours'] !== null) {
        $stats['avg_resolve_days'] = round((float)$row['avg_hours'] / 24, 1);
    }
}

$r = $conn->query("
    SELECT COUNT(*) AS c FROM action_plans
    WHERE status = 'Resolved'
      AND resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
if ($r) $stats['resolved_30d'] = (int)$r->fetch_assoc()['c'];

// ---------------------------------------------------------------------------
// AGING BUCKETS (for chart)
// ---------------------------------------------------------------------------
$aging_buckets = ['fresh' => 0, 'aging' => 0, 'overdue' => 0, 'critical' => 0];
$ab_res = $conn->query("
    SELECT DATEDIFF(NOW(), created_at) AS age_days
    FROM action_plans
    WHERE status != 'Resolved'
");
if ($ab_res instanceof mysqli_result) {
    while ($row = $ab_res->fetch_assoc()) {
        $aging_buckets[aging_bucket((int)$row['age_days'])]++;
    }
}

// ---------------------------------------------------------------------------
// FILTERS (GET)
// ---------------------------------------------------------------------------
$status_f   = $_GET['status']    ?? '';
$assignee_f = $_GET['assignee']  ?? '';
$office_f   = (int)($_GET['office_id'] ?? 0);
$aging_f    = $_GET['aging']     ?? '';

$where = ["1=1"];
if (in_array($status_f, ['Pending','In Progress','Resolved'], true)) {
    $where[] = "ap.status = '" . $conn->real_escape_string($status_f) . "'";
}
if ($assignee_f === '__unassigned__') {
    $where[] = "(ap.assigned_staff_id IS NULL OR ap.assigned_staff_id = '')";
} elseif ($assignee_f !== '') {
    $where[] = "ap.assigned_staff_id = '" . $conn->real_escape_string($assignee_f) . "'";
}
if ($office_f > 0) {
    $where[] = "ap.office_id = " . (int)$office_f;
}
if (in_array($aging_f, ['fresh','aging','overdue','critical'], true)) {
    $where[] = match ($aging_f) {
        'fresh'    => "ap.status != 'Resolved' AND DATEDIFF(NOW(), ap.created_at) <= " . AGE_FRESH_MAX,
        'aging'    => "ap.status != 'Resolved' AND DATEDIFF(NOW(), ap.created_at) BETWEEN " . (AGE_FRESH_MAX+1) . " AND " . AGE_AGING_MAX,
        'overdue'  => "ap.status != 'Resolved' AND DATEDIFF(NOW(), ap.created_at) BETWEEN " . (AGE_AGING_MAX+1) . " AND " . AGE_OVERDUE_MAX,
        'critical' => "ap.status != 'Resolved' AND DATEDIFF(NOW(), ap.created_at) > " . AGE_OVERDUE_MAX,
    };
}
$WHERE_SQL = implode(' AND ', $where);

// ---------------------------------------------------------------------------
// MAIN TABLE
// ---------------------------------------------------------------------------
$plans = [];
$plan_res = $conn->query("
    SELECT
        ap.id, ap.feedback_id, ap.office_id, ap.assigned_staff_id,
        ap.root_cause_analysis, ap.action_taken, ap.status,
        ap.created_at, ap.resolved_at,
        f.service_accessed, f.client_type, f.submission_timestamp,
        o.office_name, o.office_type,
        u.full_name AS staff_name,
        u.internal_user_type AS staff_role,
        DATEDIFF(NOW(), ap.created_at) AS age_days,
        CASE WHEN ap.status = 'Resolved' AND ap.resolved_at IS NOT NULL
             THEN TIMESTAMPDIFF(HOUR, ap.created_at, ap.resolved_at) END AS hours_to_resolve
    FROM action_plans ap
    LEFT JOIN arta_feedback f ON ap.feedback_id = f.feedback_id
    LEFT JOIN offices o       ON ap.office_id   = o.id
    LEFT JOIN users u         ON ap.assigned_staff_id = u.user_id
    WHERE $WHERE_SQL
    ORDER BY
        CASE
            WHEN ap.status = 'Resolved' THEN 3
            WHEN ap.status = 'In Progress' THEN 2
            ELSE 1
        END,
        CASE WHEN ap.status != 'Resolved' THEN 0 ELSE 1 END,
        DATEDIFF(NOW(), ap.created_at) DESC,
        ap.created_at DESC
    LIMIT 400
");
if ($plan_res instanceof mysqli_result) {
    while ($r = $plan_res->fetch_assoc()) $plans[] = $r;
}

// ---------------------------------------------------------------------------
// ASSIGNEE WORKLOAD
// ---------------------------------------------------------------------------
$workload = [];
$wl_res = $conn->query("
    SELECT
        ap.assigned_staff_id AS uid,
        COALESCE(u.full_name, 'Unassigned') AS name,
        SUM(CASE WHEN ap.status = 'Pending'     THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN ap.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN ap.status = 'Resolved'    THEN 1 ELSE 0 END) AS resolved,
        SUM(CASE WHEN ap.status != 'Resolved'   THEN 1 ELSE 0 END) AS open_total,
        MAX(CASE WHEN ap.status != 'Resolved' THEN DATEDIFF(NOW(), ap.created_at) ELSE 0 END) AS oldest_open_days
    FROM action_plans ap
    LEFT JOIN users u ON ap.assigned_staff_id = u.user_id
    GROUP BY ap.assigned_staff_id, u.full_name
    HAVING open_total > 0
    ORDER BY open_total DESC, oldest_open_days DESC
    LIMIT 8
");
if ($wl_res instanceof mysqli_result) {
    while ($r = $wl_res->fetch_assoc()) $workload[] = $r;
}

// ---------------------------------------------------------------------------
// SUPPORTING LISTS
// ---------------------------------------------------------------------------
$staff_list = [];
$s_res = $conn->query("
    SELECT user_id, full_name, internal_user_type
    FROM users
    WHERE internal_user_type IN ('zppsu_employee','admin')
    ORDER BY full_name ASC
");
if ($s_res) while ($r = $s_res->fetch_assoc()) $staff_list[] = $r;

$offices_list = [];
$o_res = $conn->query("SELECT id, office_name FROM offices ORDER BY office_name ASC");
if ($o_res) while ($r = $o_res->fetch_assoc()) $offices_list[] = $r;

// Payload for chart
$chart_payload = [
    'aging' => $aging_buckets,
];

// Page metadata
$page_title    = 'Action Plan Tracker';
$page_crumb    = 'ARTA Compliance / Action Plans';
$page_subtitle = 'Drive corrective-action tickets to resolution and monitor aging';
$unread_notifications = 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> | ZPPSU CSM</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --maroon: #7A0000; --maroon-dark: #580000; --gold: #F2A900;
            --bg: #F8FAFC; --surface: #FFFFFF;
            --border: #E2E8F0; --border-strong: #CBD5E1;
            --text-heading: #0F172A; --text-body: #475569; --text-muted: #94A3B8;
            --success: #10B981; --danger: #EF4444; --warning: #F59E0B; --info: #2563EB;
            --sidebar-width: 272px; --header-height: 72px;
        }
        html, body { width:100%; height:100%; margin:0; padding:0; overflow-x:hidden; }
        * { box-sizing:border-box; font-family:'Plus Jakarta Sans',sans-serif; -webkit-text-size-adjust:100%; }
        body { background-color:var(--bg); color:var(--text-body); display:flex; min-height:100vh; }

        .admin-main-wrap {
            margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width));
            min-width:0; display:flex; flex-direction:column; min-height:100vh; overflow-x:hidden;
            transition: margin-left 0.35s cubic-bezier(0.4,0,0.2,1), width 0.35s cubic-bezier(0.4,0,0.2,1);
        }
        .admin-container {
            width:100%; max-width:100%;
            padding: calc(var(--header-height) + 20px) 24px 20px;
            flex:1; display:flex; flex-direction:column;
        }
        .fixed-header-deck {
            position:sticky; top: var(--header-height); z-index:800;
            background:var(--bg); padding-top:4px; padding-bottom:12px;
        }

        /* ===== KPI ===== */
        .kpi-grid {
            display:grid; grid-template-columns:repeat(4, minmax(0,1fr));
            gap:12px; margin-bottom:12px;
        }
        .kpi-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:14px; padding:16px 18px;
            box-shadow:0 1px 3px rgba(0,0,0,0.02);
            position:relative; overflow:hidden;
            display:flex; flex-direction:column; gap:8px; min-width:0;
        }
        .kpi-card::before {
            content:''; position:absolute; left:0; top:0; bottom:0; width:4px;
            background:linear-gradient(180deg, var(--maroon), var(--maroon-dark));
        }
        .kpi-card.open::before     { background:linear-gradient(180deg,#2563EB,#1D4ED8); }
        .kpi-card.overdue::before  { background:linear-gradient(180deg,#EF4444,#B91C1C); }
        .kpi-card.avg::before      { background:linear-gradient(180deg,#10B981,#047857); }
        .kpi-card.resolved::before { background:linear-gradient(180deg,#8B5CF6,#6D28D9); }

        .kpi-head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
        .kpi-label {
            font-size:11px; font-weight:800; color:var(--text-muted);
            text-transform:uppercase; letter-spacing:0.7px;
        }
        .kpi-icon {
            width:28px; height:28px; border-radius:8px;
            display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
        }
        .kpi-icon svg { width:15px; height:15px; }
        .kpi-icon.blue    { background:#EFF6FF; color:#1D4ED8; }
        .kpi-icon.red     { background:#FEF2F2; color:#B91C1C; }
        .kpi-icon.green   { background:#ECFDF5; color:#047857; }
        .kpi-icon.purple  { background:#F5F3FF; color:#6D28D9; }

        .kpi-value {
            font-size:26px; font-weight:800; color:var(--text-heading);
            line-height:1; letter-spacing:-0.7px;
            display:flex; align-items:baseline; gap:4px;
        }
        .kpi-unit { font-size:14px; font-weight:700; color:var(--text-muted); }
        .kpi-sub {
            font-size:11.5px; color:var(--text-muted); font-weight:600;
            display:flex; align-items:center; gap:5px;
        }
        .kpi-sub .dot {
            display:inline-block; width:6px; height:6px; border-radius:50%;
            background:currentColor; flex-shrink:0;
        }

        /* ===== TOOLBAR ===== */
        .toolbar {
            background:var(--surface); border:1px solid var(--border);
            border-radius:12px; padding:16px 20px; margin-bottom:10px;
            display:flex; align-items:center; justify-content:space-between;
            flex-wrap:wrap; gap:12px;
            box-shadow:0 1px 3px rgba(0,0,0,0.02);
        }
        .toolbar-title h2 { font-size:19px; font-weight:800; color:var(--text-heading); margin:0; }
        .toolbar-title p { font-size:12px; color:var(--text-muted); margin:2px 0 0; }

        .filter-controls { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .select-live-filter, .input-live-search {
            padding:9px 12px; border:1.5px solid var(--border); border-radius:8px;
            font-size:12.5px; background:#F8FAFC; color:var(--text-heading);
            outline:none; font-family:inherit;
            transition:border-color 0.18s, background 0.18s;
        }
        .select-live-filter:focus, .input-live-search:focus { border-color:var(--maroon); background:#FFFFFF; }
        .input-live-search { min-width:220px; }

        .btn-reset {
            background:#FFFFFF; color:var(--text-heading);
            border:1.5px solid var(--border); padding:9px 14px;
            border-radius:8px; font-size:12.5px; font-weight:700;
            cursor:pointer; text-decoration:none; font-family:inherit;
            transition: background 0.18s, border-color 0.18s;
        }
        .btn-reset:hover { background:#F1F5F9; border-color:var(--border-strong); }

        /* ===== LAYOUT: table + workload sidebar ===== */
        .split-layout {
            display:grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap:12px;
            flex:1;
            min-height:0;
            min-width: 0; /* FIX: Allows grid children to shrink */
        }
        @media (max-width: 1200px) {
            .split-layout { grid-template-columns:1fr; }
        }

        /* ===== PANEL / TABLE ===== */
        .panel-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:12px; padding:16px;
            display:flex; flex-direction:column; min-height:0;
            min-width: 0; /* FIX: Prevents child table from stretching parent */
            box-shadow:0 1px 3px rgba(0,0,0,0.02);
        }
        .panel-head {
            display:flex; align-items:flex-start; justify-content:space-between;
            gap:12px; margin-bottom:12px;
        }
        .panel-head h3 { font-size:14px; font-weight:800; color:var(--text-heading); margin:0; }
        .panel-head p { font-size:11.5px; color:var(--text-muted); margin:3px 0 0; }

        .table-scroll-wrap {
            flex:1;
            min-height:400px;
            max-height: calc(100vh - 380px);
            overflow-y:auto;
            overflow-x:auto; /* Enables horizontal scroll */
            border:1px solid var(--border);
            border-radius:8px;
            -webkit-overflow-scrolling: touch;
            width: 100%; /* FIX: Ensures wrapper respects container width */
        }
        table { width:100%; border-collapse:collapse; font-size:12.5px; min-width:920px; }
        .table-scroll-wrap thead th {
            position:sticky; top:0; background-color:#F1F5F9; z-index:10;
            box-shadow:0 1px 0 var(--border);
        }
        th {
            background-color:#F1F5F9; color:var(--text-muted); font-weight:700;
            padding:10px 12px; border-bottom:1px solid var(--border);
            text-transform:uppercase; font-size:10.5px; text-align:left; letter-spacing:0.4px;
            white-space:nowrap;
        }
        td {
            padding:11px 12px; border-bottom:1px solid var(--border);
            color:var(--text-body); vertical-align:middle;
        }
        tbody tr:hover td { background-color:#FAFBFC; }
        tbody tr.overdue-row td { background-color:#FFF9F9; }
        tbody tr.overdue-row:hover td { background-color:#FFF3F3; }

        .strong { font-weight:700; color:var(--text-heading); }
        .muted  { color:var(--text-muted); font-size:11px; display:block; margin-top:2px; }

        /* Ticket ID */
        .ticket-id {
            display:inline-flex; align-items:center; gap:5px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size:11.5px; font-weight:800;
            background:#F1F5F9; color:var(--text-heading);
            padding:3px 8px; border-radius:6px; border:1px solid var(--border);
            letter-spacing:0.3px;
        }

        /* Aging bar */
        .aging-cell {
            display:flex; flex-direction:column; gap:5px;
            min-width:110px;
        }
        .aging-days {
            font-size:11.5px; font-weight:700; color:var(--text-heading);
            display:flex; align-items:center; justify-content:space-between;
            gap:6px;
        }
        .aging-bar {
            height:5px;
            background:#F1F5F9;
            border-radius:3px;
            overflow:hidden;
            width:100%;
        }
        .aging-bar-fill {
            height:100%;
            border-radius:3px;
            transition: width 0.3s;
        }
        .aging-bar-fill.fresh    { background:linear-gradient(90deg,#10B981,#047857); }
        .aging-bar-fill.aging    { background:linear-gradient(90deg,#F59E0B,#B45309); }
        .aging-bar-fill.overdue  { background:linear-gradient(90deg,#F97316,#C2410C); }
        .aging-bar-fill.critical { background:linear-gradient(90deg,#EF4444,#7F1D1D); }

        .aging-tag {
            display:inline-flex; align-items:center;
            font-size:9.5px; font-weight:800; text-transform:uppercase;
            letter-spacing:0.4px; padding:1px 6px; border-radius:4px;
        }
        .aging-tag.fresh    { background:#ECFDF5; color:#047857; }
        .aging-tag.aging    { background:#FEF3C7; color:#B45309; }
        .aging-tag.overdue  { background:#FFEDD5; color:#C2410C; }
        .aging-tag.critical { background:#FEE2E2; color:#B91C1C; }

        /* Status select (inline quick-change) */
        .status-inline {
            appearance: none; -webkit-appearance: none;
            border:1.5px solid var(--border); border-radius:6px;
            padding:6px 24px 6px 10px;
            font-size:11.5px; font-weight:700; font-family:inherit;
            cursor:pointer; outline:none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 7px center;
            transition: border-color 0.15s, background-color 0.15s;
            min-width: 118px;
        }
        .status-inline:focus { border-color: var(--maroon); }
        .status-inline.status-Pending      { background-color:#FFF7ED; color:#C2410C; border-color:#FED7AA; }
        .status-inline.status-progress     { background-color:#EFF6FF; color:#1D4ED8; border-color:#BFDBFE; }
        .status-inline.status-Resolved     { background-color:#ECFDF5; color:#047857; border-color:#A7F3D0; }

        /* Action buttons */
        .action-btns { display:flex; align-items:center; gap:5px; justify-content:flex-end; }
        .btn-icon {
            width:30px; height:30px; border-radius:7px;
            display:inline-flex; align-items:center; justify-content:center;
            cursor:pointer; border:1px solid transparent;
            transition: filter 0.15s; font-family:inherit;
            padding:0;
        }
        .btn-icon svg { width:14px; height:14px; }
        .btn-icon:hover { filter: brightness(0.94); }
        .btn-icon-edit   { background:#EFF6FF; color:#2563EB; border-color:#BFDBFE; }
        .btn-icon-delete { background:#FEF2F2; color:var(--danger); border-color:#FECACA; }

        /* ===== WORKLOAD PANEL ===== */
        .workload-panel {
            display:flex; flex-direction:column; min-height:0;
        }
        .workload-list {
            display:flex; flex-direction:column; gap:8px;
            overflow-y:auto;
            max-height: calc(100vh - 380px);
            padding-right:4px;
        }
        .workload-item {
            padding:11px 13px;
            border:1px solid var(--border);
            border-radius:10px;
            background:#FAFBFC;
            display:flex; flex-direction:column; gap:6px;
            position:relative; overflow:hidden;
            transition: background 0.15s, border-color 0.15s;
        }
        .workload-item:hover { background:#FFFFFF; border-color:var(--border-strong); }
        .workload-item.has-overdue { border-left:3px solid var(--danger); padding-left:11px; }

        .wl-head {
            display:flex; align-items:center; justify-content:space-between;
            gap:8px;
        }
        .wl-name {
            font-size:12.5px; font-weight:700; color:var(--text-heading);
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .wl-count {
            font-size:15px; font-weight:800; color:var(--text-heading);
            letter-spacing:-0.3px;
        }
        .wl-meta {
            display:flex; align-items:center; gap:6px; flex-wrap:wrap;
            font-size:10.5px;
        }
        .wl-chip {
            display:inline-flex; align-items:center; gap:3px;
            padding:2px 6px; border-radius:4px;
            font-weight:700; white-space:nowrap;
        }
        .wl-chip.pending      { background:#FFF7ED; color:#C2410C; }
        .wl-chip.in-progress  { background:#EFF6FF; color:#1D4ED8; }
        .wl-chip.oldest       { background:#FEF2F2; color:#B91C1C; }

        /* ===== AGING CHART ===== */
        .aging-chart-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:12px; padding:16px;
            margin-bottom:12px;
            box-shadow:0 1px 3px rgba(0,0,0,0.02);
        }
        .aging-chart-wrap {
            display:grid; grid-template-columns: repeat(4, 1fr); gap:10px;
            margin-top:10px;
        }
        .aging-stat {
            display:flex; flex-direction:column; align-items:center;
            padding:12px 8px;
            background:#FAFBFC;
            border:1px solid var(--border);
            border-radius:10px;
            text-align:center;
            cursor:pointer;
            transition: background 0.15s, border-color 0.15s, transform 0.15s;
            text-decoration:none;
        }
        .aging-stat:hover { background:#FFFFFF; border-color:var(--border-strong); transform: translateY(-1px); }
        .aging-stat .value {
            font-size:22px; font-weight:800; color:var(--text-heading);
            line-height:1; letter-spacing:-0.5px;
        }
        .aging-stat .label {
            font-size:10px; font-weight:800; color:var(--text-muted);
            text-transform:uppercase; letter-spacing:0.6px;
            margin-top:6px;
        }
        .aging-stat.fresh    .value { color: var(--success); }
        .aging-stat.aging    .value { color: var(--warning); }
        .aging-stat.overdue  .value { color: #C2410C; }
        .aging-stat.critical .value { color: var(--danger); }

        /* Empty state */
        .empty-state {
            text-align:center; color:var(--text-muted); padding:50px 20px;
        }
        .empty-state svg { width:44px; height:44px; color:#CBD5E1; margin-bottom:10px; }
        .empty-state h4 { color:var(--text-heading); font-size:15px; margin:0 0 4px; }
        .empty-state p { font-size:12.5px; margin:0; }

        /* ===== TOAST ===== */
        .toast-container {
            position:fixed; top: calc(var(--header-height) + 16px); right:20px;
            z-index:9999; display:flex; flex-direction:column; gap:10px;
            max-width:420px; pointer-events:none;
        }
        .toast {
            pointer-events:auto; background:#FFFFFF; border-radius:8px;
            padding:12px 16px; box-shadow:0 10px 20px rgba(0,0,0,0.1);
            border-left:4px solid var(--success); font-size:13px; font-weight:600;
            color:var(--text-heading); display:flex; align-items:center;
            justify-content:space-between; gap:10px;
            animation: toastSlideIn 0.3s forwards;
        }
        .toast-error { border-left-color:var(--danger); }
        .toast-close { background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-size:18px; line-height:1; }
        @keyframes toastSlideIn { from { opacity:0; transform:translateX(40px); } to { opacity:1; transform:translateX(0); } }

        /* ===== MODAL ===== */
        .modal-overlay {
            position:fixed; inset:0;
            background:rgba(15,23,42,0.68);
            -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px);
            z-index:1050; display:none;
            align-items:center; justify-content:center; padding:20px;
        }
        .modal-overlay.active { display:flex; }
        .modal-box {
            background:var(--surface); border-radius:18px;
            width:100%; max-width:680px;
            max-height: calc(100vh - 40px); max-height: calc(100dvh - 40px);
            border:1px solid var(--border);
            box-shadow: 0 30px 60px -12px rgba(15,23,42,0.35);
            display:flex; flex-direction:column; overflow:hidden;
            animation: modalIn 0.28s cubic-bezier(0.34,1.3,0.64,1);
        }
        @keyframes modalIn { from { opacity:0; transform:translateY(14px) scale(0.98); } to { opacity:1; transform:translateY(0) scale(1); } }

        .modal-header {
            flex-shrink:0; padding:18px 22px;
            border-bottom:1px solid var(--border);
            display:flex; align-items:flex-start; justify-content:space-between; gap:14px;
            background: linear-gradient(180deg, #FFFFFF 0%, #FCFCFD 100%);
        }
        .modal-header-left { display:flex; align-items:flex-start; gap:13px; min-width:0; flex:1; }
        .modal-header-icon {
            width:42px; height:42px; border-radius:11px;
            background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
            color:var(--maroon); display:flex; align-items:center; justify-content:center;
            flex-shrink:0; border:1px solid #FECACA;
        }
        .modal-header-icon svg { width:20px; height:20px; }
        .modal-header-text { min-width:0; padding-top:1px; }
        .modal-header-text h3 { font-size:16.5px; font-weight:800; color:var(--text-heading); margin:0; letter-spacing:-0.3px; line-height:1.2; }
        .modal-header-text p { font-size:12px; color:var(--text-muted); margin:3px 0 0; line-height:1.4; }

        .btn-close-modal {
            width:34px; height:34px; flex-shrink:0; border-radius:9px;
            background:transparent; border:1px solid transparent; color:var(--text-muted);
            cursor:pointer; display:inline-flex; align-items:center; justify-content:center;
            transition: background 0.18s, color 0.18s, border-color 0.18s; padding:0;
        }
        .btn-close-modal:hover { background:#F1F5F9; color:var(--text-heading); border-color:var(--border); }
        .btn-close-modal svg { width:18px; height:18px; }

        .modal-form { display:flex; flex-direction:column; flex:1 1 auto; min-height:0; overflow:hidden; }
        .modal-body {
            flex:1 1 auto; min-height:0; overflow-y:auto; overflow-x:hidden;
            padding:22px; -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }
        .form-section { margin-bottom:20px; }
        .form-section:last-child { margin-bottom:0; }
        .form-section-title {
            display:flex; align-items:center; gap:10px;
            font-size:11px; font-weight:800; letter-spacing:1.1px;
            text-transform:uppercase; color:var(--maroon);
            margin-bottom:14px; padding-bottom:10px; border-bottom:1px dashed #FECACA;
        }
        .section-num {
            width:22px; height:22px; border-radius:50%;
            background:var(--maroon); color:#FFF;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:10.5px; font-weight:800; flex-shrink:0;
        }

        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .modal-form-group { margin-bottom:0; min-width:0; }
        .modal-form-group.full { grid-column: 1 / -1; }
        .modal-form-group label {
            display:flex; align-items:center; gap:5px;
            font-size:12px; font-weight:700; color:var(--text-heading);
            margin-bottom:6px; letter-spacing:-0.1px;
        }
        .modal-form-group label .req { color:var(--danger); }
        .modal-form-group input,
        .modal-form-group select,
        .modal-form-group textarea {
            width:100%; padding:10px 13px;
            border:1.5px solid var(--border); border-radius:9px;
            font-size:13px; outline:none; background:#F8FAFC;
            color:var(--text-heading); font-family:inherit;
            transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
        }
        .modal-form-group input,
        .modal-form-group select { height:42px; }
        .modal-form-group textarea { min-height:90px; resize:vertical; line-height:1.5; }
        .modal-form-group input:focus,
        .modal-form-group select:focus,
        .modal-form-group textarea:focus {
            border-color:var(--maroon); background:#FFFFFF;
            box-shadow: 0 0 0 4px rgba(122,0,0,0.08);
        }

        .readonly-card {
            background:#FAFBFC; border:1.5px dashed #CBD5E1;
            border-radius:9px; padding:10px 13px;
            font-size:12.5px; color:var(--text-heading); line-height:1.45;
        }
        .readonly-card .label {
            font-size:10.5px; font-weight:800; letter-spacing:0.6px;
            text-transform:uppercase; color:var(--text-muted);
            display:block; margin-bottom:3px;
        }

        .field-hint { display:block; font-size:11px; color:var(--text-muted); margin-top:5px; line-height:1.4; }

        .modal-footer {
            flex-shrink:0; padding:14px 22px;
            background:#FAFBFC; border-top:1px solid var(--border);
            display:flex; justify-content:flex-end; gap:10px;
        }
        .btn-primary {
            background:var(--maroon); color:#FFFFFF; border:none;
            padding:10px 16px; border-radius:8px; font-size:12.5px; font-weight:700;
            cursor:pointer; display:inline-flex; align-items:center; gap:6px;
            font-family:inherit; transition: background 0.18s, transform 0.12s;
        }
        .btn-primary:hover { background:var(--maroon-dark); }
        .btn-primary:active { transform:scale(0.98); }
        .btn-secondary {
            background:#FFFFFF; color:var(--text-heading); border:1.5px solid var(--border);
            padding:10px 16px; border-radius:8px; font-size:12.5px; font-weight:700;
            cursor:pointer; transition: background 0.18s, border-color 0.18s; font-family:inherit;
        }
        .btn-secondary:hover { background:#F1F5F9; border-color:var(--border-strong); }
        .modal-footer .btn-primary,
        .modal-footer .btn-secondary { min-width:110px; justify-content:center; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .admin-main-wrap { margin-left:0; width:100%; }
            .kpi-grid { grid-template-columns:repeat(2, minmax(0,1fr)); }
        }
        @media (max-width: 768px) {
            .admin-container { padding: calc(var(--header-height) + 12px) 14px 18px; }
            .toolbar { flex-direction: column; align-items: stretch; padding:14px 16px; }
            .filter-controls { width:100%; }
            .select-live-filter, .input-live-search { flex:1; min-width:0; }
            .table-scroll-wrap { max-height: calc(100vh - 420px); }
            .modal-overlay { padding:14px; }
            .modal-box { max-height: calc(100dvh - 28px); }
        }
        @media (max-width: 640px) {
            .kpi-grid { grid-template-columns:1fr 1fr; gap:8px; }
            .kpi-card { padding:12px 14px; border-radius:12px; }
            .kpi-value { font-size:20px; }
            .kpi-label { font-size:10px; }
            .kpi-icon { width:24px; height:24px; }
            .kpi-icon svg { width:13px; height:13px; }
            .aging-chart-wrap { grid-template-columns: repeat(2, 1fr); }
            .modal-overlay {
                padding:0; align-items: stretch; justify-content: stretch;
                background:#FFFFFF;
                -webkit-backdrop-filter: none; backdrop-filter: none;
            }
            .modal-box {
                max-width:100%; width:100%; max-height:100dvh; height:100dvh;
                border-radius:0; border:none; box-shadow:none;
                animation: modalSlideUp 0.3s cubic-bezier(0.32,0.72,0,1);
            }
            @keyframes modalSlideUp {
                from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); }
            }
            .modal-header { padding:16px; padding-top: calc(16px + env(safe-area-inset-top,0px)); }
            .modal-body { padding:18px 16px; -webkit-overflow-scrolling: touch; }
            .modal-footer {
                padding:12px 16px; padding-bottom: calc(12px + env(safe-area-inset-bottom,0px));
                gap:8px;
            }
            .modal-footer .btn-primary, .modal-footer .btn-secondary { flex:1; min-width:0; }
            .form-grid { grid-template-columns:1fr; gap:12px; }
            .form-section { margin-bottom:18px; }
            .modal-form-group input, .modal-form-group select, .modal-form-group textarea { font-size:16px; }
            .modal-form-group input, .modal-form-group select { height:46px; }
        }
        @media (max-width: 480px) {
            :root { --header-height: 66px; }
            .admin-container { padding: calc(var(--header-height) + 10px) 10px 16px; }
            .toolbar-title h2 { font-size:17px; }
            .toolbar-title p { font-size:11.5px; }
            .toast-container { top: calc(var(--header-height) + 10px); right:10px; left:10px; max-width:none; }
            .toast { font-size:12.5px; }
        }
        @media (prefers-reduced-motion: reduce) {
            * { transition:none !important; animation:none !important; }
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
                <div class="kpi-card open">
                    <div class="kpi-head">
                        <span class="kpi-label">Open Tickets</span>
                        <span class="kpi-icon blue">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value"><?php echo number_format($stats['open']); ?></div>
                    <div class="kpi-sub">
                        <span class="dot" style="background:var(--info);"></span>
                        Currently in Pending or In Progress
                    </div>
                </div>

                <div class="kpi-card overdue">
                    <div class="kpi-head">
                        <span class="kpi-label">Overdue (&gt;<?php echo AGE_AGING_MAX; ?>d)</span>
                        <span class="kpi-icon red">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value"><?php echo number_format($stats['overdue']); ?></div>
                    <div class="kpi-sub">
                        <span class="dot" style="background:var(--danger);"></span>
                        Needs immediate attention
                    </div>
                </div>

                <div class="kpi-card avg">
                    <div class="kpi-head">
                        <span class="kpi-label">Avg Time to Resolve</span>
                        <span class="kpi-icon green">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value">
                        <?php if ($stats['avg_resolve_days'] !== null): ?>
                            <?php echo number_format($stats['avg_resolve_days'], 1); ?><span class="kpi-unit">d</span>
                        <?php else: ?>
                            <span style="font-size:18px; color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </div>
                    <div class="kpi-sub">
                        <span class="dot" style="background:var(--success);"></span>
                        Across all resolved tickets
                    </div>
                </div>

                <div class="kpi-card resolved">
                    <div class="kpi-head">
                        <span class="kpi-label">Resolved (30 days)</span>
                        <span class="kpi-icon purple">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value"><?php echo number_format($stats['resolved_30d']); ?></div>
                    <div class="kpi-sub">
                        <span class="dot" style="background:#8B5CF6;"></span>
                        Closed in the last month
                    </div>
                </div>
            </section>

            <div class="fixed-header-deck">
                <section class="toolbar">
                    <div class="toolbar-title">
                        <h2>Action Plan Tracker</h2>
                        <p>Drive corrective-action tickets to resolution — aging, workload, and quick status changes</p>
                    </div>

                    <form method="GET" action="admin_action_plans.php" class="filter-controls">
                        <input type="text" name="q" class="input-live-search"
                               placeholder="Search ticket, office, service..."
                               value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
                               id="searchInput" autocomplete="off">

                        <select name="status" class="select-live-filter" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="Pending"     <?php echo $status_f === 'Pending'     ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo $status_f === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Resolved"    <?php echo $status_f === 'Resolved'    ? 'selected' : ''; ?>>Resolved</option>
                        </select>

                        <select name="assignee" class="select-live-filter" onchange="this.form.submit()">
                            <option value="">All Assignees</option>
                            <option value="__unassigned__" <?php echo $assignee_f === '__unassigned__' ? 'selected' : ''; ?>>— Unassigned —</option>
                            <?php foreach ($staff_list as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['user_id']); ?>" <?php echo $assignee_f === $s['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="office_id" class="select-live-filter" onchange="this.form.submit()">
                            <option value="0">All Offices</option>
                            <?php foreach ($offices_list as $o): ?>
                                <option value="<?php echo (int)$o['id']; ?>" <?php echo $office_f === (int)$o['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($o['office_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="aging" class="select-live-filter" onchange="this.form.submit()">
                            <option value="">All Aging</option>
                            <option value="fresh"    <?php echo $aging_f === 'fresh'    ? 'selected' : ''; ?>>Fresh (0–<?php echo AGE_FRESH_MAX; ?>d)</option>
                            <option value="aging"    <?php echo $aging_f === 'aging'    ? 'selected' : ''; ?>>Aging (<?php echo AGE_FRESH_MAX+1; ?>–<?php echo AGE_AGING_MAX; ?>d)</option>
                            <option value="overdue"  <?php echo $aging_f === 'overdue'  ? 'selected' : ''; ?>>Overdue (<?php echo AGE_AGING_MAX+1; ?>–<?php echo AGE_OVERDUE_MAX; ?>d)</option>
                            <option value="critical" <?php echo $aging_f === 'critical' ? 'selected' : ''; ?>>Critical (&gt;<?php echo AGE_OVERDUE_MAX; ?>d)</option>
                        </select>

                        <?php if ($status_f !== '' || $assignee_f !== '' || $office_f > 0 || $aging_f !== '' || !empty($_GET['q'])): ?>
                            <a href="admin_action_plans.php" class="btn-reset">Reset</a>
                        <?php endif; ?>
                    </form>
                </section>
            </div>

            <!-- ============= AGING BUCKETS ============= -->
            <section class="aging-chart-card">
                <div class="panel-head">
                    <div>
                        <h3>Aging Distribution</h3>
                        <p>Open tickets bucketed by days since creation — click a bucket to filter</p>
                    </div>
                </div>
                <div class="aging-chart-wrap">
                    <a href="?aging=fresh" class="aging-stat fresh">
                        <span class="value"><?php echo (int)$aging_buckets['fresh']; ?></span>
                        <span class="label">Fresh · 0–<?php echo AGE_FRESH_MAX; ?>d</span>
                    </a>
                    <a href="?aging=aging" class="aging-stat aging">
                        <span class="value"><?php echo (int)$aging_buckets['aging']; ?></span>
                        <span class="label">Aging · <?php echo AGE_FRESH_MAX+1; ?>–<?php echo AGE_AGING_MAX; ?>d</span>
                    </a>
                    <a href="?aging=overdue" class="aging-stat overdue">
                        <span class="value"><?php echo (int)$aging_buckets['overdue']; ?></span>
                        <span class="label">Overdue · <?php echo AGE_AGING_MAX+1; ?>–<?php echo AGE_OVERDUE_MAX; ?>d</span>
                    </a>
                    <a href="?aging=critical" class="aging-stat critical">
                        <span class="value"><?php echo (int)$aging_buckets['critical']; ?></span>
                        <span class="label">Critical · &gt;<?php echo AGE_OVERDUE_MAX; ?>d</span>
                    </a>
                </div>
            </section>

            <!-- ============= MAIN SPLIT: TABLE + WORKLOAD ============= -->
            <section class="split-layout">

                <!-- ---------- MAIN TABLE ---------- -->
                <div class="panel-card">
                    <div class="panel-head">
                        <div>
                            <h3>Active Queue</h3>
                            <p>Sorted by status, then aging — overdue tickets appear first</p>
                        </div>
                        <span style="font-size:11.5px; color:var(--text-muted); font-weight:600;" id="rowCountBadge">
                            <?php echo count($plans); ?> ticket<?php echo count($plans) === 1 ? '' : 's'; ?>
                        </span>
                    </div>

                    <div class="table-scroll-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:100px;">Ticket</th>
                                    <th style="width:190px;">Office &amp; Service</th>
                                    <th style="width:170px;">Assigned To</th>
                                    <th style="width:130px;">Aging</th>
                                    <th style="width:140px;">Status</th>
                                    <th style="width:80px; text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="plansBody">
                                <?php if (!empty($plans)): ?>
                                    <?php foreach ($plans as $ap): ?>
                                        <?php
                                            $age_days = (int)$ap['age_days'];
                                            $bucket   = $ap['status'] === 'Resolved' ? null : aging_bucket($age_days);
                                            $is_overdue = in_array($bucket, ['overdue','critical'], true);
                                            $bar_pct = min(100, ($age_days / 21) * 100); // scale to 3 weeks max

                                            $status_input_val = $ap['status'];
                                        ?>
                                        <tr class="plan-row <?php echo $is_overdue ? 'overdue-row' : ''; ?>"
                                            data-search="<?php echo strtolower(htmlspecialchars(
                                                '#' . $ap['id'] . ' ' . ($ap['office_name'] ?? '') . ' ' .
                                                ($ap['service_accessed'] ?? '') . ' ' . ($ap['staff_name'] ?? '')
                                            )); ?>">
                                            <td>
                                                <span class="ticket-id">#<?php echo str_pad((string)$ap['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                                <small class="muted">
                                                    <?php echo htmlspecialchars(date('M j', strtotime($ap['created_at']))); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="strong"><?php echo htmlspecialchars($ap['office_name'] ?? '—'); ?></span>
                                                <small class="muted"><?php echo htmlspecialchars($ap['service_accessed'] ?? '—'); ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($ap['staff_name'])): ?>
                                                    <span class="strong" style="font-size:12px;"><?php echo htmlspecialchars($ap['staff_name']); ?></span>
                                                    <small class="muted"><?php echo htmlspecialchars($ap['assigned_staff_id']); ?></small>
                                                <?php else: ?>
                                                    <span style="font-size:11px; color:var(--text-muted); font-style:italic;">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="aging-cell">
                                                    <div class="aging-days">
                                                        <span><?php echo $age_days; ?>d</span>
                                                        <?php if ($bucket !== null): ?>
                                                            <span class="aging-tag <?php echo $bucket; ?>"><?php echo aging_label($bucket); ?></span>
                                                        <?php else: ?>
                                                            <span class="aging-tag fresh">Closed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="aging-bar">
                                                        <div class="aging-bar-fill <?php echo $bucket ?? 'fresh'; ?>"
                                                             style="width: <?php echo $bucket === null ? 100 : max(4, $bar_pct); ?>%;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <form method="POST" action="admin_action_plans.php" style="display:inline;">
                                                    <input type="hidden" name="action" value="quick_status">
                                                    <input type="hidden" name="id" value="<?php echo (int)$ap['id']; ?>">
                                                    <select name="status" class="status-inline status-<?php echo htmlspecialchars($status_input_val); ?>"
                                                            onchange="this.form.submit()">
                                                        <option value="Pending"     <?php echo $ap['status'] === 'Pending'     ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="In Progress" <?php echo $ap['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                        <option value="Resolved"    <?php echo $ap['status'] === 'Resolved'    ? 'selected' : ''; ?>>Resolved</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td style="text-align: right;">
                                                <div class="action-btns">
                                                    <button type="button" class="btn-icon btn-icon-edit"
                                                            title="Edit details"
                                                            onclick='openEditModal(<?php echo htmlspecialchars(json_encode([
                                                                "id" => (int)$ap["id"],
                                                                "feedback_id" => (int)$ap["feedback_id"],
                                                                "office_name" => $ap["office_name"] ?? "",
                                                                "service_accessed" => $ap["service_accessed"] ?? "",
                                                                "assigned_staff_id" => $ap["assigned_staff_id"] ?? "",
                                                                "root_cause_analysis" => $ap["root_cause_analysis"] ?? "",
                                                                "action_taken" => $ap["action_taken"] ?? "",
                                                                "status" => $ap["status"],
                                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8"); ?>)'>
                                                        <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                    </button>
                                                    <form method="POST" action="admin_action_plans.php" style="display:inline;"
                                                          onsubmit="return confirm('Permanently delete ticket #<?php echo str_pad((string)$ap['id'], 4, '0', STR_PAD_LEFT); ?>? The linked feedback will return to the resolutions pending queue.');">
                                                        <input type="hidden" name="action" value="delete_plan">
                                                        <input type="hidden" name="id" value="<?php echo (int)$ap['id']; ?>">
                                                        <button type="submit" class="btn-icon btn-icon-delete" title="Delete ticket">
                                                            <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                                <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                                </svg>
                                                <h4>No action plans found</h4>
                                                <p>Adjust filters or create tickets from the Feedback Resolutions page.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ---------- WORKLOAD SIDEBAR ---------- -->
                <aside class="panel-card workload-panel">
                    <div class="panel-head">
                        <div>
                            <h3>Assignee Workload</h3>
                            <p>Open tickets per staff member</p>
                        </div>
                    </div>

                    <?php if (!empty($workload)): ?>
                        <div class="workload-list">
                            <?php foreach ($workload as $w): ?>
                                <?php $has_overdue = (int)$w['oldest_open_days'] > AGE_AGING_MAX; ?>
                                <a href="?assignee=<?php echo urlencode($w['uid'] ?? '__unassigned__'); ?>"
                                   class="workload-item <?php echo $has_overdue ? 'has-overdue' : ''; ?>"
                                   style="text-decoration:none;">
                                    <div class="wl-head">
                                        <span class="wl-name"><?php echo htmlspecialchars($w['name']); ?></span>
                                        <span class="wl-count"><?php echo (int)$w['open_total']; ?></span>
                                    </div>
                                    <div class="wl-meta">
                                        <?php if ((int)$w['pending'] > 0): ?>
                                            <span class="wl-chip pending"><?php echo (int)$w['pending']; ?> pending</span>
                                        <?php endif; ?>
                                        <?php if ((int)$w['in_progress'] > 0): ?>
                                            <span class="wl-chip in-progress"><?php echo (int)$w['in_progress']; ?> in progress</span>
                                        <?php endif; ?>
                                        <?php if ((int)$w['oldest_open_days'] > 0): ?>
                                            <span class="wl-chip oldest">oldest: <?php echo (int)$w['oldest_open_days']; ?>d</span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding:30px 10px;">
                            <p style="font-size:12px;">No open tickets assigned to anyone yet.</p>
                        </div>
                    <?php endif; ?>
                </aside>

            </section>

        </main>
    </div>

    <!-- ============================================================
         MODAL: Edit Action Plan
         ============================================================ -->
    <div class="modal-overlay" id="planModal">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modalTitle">

            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-header-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </div>
                    <div class="modal-header-text">
                        <h3 id="modalTitle">Edit Action Plan</h3>
                        <p id="modalSubtitle">Update assignment, status, and resolution notes</p>
                    </div>
                </div>
                <button type="button" class="btn-close-modal" onclick="closeModal('planModal')" aria-label="Close">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="admin_action_plans.php" class="modal-form" id="planForm">
                <input type="hidden" name="action" value="update_plan">
                <input type="hidden" name="id" id="planId" value="">

                <div class="modal-body">

                    <!-- SECTION 1 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">1</span>
                            <span>Linked Feedback</span>
                        </div>

                        <div class="form-grid">
                            <div class="modal-form-group">
                                <label>Ticket Reference</label>
                                <div class="readonly-card">
                                    <span class="label">Ticket</span>
                                    <span id="ctxTicket">—</span>
                                </div>
                            </div>
                            <div class="modal-form-group">
                                <label>Feedback Source</label>
                                <div class="readonly-card">
                                    <span class="label">Feedback</span>
                                    <span id="ctxFeedback">—</span>
                                </div>
                            </div>
                            <div class="modal-form-group full">
                                <label>Office &amp; Service</label>
                                <div class="readonly-card">
                                    <span class="label">Target</span>
                                    <span id="ctxTarget">—</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">2</span>
                            <span>Assignment &amp; Status</span>
                        </div>

                        <div class="form-grid">
                            <div class="modal-form-group">
                                <label>Assigned To</label>
                                <select name="assigned_staff_id" id="editAssignedStaff">
                                    <option value="">— Unassigned —</option>
                                    <?php foreach ($staff_list as $s): ?>
                                        <option value="<?php echo htmlspecialchars($s['user_id']); ?>">
                                            <?php echo htmlspecialchars($s['full_name']); ?>
                                            (<?php echo htmlspecialchars($s['user_id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="modal-form-group">
                                <label>Status <span class="req">*</span></label>
                                <select name="status" id="editStatus" required>
                                    <option value="Pending">Pending</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Resolved">Resolved</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">3</span>
                            <span>Documentation</span>
                        </div>

                        <div class="form-grid">
                            <div class="modal-form-group full">
                                <label>Root Cause Analysis <span class="req">*</span></label>
                                <textarea name="root_cause_analysis" id="editRootCause" required></textarea>
                            </div>

                            <div class="modal-form-group full">
                                <label>Corrective Action Taken</label>
                                <textarea name="action_taken" id="editActionTaken"
                                    placeholder="What has been (or will be) done to resolve the issue?"></textarea>
                                <small class="field-hint">Leave blank while Pending; update as progress is made.</small>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('planModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
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
            setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4200);
        }

        <?php if (!empty($toast_message)): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showToast(<?php echo json_encode($toast_message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($toast_type); ?>);
            });
        <?php endif; ?>

        /* ============================================================
           MODAL
           ============================================================ */
        function openEditModal(ap) {
            document.getElementById('planId').value = ap.id;
            document.getElementById('ctxTicket').textContent   = '#' + String(ap.id).padStart(4, '0');
            document.getElementById('ctxFeedback').textContent = '#FB-' + String(ap.feedback_id).padStart(4, '0');
            document.getElementById('ctxTarget').textContent   = (ap.office_name || '—') + ' · ' + (ap.service_accessed || '—');
            document.getElementById('editAssignedStaff').value = ap.assigned_staff_id || '';
            document.getElementById('editStatus').value        = ap.status || 'Pending';
            document.getElementById('editRootCause').value     = ap.root_cause_analysis || '';
            document.getElementById('editActionTaken').value   = ap.action_taken || '';

            document.getElementById('planModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
        }
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) closeModal(e.target.id);
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('planModal');
                if (modal.classList.contains('active')) closeModal('planModal');
            }
        });

        /* ============================================================
           CLIENT-SIDE SEARCH
           ============================================================ */
        (function() {
            const input = document.getElementById('searchInput');
            const rows  = document.querySelectorAll('.plan-row');
            const badge = document.getElementById('rowCountBadge');
            if (!input) return;
            let timeout;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    const term = this.value.toLowerCase().trim();
                    let matches = 0;
                    rows.forEach(r => {
                        const haystack = r.getAttribute('data-search') || '';
                        if (term === '' || haystack.includes(term)) {
                            r.style.display = '';
                            matches++;
                        } else {
                            r.style.display = 'none';
                        }
                    });
                    if (badge) badge.textContent = matches + ' ticket' + (matches === 1 ? '' : 's');
                }, 120);
            });
        })();
    </script>
</body>
</html>