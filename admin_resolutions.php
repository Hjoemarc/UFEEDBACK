<?php
// admin_resolutions.php
// "Close the Loop" module — convert poor ARTA feedback into tracked action-plan tickets.

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

// ---------------------------------------------------------------------------
// NEGATIVE FEEDBACK CRITERIA
// Any SQD score of 1–2, or CC1=4 / CC2 in (3,4) / CC3=3 flags feedback for review.
// ---------------------------------------------------------------------------
$NEGATIVE_CRITERIA = "(
    f.sqd1_responsiveness IN (1,2) OR
    f.sqd2_reliability IN (1,2) OR
    f.sqd3_access_facilities IN (1,2) OR
    f.sqd4_communication IN (1,2) OR
    f.sqd5_costs IN (1,2) OR
    f.sqd6_integrity IN (1,2) OR
    f.sqd7_assurance IN (1,2) OR
    f.sqd8_outcome IN (1,2) OR
    f.cc1 = 4 OR
    f.cc2 IN (3,4) OR
    f.cc3 = 3
)";

// ---------------------------------------------------------------------------
// POST ACTIONS
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_action_plan') {
        $feedback_id         = (int)($_POST['feedback_id'] ?? 0);
        $office_id           = (int)($_POST['office_id'] ?? 0);
        $assigned_staff_id   = trim($_POST['assigned_staff_id'] ?? '');
        $root_cause_analysis = trim($_POST['root_cause_analysis'] ?? '');
        $action_taken        = trim($_POST['action_taken'] ?? '');
        $status              = $_POST['status'] ?? 'Pending';

        if (!in_array($status, ['Pending','In Progress','Resolved'])) $status = 'Pending';
        if ($assigned_staff_id === '') $assigned_staff_id = null;

        if ($feedback_id <= 0 || $office_id <= 0 || $root_cause_analysis === '') {
            $toast_message = "Feedback reference, office, and root-cause analysis are required.";
            $toast_type = 'error';
        } else {
            $chk = $conn->prepare("SELECT id FROM action_plans WHERE feedback_id = ? LIMIT 1");
            $chk->bind_param("i", $feedback_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $toast_message = "An action plan already exists for this feedback.";
                $toast_type = 'error';
            } else {
                $resolved_at = ($status === 'Resolved') ? date('Y-m-d H:i:s') : null;

                $stmt = $conn->prepare("
                    INSERT INTO action_plans
                    (feedback_id, office_id, assigned_staff_id, root_cause_analysis, action_taken, status, resolved_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iisssss",
                    $feedback_id, $office_id, $assigned_staff_id,
                    $root_cause_analysis, $action_taken, $status, $resolved_at
                );

                if ($stmt->execute()) {
                    $toast_message = "Action plan ticket #" . str_pad((string)$stmt->insert_id, 4, '0', STR_PAD_LEFT) . " created.";
                    $toast_type = 'success';
                } else {
                    $toast_message = "Database error: " . $conn->error;
                    $toast_type = 'error';
                }
                $stmt->close();
            }
            $chk->close();
        }
    }

    elseif ($action === 'update_action_plan') {
        $id                  = (int)($_POST['id'] ?? 0);
        $assigned_staff_id   = trim($_POST['assigned_staff_id'] ?? '');
        $root_cause_analysis = trim($_POST['root_cause_analysis'] ?? '');
        $action_taken        = trim($_POST['action_taken'] ?? '');
        $status              = $_POST['status'] ?? 'Pending';

        if (!in_array($status, ['Pending','In Progress','Resolved'])) $status = 'Pending';
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

    elseif ($action === 'delete_action_plan') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $del = $conn->prepare("DELETE FROM action_plans WHERE id = ?");
            $del->bind_param("i", $id);
            if ($del->execute()) {
                $toast_message = "Ticket #" . str_pad((string)$id, 4, '0', STR_PAD_LEFT) . " deleted. Feedback returns to Pending Review.";
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
// HELPER: extract "negative signals" from a feedback row
// ---------------------------------------------------------------------------
function get_negative_signals(array $f): array {
    $labels = [
        'sqd1_responsiveness' => 'Responsiveness',
        'sqd2_reliability' => 'Reliability',
        'sqd3_access_facilities' => 'Access & Facilities',
        'sqd4_communication' => 'Communication',
        'sqd5_costs' => 'Costs',
        'sqd6_integrity' => 'Integrity',
        'sqd7_assurance' => 'Assurance',
        'sqd8_outcome' => 'Outcome',
    ];
    $out = [];
    foreach ($labels as $field => $short) {
        $v = (int)($f[$field] ?? 0);
        if ($v >= 1 && $v <= 2) {
            $out[] = ['label' => $short, 'value' => $v, 'kind' => $v === 1 ? 'poor' : 'warn'];
        }
    }
    if ((int)($f['cc1'] ?? 0) === 4)   $out[] = ['label' => 'CC Not Known',    'value' => null, 'kind' => 'warn'];
    if (in_array((int)($f['cc2'] ?? 0), [3,4], true)) $out[] = ['label' => 'CC Not Visible', 'value' => null, 'kind' => 'warn'];
    if ((int)($f['cc3'] ?? 0) === 3)   $out[] = ['label' => 'CC Did Not Help','value' => null, 'kind' => 'warn'];
    return $out;
}

// ---------------------------------------------------------------------------
// STATISTICS
// ---------------------------------------------------------------------------
$stats = [
    'unticketed'    => 0,
    'pending'       => 0,
    'in_progress'   => 0,
    'resolved'      => 0,
    'total_flagged' => 0,
];

$r = $conn->query("SELECT COUNT(*) AS c FROM arta_feedback f WHERE f.is_archived = 0 AND $NEGATIVE_CRITERIA");
if ($r) $stats['total_flagged'] = (int)$r->fetch_assoc()['c'];

$r = $conn->query("
    SELECT COUNT(*) AS c FROM arta_feedback f
    WHERE f.is_archived = 0 AND $NEGATIVE_CRITERIA
      AND NOT EXISTS (SELECT 1 FROM action_plans ap WHERE ap.feedback_id = f.feedback_id)
");
if ($r) $stats['unticketed'] = (int)$r->fetch_assoc()['c'];

foreach (['Pending' => 'pending', 'In Progress' => 'in_progress', 'Resolved' => 'resolved'] as $dbv => $key) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM action_plans WHERE status = ?");
    $stmt->bind_param("s", $dbv);
    $stmt->execute();
    $stats[$key] = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
}

// ---------------------------------------------------------------------------
// FETCH: Action Plans (tickets)
// ---------------------------------------------------------------------------
$action_plans = [];
$ap_res = $conn->query("
    SELECT
        ap.id, ap.feedback_id, ap.office_id, ap.assigned_staff_id,
        ap.root_cause_analysis, ap.action_taken, ap.status,
        ap.created_at, ap.resolved_at,
        f.service_accessed, f.submission_timestamp, f.client_type,
        f.sqd1_responsiveness, f.sqd2_reliability, f.sqd3_access_facilities,
        f.sqd4_communication, f.sqd5_costs, f.sqd6_integrity,
        f.sqd7_assurance, f.sqd8_outcome, f.cc1, f.cc2, f.cc3,
        f.comments_recommendations,
        o.office_name, o.office_type,
        u.full_name AS staff_name
    FROM action_plans ap
    LEFT JOIN arta_feedback f ON ap.feedback_id = f.feedback_id
    LEFT JOIN offices o       ON ap.office_id   = o.id
    LEFT JOIN users u         ON ap.assigned_staff_id = u.user_id
    ORDER BY
        CASE ap.status
            WHEN 'Pending'     THEN 1
            WHEN 'In Progress' THEN 2
            ELSE 3
        END,
        ap.created_at DESC
");
if ($ap_res instanceof mysqli_result) {
    while ($row = $ap_res->fetch_assoc()) $action_plans[] = $row;
}

// ---------------------------------------------------------------------------
// FETCH: Unticketed negative feedback
// ---------------------------------------------------------------------------
$unticketed = [];
$ut_res = $conn->query("
    SELECT
        f.feedback_id, f.office_id, f.service_accessed, f.transaction_date,
        f.client_type, f.age_group, f.sex, f.region,
        f.sqd1_responsiveness, f.sqd2_reliability, f.sqd3_access_facilities,
        f.sqd4_communication, f.sqd5_costs, f.sqd6_integrity,
        f.sqd7_assurance, f.sqd8_outcome, f.cc1, f.cc2, f.cc3,
        f.comments_recommendations, f.user_id, f.submission_timestamp,
        o.office_name, o.office_type,
        u.full_name AS user_name
    FROM arta_feedback f
    LEFT JOIN offices o ON f.office_id = o.id
    LEFT JOIN users u   ON f.user_id   = u.user_id
    WHERE f.is_archived = 0 AND $NEGATIVE_CRITERIA
      AND NOT EXISTS (SELECT 1 FROM action_plans ap WHERE ap.feedback_id = f.feedback_id)
    ORDER BY f.submission_timestamp DESC
");
if ($ut_res instanceof mysqli_result) {
    while ($row = $ut_res->fetch_assoc()) $unticketed[] = $row;
}

// ---------------------------------------------------------------------------
// FETCH: Staff list for assignment dropdown
// ---------------------------------------------------------------------------
$staff_list = [];
$s_res = $conn->query("
    SELECT user_id, full_name, internal_user_type
    FROM users
    WHERE internal_user_type IN ('zppsu_employee','admin')
    ORDER BY full_name ASC
");
if ($s_res instanceof mysqli_result) {
    while ($row = $s_res->fetch_assoc()) $staff_list[] = $row;
}

// Page metadata for admin_header.php
$page_title    = 'Feedback Resolutions';
$page_crumb    = 'Administration / Resolutions';
$page_subtitle = 'Convert poor ARTA ratings into tracked corrective actions';
$unread_notifications = 5;
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
            --maroon: #7A0000;
            --maroon-dark: #580000;
            --gold: #F2A900;
            --bg: #F8FAFC;
            --surface: #FFFFFF;
            --border: #E2E8F0;
            --border-strong: #CBD5E1;
            --text-heading: #0F172A;
            --text-body: #475569;
            --text-muted: #94A3B8;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;

            --sidebar-width: 272px;
            --header-height: 72px;
        }

        html, body { width: 100%; height: 100%; margin: 0; padding: 0; overflow-x: hidden; }
        * { box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; -webkit-text-size-adjust: 100%; }
        body { background-color: var(--bg); color: var(--text-body); display: flex; min-height: 100vh; }

        .admin-main-wrap {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            min-width: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
            transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                        width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .admin-container {
            width: 100%;
            max-width: 100%;
            padding: calc(var(--header-height) + 20px) 24px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .fixed-header-deck {
            position: sticky;
            top: var(--header-height);
            z-index: 800;
            background: var(--bg);
            padding-top: 4px;
            padding-bottom: 12px;
        }

        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            min-width: 0;
        }
        .stat-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            border: 1px solid;
        }
        .stat-icon svg { width: 20px; height: 20px; }
        .stat-icon.red    { background:#FEF2F2; border-color:#FECACA; color:#B91C1C; }
        .stat-icon.orange { background:#FFF7ED; border-color:#FED7AA; color:#C2410C; }
        .stat-icon.blue   { background:#EFF6FF; border-color:#BFDBFE; color:#1D4ED8; }
        .stat-icon.green  { background:#ECFDF5; border-color:#A7F3D0; color:#047857; }

        .stat-body { min-width: 0; }
        .stat-value {
            font-size: 22px; font-weight: 800; color: var(--text-heading);
            line-height: 1; letter-spacing: -0.5px;
        }
        .stat-label {
            font-size: 11px; font-weight: 700; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.6px; margin-top: 4px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* ===== TOOLBAR ===== */
        .toolbar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .toolbar-title h2 {
            font-size: 19px; font-weight: 800; color: var(--text-heading); margin: 0;
        }
        .toolbar-title p {
            font-size: 12px; color: var(--text-muted); margin: 2px 0 0;
        }

        .btn-primary {
            background: var(--maroon); color: #FFFFFF; border: none;
            padding: 10px 16px; border-radius: 8px; font-size: 12.5px;
            font-weight: 700; cursor: pointer; display: inline-flex;
            align-items: center; gap: 6px; white-space: nowrap;
            transition: background 0.18s, transform 0.12s; font-family: inherit;
        }
        .btn-primary:hover { background: var(--maroon-dark); }
        .btn-primary:active { transform: scale(0.98); }
        .btn-primary:disabled { opacity: .55; cursor: not-allowed; }

        .btn-secondary {
            background: #FFFFFF; color: var(--text-heading);
            border: 1.5px solid var(--border); padding: 10px 16px;
            border-radius: 8px; font-size: 12.5px; font-weight: 700;
            cursor: pointer; transition: background 0.18s, border-color 0.18s;
            font-family: inherit;
        }
        .btn-secondary:hover { background: #F1F5F9; border-color: var(--border-strong); }

        /* ===== TABS ===== */
        .tab-nav {
            display: flex;
            gap: 4px;
            padding: 4px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 10px;
            overflow-x: auto;
            scrollbar-width: none;
        }
        .tab-nav::-webkit-scrollbar { display: none; }
        .tab-btn {
            flex: 1;
            min-width: 180px;
            padding: 10px 14px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
            transition: all 0.18s;
        }
        .tab-btn:hover { background: #F1F5F9; color: var(--text-heading); }
        .tab-btn.active { background: var(--maroon); color: #FFFFFF; box-shadow: 0 2px 6px rgba(122,0,0,0.18); }
        .tab-count {
            background: #F1F5F9;
            color: var(--text-muted);
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 10px;
            letter-spacing: 0.2px;
        }
        .tab-btn.active .tab-count { background: rgba(255,255,255,0.22); color: #FFFFFF; }
        .tab-btn.alert:not(.active) { color: var(--danger); }
        .tab-btn.alert:not(.active) .tab-count { background: #FEE2E2; color: var(--danger); }

        /* ===== FILTER PANEL ===== */
        .filter-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .filter-controls { display: flex; align-items: center; gap: 10px; flex: 1; flex-wrap: wrap; }
        .input-live-search, .select-live-filter {
            padding: 9px 12px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 12.5px;
            background: #F8FAFC;
            color: var(--text-heading);
            outline: none;
            transition: border-color 0.18s, background 0.18s;
            font-family: inherit;
        }
        .input-live-search { min-width: 250px; flex: 1; }
        .input-live-search:focus, .select-live-filter:focus { border-color: var(--maroon); background: #FFFFFF; }

        /* ===== PANEL / TABLE ===== */
        .panel-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .panel-hidden { display: none !important; }

        .table-scroll-wrap {
            flex: 1;
            min-height: 400px;
            max-height: calc(100vh - 420px);
            overflow-y: auto;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            -webkit-overflow-scrolling: touch;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 900px; }
        .table-scroll-wrap thead th {
            position: sticky; top: 0;
            background-color: #F1F5F9; z-index: 10;
            box-shadow: 0 1px 0 var(--border);
        }
        th {
            background-color: #F1F5F9; color: var(--text-muted); font-weight: 700;
            padding: 11px 14px; border-bottom: 1px solid var(--border);
            text-transform: uppercase; font-size: 10.5px; text-align: left; letter-spacing: 0.4px;
        }
        td { padding: 12px 14px; border-bottom: 1px solid var(--border); color: var(--text-body); vertical-align: top; }

        .muted { color: var(--text-muted); font-size: 11px; display: block; margin-top: 2px; }
        .strong { font-weight: 700; color: var(--text-heading); }

        .ticket-id {
            display: inline-flex; align-items: center; gap: 6px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 11.5px; font-weight: 800;
            background: #F1F5F9; color: var(--text-heading);
            padding: 3px 8px; border-radius: 6px; border: 1px solid var(--border);
            letter-spacing: 0.3px;
        }

        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 10.5px; font-weight: 800;
            padding: 4px 9px; border-radius: 5px;
            text-transform: uppercase; letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .status-badge::before {
            content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor;
        }
        .status-pending  { background: #FFF7ED; color: #C2410C; border: 1px solid #FED7AA; }
        .status-progress { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
        .status-resolved { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }

        .signal-pill {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 10.5px; font-weight: 700;
            padding: 2px 7px; border-radius: 4px;
            margin: 0 3px 3px 0; white-space: nowrap;
        }
        .signal-pill.poor { background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA; }
        .signal-pill.warn { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
        .signal-pill.neutral { background: #F1F5F9; color: var(--text-muted); border: 1px solid var(--border); }

        .action-btns { display: flex; align-items: center; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
        .btn-table-action {
            padding: 6px 12px; border-radius: 6px; font-size: 11.5px;
            font-weight: 600; cursor: pointer; border: 1px solid transparent;
            transition: filter 0.18s; font-family: inherit;
        }
        .btn-table-action:hover { filter: brightness(0.95); }
        .btn-edit   { background: #EFF6FF; color: #2563EB; border-color: #BFDBFE; }
        .btn-delete { background: #FEF2F2; color: var(--danger); border-color: #FECACA; }
        .btn-create { background: var(--maroon); color: #FFFFFF; border-color: var(--maroon); }
        .btn-create:hover { background: var(--maroon-dark); filter: none; }

        .empty-state {
            text-align: center; color: var(--text-muted); padding: 50px 20px;
        }
        .empty-state svg { width: 44px; height: 44px; color: #CBD5E1; margin-bottom: 10px; }
        .empty-state h4 { color: var(--text-heading); font-size: 15px; margin: 0 0 4px; }
        .empty-state p { font-size: 12.5px; margin: 0; }

        /* ===== TOAST ===== */
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
            justify-content: space-between; gap: 10px;
            animation: toastSlideIn 0.3s forwards;
        }
        .toast-error { border-left-color: var(--danger); }
        .toast-close { background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 18px; line-height: 1; }
        @keyframes toastSlideIn { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }

        /* ===== MODAL ===== */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(15, 23, 42, 0.68);
            -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px);
            z-index: 1050; display: none;
            align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.active { display: flex; }

        .modal-box {
            background: var(--surface);
            border-radius: 18px;
            width: 100%; max-width: 680px;
            max-height: calc(100vh - 40px);
            max-height: calc(100dvh - 40px);
            border: 1px solid var(--border);
            box-shadow: 0 30px 60px -12px rgba(15, 23, 42, 0.35);
            display: flex; flex-direction: column; overflow: hidden;
            animation: modalIn 0.28s cubic-bezier(0.34, 1.3, 0.64, 1);
        }
        @keyframes modalIn { from { opacity: 0; transform: translateY(14px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }

        .modal-header {
            flex-shrink: 0; padding: 18px 22px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: flex-start; justify-content: space-between; gap: 14px;
            background: linear-gradient(180deg, #FFFFFF 0%, #FCFCFD 100%);
        }
        .modal-header-left { display: flex; align-items: flex-start; gap: 13px; min-width: 0; flex: 1; }
        .modal-header-icon {
            width: 42px; height: 42px; border-radius: 11px;
            background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
            color: var(--maroon); display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; border: 1px solid #FECACA;
        }
        .modal-header-icon svg { width: 20px; height: 20px; }
        .modal-header-text { min-width: 0; padding-top: 1px; }
        .modal-header-text h3 { font-size: 16.5px; font-weight: 800; color: var(--text-heading); margin: 0; line-height: 1.2; letter-spacing: -0.3px; }
        .modal-header-text p { font-size: 12px; color: var(--text-muted); margin: 3px 0 0; line-height: 1.4; }

        .btn-close-modal {
            width: 34px; height: 34px; flex-shrink: 0; border-radius: 9px;
            background: transparent; border: 1px solid transparent; color: var(--text-muted);
            cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
            transition: background 0.18s, color 0.18s, border-color 0.18s; padding: 0;
        }
        .btn-close-modal:hover { background: #F1F5F9; color: var(--text-heading); border-color: var(--border); }
        .btn-close-modal svg { width: 18px; height: 18px; }

        .modal-form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; overflow: hidden; }
        .modal-body {
            flex: 1 1 auto; min-height: 0; overflow-y: auto; overflow-x: hidden;
            padding: 22px; -webkit-overflow-scrolling: touch; overscroll-behavior: contain;
        }
        .form-section { margin-bottom: 22px; }
        .form-section:last-child { margin-bottom: 0; }
        .form-section-title {
            display: flex; align-items: center; gap: 10px;
            font-size: 11px; font-weight: 800; letter-spacing: 1.1px;
            text-transform: uppercase; color: var(--maroon);
            margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px dashed #FECACA;
        }
        .section-num {
            width: 22px; height: 22px; border-radius: 50%;
            background: var(--maroon); color: #FFFFFF;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 10.5px; font-weight: 800; flex-shrink: 0;
        }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .modal-form-group { margin-bottom: 0; min-width: 0; }
        .modal-form-group.full { grid-column: 1 / -1; }

        .modal-form-group label {
            display: flex; align-items: center; gap: 5px;
            font-size: 12px; font-weight: 700; color: var(--text-heading);
            margin-bottom: 6px; letter-spacing: -0.1px;
        }
        .modal-form-group label .req { color: var(--danger); }

        .modal-form-group input,
        .modal-form-group select,
        .modal-form-group textarea {
            width: 100%; padding: 10px 13px;
            border: 1.5px solid var(--border); border-radius: 9px;
            font-size: 13px; outline: none; background: #F8FAFC;
            color: var(--text-heading); font-family: inherit;
            transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
        }
        .modal-form-group input,
        .modal-form-group select { height: 42px; }
        .modal-form-group textarea {
            min-height: 92px; resize: vertical; line-height: 1.5;
        }
        .modal-form-group input:focus,
        .modal-form-group select:focus,
        .modal-form-group textarea:focus {
            border-color: var(--maroon); background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(122, 0, 0, 0.08);
        }
        .modal-form-group input[readonly],
        .modal-form-group textarea[readonly] {
            background: #F1F5F9; color: var(--text-muted); cursor: not-allowed;
        }

        .readonly-card {
            background: #FAFBFC;
            border: 1.5px dashed #CBD5E1;
            border-radius: 9px;
            padding: 10px 13px;
            font-size: 12.5px;
            color: var(--text-heading);
            line-height: 1.45;
        }
        .readonly-card .label {
            font-size: 10.5px; font-weight: 800; letter-spacing: 0.6px;
            text-transform: uppercase; color: var(--text-muted);
            display: block; margin-bottom: 3px;
        }

        .field-hint {
            display: block; font-size: 11px; color: var(--text-muted);
            margin-top: 5px; line-height: 1.4;
        }

        .modal-footer {
            flex-shrink: 0; padding: 14px 22px;
            background: #FAFBFC; border-top: 1px solid var(--border);
            display: flex; justify-content: flex-end; gap: 10px;
        }
        .modal-footer .btn-primary,
        .modal-footer .btn-secondary { min-width: 110px; justify-content: center; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .admin-main-wrap { margin-left: 0; width: 100%; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 768px) {
            .admin-container { padding: calc(var(--header-height) + 12px) 14px 18px; }
            .toolbar { flex-direction: column; align-items: stretch; padding: 14px 16px; }
            .toolbar-actions .btn-primary { width: 100%; justify-content: center; }
            .input-live-search, .select-live-filter { width: 100%; min-width: 0; }
            .table-scroll-wrap { max-height: calc(100vh - 460px); }
            #searchCountBadge { display: none !important; }
            .modal-overlay { padding: 14px; }
            .modal-box { max-height: calc(100dvh - 28px); }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; gap: 8px; border-radius: 10px; }
            .stat-icon { width: 34px; height: 34px; }
            .stat-value { font-size: 18px; }
            .stat-label { font-size: 10px; }

            .modal-overlay {
                padding: 0; align-items: stretch; justify-content: stretch;
                background: #FFFFFF;
                -webkit-backdrop-filter: none; backdrop-filter: none;
            }
            .modal-box {
                max-width: 100%; width: 100%; max-height: 100dvh; height: 100dvh;
                border-radius: 0; border: none; box-shadow: none;
                animation: modalSlideUp 0.3s cubic-bezier(0.32, 0.72, 0, 1);
            }
            @keyframes modalSlideUp {
                from { opacity: 0; transform: translateY(24px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            .modal-header { padding: 16px; padding-top: calc(16px + env(safe-area-inset-top, 0px)); }
            .modal-body { padding: 18px 16px; -webkit-overflow-scrolling: touch; }
            .modal-footer {
                padding: 12px 16px;
                padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
                gap: 8px;
            }
            .modal-footer .btn-primary,
            .modal-footer .btn-secondary { flex: 1; min-width: 0; }
            .form-grid { grid-template-columns: 1fr; gap: 12px; }
            .form-section { margin-bottom: 18px; }
            .modal-form-group input,
            .modal-form-group select,
            .modal-form-group textarea { font-size: 16px; }
            .modal-form-group input,
            .modal-form-group select { height: 46px; }
        }
        @media (max-width: 480px) {
            :root { --header-height: 66px; }
            .admin-container { padding: calc(var(--header-height) + 10px) 10px 16px; }
            .toolbar-title h2 { font-size: 17px; }
            .toolbar-title p { font-size: 11.5px; }
            .filter-panel { padding: 10px; }
            .toast-container { top: calc(var(--header-height) + 10px); right: 10px; left: 10px; max-width: none; }
            .toast { font-size: 12.5px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .admin-main-wrap, .toast, .modal-box { transition: none !important; animation: none !important; }
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

            <!-- ============================================================
                 STATS
                 ============================================================ -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon red">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value"><?php echo number_format($stats['unticketed']); ?></div>
                        <div class="stat-label">Awaiting Review</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
                        <div class="stat-label">Pending Tickets</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value"><?php echo number_format($stats['in_progress']); ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value"><?php echo number_format($stats['resolved']); ?></div>
                        <div class="stat-label">Resolved</div>
                    </div>
                </div>
            </section>

            <div class="fixed-header-deck">
                <section class="toolbar">
                    <div class="toolbar-title">
                        <h2>Feedback Resolutions</h2>
                        <p>Close the loop on negative ARTA ratings with documented corrective action</p>
                    </div>
                    <div class="toolbar-actions">
                        <button class="btn-primary" onclick="switchTab('plans')">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                            View All Tickets
                        </button>
                    </div>
                </section>

                <nav class="tab-nav" id="tabNav">
                    <button class="tab-btn alert active" data-tab="pending" onclick="switchTab('pending')">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.062 20h13.876c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.33 17c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Pending Review
                        <span class="tab-count"><?php echo (int)$stats['unticketed']; ?></span>
                    </button>
                    <button class="tab-btn" data-tab="plans" onclick="switchTab('plans')">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Action Plans
                        <span class="tab-count"><?php echo count($action_plans); ?></span>
                    </button>
                </nav>

                <section class="filter-panel" id="planFilters" style="display:none;">
                    <div class="filter-controls">
                        <input type="text" id="planSearch" class="input-live-search" placeholder="Search ticket, office, service, or assignee..." autocomplete="off">
                        <select id="planStatusFilter" class="select-live-filter">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="in progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>
                    <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 600;" id="planCountBadge">
                        Showing <?php echo count($action_plans); ?> ticket(s)
                    </div>
                </section>
            </div>

            <!-- ============================================================
                 TAB 1: PENDING REVIEW
                 ============================================================ -->
            <section class="panel-card" id="tabPanel-pending">
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 130px;">Reference</th>
                                <th style="width: 240px;">Office &amp; Service</th>
                                <th style="width: 200px;">Negative Signals</th>
                                <th>Client Context</th>
                                <th style="width: 170px; text-align: right;">Resolution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($unticketed)): ?>
                                <?php foreach ($unticketed as $fb): ?>
                                    <?php $signals = get_negative_signals($fb); ?>
                                    <tr>
                                        <td>
                                            <span class="ticket-id">#<?php echo (int)$fb['feedback_id']; ?></span>
                                            <small class="muted">
                                                <?php echo htmlspecialchars(date('M j, Y', strtotime($fb['submission_timestamp']))); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="strong"><?php echo htmlspecialchars($fb['office_name'] ?? '— Unknown —'); ?></span>
                                            <small class="muted">
                                                <?php echo htmlspecialchars($fb['service_accessed']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if (!empty($signals)): ?>
                                                <?php foreach ($signals as $s): ?>
                                                    <span class="signal-pill <?php echo $s['kind']; ?>">
                                                        <?php echo htmlspecialchars($s['label']); ?>
                                                        <?php if ($s['value'] !== null): ?>· <?php echo (int)$s['value']; ?>/5<?php endif; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="signal-pill neutral">No signal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="strong" style="font-size:12px;">
                                                <?php echo htmlspecialchars($fb['user_name'] ?? 'Anonymous Guest'); ?>
                                            </span>
                                            <small class="muted">
                                                <?php echo htmlspecialchars($fb['client_type'] . ' · ' . ($fb['sex'] ?: '—') . ' · ' . ($fb['age_group'] ?: '—')); ?>
                                            </small>
                                            <?php if (!empty($fb['comments_recommendations'])): ?>
                                                <small class="muted" style="font-style:italic; margin-top:4px;">
                                                    "<?php echo htmlspecialchars(mb_strimwidth($fb['comments_recommendations'], 0, 80, '…')); ?>"
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <button class="btn-table-action btn-create"
                                                onclick='openCreateTicketModal(<?php echo htmlspecialchars(json_encode($fb, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                + Create Ticket
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <h4>All clear</h4>
                                            <p>Every flagged negative feedback has an active action plan.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ============================================================
                 TAB 2: ACTION PLANS
                 ============================================================ -->
            <section class="panel-card panel-hidden" id="tabPanel-plans">
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 130px;">Ticket</th>
                                <th style="width: 220px;">Office &amp; Feedback</th>
                                <th style="width: 200px;">Assigned To</th>
                                <th>Root Cause / Action Taken</th>
                                <th style="width: 130px;">Status</th>
                                <th style="width: 130px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="plansTableBody">
                            <?php if (!empty($action_plans)): ?>
                                <?php foreach ($action_plans as $ap): ?>
                                    <?php
                                        $status_key = strtolower($ap['status']);
                                        $status_class = 'status-pending';
                                        if ($ap['status'] === 'In Progress') $status_class = 'status-progress';
                                        elseif ($ap['status'] === 'Resolved') $status_class = 'status-resolved';
                                    ?>
                                    <tr class="plan-row"
                                        data-search="<?php echo strtolower(htmlspecialchars(
                                            '#' . $ap['id'] . ' ' . $ap['office_name'] . ' ' . $ap['service_accessed'] . ' ' .
                                            ($ap['staff_name'] ?? '') . ' ' . ($ap['root_cause_analysis'] ?? '') . ' ' . ($ap['action_taken'] ?? '')
                                        )); ?>"
                                        data-status="<?php echo htmlspecialchars($status_key); ?>">

                                        <td>
                                            <span class="ticket-id">#<?php echo str_pad((string)$ap['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                            <small class="muted">
                                                <?php echo htmlspecialchars(date('M j, Y', strtotime($ap['created_at']))); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="strong"><?php echo htmlspecialchars($ap['office_name'] ?? '—'); ?></span>
                                            <small class="muted">FB #<?php echo (int)$ap['feedback_id']; ?> · <?php echo htmlspecialchars($ap['service_accessed'] ?? '—'); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($ap['staff_name'])): ?>
                                                <span class="strong" style="font-size:12px;"><?php echo htmlspecialchars($ap['staff_name']); ?></span>
                                                <small class="muted"><?php echo htmlspecialchars($ap['assigned_staff_id']); ?></small>
                                            <?php else: ?>
                                                <span class="signal-pill neutral">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size:12px; font-weight:600; color: var(--text-heading); margin-bottom:4px;">
                                                <?php echo htmlspecialchars(mb_strimwidth($ap['root_cause_analysis'], 0, 100, '…')); ?>
                                            </div>
                                            <?php if (!empty($ap['action_taken'])): ?>
                                                <small class="muted">↳ <?php echo htmlspecialchars(mb_strimwidth($ap['action_taken'], 0, 90, '…')); ?></small>
                                            <?php else: ?>
                                                <small class="muted" style="font-style:italic;">No corrective action recorded yet.</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($ap['status']); ?>
                                            </span>
                                            <?php if ($ap['resolved_at']): ?>
                                                <small class="muted"><?php echo htmlspecialchars(date('M j, Y', strtotime($ap['resolved_at']))); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <div class="action-btns">
                                                <button class="btn-table-action btn-edit"
                                                    onclick='openEditTicketModal(<?php echo htmlspecialchars(json_encode($ap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                    Edit
                                                </button>
                                                <form method="POST" action="admin_resolutions.php" style="display:inline;"
                                                    onsubmit="return confirm('Permanently delete ticket #<?php echo str_pad((string)$ap['id'], 4, '0', STR_PAD_LEFT); ?>? The linked feedback will return to Pending Review.');">
                                                    <input type="hidden" name="action" value="delete_action_plan">
                                                    <input type="hidden" name="id" value="<?php echo (int)$ap['id']; ?>">
                                                    <button type="submit" class="btn-table-action btn-delete">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="noPlansRow">
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                            </svg>
                                            <h4>No action plans yet</h4>
                                            <p>Create tickets from the Pending Review tab to start tracking resolutions.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr id="noResultsRow" style="display: none;">
                                <td colspan="6">
                                    <div class="empty-state">
                                        <p>No tickets match your search or filter settings.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

    <!-- ============================================================
         MODAL: Create / Edit Action Plan
         ============================================================ -->
    <div class="modal-overlay" id="ticketModal">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modalTitle">

            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-header-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                    <div class="modal-header-text">
                        <h3 id="modalTitle">Create Action Plan</h3>
                        <p id="modalSubtitle">Log the root cause and assign a corrective action</p>
                    </div>
                </div>
                <button type="button" class="btn-close-modal" onclick="closeModal('ticketModal')" aria-label="Close">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form method="POST" action="admin_resolutions.php" class="modal-form" id="ticketForm">
                <input type="hidden" name="action" id="formAction" value="create_action_plan">
                <input type="hidden" name="id" id="ticketId" value="">
                <input type="hidden" name="feedback_id" id="feedbackId" value="">
                <input type="hidden" name="office_id" id="officeId" value="">

                <div class="modal-body">

                    <!-- SECTION 1: Linked Feedback -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">1</span>
                            <span>Linked Feedback</span>
                        </div>

                        <div class="form-grid">
                            <div class="modal-form-group">
                                <label>Feedback Reference</label>
                                <div class="readonly-card" id="linkedFeedbackCard">
                                    <span class="label">Reference</span>
                                    <span id="linkedFeedbackRef">—</span>
                                </div>
                            </div>

                            <div class="modal-form-group">
                                <label>Responsible Office</label>
                                <div class="readonly-card" id="linkedOfficeCard">
                                    <span class="label">Office</span>
                                    <span id="linkedOfficeName">—</span>
                                </div>
                            </div>

                            <div class="modal-form-group full">
                                <label>Reported Service</label>
                                <div class="readonly-card">
                                    <span class="label">Service Accessed</span>
                                    <span id="linkedService">—</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: Assignment & Status -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">2</span>
                            <span>Assignment &amp; Status</span>
                        </div>

                        <div class="form-grid">
                            <div class="modal-form-group">
                                <label>Assigned To</label>
                                <select id="assignedStaff" name="assigned_staff_id">
                                    <option value="">— Unassigned —</option>
                                    <?php foreach ($staff_list as $s): ?>
                                        <option value="<?php echo htmlspecialchars($s['user_id']); ?>">
                                            <?php echo htmlspecialchars($s['full_name']); ?>
                                            (<?php echo htmlspecialchars($s['user_id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="field-hint">Staff/faculty members responsible for documenting the fix.</small>
                            </div>

                            <div class="modal-form-group">
                                <label>Status <span class="req">*</span></label>
                                <select id="ticketStatus" name="status" required>
                                    <option value="Pending">Pending</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Resolved">Resolved</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: Documentation -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">3</span>
                            <span>Documentation</span>
                        </div>

                        <div class="form-grid">
                            <div class="modal-form-group full">
                                <label>Root Cause Analysis <span class="req">*</span></label>
                                <textarea id="rootCause" name="root_cause_analysis" required
                                    placeholder="Why did the client report a poor experience? (e.g., understaffed frontline, unclear signage, slow system)"></textarea>
                            </div>

                            <div class="modal-form-group full">
                                <label>Corrective Action Taken</label>
                                <textarea id="actionTaken" name="action_taken"
                                    placeholder="What has been (or will be) done to resolve the issue? (e.g., retrained staff, added signages, system upgrade)"></textarea>
                                <small class="field-hint">Leave blank while the ticket is still Pending; update once the office reports progress.</small>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('ticketModal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="btnSubmit">Save Action Plan</button>
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
           TABS
           ============================================================ */
        function switchTab(name) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === name);
            });
            document.getElementById('tabPanel-pending').classList.toggle('panel-hidden', name !== 'pending');
            document.getElementById('tabPanel-plans').classList.toggle('panel-hidden', name !== 'plans');
            document.getElementById('planFilters').style.display = (name === 'plans') ? '' : 'none';
        }

        /* ============================================================
           MODAL OPEN/CLOSE
           ============================================================ */
        function openCreateTicketModal(fb) {
            document.getElementById('modalTitle').textContent = 'Create Action Plan';
            document.getElementById('modalSubtitle').textContent = 'Log the root cause and assign a corrective action';
            document.getElementById('formAction').value = 'create_action_plan';
            document.getElementById('ticketId').value = '';
            document.getElementById('feedbackId').value = fb.feedback_id;
            document.getElementById('officeId').value = fb.office_id;

            document.getElementById('linkedFeedbackRef').textContent = '#FB-' + String(fb.feedback_id).padStart(4, '0');
            document.getElementById('linkedOfficeName').textContent = fb.office_name || '— Unknown Office —';
            document.getElementById('linkedService').textContent = fb.service_accessed || '—';

            document.getElementById('assignedStaff').value = '';
            document.getElementById('ticketStatus').value = 'Pending';
            document.getElementById('rootCause').value = '';
            document.getElementById('actionTaken').value = '';

            document.getElementById('btnSubmit').textContent = 'Create Ticket';
            document.getElementById('ticketModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('rootCause').focus(), 120);
        }

        function openEditTicketModal(ap) {
            document.getElementById('modalTitle').textContent = 'Edit Action Plan';
            document.getElementById('modalSubtitle').textContent = 'Update assignment, status, and resolution notes';
            document.getElementById('formAction').value = 'update_action_plan';
            document.getElementById('ticketId').value = ap.id;
            document.getElementById('feedbackId').value = ap.feedback_id;
            document.getElementById('officeId').value = ap.office_id;

            document.getElementById('linkedFeedbackRef').textContent =
                '#FB-' + String(ap.feedback_id).padStart(4, '0') +
                '  ·  Ticket #' + String(ap.id).padStart(4, '0');
            document.getElementById('linkedOfficeName').textContent = ap.office_name || '— Unknown Office —';
            document.getElementById('linkedService').textContent = ap.service_accessed || '—';

            document.getElementById('assignedStaff').value = ap.assigned_staff_id || '';
            document.getElementById('ticketStatus').value = ap.status || 'Pending';
            document.getElementById('rootCause').value = ap.root_cause_analysis || '';
            document.getElementById('actionTaken').value = ap.action_taken || '';

            document.getElementById('btnSubmit').textContent = 'Update Ticket';
            document.getElementById('ticketModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
        }

        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                closeModal(e.target.id);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('ticketModal');
                if (modal.classList.contains('active')) closeModal('ticketModal');
            }
        });

        /* ============================================================
           LIVE TABLE FILTER (Action Plans tab)
           ============================================================ */
        const planSearch = document.getElementById('planSearch');
        const planStatusFilter = document.getElementById('planStatusFilter');
        const planRows = document.querySelectorAll('.plan-row');
        const planBadge = document.getElementById('planCountBadge');
        const noResultsRow = document.getElementById('noResultsRow');

        function runPlanFilter() {
            const term = (planSearch.value || '').toLowerCase().trim();
            const status = (planStatusFilter.value || '').toLowerCase().trim();
            let matches = 0;

            planRows.forEach(row => {
                const rSearch = row.getAttribute('data-search') || '';
                const rStatus = row.getAttribute('data-status') || '';
                const okTerm = (term === '' || rSearch.includes(term));
                const okStatus = (status === '' || rStatus === status);
                if (okTerm && okStatus) { row.style.display = ''; matches++; }
                else { row.style.display = 'none'; }
            });

            if (noResultsRow) noResultsRow.style.display = (matches === 0 && planRows.length > 0) ? '' : 'none';
            if (planBadge) planBadge.textContent = `Showing ${matches} ticket(s)`;
        }

        if (planSearch) planSearch.addEventListener('input', runPlanFilter);
        if (planStatusFilter) planStatusFilter.addEventListener('change', runPlanFilter);
    </script>
</body>
</html>