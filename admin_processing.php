<?php
// admin_sla_tracker.php — ARTA SLA Compliance Tracker
// Forensic per-transaction view of every breach with documented review workflow.

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

// Severity thresholds (days over SLA)
const SEV_MILD_MAX     = 3;
const SEV_MODERATE_MAX = 7;
const SEV_SEVERE_MAX   = 14;

function severity_of(int $delta): string {
    if ($delta <= 0) return 'compliant';
    if ($delta <= SEV_MILD_MAX)     return 'mild';
    if ($delta <= SEV_MODERATE_MAX) return 'moderate';
    if ($delta <= SEV_SEVERE_MAX)   return 'severe';
    return 'critical';
}

function severity_label(string $sev): string {
    return match ($sev) {
        'compliant' => 'Compliant',
        'mild'      => 'Mild',
        'moderate'  => 'Moderate',
        'severe'    => 'Severe',
        'critical'  => 'Critical',
        default     => 'Unknown',
    };
}

// ---------------------------------------------------------------------------
// POST ACTIONS
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -------- ESCALATE (create action_plan + sla_review together) --------
    if ($action === 'escalate_breach') {
        $feedback_id       = (int)($_POST['feedback_id'] ?? 0);
        $office_id         = (int)($_POST['office_id'] ?? 0);
        $assigned_staff_id = trim($_POST['assigned_staff_id'] ?? '');
        $root_cause        = trim($_POST['root_cause_analysis'] ?? '');
        $notes             = trim($_POST['notes'] ?? '');

        if ($feedback_id <= 0 || $office_id <= 0 || $root_cause === '') {
            $toast_message = "Feedback reference, office, and root-cause analysis are required.";
            $toast_type = 'error';
        } elseif ($assigned_staff_id === '') {
            $toast_message = "Please assign a staff member before escalating.";
            $toast_type = 'error';
        } else {
            $conn->begin_transaction();
            try {
                // 1. Create the action plan
                $stmt = $conn->prepare("
                    INSERT INTO action_plans
                    (feedback_id, office_id, assigned_staff_id, root_cause_analysis, action_taken, status)
                    VALUES (?, ?, ?, ?, '', 'Pending')
                ");
                $stmt->bind_param("iiss", $feedback_id, $office_id, $assigned_staff_id, $root_cause);
                if (!$stmt->execute()) throw new Exception($conn->error);
                $action_plan_id = $stmt->insert_id;
                $stmt->close();

                // 2. Create the review record (replace if re-review)
                $admin_id = $_SESSION['user_id'] ?? null;
                $outcome  = 'Escalated';
                $del = $conn->prepare("DELETE FROM sla_reviews WHERE feedback_id = ?");
                $del->bind_param("i", $feedback_id);
                $del->execute();
                $del->close();

                $rev = $conn->prepare("
                    INSERT INTO sla_reviews (feedback_id, reviewed_by, outcome, notes, action_plan_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $rev->bind_param("isssi", $feedback_id, $admin_id, $outcome, $notes, $action_plan_id);
                if (!$rev->execute()) throw new Exception($conn->error);
                $rev->close();

                $conn->commit();
                $toast_message = "Breach escalated. Action plan #" . str_pad((string)$action_plan_id, 4, '0', STR_PAD_LEFT) . " created.";
                $toast_type = 'success';
            } catch (Throwable $e) {
                $conn->rollback();
                $toast_message = "Escalation failed: " . $e->getMessage();
                $toast_type = 'error';
            }
        }
    }

    // -------- REVIEW WITHOUT ESCALATION (Acknowledged / False Positive) --------
    elseif ($action === 'review_breach') {
        $feedback_id = (int)($_POST['feedback_id'] ?? 0);
        $outcome     = $_POST['outcome'] ?? '';
        $notes       = trim($_POST['notes'] ?? '');

        if ($feedback_id <= 0 || !in_array($outcome, ['Acknowledged','False Positive'], true)) {
            $toast_message = "Invalid review submission.";
            $toast_type = 'error';
        } elseif ($notes === '') {
            $toast_message = "Please provide reviewer notes for the audit trail.";
            $toast_type = 'error';
        } else {
            $admin_id = $_SESSION['user_id'] ?? null;
            $del = $conn->prepare("DELETE FROM sla_reviews WHERE feedback_id = ?");
            $del->bind_param("i", $feedback_id);
            $del->execute();
            $del->close();

            $stmt = $conn->prepare("
                INSERT INTO sla_reviews (feedback_id, reviewed_by, outcome, notes, action_plan_id)
                VALUES (?, ?, ?, ?, NULL)
            ");
            $stmt->bind_param("isss", $feedback_id, $admin_id, $outcome, $notes);

            if ($stmt->execute()) {
                $toast_message = "Breach reviewed and marked as \"{$outcome}\".";
                $toast_type = 'success';
            } else {
                $toast_message = "Review failed: " . $conn->error;
                $toast_type = 'error';
            }
            $stmt->close();
        }
    }

    // -------- UNDO REVIEW (admin correction) --------
    elseif ($action === 'undo_review') {
        $review_id = (int)($_POST['review_id'] ?? 0);
        if ($review_id > 0) {
            $stmt = $conn->prepare("DELETE FROM sla_reviews WHERE id = ?");
            $stmt->bind_param("i", $review_id);
            if ($stmt->execute()) {
                $toast_message = "Review record removed. Transaction is now Unreviewed.";
                $toast_type = 'success';
            } else {
                $toast_message = "Undo failed: " . $conn->error;
                $toast_type = 'error';
            }
            $stmt->close();
        }
    }
}

// ---------------------------------------------------------------------------
// FILTERS
// ---------------------------------------------------------------------------
$range         = $_GET['range'] ?? '90d';
$office_filter = (int)($_GET['office_id'] ?? 0);
$severity_f    = $_GET['severity'] ?? '';
$review_f      = $_GET['review']   ?? '';

$range_days = match ($range) {
    '30d' => 30, '90d' => 90, '180d' => 180, '365d' => 365, 'all' => null, default => 90,
};

$now_ts    = time();
$cur_end   = ($range_days === null) ? '2099-12-31 23:59:59' : date('Y-m-d H:i:s', $now_ts);
$cur_start = $range_days !== null ? date('Y-m-d H:i:s', $now_ts - ($range_days * 86400)) : '1970-01-01 00:00:00';

$extra = '';
if ($office_filter > 0) $extra .= " AND f.office_id = " . (int)$office_filter;

// ---------------------------------------------------------------------------
// BASE QUERY (used by table + stats)
// ---------------------------------------------------------------------------
$base_sql = "
    FROM arta_feedback f
    INNER JOIN services s
        ON s.office_id = f.office_id
       AND BINARY TRIM(s.service_name) = BINARY TRIM(f.service_accessed)
    LEFT JOIN offices o ON o.id = f.office_id
    LEFT JOIN users u   ON u.user_id = f.user_id
    LEFT JOIN sla_reviews sr ON sr.feedback_id = f.feedback_id
    LEFT JOIN users ru       ON ru.user_id = sr.reviewed_by
    LEFT JOIN action_plans ap ON ap.feedback_id = f.feedback_id
    WHERE f.is_archived = 0
      AND f.service_start_date IS NOT NULL
      AND f.transaction_date IS NOT NULL
      AND f.transaction_date >= f.service_start_date
      AND f.submission_timestamp >= '{$conn->real_escape_string($cur_start)}'
      AND f.submission_timestamp <  '{$conn->real_escape_string($cur_end)}'
      $extra
";

// ---------------------------------------------------------------------------
// STATS
// ---------------------------------------------------------------------------
$stats = ['total' => 0, 'compliant' => 0, 'breached' => 0, 'unreviewed' => 0,
          'mild' => 0, 'moderate' => 0, 'severe' => 0, 'critical' => 0, 'avg_delay' => 0.0];

$stats_res = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN DATEDIFF(f.transaction_date, f.service_start_date) <= s.processing_time_days THEN 1 ELSE 0 END) AS compliant,
        SUM(CASE WHEN DATEDIFF(f.transaction_date, f.service_start_date) >  s.processing_time_days THEN 1 ELSE 0 END) AS breached,
        SUM(CASE WHEN DATEDIFF(f.transaction_date, f.service_start_date) >  s.processing_time_days AND sr.id IS NULL THEN 1 ELSE 0 END) AS unreviewed,
        SUM(CASE WHEN DATEDIFF(f.transaction_date, f.service_start_date) - s.processing_time_days BETWEEN 1 AND 3 THEN 1 ELSE 0 END) AS mild,
        SUM(CASE WHEN DATEDIFF(f.transaction_date, f.service_start_date) - s.processing_time_days BETWEEN 4 AND 7 THEN 1 ELSE 0 END) AS moderate,
        SUM(CASE WHEN DATEDIFF(f.transaction_date, f.service_start_date) - s.processing_time_days BETWEEN 8 AND 14 THEN 1 ELSE 0 END) AS severe,
        SUM(CASE WHEN DATEDIFF(f.transaction_date, f.service_start_date) - s.processing_time_days > 14 THEN 1 ELSE 0 END) AS critical,
        AVG(CASE WHEN DATEDIFF(f.transaction_date, f.service_start_date) > s.processing_time_days
                 THEN DATEDIFF(f.transaction_date, f.service_start_date) - s.processing_time_days END) AS avg_delay
    $base_sql
");
if ($stats_res) {
    $sr = $stats_res->fetch_assoc();
    foreach (['total','compliant','breached','unreviewed','mild','moderate','severe','critical'] as $k) {
        $stats[$k] = (int)($sr[$k] ?? 0);
    }
    $stats['avg_delay'] = round((float)($sr['avg_delay'] ?? 0), 1);
}

$compliance_rate = $stats['total'] > 0 ? round(($stats['compliant'] / $stats['total']) * 100, 1) : 0.0;
$breach_rate     = $stats['total'] > 0 ? round(($stats['breached']  / $stats['total']) * 100, 1) : 0.0;

// ---------------------------------------------------------------------------
// MAIN TABLE QUERY (with filters)
// ---------------------------------------------------------------------------
$table_where = '';
if ($severity_f === 'compliant') {
    $table_where .= " AND DATEDIFF(f.transaction_date, f.service_start_date) <= s.processing_time_days";
} elseif ($severity_f === 'mild') {
    $table_where .= " AND DATEDIFF(f.transaction_date, f.service_start_date) - s.processing_time_days BETWEEN 1 AND 3";
} elseif ($severity_f === 'moderate') {
    $table_where .= " AND DATEDIFF(f.transaction_date, f.service_start_date) - s.processing_time_days BETWEEN 4 AND 7";
} elseif ($severity_f === 'severe') {
    $table_where .= " AND DATEDIFF(f.transaction_date, f.service_start_date) - s.processing_time_days BETWEEN 8 AND 14";
} elseif ($severity_f === 'critical') {
    $table_where .= " AND DATEDIFF(f.transaction_date, f.service_start_date) - s.processing_time_days > 14";
} elseif ($severity_f === 'breached') {
    $table_where .= " AND DATEDIFF(f.transaction_date, f.service_start_date) > s.processing_time_days";
}

if ($review_f === 'unreviewed') {
    $table_where .= " AND sr.id IS NULL AND DATEDIFF(f.transaction_date, f.service_start_date) > s.processing_time_days";
} elseif ($review_f === 'reviewed') {
    $table_where .= " AND sr.id IS NOT NULL";
} elseif ($review_f === 'escalated') {
    $table_where .= " AND sr.outcome = 'Escalated'";
} elseif ($review_f === 'acknowledged') {
    $table_where .= " AND sr.outcome = 'Acknowledged'";
} elseif ($review_f === 'false_positive') {
    $table_where .= " AND sr.outcome = 'False Positive'";
}

$transactions = [];
$tx_res = $conn->query("
    SELECT
        f.feedback_id, f.office_id, f.service_accessed, f.transaction_date,
        f.service_start_date, f.client_type, f.age_group, f.sex,
        f.submission_timestamp, f.comments_recommendations, f.user_id,
        u.full_name AS user_name,
        o.office_name, o.office_type,
        s.id AS service_id, s.processing_time_days AS sla_days, s.classification,
        DATEDIFF(f.transaction_date, f.service_start_date) AS actual_days,
        DATEDIFF(f.transaction_date, f.service_start_date) - s.processing_time_days AS delta_days,
        sr.id AS review_id, sr.outcome AS review_outcome, sr.notes AS review_notes,
        sr.created_at AS reviewed_at, sr.reviewed_by,
        ru.full_name AS reviewer_name,
        ap.id AS action_plan_id, ap.status AS action_plan_status
    $base_sql
    $table_where
    ORDER BY
        CASE WHEN sr.id IS NULL AND DATEDIFF(f.transaction_date, f.service_start_date) > s.processing_time_days THEN 0 ELSE 1 END,
        (DATEDIFF(f.transaction_date, f.service_start_date) - s.processing_time_days) DESC,
        f.submission_timestamp DESC
    LIMIT 500
");
if ($tx_res instanceof mysqli_result) {
    while ($r = $tx_res->fetch_assoc()) $transactions[] = $r;
}

// ---------------------------------------------------------------------------
// WEEKLY COMPLIANCE TREND (chart)
// ---------------------------------------------------------------------------
$trend = ['labels' => [], 'compliance' => [], 'breaches' => []];
$tr_res = $conn->query("
    SELECT
        DATE_FORMAT(f.submission_timestamp, '%x-W%v') AS wk,
        MIN(DATE(f.submission_timestamp)) AS wk_start,
        COUNT(*) AS total,
        SUM(CASE WHEN DATEDIFF(f.transaction_date, f.service_start_date) > s.processing_time_days THEN 1 ELSE 0 END) AS breached
    $base_sql
    GROUP BY wk
    ORDER BY wk_start ASC
");
if ($tr_res instanceof mysqli_result) {
    while ($r = $tr_res->fetch_assoc()) {
        $t = (int)$r['total'];
        $b = (int)$r['breached'];
        $trend['labels'][]     = date('M j', strtotime($r['wk_start']));
        $trend['compliance'][] = $t > 0 ? round((($t - $b) / $t) * 100, 1) : 0;
        $trend['breaches'][]   = $b;
    }
}

// ---------------------------------------------------------------------------
// SUPPORTING LISTS
// ---------------------------------------------------------------------------
$filter_offices = [];
$fo = $conn->query("SELECT id, office_name FROM offices ORDER BY office_name ASC");
if ($fo) while ($r = $fo->fetch_assoc()) $filter_offices[] = $r;

$staff_list = [];
$sl = $conn->query("
    SELECT user_id, full_name, internal_user_type
    FROM users
    WHERE internal_user_type IN ('zppsu_employee','admin')
    ORDER BY full_name ASC
");
if ($sl) while ($r = $sl->fetch_assoc()) $staff_list[] = $r;

// Payload for charts
$chart_payload = [
    'trend' => $trend,
    'severity' => [
        'mild' => $stats['mild'], 'moderate' => $stats['moderate'],
        'severe' => $stats['severe'], 'critical' => $stats['critical'],
    ],
];

// Page metadata
$page_title    = 'SLA Tracker';
$page_crumb    = 'Administration / SLA Tracker';
$page_subtitle = 'Per-transaction ARTA compliance ledger with documented breach review';
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
            position:sticky; top:var(--header-height); z-index:800;
            background:var(--bg); padding-top:4px; padding-bottom:12px;
        }

        /* ===== KPI ===== */
        .kpi-grid {
            display:grid; grid-template-columns:repeat(4, minmax(0,1fr));
            gap:12px; margin-bottom:12px;
        }
        .kpi-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:14px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.02);
            position:relative; overflow:hidden;
            display:flex; flex-direction:column; gap:8px; min-width:0;
        }
        .kpi-card::before {
            content:''; position:absolute; left:0; top:0; bottom:0; width:4px;
            background:linear-gradient(180deg, var(--maroon), var(--maroon-dark));
        }
        .kpi-card.compliance::before { background:linear-gradient(180deg,#10B981,#047857); }
        .kpi-card.breach::before     { background:linear-gradient(180deg,#EF4444,#B91C1C); }
        .kpi-card.unreviewed::before { background:linear-gradient(180deg,#F59E0B,#B45309); }
        .kpi-card.delay::before      { background:linear-gradient(180deg,#2563EB,#1D4ED8); }

        .kpi-head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
        .kpi-label {
            font-size:11px; font-weight:800; color:var(--text-muted);
            text-transform:uppercase; letter-spacing:0.7px;
        }
        .kpi-icon {
            width:30px; height:30px; border-radius:8px;
            display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
        }
        .kpi-icon svg { width:16px; height:16px; }
        .kpi-icon.green  { background:#ECFDF5; color:#047857; }
        .kpi-icon.red    { background:#FEF2F2; color:#B91C1C; }
        .kpi-icon.orange { background:#FFF7ED; color:#C2410C; }
        .kpi-icon.blue   { background:#EFF6FF; color:#1D4ED8; }

        .kpi-value {
            font-size:28px; font-weight:800; color:var(--text-heading);
            line-height:1; letter-spacing:-0.8px;
            display:flex; align-items:baseline; gap:4px;
        }
        .kpi-unit { font-size:15px; font-weight:700; color:var(--text-muted); }

        .kpi-sub {
            font-size:11.5px; color:var(--text-muted); font-weight:600;
            display:flex; align-items:center; gap:5px; line-height:1.4;
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
            flex-wrap:wrap; gap:12px; box-shadow:0 1px 3px rgba(0,0,0,0.02);
        }
        .toolbar-title h2 { font-size:19px; font-weight:800; color:var(--text-heading); margin:0; }
        .toolbar-title p { font-size:12px; color:var(--text-muted); margin:2px 0 0; }

        .filter-controls { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .select-live-filter, .input-live-search {
            padding:9px 12px; border:1.5px solid var(--border); border-radius:8px;
            font-size:12.5px; background:#F8FAFC; color:var(--text-heading);
            outline:none; font-family:inherit; transition:border-color 0.18s, background 0.18s;
        }
        .select-live-filter:focus, .input-live-search:focus { border-color:var(--maroon); background:#FFFFFF; }

        /* ===== CHART CARD ===== */
        .chart-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:14px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.02);
            margin-bottom:12px;
        }
        .chart-head {
            display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:14px;
        }
        .chart-head h3 { font-size:14px; font-weight:800; color:var(--text-heading); margin:0; }
        .chart-head p { font-size:11.5px; color:var(--text-muted); margin:3px 0 0; }
        .chart-canvas-wrap { position:relative; height:240px; }

        /* ===== TABLE ===== */
        .panel-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:12px; padding:16px; flex:1;
            display:flex; flex-direction:column; min-height:0;
            box-shadow:0 1px 3px rgba(0,0,0,0.02);
        }
        .table-scroll-wrap {
            flex:1; min-height:400px; max-height: calc(100vh - 420px);
            overflow-y:auto; overflow-x:auto;
            border:1px solid var(--border); border-radius:8px;
            -webkit-overflow-scrolling: touch;
        }
        table { width:100%; border-collapse:collapse; font-size:13px; min-width:1080px; }
        .table-scroll-wrap thead th {
            position:sticky; top:0; background-color:#F1F5F9; z-index:10;
            box-shadow:0 1px 0 var(--border);
        }
        th {
            background-color:#F1F5F9; color:var(--text-muted); font-weight:700;
            padding:11px 12px; border-bottom:1px solid var(--border);
            text-transform:uppercase; font-size:10.5px; text-align:left; letter-spacing:0.4px;
            white-space:nowrap;
        }
        td { padding:12px; border-bottom:1px solid var(--border); color:var(--text-body); vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tbody tr:hover td { background-color:#FAFAFA; }

        .strong { font-weight:700; color:var(--text-heading); }
        .muted  { color:var(--text-muted); font-size:11px; display:block; margin-top:2px; }

        /* Severity badges */
        .sev-badge {
            display:inline-flex; align-items:center; gap:5px;
            font-size:10.5px; font-weight:800;
            padding:3px 8px; border-radius:5px; white-space:nowrap;
            text-transform:uppercase; letter-spacing:0.4px;
        }
        .sev-badge::before {
            content:''; width:6px; height:6px; border-radius:50%; background:currentColor;
        }
        .sev-compliant { background:#ECFDF5; color:#047857; }
        .sev-mild      { background:#FEF3C7; color:#B45309; }
        .sev-moderate  { background:#FFEDD5; color:#C2410C; }
        .sev-severe    { background:#FEE2E2; color:#B91C1C; }
        .sev-critical  { background:#7F1D1D; color:#FEF2F2; }

        /* Review badges */
        .rev-badge {
            display:inline-flex; align-items:center; gap:5px;
            font-size:10.5px; font-weight:700;
            padding:3px 8px; border-radius:5px; white-space:nowrap;
        }
        .rev-unreviewed    { background:#FEF3C7; color:#B45309; }
        .rev-escalated     { background:#EFF6FF; color:#1D4ED8; }
        .rev-acknowledged  { background:#F1F5F9; color:#475569; }
        .rev-false-positive{ background:#ECFDF5; color:#047857; }

        /* Delta chip */
        .delta-chip {
            display:inline-flex; align-items:center; gap:4px;
            font-size:11.5px; font-weight:700; padding:2px 8px; border-radius:5px;
        }
        .delta-ok    { background:#ECFDF5; color:#047857; }
        .delta-late  { background:#FEE2E2; color:#B91C1C; }
        .delta-mild  { background:#FEF3C7; color:#B45309; }

        /* Action buttons */
        .action-btns { display:flex; align-items:center; gap:6px; justify-content:flex-end; flex-wrap:wrap; }
        .btn-table-action {
            padding:6px 11px; border-radius:6px; font-size:11.5px;
            font-weight:600; cursor:pointer; border:1px solid transparent;
            transition: filter 0.18s; font-family:inherit; white-space:nowrap;
        }
        .btn-table-action:hover { filter: brightness(0.95); }
        .btn-escalate   { background:var(--maroon); color:#FFFFFF; border-color:var(--maroon); }
        .btn-escalate:hover { background:var(--maroon-dark); filter:none; }
        .btn-review     { background:#FFF7ED; color:#C2410C; border-color:#FED7AA; }
        .btn-view       { background:#EFF6FF; color:#2563EB; border-color:#BFDBFE; }
        .btn-undo       { background:#FEF2F2; color:var(--danger); border-color:#FECACA; }

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
            position:fixed; inset:0; background:rgba(15,23,42,0.68);
            -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px);
            z-index:1050; display:none; align-items:center; justify-content:center; padding:20px;
        }
        .modal-overlay.active { display:flex; }
        .modal-box {
            background:var(--surface); border-radius:18px;
            width:100%; max-width:640px;
            max-height: calc(100vh - 40px); max-height: calc(100dvh - 40px);
            border:1px solid var(--border);
            box-shadow: 0 30px 60px -12px rgba(15,23,42,0.35);
            display:flex; flex-direction:column; overflow:hidden;
            animation: modalIn 0.28s cubic-bezier(0.34,1.3,0.64,1);
        }
        @keyframes modalIn { from { opacity:0; transform:translateY(14px) scale(0.98); } to { opacity:1; transform:translateY(0) scale(1); } }

        .modal-header {
            flex-shrink:0; padding:18px 22px; border-bottom:1px solid var(--border);
            display:flex; align-items:flex-start; justify-content:space-between; gap:14px;
            background: linear-gradient(180deg, #FFFFFF 0%, #FCFCFD 100%);
        }
        .modal-header-left { display:flex; align-items:flex-start; gap:13px; min-width:0; flex:1; }
        .modal-header-icon {
            width:42px; height:42px; border-radius:11px;
            background: linear-gradient(135deg,#FEF2F2 0%,#FEE2E2 100%);
            color:var(--maroon); display:flex; align-items:center; justify-content:center;
            flex-shrink:0; border:1px solid #FECACA;
        }
        .modal-header-icon svg { width:20px; height:20px; }
        .modal-header-text { min-width:0; padding-top:1px; }
        .modal-header-text h3 { font-size:16.5px; font-weight:800; color:var(--text-heading); margin:0; letter-spacing:-0.3px; }
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
            padding:22px; -webkit-overflow-scrolling:touch; overscroll-behavior:contain;
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

        .context-card {
            background:#FAFBFC; border:1.5px dashed #CBD5E1;
            border-radius:10px; padding:14px 16px;
            display:grid; grid-template-columns:1fr 1fr; gap:12px;
            font-size:12.5px;
        }
        .context-card .ctx-item { min-width:0; }
        .context-card .ctx-label {
            font-size:10.5px; font-weight:800; letter-spacing:0.6px;
            text-transform:uppercase; color:var(--text-muted);
            display:block; margin-bottom:3px;
        }
        .context-card .ctx-value {
            color:var(--text-heading); font-weight:600;
            word-wrap:break-word;
        }
        .context-card .ctx-value.big {
            font-size:15px; font-weight:800; color:var(--danger);
        }

        .modal-form-group { margin-bottom:14px; min-width:0; }
        .modal-form-group:last-child { margin-bottom:0; }
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

        .field-hint { display:block; font-size:11px; color:var(--text-muted); margin-top:5px; line-height:1.4; }

        /* Outcome radio cards */
        .outcome-grid {
            display:grid; grid-template-columns:repeat(3, minmax(0,1fr));
            gap:8px; margin-bottom:14px;
        }
        .outcome-option {
            position:relative; display:block; cursor:pointer;
        }
        .outcome-option input[type="radio"] {
            position:absolute; opacity:0; pointer-events:none;
        }
        .outcome-option-inner {
            border:1.5px solid var(--border); border-radius:10px;
            padding:12px 10px; text-align:center;
            background:#F8FAFC; transition: all 0.18s;
            height:100%;
            display:flex; flex-direction:column; align-items:center; gap:6px;
        }
        .outcome-option-inner svg { width:18px; height:18px; color:var(--text-muted); transition: color 0.18s; }
        .outcome-option-inner .oo-title {
            font-size:11.5px; font-weight:800; color:var(--text-heading);
            text-transform:uppercase; letter-spacing:0.5px;
        }
        .outcome-option-inner .oo-sub {
            font-size:10.5px; color:var(--text-muted); line-height:1.35;
        }
        .outcome-option:hover .outcome-option-inner { border-color:var(--border-strong); }

        .outcome-option input:checked + .outcome-option-inner {
            border-color:var(--maroon); background:#FEF2F2;
        }
        .outcome-option input:checked + .outcome-option-inner svg { color:var(--maroon); }
        .outcome-option input:checked + .outcome-option-inner .oo-title { color:var(--maroon); }

        .modal-footer {
            flex-shrink:0; padding:14px 22px;
            background:#FAFBFC; border-top:1px solid var(--border);
            display:flex; justify-content:flex-end; gap:10px;
        }
        .modal-footer .btn-primary, .modal-footer .btn-secondary {
            min-width:110px; justify-content:center;
        }

        .btn-primary {
            background:var(--maroon); color:#FFFFFF; border:none;
            padding:10px 16px; border-radius:8px; font-size:12.5px; font-weight:700;
            cursor:pointer; display:inline-flex; align-items:center; gap:6px;
            font-family:inherit; transition: background 0.18s, transform 0.12s;
        }
        .btn-primary:hover { background:var(--maroon-dark); }
        .btn-primary:active { transform: scale(0.98); }
        .btn-secondary {
            background:#FFFFFF; color:var(--text-heading); border:1.5px solid var(--border);
            padding:10px 16px; border-radius:8px; font-size:12.5px; font-weight:700;
            cursor:pointer; transition: background 0.18s, border-color 0.18s; font-family:inherit;
        }
        .btn-secondary:hover { background:#F1F5F9; border-color:var(--border-strong); }

        /* Empty state */
        .empty-state { text-align:center; color:var(--text-muted); padding:50px 20px; }
        .empty-state svg { width:44px; height:44px; color:#CBD5E1; margin-bottom:10px; }
        .empty-state h4 { color:var(--text-heading); font-size:15px; margin:0 0 4px; }
        .empty-state p { font-size:12.5px; margin:0; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .kpi-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
        }
        @media (max-width: 1024px) {
            .admin-main-wrap { margin-left:0; width:100%; }
        }
        @media (max-width: 768px) {
            .admin-container { padding: calc(var(--header-height) + 12px) 14px 18px; }
            .toolbar { flex-direction: column; align-items: stretch; padding:14px 16px; }
            .filter-controls { width:100%; }
            .select-live-filter, .input-live-search { flex:1; min-width:0; }
            .table-scroll-wrap { max-height: calc(100vh - 480px); }
            .modal-overlay { padding:14px; }
            .modal-box { max-height: calc(100dvh - 28px); }
            .context-card { grid-template-columns:1fr; }
            .outcome-grid { grid-template-columns:1fr; }
        }
        @media (max-width: 640px) {
            .modal-overlay {
                padding:0; align-items:stretch; justify-content:stretch;
                background:#FFFFFF; -webkit-backdrop-filter:none; backdrop-filter:none;
            }
            .modal-box {
                max-width:100%; width:100%; max-height:100dvh; height:100dvh;
                border-radius:0; border:none; box-shadow:none;
                animation: modalSlideUp 0.3s cubic-bezier(0.32,0.72,0,1);
            }
            @keyframes modalSlideUp {
                from { opacity:0; transform: translateY(24px); }
                to   { opacity:1; transform: translateY(0); }
            }
            .modal-header { padding:16px; padding-top: calc(16px + env(safe-area-inset-top,0px)); }
            .modal-body { padding:18px 16px; -webkit-overflow-scrolling: touch; }
            .modal-footer {
                padding:12px 16px; padding-bottom: calc(12px + env(safe-area-inset-bottom,0px));
                gap:8px;
            }
            .modal-footer .btn-primary, .modal-footer .btn-secondary { flex:1; min-width:0; }
            .modal-form-group input, .modal-form-group select, .modal-form-group textarea { font-size:16px; }
            .modal-form-group input, .modal-form-group select { height:46px; }
        }
        @media (max-width: 480px) {
            :root { --header-height: 66px; }
            .admin-container { padding: calc(var(--header-height) + 10px) 10px 16px; }
            .toolbar-title h2 { font-size:17px; }
            .toolbar-title p { font-size:11.5px; }
            .kpi-grid { gap:8px; }
            .kpi-card { padding:14px; }
            .kpi-value { font-size:22px; }
            .toast-container { top: calc(var(--header-height) + 10px); right:10px; left:10px; max-width:none; }
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
                <div class="kpi-card compliance">
                    <div class="kpi-head">
                        <span class="kpi-label">Compliance Rate</span>
                        <span class="kpi-icon green">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value">
                        <?php echo number_format($compliance_rate, 1); ?><span class="kpi-unit">%</span>
                    </div>
                    <div class="kpi-sub">
                        <span class="dot" style="background:var(--success);"></span>
                        <?php echo number_format($stats['compliant']); ?> of
                        <?php echo number_format($stats['total']); ?> transactions on time
                    </div>
                </div>

                <div class="kpi-card breach">
                    <div class="kpi-head">
                        <span class="kpi-label">SLA Breaches</span>
                        <span class="kpi-icon red">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value">
                        <?php echo number_format($stats['breached']); ?>
                    </div>
                    <div class="kpi-sub">
                        <span class="dot" style="background:var(--danger);"></span>
                        <?php echo number_format($breach_rate, 1); ?>% breach rate ·
                        <?php echo $stats['critical']; ?> critical, <?php echo $stats['severe']; ?> severe
                    </div>
                </div>

                <div class="kpi-card unreviewed">
                    <div class="kpi-head">
                        <span class="kpi-label">Awaiting Review</span>
                        <span class="kpi-icon orange">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value"><?php echo number_format($stats['unreviewed']); ?></div>
                    <div class="kpi-sub">
                        <span class="dot" style="background:var(--warning);"></span>
                        Breaches without a documented review
                    </div>
                </div>

                <div class="kpi-card delay">
                    <div class="kpi-head">
                        <span class="kpi-label">Average Delay</span>
                        <span class="kpi-icon blue">
                            <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M3 12h4l3-7 4 14 3-7h4"/>
                            </svg>
                        </span>
                    </div>
                    <div class="kpi-value">
                        <?php echo number_format($stats['avg_delay'], 1); ?><span class="kpi-unit">d</span>
                    </div>
                    <div class="kpi-sub">
                        <span class="dot" style="background:var(--info);"></span>
                        Mean days over SLA across breaches only
                    </div>
                </div>
            </section>

            <!-- ============= TOOLBAR / FILTERS ============= -->
            <div class="fixed-header-deck">
                <section class="toolbar">
                    <div class="toolbar-title">
                        <h2>SLA Compliance Ledger</h2>
                        <p>Every ARTA-eligible transaction with severity, review status, and corrective action</p>
                    </div>

                    <form method="GET" action="admin_sla_tracker.php" class="filter-controls">
                        <select name="range" class="select-live-filter" onchange="this.form.submit()">
                            <option value="30d"  <?php echo $range === '30d'  ? 'selected' : ''; ?>>Last 30 days</option>
                            <option value="90d"  <?php echo $range === '90d'  ? 'selected' : ''; ?>>Last 90 days</option>
                            <option value="180d" <?php echo $range === '180d' ? 'selected' : ''; ?>>Last 6 months</option>
                            <option value="365d" <?php echo $range === '365d' ? 'selected' : ''; ?>>Last 12 months</option>
                            <option value="all"  <?php echo $range === 'all'  ? 'selected' : ''; ?>>All time</option>
                        </select>

                        <select name="office_id" class="select-live-filter" onchange="this.form.submit()">
                            <option value="0">All Offices</option>
                            <?php foreach ($filter_offices as $o): ?>
                                <option value="<?php echo (int)$o['id']; ?>" <?php echo $office_filter === (int)$o['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($o['office_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="severity" class="select-live-filter" onchange="this.form.submit()">
                            <option value="">All Severities</option>
                            <option value="compliant" <?php echo $severity_f === 'compliant' ? 'selected' : ''; ?>>Compliant only</option>
                            <option value="breached"  <?php echo $severity_f === 'breached'  ? 'selected' : ''; ?>>All breaches</option>
                            <option value="mild"      <?php echo $severity_f === 'mild'      ? 'selected' : ''; ?>>Mild (1–3d)</option>
                            <option value="moderate"  <?php echo $severity_f === 'moderate'  ? 'selected' : ''; ?>>Moderate (4–7d)</option>
                            <option value="severe"    <?php echo $severity_f === 'severe'    ? 'selected' : ''; ?>>Severe (8–14d)</option>
                            <option value="critical"  <?php echo $severity_f === 'critical'  ? 'selected' : ''; ?>>Critical (15d+)</option>
                        </select>

                        <select name="review" class="select-live-filter" onchange="this.form.submit()">
                            <option value="">All Review States</option>
                            <option value="unreviewed"     <?php echo $review_f === 'unreviewed'     ? 'selected' : ''; ?>>Unreviewed breaches</option>
                            <option value="reviewed"       <?php echo $review_f === 'reviewed'       ? 'selected' : ''; ?>>Any review recorded</option>
                            <option value="escalated"      <?php echo $review_f === 'escalated'      ? 'selected' : ''; ?>>Escalated</option>
                            <option value="acknowledged"   <?php echo $review_f === 'acknowledged'   ? 'selected' : ''; ?>>Acknowledged</option>
                            <option value="false_positive" <?php echo $review_f === 'false_positive' ? 'selected' : ''; ?>>False positive</option>
                        </select>
                    </form>
                </section>
            </div>

            <!-- ============= CHART ============= -->
            <section class="chart-card">
                <div class="chart-head">
                    <div>
                        <h3>Weekly Compliance Trend</h3>
                        <p>Share of tracked transactions meeting ARTA SLA, week over week</p>
                    </div>
                </div>
                <div class="chart-canvas-wrap">
                    <canvas id="complianceTrendChart"></canvas>
                </div>
            </section>

            <!-- ============= TABLE ============= -->
            <section class="panel-card">
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:110px;">Reference</th>
                                <th style="width:220px;">Office &amp; Service</th>
                                <th style="width:130px;">Client</th>
                                <th style="width:160px;">Actual / SLA</th>
                                <th style="width:110px;">Severity</th>
                                <th style="width:130px;">Review Status</th>
                                <th style="width:190px; text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($transactions)): ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <?php
                                        $delta      = (int)$tx['delta_days'];
                                        $actual     = (int)$tx['actual_days'];
                                        $sla        = (int)$tx['sla_days'];
                                        $sev        = severity_of($delta);
                                        $sev_label  = severity_label($sev);

                                        $rev_state = 'unreviewed';
                                        $rev_label = 'Unreviewed';
                                        $rev_class = 'rev-unreviewed';
                                        if (!empty($tx['review_outcome'])) {
                                            if ($tx['review_outcome'] === 'Escalated')      { $rev_state = 'escalated';      $rev_label = 'Escalated';      $rev_class = 'rev-escalated'; }
                                            elseif ($tx['review_outcome'] === 'Acknowledged'){ $rev_state = 'acknowledged';   $rev_label = 'Acknowledged';   $rev_class = 'rev-acknowledged'; }
                                            elseif ($tx['review_outcome'] === 'False Positive'){ $rev_state = 'false_positive'; $rev_label = 'False Positive'; $rev_class = 'rev-false-positive'; }
                                        }

                                        $delta_class = $delta <= 0 ? 'delta-ok' : ($delta <= SEV_MILD_MAX ? 'delta-mild' : 'delta-late');
                                        $delta_label = $delta <= 0
                                            ? ($delta === 0 ? 'On time' : abs($delta) . 'd early')
                                            : '+' . $delta . 'd late';
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="strong" style="font-family: ui-monospace, monospace;">
                                                #FB-<?php echo str_pad((string)$tx['feedback_id'], 4, '0', STR_PAD_LEFT); ?>
                                            </span>
                                            <small class="muted">
                                                <?php echo htmlspecialchars(date('M j, Y', strtotime($tx['submission_timestamp']))); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="strong"><?php echo htmlspecialchars($tx['office_name'] ?? '—'); ?></span>
                                            <small class="muted"><?php echo htmlspecialchars($tx['service_accessed']); ?></small>
                                        </td>
                                        <td>
                                            <span style="font-size:12px;"><?php echo htmlspecialchars($tx['user_name'] ?? 'Guest'); ?></span>
                                            <small class="muted">
                                                <?php echo htmlspecialchars($tx['client_type']); ?>
                                                <?php if ($tx['sex']): ?> · <?php echo htmlspecialchars($tx['sex']); ?><?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="strong"><?php echo $actual; ?> days</span>
                                            <small class="muted">Mandated: <?php echo $sla; ?> days</small>
                                            <span class="delta-chip <?php echo $delta_class; ?>" style="margin-top:4px;">
                                                <?php echo htmlspecialchars($delta_label); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="sev-badge sev-<?php echo $sev; ?>"><?php echo htmlspecialchars($sev_label); ?></span>
                                        </td>
                                        <td>
                                            <span class="rev-badge <?php echo $rev_class; ?>"><?php echo htmlspecialchars($rev_label); ?></span>
                                            <?php if (!empty($tx['reviewer_name'])): ?>
                                                <small class="muted">by <?php echo htmlspecialchars($tx['reviewer_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <div class="action-btns">
                                                <?php if ($delta > 0 && $rev_state === 'unreviewed'): ?>
                                                    <button class="btn-table-action btn-escalate"
                                                        onclick='openEscalateModal(<?php echo htmlspecialchars(json_encode([
                                                            "feedback_id" => (int)$tx["feedback_id"],
                                                            "office_id"   => (int)$tx["office_id"],
                                                            "office_name" => $tx["office_name"] ?? "",
                                                            "service"     => $tx["service_accessed"],
                                                            "actual_days" => $actual,
                                                            "sla_days"    => $sla,
                                                            "delta"       => $delta,
                                                            "severity"    => $sev_label,
                                                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8"); ?>)'>
                                                        Escalate
                                                    </button>
                                                    <button class="btn-table-action btn-review"
                                                        onclick='openReviewModal(<?php echo htmlspecialchars(json_encode([
                                                            "feedback_id" => (int)$tx["feedback_id"],
                                                            "office_name" => $tx["office_name"] ?? "",
                                                            "service"     => $tx["service_accessed"],
                                                            "actual_days" => $actual,
                                                            "sla_days"    => $sla,
                                                            "delta"       => $delta,
                                                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8"); ?>)'>
                                                        Review
                                                    </button>
                                                <?php elseif (!empty($tx['action_plan_id'])): ?>
                                                    <a href="admin_resolutions.php" class="btn-table-action btn-view">
                                                        View Ticket #<?php echo str_pad((string)$tx['action_plan_id'], 4, '0', STR_PAD_LEFT); ?>
                                                    </a>
                                                    <form method="POST" action="admin_sla_tracker.php" style="display:inline;"
                                                        onsubmit="return confirm('Remove the review record? The breach will return to Unreviewed.');">
                                                        <input type="hidden" name="action" value="undo_review">
                                                        <input type="hidden" name="review_id" value="<?php echo (int)$tx['review_id']; ?>">
                                                        <button type="submit" class="btn-table-action btn-undo">Undo</button>
                                                    </form>
                                                <?php elseif (!empty($tx['review_id'])): ?>
                                                    <span class="muted" style="font-size:11px;">Reviewed</span>
                                                    <form method="POST" action="admin_sla_tracker.php" style="display:inline;"
                                                        onsubmit="return confirm('Remove the review record? The breach will return to Unreviewed.');">
                                                        <input type="hidden" name="action" value="undo_review">
                                                        <input type="hidden" name="review_id" value="<?php echo (int)$tx['review_id']; ?>">
                                                        <button type="submit" class="btn-table-action btn-undo">Undo</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="muted" style="font-size:11px;">Compliant</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <h4>No SLA-eligible transactions</h4>
                                            <p>Feedback must have both a start date and matching service record to be tracked here.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

    <!-- ============================================================
         MODAL: Review / Escalate a breach
         ============================================================ -->
    <div class="modal-overlay" id="reviewModal">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle">

            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-header-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                    </div>
                    <div class="modal-header-text">
                        <h3 id="reviewModalTitle">Review SLA Breach</h3>
                        <p id="reviewModalSubtitle">Document a decision to satisfy ARTA's close-the-loop requirement</p>
                    </div>
                </div>
                <button type="button" class="btn-close-modal" onclick="closeModal('reviewModal')" aria-label="Close">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="admin_sla_tracker.php" class="modal-form" id="reviewForm">
                <input type="hidden" name="action" id="reviewFormAction" value="review_breach">
                <input type="hidden" name="feedback_id" id="reviewFeedbackId" value="">
                <input type="hidden" name="office_id" id="reviewOfficeId" value="">

                <div class="modal-body">

                    <!-- Context card -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">1</span>
                            <span>Breach Context</span>
                        </div>
                        <div class="context-card">
                            <div class="ctx-item">
                                <span class="ctx-label">Feedback Reference</span>
                                <span class="ctx-value" id="ctxRef">—</span>
                            </div>
                            <div class="ctx-item">
                                <span class="ctx-label">Severity</span>
                                <span class="ctx-value" id="ctxSeverity">—</span>
                            </div>
                            <div class="ctx-item">
                                <span class="ctx-label">Office</span>
                                <span class="ctx-value" id="ctxOffice">—</span>
                            </div>
                            <div class="ctx-item">
                                <span class="ctx-label">Service</span>
                                <span class="ctx-value" id="ctxService">—</span>
                            </div>
                            <div class="ctx-item">
                                <span class="ctx-label">Actual Duration</span>
                                <span class="ctx-value" id="ctxActual">—</span>
                            </div>
                            <div class="ctx-item">
                                <span class="ctx-label">Over SLA</span>
                                <span class="ctx-value big" id="ctxDelta">—</span>
                            </div>
                        </div>
                    </div>

                    <!-- Outcome -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">2</span>
                            <span>Review Outcome</span>
                        </div>

                        <div class="outcome-grid">
                            <label class="outcome-option">
                                <input type="radio" name="outcome" value="Escalated" checked onchange="updateOutcomeFields()">
                                <div class="outcome-option-inner">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
                                    <span class="oo-title">Escalate</span>
                                    <span class="oo-sub">Create an action plan ticket</span>
                                </div>
                            </label>

                            <label class="outcome-option">
                                <input type="radio" name="outcome" value="Acknowledged" onchange="updateOutcomeFields()">
                                <div class="outcome-option-inner">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                    <span class="oo-title">Acknowledge</span>
                                    <span class="oo-sub">Reviewed, no action needed</span>
                                </div>
                            </label>

                            <label class="outcome-option">
                                <input type="radio" name="outcome" value="False Positive" onchange="updateOutcomeFields()">
                                <div class="outcome-option-inner">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                    <span class="oo-title">False Positive</span>
                                    <span class="oo-sub">Data error or outlier</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Outcome-specific fields -->
                    <div class="form-section" id="escalateFields">
                        <div class="form-section-title">
                            <span class="section-num">3</span>
                            <span>Escalation Details</span>
                        </div>

                        <div class="modal-form-group">
                            <label>Assign To <span class="req">*</span></label>
                            <select name="assigned_staff_id" id="escalateAssignee">
                                <option value="">— Select a staff member —</option>
                                <?php foreach ($staff_list as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['user_id']); ?>">
                                        <?php echo htmlspecialchars($s['full_name']); ?> (<?php echo htmlspecialchars($s['user_id']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="modal-form-group">
                            <label>Root Cause Analysis <span class="req">*</span></label>
                            <textarea name="root_cause_analysis" id="escalateRootCause"
                                placeholder="Why did this transaction exceed the mandated processing time?"></textarea>
                            <small class="field-hint">This becomes the initial analysis on the action plan ticket.</small>
                        </div>

                        <div class="modal-form-group">
                            <label>Reviewer Notes</label>
                            <textarea name="notes" id="escalateNotes"
                                placeholder="Optional context for the audit trail (e.g. witnessed peak-season backlog)"></textarea>
                        </div>
                    </div>

                    <div class="form-section" id="reviewFields" style="display:none;">
                        <div class="form-section-title">
                            <span class="section-num">3</span>
                            <span>Reviewer Notes</span>
                        </div>

                        <div class="modal-form-group">
                            <label>Notes <span class="req">*</span></label>
                            <textarea name="notes" id="reviewNotes"
                                placeholder="Required. Explain why no escalation is warranted."></textarea>
                            <small class="field-hint">This is your documented justification for closing the breach without action.</small>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('reviewModal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="reviewSubmitBtn">Save Review</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const CHART_DATA = <?php echo json_encode($chart_payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

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
           MODAL OPEN
           ============================================================ */
        function openEscalateModal(tx) {
            document.getElementById('reviewModalTitle').textContent = 'Review SLA Breach';
            document.getElementById('reviewModalSubtitle').textContent = 'Document a decision to satisfy ARTA\'s close-the-loop requirement';
            document.getElementById('reviewFormAction').value = 'escalate_breach';
            document.getElementById('reviewFeedbackId').value = tx.feedback_id;
            document.getElementById('reviewOfficeId').value = tx.office_id;

            document.getElementById('ctxRef').textContent = '#FB-' + String(tx.feedback_id).padStart(4, '0');
            document.getElementById('ctxSeverity').textContent = tx.severity;
            document.getElementById('ctxOffice').textContent = tx.office_name;
            document.getElementById('ctxService').textContent = tx.service;
            document.getElementById('ctxActual').textContent = tx.actual_days + ' days';
            document.getElementById('ctxDelta').textContent = '+' + tx.delta + ' days over ' + tx.sla_days + '-day SLA';

            // Default outcome = escalate; prefill root cause template
            document.querySelector('input[name="outcome"][value="Escalated"]').checked = true;
            document.getElementById('escalateRootCause').value =
                'ARTA SLA breach detected: transaction took ' + tx.actual_days +
                ' days against a mandated ' + tx.sla_days + ' days (' + tx.delta + ' days over). ';
            document.getElementById('escalateAssignee').value = '';
            document.getElementById('escalateNotes').value = '';
            document.getElementById('reviewNotes').value = '';

            updateOutcomeFields();
            document.getElementById('reviewModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function openReviewModal(tx) {
            openEscalateModal(tx);
            // Override: default to Acknowledge
            document.querySelector('input[name="outcome"][value="Acknowledged"]').checked = true;
            updateOutcomeFields();
        }

        function updateOutcomeFields() {
            const outcome = document.querySelector('input[name="outcome"]:checked').value;
            const escalateFields = document.getElementById('escalateFields');
            const reviewFields   = document.getElementById('reviewFields');
            const submitBtn      = document.getElementById('reviewSubmitBtn');
            const actionInput    = document.getElementById('reviewFormAction');

            // Disable the hidden set so PHP doesn't see both note fields with same name
            const escNotes = document.getElementById('escalateNotes');
            const revNotes = document.getElementById('reviewNotes');
            const rcField  = document.getElementById('escalateRootCause');
            const asField  = document.getElementById('escalateAssignee');

            if (outcome === 'Escalated') {
                escalateFields.style.display = '';
                reviewFields.style.display = 'none';
                submitBtn.textContent = 'Escalate Breach';
                actionInput.value = 'escalate_breach';
                rcField.disabled = false;
                asField.disabled = false;
                escNotes.disabled = false;
                revNotes.disabled = true;
                rcField.required = true;
                asField.required = true;
                revNotes.required = false;
            } else {
                escalateFields.style.display = 'none';
                reviewFields.style.display = '';
                submitBtn.textContent = 'Save Review';
                actionInput.value = 'review_breach';
                rcField.disabled = true;
                asField.disabled = true;
                escNotes.disabled = true;
                revNotes.disabled = false;
                rcField.required = false;
                asField.required = false;
                revNotes.required = true;
            }
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
                const modal = document.getElementById('reviewModal');
                if (modal.classList.contains('active')) closeModal('reviewModal');
            }
        });

        /* ============================================================
           CHART: Weekly compliance trend
           ============================================================ */
        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
        Chart.defaults.font.size = 11;
        Chart.defaults.color = '#64748B';

        (function() {
            const el = document.getElementById('complianceTrendChart');
            if (!el) return;

            const labels = CHART_DATA.trend.labels.length ? CHART_DATA.trend.labels : ['No data'];
            const comp   = CHART_DATA.trend.compliance.length ? CHART_DATA.trend.compliance : [0];
            const brch   = CHART_DATA.trend.breaches.length ? CHART_DATA.trend.breaches : [0];

            new Chart(el, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Compliance %',
                            data: comp,
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.08)',
                            borderWidth: 2.5,
                            tension: 0.35,
                            fill: true,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#10B981',
                            pointBorderColor: '#FFFFFF',
                            pointBorderWidth: 2,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Breaches',
                            data: brch,
                            type: 'bar',
                            backgroundColor: 'rgba(239, 68, 68, 0.18)',
                            borderColor: 'rgba(239, 68, 68, 0.4)',
                            borderWidth: 1,
                            borderRadius: 4,
                            maxBarThickness: 20,
                            yAxisID: 'y1',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top', align: 'end',
                            labels: {
                                boxWidth: 10, boxHeight: 10, usePointStyle: true,
                                pointStyle: 'circle', padding: 14,
                                font: { weight: '600', size: 11 },
                            },
                        },
                        tooltip: {
                            backgroundColor: '#0F172A',
                            titleColor: '#FFFFFF',
                            bodyColor: '#E2E8F0',
                            padding: 10, cornerRadius: 8,
                            titleFont: { weight: '700', size: 12 },
                            bodyFont: { size: 12 },
                        },
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
                        y: {
                            position: 'left',
                            beginAtZero: true, max: 100,
                            grid: { color: '#F1F5F9', drawTicks: false },
                            ticks: { callback: v => v + '%', padding: 8 },
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            grid: { display: false },
                            ticks: { precision: 0, padding: 8 },
                        },
                    },
                },
            });
        })();
    </script>
</body>
</html>