
<?php
// admin_charter.php — Citizen's Charter Manager

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
// POST ACTIONS
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_service' || $action === 'update_service') {
        $service_id           = (int)($_POST['service_id'] ?? 0);
        $service_name         = trim($_POST['service_name'] ?? '');
        $office_id            = (int)($_POST['office_id'] ?? 0);
        $classification       = $_POST['classification'] ?? 'Simple';
        $processing_time_days = (int)($_POST['processing_time_days'] ?? 3);
        $fee_amount           = (float)($_POST['fee_amount'] ?? 0);
        $fee_notes            = trim($_POST['fee_notes'] ?? '');
        $legal_basis          = trim($_POST['legal_basis'] ?? '');
        $charter_notes        = trim($_POST['charter_notes'] ?? '');
        $is_active            = isset($_POST['is_active']) ? 1 : 0;

        if (!in_array($classification, ['Simple','Complex','Highly Technical'], true)) {
            $classification = 'Simple';
        }
        if ($processing_time_days < 1) $processing_time_days = 1;

        if ($service_name === '' || $office_id <= 0) {
            $toast_message = "Service name and office are required.";
            $toast_type = 'error';
        } else {
            // Duplicate check within same office
            $chk = $conn->prepare("SELECT id FROM services WHERE office_id = ? AND service_name = ? AND id != ? LIMIT 1");
            $chk->bind_param("isi", $office_id, $service_name, $service_id);
            $chk->execute();
            $dup = $chk->get_result()->num_rows > 0;
            $chk->close();

            if ($dup) {
                $toast_message = "A service with this name already exists under that office.";
                $toast_type = 'error';
            } else {
                if ($action === 'create_service') {
                    $stmt = $conn->prepare("
                        INSERT INTO services
                        (office_id, service_name, classification, processing_time_days,
                         fee_amount, fee_notes, legal_basis, charter_notes, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("issidsssi",
                        $office_id, $service_name, $classification, $processing_time_days,
                        $fee_amount, $fee_notes, $legal_basis, $charter_notes, $is_active
                    );
                    $ok = $stmt->execute();
                    $service_id = $ok ? $stmt->insert_id : 0;
                    $stmt->close();
                    $verb = "created";
                } else {
                    $stmt = $conn->prepare("
                        UPDATE services
                        SET office_id = ?, service_name = ?, classification = ?, processing_time_days = ?,
                            fee_amount = ?, fee_notes = ?, legal_basis = ?, charter_notes = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param("issidsssii",
                        $office_id, $service_name, $classification, $processing_time_days,
                        $fee_amount, $fee_notes, $legal_basis, $charter_notes, $is_active, $service_id
                    );
                    $ok = $stmt->execute();
                    $stmt->close();
                    $verb = "updated";
                }

                if ($ok && $service_id > 0) {
                    // Replace requirements and steps wholesale
                    $conn->query("DELETE FROM service_requirements WHERE service_id = " . (int)$service_id);
                    $conn->query("DELETE FROM service_steps WHERE service_id = " . (int)$service_id);

                    // Requirements
                    $req_docs = $_POST['req_document'] ?? [];
                    $req_mand = $_POST['req_mandatory'] ?? [];
                    if (is_array($req_docs) && !empty($req_docs)) {
                        $ins = $conn->prepare("INSERT INTO service_requirements (service_id, document_name, is_mandatory, sort_order) VALUES (?, ?, ?, ?)");
                        foreach ($req_docs as $i => $doc) {
                            $doc = trim((string)$doc);
                            if ($doc === '') continue;
                            $mand = ((int)($req_mand[$i] ?? 1)) ? 1 : 0;
                            $ord  = $i;
                            $ins->bind_param("isii", $service_id, $doc, $mand, $ord);
                            $ins->execute();
                        }
                        $ins->close();
                    }

                    // Steps
                    $step_desc  = $_POST['step_description'] ?? [];
                    $step_resp  = $_POST['step_responsible'] ?? [];
                    $step_mins  = $_POST['step_minutes'] ?? [];
                    if (is_array($step_desc) && !empty($step_desc)) {
                        $ins = $conn->prepare("INSERT INTO service_steps (service_id, step_number, step_description, responsible_person, estimated_minutes) VALUES (?, ?, ?, ?, ?)");
                        $n = 0;
                        foreach ($step_desc as $i => $d) {
                            $d = trim((string)$d);
                            if ($d === '') continue;
                            $n++;
                            $resp = trim((string)($step_resp[$i] ?? ''));
                            $resp = $resp === '' ? null : $resp;
                            $mins = (int)($step_mins[$i] ?? 0);
                            $mins = $mins > 0 ? $mins : null;
                            $ins->bind_param("iissi", $service_id, $n, $d, $resp, $mins);
                            $ins->execute();
                        }
                        $ins->close();
                    }

                    $toast_message = "Service standard {$verb} successfully.";
                    $toast_type = 'success';
                } else {
                    $toast_message = "Database error: " . $conn->error;
                    $toast_type = 'error';
                }
            }
        }
    }

    elseif ($action === 'delete_service') {
        $service_id = (int)($_POST['service_id'] ?? 0);
        if ($service_id > 0) {
            $del = $conn->prepare("DELETE FROM services WHERE id = ?");
            $del->bind_param("i", $service_id);
            if ($del->execute()) {
                $toast_message = "Service standard and all linked requirements removed.";
                $toast_type = 'success';
            } else {
                $toast_message = "Delete failed: " . $conn->error;
                $toast_type = 'error';
            }
            $del->close();
        }
    }

    elseif ($action === 'toggle_active') {
        $service_id = (int)($_POST['service_id'] ?? 0);
        if ($service_id > 0) {
            $conn->query("UPDATE services SET is_active = 1 - is_active WHERE id = " . (int)$service_id);
            $toast_message = "Service visibility toggled.";
            $toast_type = 'success';
        }
    }
}

// ---------------------------------------------------------------------------
// STATISTICS
// ---------------------------------------------------------------------------
$stats = [
    'total_services' => 0,
    'compliant'      => 0,
    'breaches'       => 0,
    'offices'        => 0,
];

$r = $conn->query("SELECT COUNT(*) AS c FROM services");
if ($r) $stats['total_services'] = (int)$r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(DISTINCT office_id) AS c FROM services");
if ($r) $stats['offices'] = (int)$r->fetch_assoc()['c'];

// ---------------------------------------------------------------------------
// FETCH SERVICES + breach count + requirements count
// ---------------------------------------------------------------------------
$services = [];
$svc_res = $conn->query("
    SELECT
        s.id, s.office_id, s.service_name, s.classification,
        s.processing_time_days, s.fee_amount, s.fee_notes,
        s.legal_basis, s.charter_notes, s.is_active, s.updated_at,
        o.office_name, o.office_type,
        (SELECT COUNT(*) FROM service_requirements r WHERE r.service_id = s.id) AS req_count,
        (SELECT COUNT(*) FROM service_steps st WHERE st.service_id = s.id)       AS step_count,
        COALESCE((
            SELECT COUNT(*)
            FROM arta_feedback f
            WHERE f.office_id = s.office_id
              AND f.service_accessed = s.service_name
              AND f.service_start_date IS NOT NULL
              AND f.transaction_date IS NOT NULL
              AND DATEDIFF(f.transaction_date, f.service_start_date) > s.processing_time_days
        ), 0) AS breach_count
    FROM services s
    LEFT JOIN offices o ON s.office_id = o.id
    ORDER BY o.office_name ASC, s.service_name ASC
");

if ($svc_res instanceof mysqli_result) {
    while ($row = $svc_res->fetch_assoc()) {
        $services[] = $row;
        if ((int)$row['breach_count'] > 0) $stats['breaches']++;
        else $stats['compliant']++;
    }
}

// ---------------------------------------------------------------------------
// FETCH OFFICES (for filter + modal)
// ---------------------------------------------------------------------------
$offices = [];
$o_res = $conn->query("SELECT id, office_name, office_type FROM offices ORDER BY office_name ASC");
if ($o_res instanceof mysqli_result) {
    while ($row = $o_res->fetch_assoc()) $offices[] = $row;
}

// ---------------------------------------------------------------------------
// FETCH REQUIREMENTS + STEPS per service (for edit modal hydration)
// ---------------------------------------------------------------------------
$requirements_by_service = [];
$r_res = $conn->query("SELECT service_id, document_name, is_mandatory, sort_order FROM service_requirements ORDER BY service_id, sort_order, id");
if ($r_res instanceof mysqli_result) {
    while ($row = $r_res->fetch_assoc()) {
        $requirements_by_service[(int)$row['service_id']][] = [
            'document_name' => $row['document_name'],
            'is_mandatory'  => (int)$row['is_mandatory'],
        ];
    }
}

$steps_by_service = [];
$st_res = $conn->query("SELECT service_id, step_number, step_description, responsible_person, estimated_minutes FROM service_steps ORDER BY service_id, step_number");
if ($st_res instanceof mysqli_result) {
    while ($row = $st_res->fetch_assoc()) {
        $steps_by_service[(int)$row['service_id']][] = [
            'step_description'   => $row['step_description'],
            'responsible_person' => $row['responsible_person'],
            'estimated_minutes'  => $row['estimated_minutes'],
        ];
    }
}

$role_map = [
    'Academic'       => 'Academic',
    'Administrative' => 'Administrative',
];

// Page metadata for admin_header.php
$page_title    = "Citizen's Charter Manager";
$page_crumb    = 'Administration / Citizen\'s Charter';
$page_subtitle = 'Digitize service standards, fees, and required documents';
$unread_notifications = 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> | ZPPSU CSM</title>
    <link href="[https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap](https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap)" rel="stylesheet">
    <style>
        :root {
            --maroon: #7A0000; --maroon-dark: #580000; --gold: #F2A900;
            --bg: #F8FAFC; --surface: #FFFFFF;
            --border: #E2E8F0; --border-strong: #CBD5E1;
            --text-heading: #0F172A; --text-body: #475569; --text-muted: #94A3B8;
            --success: #10B981; --danger: #EF4444; --warning: #F59E0B;
            --sidebar-width: 272px; --header-height: 72px;
        }
        html, body { width: 100%; height: 100%; margin: 0; padding: 0; overflow-x: hidden; }
        * { box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; -webkit-text-size-adjust: 100%; }
        body { background-color: var(--bg); color: var(--text-body); display: flex; min-height: 100vh; }

        .admin-main-wrap {
            margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width));
            min-width: 0; display: flex; flex-direction: column; min-height: 100vh;
            overflow-x: hidden;
            transition: margin-left 0.35s cubic-bezier(0.4,0,0.2,1), width 0.35s cubic-bezier(0.4,0,0.2,1);
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

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; margin-bottom: 12px; }
        .stat-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 14px 16px;
            display: flex; align-items: center; gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02); min-width: 0;
        }
        .stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid; }
        .stat-icon svg { width: 20px; height: 20px; }
        .stat-icon.red    { background:#FEF2F2; border-color:#FECACA; color:#B91C1C; }
        .stat-icon.green  { background:#ECFDF5; border-color:#A7F3D0; color:#047857; }
        .stat-icon.blue   { background:#EFF6FF; border-color:#BFDBFE; color:#1D4ED8; }
        .stat-icon.gray   { background:#F1F5F9; border-color:#E2E8F0; color:#475569; }
        .stat-body { min-width: 0; }
        .stat-value { font-size: 22px; font-weight: 800; color: var(--text-heading); line-height: 1; letter-spacing: -0.5px; }
        .stat-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* TOOLBAR */
        .toolbar {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px 20px; margin-bottom: 10px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .toolbar-title h2 { font-size: 19px; font-weight: 800; color: var(--text-heading); margin: 0; }
        .toolbar-title p { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }
        .btn-primary {
            background: var(--maroon); color: #FFFFFF; border: none;
            padding: 10px 16px; border-radius: 8px; font-size: 12.5px; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
            white-space: nowrap; transition: background 0.18s, transform 0.12s; font-family: inherit;
        }
        .btn-primary:hover { background: var(--maroon-dark); }
        .btn-primary:active { transform: scale(0.98); }
        .btn-secondary {
            background: #FFFFFF; color: var(--text-heading); border: 1.5px solid var(--border);
            padding: 10px 16px; border-radius: 8px; font-size: 12.5px; font-weight: 700;
            cursor: pointer; transition: background 0.18s, border-color 0.18s; font-family: inherit;
        }
        .btn-secondary:hover { background: #F1F5F9; border-color: var(--border-strong); }

        /* FILTER */
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

        .btn-toggle-filter {
            display: none; /* Hidden by default on desktop */
            background: #F1F5F9; color: var(--text-heading); border: 1.5px solid var(--border);
            padding: 8px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 700;
            cursor: pointer; align-items: center; gap: 6px; width: 100%; justify-content: center;
        }

        /* TABLE */
        .panel-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px; flex: 1;
            display: flex; flex-direction: column; min-height: 0;
        }
        .table-scroll-wrap {
            flex: 1; min-height: 400px; max-height: calc(100vh - 420px);
            overflow-y: auto; overflow-x: auto;
            border: 1px solid var(--border); border-radius: 8px;
            -webkit-overflow-scrolling: touch;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 900px; }
        .table-scroll-wrap thead th { position: sticky; top: 0; background-color: #F1F5F9; z-index: 10; box-shadow: 0 1px 0 var(--border); }
        th {
            background-color: #F1F5F9; color: var(--text-muted); font-weight: 700;
            padding: 11px 14px; border-bottom: 1px solid var(--border);
            text-transform: uppercase; font-size: 10.5px; text-align: left; letter-spacing: 0.4px;
        }
        td { padding: 12px 14px; border-bottom: 1px solid var(--border); color: var(--text-body); vertical-align: top; }

        .strong { font-weight: 700; color: var(--text-heading); }
        .muted  { color: var(--text-muted); font-size: 11px; display: block; margin-top: 2px; }

        /* Classification badges */
        .badge-type {
            display: inline-block; font-size: 11px; font-weight: 700;
            padding: 3px 8px; border-radius: 4px;
            background: #F1F5F9; color: var(--text-body); border: 1px solid #E2E8F0;
        }
        .badge-simple   { background: #ECFDF5; color: #047857; border-color: #A7F3D0; }
        .badge-complex  { background: #FFFBEB; color: #B45309; border-color: #FDE68A; }
        .badge-technical { background: #FEF2F2; color: #B91C1C; border-color: #FECACA; }
        .badge-inactive { background: #F1F5F9; color: var(--text-muted); border-color: #E2E8F0; }

        /* Compliance pill */
        .signal-pill {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 10.5px; font-weight: 700;
            padding: 3px 8px; border-radius: 4px;
            white-space: nowrap;
        }
        .signal-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .signal-ok    { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }
        .signal-warn  { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
        .signal-breach{ background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA; }

        .action-btns { display: flex; align-items: center; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
        .btn-table-action {
            padding: 6px 12px; border-radius: 6px; font-size: 11.5px; font-weight: 600;
            cursor: pointer; border: 1px solid transparent; transition: filter 0.18s; font-family: inherit;
        }
        .btn-table-action:hover { filter: brightness(0.95); }
        .btn-edit   { background: #EFF6FF; color: #2563EB; border-color: #BFDBFE; }
        .btn-delete { background: #FEF2F2; color: var(--danger); border-color: #FECACA; }
        .btn-view   { background: #F1F5F9; color: var(--text-body); border-color: #E2E8F0; }

        /* TOAST */
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

        /* MODAL */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(15,23,42,0.68);
            -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px);
            z-index: 1050; display: none; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: var(--surface); border-radius: 18px;
            width: 100%; max-width: 780px;
            max-height: calc(100vh - 40px); max-height: calc(100dvh - 40px);
            border: 1px solid var(--border);
            box-shadow: 0 30px 60px -12px rgba(15,23,42,0.35);
            display: flex; flex-direction: column; overflow: hidden;
            animation: modalIn 0.28s cubic-bezier(0.34,1.3,0.64,1);
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
            background: linear-gradient(135deg,#FEF2F2 0%,#FEE2E2 100%);
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
        .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 22px; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }

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
        .modal-form-group textarea { min-height: 80px; resize: vertical; line-height: 1.5; }
        .modal-form-group input:focus,
        .modal-form-group select:focus,
        .modal-form-group textarea:focus {
            border-color: var(--maroon); background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(122,0,0,0.08);
        }
        .field-hint { display: block; font-size: 11px; color: var(--text-muted); margin-top: 5px; line-height: 1.4; }

        /* Checkbox switch */
        .switch-row {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 13px; border: 1.5px solid var(--border);
            border-radius: 9px; background: #F8FAFC;
        }
        .switch-row input[type="checkbox"] {
            width: 18px; height: 18px; accent-color: var(--maroon);
            cursor: pointer; flex-shrink: 0;
        }
        .switch-row label { margin: 0; cursor: pointer; font-size: 12.5px; }

        /* Repeatable rows */
        .repeatable-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 10px; }
        .repeatable-row {
            display: grid;
            gap: 8px;
            padding: 10px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            background: #FAFBFC;
            align-items: center;
        }
        .repeatable-row.req-row  { grid-template-columns: 1fr 140px 36px; }
        .repeatable-row.step-row { grid-template-columns: 1fr 1fr 110px 36px; }
        .repeatable-row input, .repeatable-row select, .repeatable-row textarea {
            width: 100%; padding: 8px 10px;
            border: 1.5px solid var(--border); border-radius: 7px;
            font-size: 12.5px; background: #FFFFFF; color: var(--text-heading);
            font-family: inherit; outline: none;
        }
        .repeatable-row input:focus, .repeatable-row select:focus, .repeatable-row textarea:focus {
            border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(122,0,0,0.06);
        }
        .repeatable-row textarea { min-height: 38px; resize: vertical; }
        .btn-row-remove {
            width: 36px; height: 36px; border-radius: 8px;
            background: #FEF2F2; color: var(--danger); border: 1px solid #FECACA;
            cursor: pointer; font-size: 16px; font-weight: 800;
            display: inline-flex; align-items: center; justify-content: center;
            transition: filter 0.18s; font-family: inherit; padding: 0;
        }
        .btn-row-remove:hover { filter: brightness(0.94); }

        .btn-add-row {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 8px;
            background: #FFFFFF; color: var(--maroon);
            border: 1.5px dashed #FECACA; cursor: pointer;
            font-size: 12px; font-weight: 700; font-family: inherit;
            transition: background 0.18s, border-color 0.18s;
        }
        .btn-add-row:hover { background: #FEF2F2; border-color: var(--maroon); }

        .empty-hint {
            padding: 12px; text-align: center; font-size: 12px;
            color: var(--text-muted); border: 1.5px dashed var(--border);
            border-radius: 8px; background: #FAFBFC;
        }

        .modal-footer {
            flex-shrink: 0; padding: 14px 22px;
            background: #FAFBFC; border-top: 1px solid var(--border);
            display: flex; justify-content: flex-end; gap: 10px;
        }
        .modal-footer .btn-primary,
        .modal-footer .btn-secondary { min-width: 110px; justify-content: center; }

        /* Empty state */
        .empty-state { text-align: center; color: var(--text-muted); padding: 50px 20px; }
        .empty-state svg { width: 44px; height: 44px; color: #CBD5E1; margin-bottom: 10px; }
        .empty-state h4 { color: var(--text-heading); font-size: 15px; margin: 0 0 4px; }
        .empty-state p { font-size: 12.5px; margin: 0; }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .admin-main-wrap { margin-left: 0; width: 100%; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
        }
        @media (max-width: 768px) {
            .admin-container { padding: calc(var(--header-height) + 12px) 14px 18px; }
            .toolbar { flex-direction: column; align-items: stretch; padding: 14px 16px; }
            .toolbar-actions .btn-primary { width: 100%; justify-content: center; }
            
            /* FILTER COLLAPSE STYLES FOR MOBILE */
            .filter-panel { flex-direction: column; align-items: stretch; padding: 0; border: none; background: transparent; }
            .btn-toggle-filter { display: flex; } /* Show toggle button */
            .filter-controls { 
                display: none; /* Hide filters by default */
                flex-direction: column; 
                align-items: stretch; 
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 10px;
                padding: 14px;
                margin-top: 8px;
            }
            .filter-controls.expanded { display: flex; } /* Show when toggled */
            
            .input-live-search, .select-live-filter { width: 100%; min-width: 0; }
            .table-scroll-wrap { max-height: calc(100vh - 480px); }
            #searchCountBadge { display: none !important; }
            .modal-overlay { padding: 14px; }
            .modal-box { max-height: calc(100dvh - 28px); }
            .repeatable-row.req-row  { grid-template-columns: 1fr 120px 36px; }
            .repeatable-row.step-row { grid-template-columns: 1fr 1fr; }
            .repeatable-row.step-row .btn-row-remove { grid-column: span 2; justify-self: end; }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; gap: 8px; border-radius: 10px; }
            .stat-icon { width: 34px; height: 34px; }
            .stat-value { font-size: 18px; }
            .stat-label { font-size: 10px; }

            .modal-overlay { padding: 0; align-items: stretch; justify-content: stretch; background: #FFFFFF; -webkit-backdrop-filter: none; backdrop-filter: none; }
            .modal-box {
                max-width: 100%; width: 100%; max-height: 100dvh; height: 100dvh;
                border-radius: 0; border: none; box-shadow: none;
                animation: modalSlideUp 0.3s cubic-bezier(0.32,0.72,0,1);
            }
            @keyframes modalSlideUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
            .modal-header { padding: 16px; padding-top: calc(16px + env(safe-area-inset-top, 0px)); }
            .modal-body { padding: 18px 16px; -webkit-overflow-scrolling: touch; }
            .modal-footer { padding: 12px 16px; padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px)); gap: 8px; }
            .modal-footer .btn-primary, .modal-footer .btn-secondary { flex: 1; min-width: 0; }
            .form-grid { grid-template-columns: 1fr; gap: 12px; }
            .form-section { margin-bottom: 18px; }
            .modal-form-group input, .modal-form-group select, .modal-form-group textarea { font-size: 16px; }
            .modal-form-group input, .modal-form-group select { height: 46px; }
            .repeatable-row.req-row, .repeatable-row.step-row { grid-template-columns: 1fr; }
            .repeatable-row .btn-row-remove { justify-self: end; }
        }
        @media (max-width: 480px) {
            :root { --header-height: 66px; }
            .admin-container { padding: calc(var(--header-height) + 10px) 10px 16px; }
            .toolbar-title h2 { font-size: 17px; }
            .toolbar-title p { font-size: 11.5px; }
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

            <!-- STATS -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon gray">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3m6-14h6M9 11h6M9 15h4"/>
                        </svg>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value"><?php echo number_format($stats['total_services']); ?></div>
                        <div class="stat-label">Chartered Services</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value"><?php echo number_format($stats['compliant']); ?></div>
                        <div class="stat-label">ARTA Compliant</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value"><?php echo number_format($stats['breaches']); ?></div>
                        <div class="stat-label">SLA Breaches</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h.01M9 12h.01M9 15h.01M9 18h.01"/>
                        </svg>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value"><?php echo number_format($stats['offices']); ?></div>
                        <div class="stat-label">Offices Covered</div>
                    </div>
                </div>
            </section>

            <div class="fixed-header-deck">
                <section class="toolbar">
                    <div class="toolbar-title">
                        <h2>Citizen's Charter Manager</h2>
                        <p>Digitized service standards, fees, and processing times per office</p>
                    </div>
                    <div class="toolbar-actions">
                        <button class="btn-primary" onclick="openServiceModal()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Add Service Standard
                        </button>
                    </div>
                </section>

                <section class="filter-panel">
                    <button class="btn-toggle-filter" onclick="toggleFilters()">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filter Options
                    </button>
                    <div class="filter-controls" id="mobileFilterControls">
                        <input type="text" id="liveSearchInput" class="input-live-search" placeholder="Search service, office, or legal basis..." autocomplete="off">
                        <select id="liveOfficeSelect" class="select-live-filter">
                            <option value="">All Offices</option>
                            <?php foreach ($offices as $o): ?>
                                <option value="<?php echo (int)$o['id']; ?>">
                                    <?php echo htmlspecialchars($o['office_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="liveClassSelect" class="select-live-filter">
                            <option value="">All Classifications</option>
                            <option value="Simple">Simple</option>
                            <option value="Complex">Complex</option>
                            <option value="Highly Technical">Highly Technical</option>
                        </select>
                        <select id="liveStatusSelect" class="select-live-filter">
                            <option value="">All Compliance States</option>
                            <option value="ok">Compliant</option>
                            <option value="breach">Breach Flagged</option>
                        </select>
                    </div>
                    <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 600;" id="searchCountBadge">
                        Showing <?php echo count($services); ?> service(s)
                    </div>
                </section>
            </div>

            <section class="panel-card">
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 280px;">Service &amp; Classification</th>
                                <th style="width: 200px;">Responsible Office</th>
                                <th style="width: 170px;">Processing Standard</th>
                                <th style="width: 130px;">Fee</th>
                                <th style="width: 150px;">Requirements</th>
                                <th style="width: 140px;">ARTA Status</th>
                                <th style="width: 140px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="servicesTableBody">
                            <?php if (!empty($services)): ?>
                                <?php foreach ($services as $s): ?>
                                    <?php
                                        $cls = $s['classification'];
                                        $cls_class = 'badge-type';
                                        if ($cls === 'Simple') $cls_class .= ' badge-simple';
                                        elseif ($cls === 'Complex') $cls_class .= ' badge-complex';
                                        elseif ($cls === 'Highly Technical') $cls_class .= ' badge-technical';

                                        $breach = (int)$s['breach_count'];
                                        if ($breach > 0) {
                                            $status_pill = 'signal-breach';
                                            $status_label = $breach . ' breach' . ($breach === 1 ? '' : 'es');
                                        } else {
                                            $status_pill = 'signal-ok';
                                            $status_label = 'Compliant';
                                        }
                                    ?>
                                    <tr class="service-row"
                                        data-search="<?php echo strtolower(htmlspecialchars(
                                            $s['service_name'] . ' ' . ($s['office_name'] ?? '') . ' ' . ($s['legal_basis'] ?? '')
                                        )); ?>"
                                        data-office="<?php echo (int)$s['office_id']; ?>"
                                        data-class="<?php echo strtolower(htmlspecialchars($cls)); ?>"
                                        data-status="<?php echo $breach > 0 ? 'breach' : 'ok'; ?>">

                                        <td>
                                            <span class="strong" style="display:block; <?php echo $s['is_active'] ? '' : 'opacity:.55;'; ?>">
                                                <?php echo htmlspecialchars($s['service_name']); ?>
                                            </span>
                                            <span class="badge-type <?php echo $cls_class; ?>" style="margin-top:4px;">
                                                <?php echo htmlspecialchars($cls); ?>
                                            </span>
                                            <?php if (!$s['is_active']): ?>
                                                <span class="badge-type badge-inactive" style="margin-top:4px;">Inactive</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="strong" style="font-size:12.5px;">
                                                <?php echo htmlspecialchars($s['office_name'] ?? '— Unknown —'); ?>
                                            </span>
                                            <small class="muted">
                                                <?php echo htmlspecialchars($s['office_type'] ?? ''); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <span class="strong"><?php echo (int)$s['processing_time_days']; ?> day<?php echo ((int)$s['processing_time_days'] === 1) ? '' : 's'; ?></span>
                                            <small class="muted">Mandated by ARTA</small>
                                        </td>

                                        <td>
                                            <span class="strong">₱<?php echo number_format((float)$s['fee_amount'], 2); ?></span>
                                            <?php if (!empty($s['fee_notes'])): ?>
                                                <small class="muted"><?php echo htmlspecialchars(mb_strimwidth($s['fee_notes'], 0, 40, '…')); ?></small>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="strong"><?php echo (int)$s['req_count']; ?> document<?php echo ((int)$s['req_count'] === 1) ? '' : 's'; ?></span>
                                            <small class="muted"><?php echo (int)$s['step_count']; ?> process step<?php echo ((int)$s['step_count'] === 1) ? '' : 's'; ?></small>
                                        </td>

                                        <td>
                                            <span class="signal-pill <?php echo $status_pill; ?>">
                                                <?php echo htmlspecialchars($status_label); ?>
                                            </span>
                                        </td>

                                        <td style="text-align: right;">
                                            <div class="action-btns">
                                                <form method="POST" action="admin_charter.php" style="display:inline;"
                                                    onsubmit="return true;">
                                                    <input type="hidden" name="action" value="toggle_active">
                                                    <input type="hidden" name="service_id" value="<?php echo (int)$s['id']; ?>">
                                                    <button type="submit" class="btn-table-action btn-view"
                                                        title="<?php echo $s['is_active'] ? 'Hide from public charter' : 'Show on public charter'; ?>">
                                                        <?php echo $s['is_active'] ? 'Hide' : 'Show'; ?>
                                                    </button>
                                                </form>

                                                <button class="btn-table-action btn-edit"
                                                    onclick='openEditServiceModal(<?php echo htmlspecialchars(json_encode([
                                                        'id' => (int)$s['id'],
                                                        'service_name' => $s['service_name'],
                                                        'office_id' => (int)$s['office_id'],
                                                        'classification' => $s['classification'],
                                                        'processing_time_days' => (int)$s['processing_time_days'],
                                                        'fee_amount' => (float)$s['fee_amount'],
                                                        'fee_notes' => $s['fee_notes'],
                                                        'legal_basis' => $s['legal_basis'],
                                                        'charter_notes' => $s['charter_notes'],
                                                        'is_active' => (int)$s['is_active'],
                                                        'requirements' => $requirements_by_service[(int)$s['id']] ?? [],
                                                        'steps' => $steps_by_service[(int)$s['id']] ?? [],
                                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                    Edit
                                                </button>

                                                <form method="POST" action="admin_charter.php" style="display:inline;"
                                                    onsubmit="return confirm('Permanently delete this service standard and its linked requirements/steps?');">
                                                    <input type="hidden" name="action" value="delete_service">
                                                    <input type="hidden" name="service_id" value="<?php echo (int)$s['id']; ?>">
                                                    <button type="submit" class="btn-table-action btn-delete">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <h4>No service standards yet</h4>
                                            <p>Click "Add Service Standard" to begin digitizing your Citizen's Charter.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr id="noResultsRow" style="display: none;">
                                <td colspan="7">
                                    <div class="empty-state">
                                        <p>No services match your search or filter settings.</p>
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
         MODAL: Add / Edit Service Standard
         ============================================================ -->
    <div class="modal-overlay" id="serviceModal">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modalTitle">

            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-header-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="modal-header-text">
                        <h3 id="modalTitle">Add Service Standard</h3>
                        <p id="modalSubtitle">Define the mandated service, processing time, fees, and requirements</p>
                    </div>
                </div>
                <button type="button" class="btn-close-modal" onclick="closeModal('serviceModal')" aria-label="Close">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="admin_charter.php" class="modal-form" id="serviceForm">
                <input type="hidden" name="action" id="formAction" value="create_service">
                <input type="hidden" name="service_id" id="serviceId" value="">

                <div class="modal-body">

                    <!-- SECTION 1 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">1</span>
                            <span>Service Identity</span>
                        </div>
                        <div class="form-grid">
                            <div class="modal-form-group full">
                                <label>Service Name <span class="req">*</span></label>
                                <input type="text" id="serviceName" name="service_name" placeholder="e.g. Issuance of Official Transcript of Records (TOR)" required autocomplete="off">
                            </div>

                            <div class="modal-form-group">
                                <label>Responsible Office <span class="req">*</span></label>
                                <select id="officeId" name="office_id" required>
                                    <option value="">— Select Office —</option>
                                    <?php foreach ($offices as $o): ?>
                                        <option value="<?php echo (int)$o['id']; ?>"><?php echo htmlspecialchars($o['office_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="modal-form-group">
                                <label>ARTA Classification <span class="req">*</span></label>
                                <select id="classification" name="classification" required>
                                    <option value="Simple">Simple</option>
                                    <option value="Complex">Complex</option>
                                    <option value="Highly Technical">Highly Technical</option>
                                </select>
                            </div>

                            <div class="modal-form-group full">
                                <label class="switch-row" style="border:1.5px solid var(--border);">
                                    <input type="checkbox" id="isActive" name="is_active" checked>
                                    <span>Publish this service on the public Citizen's Charter</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">2</span>
                            <span>Processing Standard &amp; Fees</span>
                        </div>
                        <div class="form-grid">
                            <div class="modal-form-group">
                                <label>Mandated Processing Time (days) <span class="req">*</span></label>
                                <input type="number" id="processingTime" name="processing_time_days" min="1" max="365" value="3" required>
                                <small class="field-hint">Number of working days per ARTA.</small>
                            </div>

                            <div class="modal-form-group">
                                <label>Fee Amount (₱) <span class="req">*</span></label>
                                <input type="number" id="feeAmount" name="fee_amount" min="0" step="0.01" value="0.00" required>
                            </div>

                            <div class="modal-form-group full">
                                <label>Fee Notes</label>
                                <input type="text" id="feeNotes" name="fee_notes" placeholder="e.g. Free for first copy, ₱50 per additional page" autocomplete="off">
                            </div>

                            <div class="modal-form-group full">
                                <label>Legal Basis / Reference</label>
                                <input type="text" id="legalBasis" name="legal_basis" placeholder="e.g. ARTA Memorandum Circular No. 2023-01" autocomplete="off">
                            </div>

                            <div class="modal-form-group full">
                                <label>Charter Notes</label>
                                <textarea id="charterNotes" name="charter_notes" placeholder="Additional notes visible on the published charter (e.g. 'Same-day release for rush requests')."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">3</span>
                            <span>Required Documents</span>
                        </div>
                        <div class="repeatable-list" id="requirementsList"></div>
                        <button type="button" class="btn-add-row" onclick="addRequirementRow()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Add Document
                        </button>
                        <small class="field-hint">List every document a client must present. Mark optional items accordingly.</small>
                    </div>

                    <!-- SECTION 4 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">4</span>
                            <span>Process Steps</span>
                        </div>
                        <div class="repeatable-list" id="stepsList"></div>
                        <button type="button" class="btn-add-row" onclick="addStepRow()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Add Step
                        </button>
                        <small class="field-hint">Optional: document the step-by-step flow. Steps are auto-numbered on save.</small>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('serviceModal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="btnSubmit">Save Service Standard</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        /* ============================================================
           TOGGLE MOBILE FILTERS
           ============================================================ */
        function toggleFilters() {
            const filterPanel = document.getElementById('mobileFilterControls');
            filterPanel.classList.toggle('expanded');
        }

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
                showToast("<?php echo addslashes($toast_message); ?>", "<?php echo $toast_type; ?>");
            });
        <?php endif; ?>

        /* ============================================================
           REPEATABLE ROWS
           ============================================================ */
        function addRequirementRow(name = '', mandatory = 1) {
            const list = document.getElementById('requirementsList');
            const row = document.createElement('div');
            row.className = 'repeatable-row req-row';
            row.innerHTML = `
                <input type="text" name="req_document[]" placeholder="e.g. Valid Student ID" value="${escapeAttr(name)}">
                <select name="req_mandatory[]">
                    <option value="1"${Number(mandatory) === 1 ? ' selected' : ''}>Required</option>
                    <option value="0"${Number(mandatory) === 0 ? ' selected' : ''}>Optional</option>
                </select>
                <button type="button" class="btn-row-remove" onclick="this.parentElement.remove()" aria-label="Remove">×</button>
            `;
            list.appendChild(row);
        }

        function addStepRow(desc = '', resp = '', mins = '') {
            const list = document.getElementById('stepsList');
            const row = document.createElement('div');
            row.className = 'repeatable-row step-row';
            row.innerHTML = `
                <textarea name="step_description[]" placeholder="Step description (e.g. Client submits request form at Window 1)">${escapeHtml(desc)}</textarea>
                <input type="text" name="step_responsible[]" placeholder="Responsible person / unit" value="${escapeAttr(resp)}">
                <input type="number" name="step_minutes[]" placeholder="Est. min" min="0" value="${escapeAttr(mins)}">
                <button type="button" class="btn-row-remove" onclick="this.parentElement.remove()" aria-label="Remove">×</button>
            `;
            list.appendChild(row);
        }

        function clearRequirements() { document.getElementById('requirementsList').innerHTML = ''; }
        function clearSteps()       { document.getElementById('stepsList').innerHTML = ''; }

        function escapeHtml(s) {
            return String(s ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
        function escapeAttr(s) { return escapeHtml(s); }

        /* ============================================================
           MODAL OPEN / CLOSE
           ============================================================ */
        function openServiceModal() {
            document.getElementById('modalTitle').textContent = 'Add Service Standard';
            document.getElementById('modalSubtitle').textContent = 'Define the mandated service, processing time, fees, and requirements';
            document.getElementById('formAction').value = 'create_service';
            document.getElementById('serviceId').value = '';

            document.getElementById('serviceName').value = '';
            document.getElementById('officeId').value = '';
            document.getElementById('classification').value = 'Simple';
            document.getElementById('processingTime').value = 3;
            document.getElementById('feeAmount').value = '0.00';
            document.getElementById('feeNotes').value = '';
            document.getElementById('legalBasis').value = '';
            document.getElementById('charterNotes').value = '';
            document.getElementById('isActive').checked = true;

            clearRequirements();
            addRequirementRow(); // one blank row to start

            clearSteps();

            document.getElementById('btnSubmit').textContent = 'Save Service Standard';
            document.getElementById('serviceModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('serviceName').focus(), 100);
        }

        function openEditServiceModal(data) {
            document.getElementById('modalTitle').textContent = 'Edit Service Standard';
            document.getElementById('modalSubtitle').textContent = 'Update processing time, fees, requirements, and process steps';
            document.getElementById('formAction').value = 'update_service';
            document.getElementById('serviceId').value = data.id;

            document.getElementById('serviceName').value = data.service_name || '';
            document.getElementById('officeId').value = data.office_id || '';
            document.getElementById('classification').value = data.classification || 'Simple';
            document.getElementById('processingTime').value = data.processing_time_days || 3;
            document.getElementById('feeAmount').value = Number(data.fee_amount || 0).toFixed(2);
            document.getElementById('feeNotes').value = data.fee_notes || '';
            document.getElementById('legalBasis').value = data.legal_basis || '';
            document.getElementById('charterNotes').value = data.charter_notes || '';
            document.getElementById('isActive').checked = Number(data.is_active) === 1;

            clearRequirements();
            if (Array.isArray(data.requirements) && data.requirements.length > 0) {
                data.requirements.forEach(r => addRequirementRow(r.document_name, r.is_mandatory));
            } else {
                addRequirementRow();
            }

            clearSteps();
            if (Array.isArray(data.steps) && data.steps.length > 0) {
                data.steps.forEach(s => addStepRow(s.step_description, s.responsible_person || '', s.estimated_minutes || ''));
            }

            document.getElementById('btnSubmit').textContent = 'Update Service Standard';
            document.getElementById('serviceModal').classList.add('active');
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
                const modal = document.getElementById('serviceModal');
                if (modal.classList.contains('active')) closeModal('serviceModal');
            }
        });

        /* ============================================================
           LIVE TABLE FILTER
           ============================================================ */
        const liveSearchInput  = document.getElementById('liveSearchInput');
        const liveOfficeSelect = document.getElementById('liveOfficeSelect');
        const liveClassSelect  = document.getElementById('liveClassSelect');
        const liveStatusSelect = document.getElementById('liveStatusSelect');
        const rows             = document.querySelectorAll('.service-row');
        const badge            = document.getElementById('searchCountBadge');
        const noResults        = document.getElementById('noResultsRow');

        function runLiveFilter() {
            const term   = (liveSearchInput.value || '').toLowerCase().trim();
            const office = (liveOfficeSelect.value || '').trim();
            const cls    = (liveClassSelect.value || '').toLowerCase().trim();
            const status = (liveStatusSelect.value || '').toLowerCase().trim();
            let matches  = 0;

            rows.forEach(row => {
                const rSearch = row.getAttribute('data-search') || '';
                const rOffice = row.getAttribute('data-office') || '';
                const rClass  = row.getAttribute('data-class')  || '';
                const rStatus = row.getAttribute('data-status') || '';

                const okSearch = (term === '' || rSearch.includes(term));
                const okOffice = (office === '' || rOffice === office);
                const okClass  = (cls === '' || rClass === cls);
                const okStatus = (status === '' || rStatus === status);

                if (okSearch && okOffice && okClass && okStatus) {
                    row.style.display = ''; matches++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noResults) noResults.style.display = (matches === 0 && rows.length > 0) ? '' : 'none';
            if (badge) badge.textContent = `Showing ${matches} service(s)`;
        }

        if (liveSearchInput)  liveSearchInput.addEventListener('input', runLiveFilter);
        if (liveOfficeSelect) liveOfficeSelect.addEventListener('change', runLiveFilter);
        if (liveClassSelect)  liveClassSelect.addEventListener('change', runLiveFilter);
        if (liveStatusSelect) liveStatusSelect.addEventListener('change', runLiveFilter);
    </script>
</body>
</html>