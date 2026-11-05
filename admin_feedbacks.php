<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Manila');

// 1. Authentication & Role Gate
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
if (($_SESSION['internal_user_type'] ?? '') !== 'admin') {
    header("Location: student_dashboard.php");
    exit();
}

require_once 'db_connection.php';

$toast_message = '';
$toast_type = '';

// 2. Handle CRUD Actions (Feedback Record Deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_feedback') {
        $feedback_id = intval($_POST['feedback_id'] ?? 0);

        if ($feedback_id > 0) {
            $stmt = $conn->prepare("DELETE FROM arta_feedback WHERE feedback_id = ?");
            $stmt->bind_param("i", $feedback_id);
            if ($stmt->execute()) {
                $toast_message = "Evaluation log entry successfully removed.";
                $toast_type = 'success';
            } else {
                $toast_message = "Failed to remove entry: " . $conn->error;
                $toast_type = 'error';
            }
            $stmt->close();
        }
    }
}

// 3. Handle CSV Audit Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ZPPSU_CSM_Feedbacks_' . date('Y-m-d_His') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Feedback ID', 'Timestamp', 'Office Visited', 'Office Type', 'Service Accessed',
        'Account Type', 'Client Classification', 'Age Group', 'Sex', 'Region',
        'CC1', 'CC2', 'CC3',
        'SQD1 (Time)', 'SQD2 (Requirements)', 'SQD3 (Steps)', 'SQD4 (Information)',
        'SQD5 (Fees)', 'SQD6 (Integrity)', 'SQD7 (Staff)', 'SQD8 (Outcome)',
        'Mean Score', 'Comments & Recommendations'
    ]);

    $export_query = "
        SELECT
            f.*,
            COALESCE(o.office_name, 'Unassigned Office') AS office_name,
            COALESCE(o.office_type, 'N/A') AS office_type
        FROM arta_feedback f
        LEFT JOIN offices o ON f.office_id = o.id
        ORDER BY f.submission_timestamp DESC
    ";
    $exp_res = $conn->query($export_query);
    while ($row = $exp_res->fetch_assoc()) {
        $sqd_vals = [
            $row['sqd1_responsiveness'], $row['sqd2_reliability'], $row['sqd3_access_facilities'],
            $row['sqd4_communication'], $row['sqd5_costs'], $row['sqd6_integrity'],
            $row['sqd7_assurance'], $row['sqd8_outcome']
        ];
        $valid_sum = 0;
        $valid_cnt = 0;
        foreach ($sqd_vals as $v) {
            if ($v > 0) {
                $valid_sum += $v;
                $valid_cnt++;
            }
        }
        $mean = $valid_cnt > 0 ? round($valid_sum / $valid_cnt, 2) : 'N/A';

        fputcsv($output, [
            $row['feedback_id'],
            $row['submission_timestamp'],
            $row['office_name'],
            $row['office_type'],
            $row['service_accessed'],
            !empty($row['user_id']) ? 'Authenticated (' . $row['user_id'] . ')' : 'Guest Client',
            $row['client_type'],
            $row['age_group'],
            $row['sex'],
            $row['region'],
            $row['cc1'],
            $row['cc2'],
            $row['cc3'],
            $row['sqd1_responsiveness'] > 0 ? $row['sqd1_responsiveness'] : 'N/A',
            $row['sqd2_reliability'] > 0 ? $row['sqd2_reliability'] : 'N/A',
            $row['sqd3_access_facilities'] > 0 ? $row['sqd3_access_facilities'] : 'N/A',
            $row['sqd4_communication'] > 0 ? $row['sqd4_communication'] : 'N/A',
            $row['sqd5_costs'] > 0 ? $row['sqd5_costs'] : 'N/A',
            $row['sqd6_integrity'] > 0 ? $row['sqd6_integrity'] : 'N/A',
            $row['sqd7_assurance'] > 0 ? $row['sqd7_assurance'] : 'N/A',
            $row['sqd8_outcome'] > 0 ? $row['sqd8_outcome'] : 'N/A',
            $mean,
            $row['comments_recommendations']
        ]);
    }
    fclose($output);
    exit();
}

// 4. Fetch Offices for Filter
$offices_list = [];
$offices_res = $conn->query("SELECT id, office_name, office_type FROM offices ORDER BY office_name ASC");
if ($offices_res) {
    while ($row = $offices_res->fetch_assoc()) {
        $offices_list[] = $row;
    }
}

// 5. Fetch Complete Feedback Stream with Dynamic ARTA Formula
$feedbacks_query = "
    SELECT
        f.*,
        COALESCE(o.office_name, 'Unassigned Office') AS office_name,
        COALESCE(o.office_type, 'N/A') AS office_type,
        ROUND((
            (
                COALESCE(NULLIF(f.sqd1_responsiveness, 0), 0) +
                COALESCE(NULLIF(f.sqd2_reliability, 0), 0) +
                COALESCE(NULLIF(f.sqd3_access_facilities, 0), 0) +
                COALESCE(NULLIF(f.sqd4_communication, 0), 0) +
                COALESCE(NULLIF(f.sqd5_costs, 0), 0) +
                COALESCE(NULLIF(f.sqd6_integrity, 0), 0) +
                COALESCE(NULLIF(f.sqd7_assurance, 0), 0) +
                COALESCE(NULLIF(f.sqd8_outcome, 0), 0)
            ) / NULLIF(
                (CASE WHEN f.sqd1_responsiveness > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd2_reliability > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd3_access_facilities > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd4_communication > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd5_costs > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd6_integrity > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd7_assurance > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd8_outcome > 0 THEN 1 ELSE 0 END), 0
            )
        ), 2) AS calculated_mean
    FROM arta_feedback f
    LEFT JOIN offices o ON f.office_id = o.id
    ORDER BY f.submission_timestamp DESC
";
$feedbacks = $conn->query($feedbacks_query)->fetch_all(MYSQLI_ASSOC);

// Page metadata for admin_header.php
$page_title    = 'Client Feedback Stream';
$page_crumb    = 'Performance / Client Feedback';
$page_subtitle = 'Real-time submission audit logs and ARTA MC 2022-05 evaluations';
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
            --gold-light: #FFF8E6;
            --bg: #F8FAFC;
            --surface: #FFFFFF;
            --border: #E2E8F0;
            --border-strong: #CBD5E1;
            --text-heading: #0F172A;
            --text-body: #475569;
            --text-muted: #94A3B8;
            --success: #10B981;
            --success-bg: #ECFDF5;
            --danger: #EF4444;
            --danger-bg: #FEF2F2;

            --sidebar-width: 272px;
            --header-height: 72px;
        }

        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        * {
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-text-size-adjust: 100%;
        }

        body {
            background-color: var(--bg);
            color: var(--text-body);
            display: flex;
            min-height: 100vh;
        }

        /* ============================================================
           MAIN SHELL
           ============================================================ */
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

        /* ============================================================
           TOOLBAR
           ============================================================ */
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
            font-size: 19px;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.4px;
            margin: 0;
        }
        .toolbar-title p {
            font-size: 12px;
            color: var(--text-muted);
            margin: 2px 0 0;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: var(--maroon);
            color: #FFFFFF;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.18s, transform 0.12s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-primary:hover { background: var(--maroon-dark); }
        .btn-primary:active { transform: scale(0.98); }

        .btn-secondary {
            background: #FFFFFF;
            color: var(--text-heading);
            border: 1.5px solid var(--border);
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.18s, border-color 0.18s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            font-family: inherit;
        }
        .btn-secondary:hover { background: #F1F5F9; border-color: var(--border-strong); }

        /* ============================================================
           FILTER PANEL
           ============================================================ */
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

        .filter-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            flex-wrap: wrap;
        }

        .input-live-search, .select-live-filter {
            padding: 9px 12px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 12.5px;
            background: #F8FAFC;
            color: var(--text-heading);
            font-weight: 500;
            outline: none;
            transition: border-color 0.18s, background 0.18s;
            font-family: inherit;
        }
        .input-live-search { min-width: 240px; flex: 1; }
        .input-live-search:focus, .select-live-filter:focus {
            border-color: var(--maroon);
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(122, 0, 0, 0.06);
        }

        .search-count-pill {
            font-size: 11.5px;
            color: var(--text-muted);
            font-weight: 600;
            white-space: nowrap;
        }

        /* ============================================================
           TABLE
           ============================================================ */
        .panel-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            min-height: 0;
        }

        .table-scroll-wrap {
            flex: 1;
            min-height: 400px;
            max-height: calc(100vh - 340px);
            overflow-y: auto;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            -webkit-overflow-scrolling: touch;
        }

        .table-scroll-wrap::-webkit-scrollbar { width: 6px; height: 6px; }
        .table-scroll-wrap::-webkit-scrollbar-track { background: #F1F5F9; }
        .table-scroll-wrap::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
            min-width: 760px;
        }

        .table-scroll-wrap thead th {
            position: sticky;
            top: 0;
            background-color: #F1F5F9;
            z-index: 10;
            box-shadow: 0 1px 0 var(--border);
        }

        th {
            background-color: #F1F5F9;
            color: var(--text-muted);
            font-weight: 700;
            padding: 11px 14px;
            border-bottom: 1px solid var(--border);
            text-transform: uppercase;
            font-size: 10.5px;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            color: var(--text-body);
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background-color: #FAFAFA; }

        .badge-type {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            background: #F1F5F9;
            color: var(--text-body);
            border: 1px solid #E2E8F0;
        }

        .badge-guest {
            background: #FFFBEB;
            color: #B45309;
            border-color: #FDE68A;
        }
        .badge-user {
            background: #EFF6FF;
            color: #1D4ED8;
            border-color: #BFDBFE;
        }

        .score-pill {
            display: inline-block;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
        }
        .score-high { background: var(--success-bg); color: var(--success); }
        .score-med { background: var(--gold-light); color: var(--gold); }
        .score-low { background: var(--danger-bg); color: var(--danger); }

        .comment-snippet {
            display: block;
            max-width: 280px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 12px;
            line-height: 1.4;
        }

        .action-btns {
            display: flex;
            align-items: center;
            gap: 6px;
            justify-content: flex-end;
        }

        .btn-table-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            transition: filter 0.18s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-family: inherit;
        }
        .btn-table-action:hover { filter: brightness(0.95); }

        .btn-view { background: #EFF6FF; color: #2563EB; border-color: #BFDBFE; }
        .btn-delete { background: var(--danger-bg); color: var(--danger); border-color: #FECACA; }

        /* ============================================================
           TOAST
           ============================================================ */
        .toast-container {
            position: fixed;
            top: calc(var(--header-height) + 16px);
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
            max-width: 420px;
            width: calc(100% - 40px);
        }

        .toast {
            pointer-events: auto;
            background: #FFFFFF;
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border-left: 5px solid transparent;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-heading);
            animation: toastSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .toast-success { border-left-color: var(--success); }
        .toast-success .toast-icon { color: var(--success); }
        .toast-error { border-left-color: var(--danger); }
        .toast-error .toast-icon { color: var(--danger); }

        .toast-content {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            line-height: 1.45;
        }

        .toast-icon { flex-shrink: 0; display: flex; align-items: center; }
        .toast-close {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            padding: 0;
            display: flex;
            align-items: center;
        }

        @keyframes toastSlideIn {
            from { opacity: 0; transform: translateX(40px) scale(0.95); }
            to   { opacity: 1; transform: translateX(0) scale(1); }
        }
        @keyframes toastSlideOut {
            from { opacity: 1; transform: translateX(0) scale(1); }
            to   { opacity: 0; transform: translateX(40px) scale(0.95); }
        }

        /* ============================================================
           MODAL — REDESIGNED
           ============================================================ */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.68);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            z-index: 1050;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }

        .modal-box {
            background: var(--surface);
            border-radius: 18px;
            width: 100%;
            max-width: 720px;
            max-height: calc(100vh - 40px);
            max-height: calc(100dvh - 40px);
            border: 1px solid var(--border);
            box-shadow: 0 30px 60px -12px rgba(15, 23, 42, 0.35),
                        0 0 0 1px rgba(255,255,255,0.5) inset;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: modalIn 0.28s cubic-bezier(0.34, 1.3, 0.64, 1);
        }

        @keyframes modalIn {
            from { opacity: 0; transform: translateY(14px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ---------- Modal header ---------- */
        .modal-header {
            flex-shrink: 0;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            background: linear-gradient(180deg, #FFFFFF 0%, #FCFCFD 100%);
        }

        .modal-header-left {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            min-width: 0;
            flex: 1;
        }

        .modal-header-icon {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
            color: var(--maroon);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid #FECACA;
        }
        .modal-header-icon svg { width: 20px; height: 20px; }

        .modal-header-text { min-width: 0; padding-top: 1px; }
        .modal-header-text h3 {
            font-size: 16.5px;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.3px;
            margin: 0;
            line-height: 1.2;
        }
        .modal-header-text p {
            font-size: 12px;
            color: var(--text-muted);
            margin: 3px 0 0;
            line-height: 1.4;
        }

        .btn-close-modal {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            border-radius: 9px;
            background: transparent;
            border: 1px solid transparent;
            color: var(--text-muted);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.18s, color 0.18s, border-color 0.18s;
            padding: 0;
        }
        .btn-close-modal:hover {
            background: #F1F5F9;
            color: var(--text-heading);
            border-color: var(--border);
        }
        .btn-close-modal svg { width: 18px; height: 18px; }

        /* ---------- Modal form shell ---------- */
        .modal-form {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;               /* CRITICAL */
            overflow: hidden;
        }

        .modal-body {
            flex: 1 1 auto;
            min-height: 0;               /* CRITICAL: body scrolls, not parent */
            overflow-y: auto;
            overflow-x: hidden;
            padding: 22px;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }

        /* Section cards */
        .form-section { margin-bottom: 22px; }
        .form-section:last-child { margin-bottom: 0; }

        .form-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.1px;
            text-transform: uppercase;
            color: var(--maroon);
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #FECACA;
        }

        .section-num {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--maroon);
            color: #FFFFFF;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10.5px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 16px;
            font-size: 12.5px;
        }
        .info-item { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
        .info-label {
            font-size: 10.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .info-val {
            font-weight: 600;
            color: var(--text-heading);
            line-height: 1.4;
            word-break: break-word;
        }

        .sqd-review-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        .sqd-card-mini {
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 8px;
            text-align: center;
            transition: border-color 0.18s, background 0.18s;
        }
        .sqd-card-mini:hover {
            border-color: var(--gold);
            background: #FFFCF5;
        }
        .sqd-card-mini span {
            display: block;
            font-size: 9.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.25;
        }
        .sqd-card-mini strong {
            display: block;
            font-size: 14px;
            margin-top: 4px;
            color: var(--text-heading);
            font-weight: 800;
        }

        .verbatim-box {
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-left: 3px solid var(--gold);
            border-radius: 8px;
            padding: 14px 16px;
            font-size: 13px;
            line-height: 1.6;
            color: var(--text-heading);
            font-style: italic;
        }

        /* ---------- Modal footer ---------- */
        .modal-footer {
            flex-shrink: 0;
            padding: 14px 22px;
            background: #FAFBFC;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .modal-footer .btn-primary,
        .modal-footer .btn-secondary {
            min-width: 110px;
            justify-content: center;
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1024px) {
            .admin-main-wrap {
                margin-left: 0;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .admin-container { padding: calc(var(--header-height) + 12px) 14px 18px; }
            .toolbar {
                flex-direction: column;
                align-items: stretch;
                padding: 14px 16px;
            }
            .toolbar-actions {
                width: 100%;
                gap: 8px;
            }
            .toolbar-actions .btn-primary,
            .toolbar-actions .btn-secondary {
                flex: 1;
                justify-content: center;
            }
            .filter-panel { padding: 10px; }
            .filter-controls {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
            }
            .input-live-search, .select-live-filter {
                width: 100%;
                min-width: 0;
            }
            .panel-card { padding: 10px; }
            .table-scroll-wrap { max-height: calc(100vh - 400px); }

            /* Hide the "Showing X of Y" pill on small screens */
            #searchCountBadge { display: none !important; }

            .info-grid { grid-template-columns: 1fr; }
            .sqd-review-grid { grid-template-columns: repeat(2, 1fr); }

            .modal-overlay { padding: 14px; }
            .modal-box { max-height: calc(100dvh - 28px); }
        }

        /* ============================================================
           MOBILE — fullscreen modal, iOS zoom fix
           ============================================================ */
        @media (max-width: 640px) {
            .modal-overlay {
                padding: 0;
                align-items: stretch;
                justify-content: stretch;
                background: #FFFFFF;
                -webkit-backdrop-filter: none;
                backdrop-filter: none;
            }

            .modal-box {
                max-width: 100%;
                width: 100%;
                max-height: 100dvh;
                height: 100dvh;
                border-radius: 0;
                border: none;
                box-shadow: none;
                animation: modalSlideUp 0.3s cubic-bezier(0.32, 0.72, 0, 1);
            }

            @keyframes modalSlideUp {
                from { opacity: 0; transform: translateY(24px); }
                to   { opacity: 1; transform: translateY(0); }
            }

            .modal-header {
                padding: 16px;
                padding-top: calc(16px + env(safe-area-inset-top, 0px));
                border-radius: 0;
            }
            .modal-header-icon { width: 38px; height: 38px; border-radius: 10px; }
            .modal-header-icon svg { width: 18px; height: 18px; }
            .modal-header-text h3 { font-size: 15.5px; }
            .modal-header-text p  { font-size: 11.5px; }

            .modal-body {
                padding: 18px 16px;
                -webkit-overflow-scrolling: touch;
            }

            .modal-footer {
                padding: 12px 16px;
                padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
                border-radius: 0;
            }
            .modal-footer .btn-secondary { flex: 1; min-width: 0; }

            .form-section { margin-bottom: 18px; }

            /* Prevent iOS focus-zoom inside the modal */
            .modal-box input,
            .modal-box select,
            .modal-box textarea {
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            :root { --header-height: 66px; }
            .admin-container { padding: calc(var(--header-height) + 10px) 10px 16px; }
            .toolbar-title h2 { font-size: 17px; }
            .toolbar-title p { font-size: 11.5px; }
            .filter-panel { padding: 10px; }
            .toast-container {
                top: calc(var(--header-height) + 10px);
                right: 10px;
                left: 10px;
                max-width: none;
                width: auto;
            }
            .toast { font-size: 12.5px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .admin-main-wrap, .toast, .modal-box {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="toast-container" id="toastContainer"></div>

    <?php
    // Sidebar first (defines toggleAdminSidebar)
    $sidebar_path = __DIR__ . '/admin_sidebar.php';
    if (file_exists($sidebar_path)) require_once $sidebar_path;

    // Header second (wraps toggle to sync icon)
    $header_path = __DIR__ . '/admin_header.php';
    if (file_exists($header_path)) require_once $header_path;
    ?>

    <div class="admin-main-wrap">
        <main class="admin-container">

            <div class="fixed-header-deck">

                <section class="toolbar">
                    <div class="toolbar-title">
                        <h2>Client Feedback Stream</h2>
                        <p>Real-time submission audit logs and ARTA MC 2022-05 harmonized evaluations</p>
                    </div>

                    <div class="toolbar-actions">
                        <button class="btn-secondary" onclick="window.print()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print Stream
                        </button>
                        <a href="admin_feedbacks.php?export=csv" class="btn-primary">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Export CSV
                        </a>
                    </div>
                </section>

                <section class="filter-panel">
                    <div class="filter-controls">
                        <input type="text" id="liveSearchInput" class="input-live-search" placeholder="Search by office, service, client, or verbatim keyword..." autocomplete="off">

                        <select id="liveOfficeSelect" class="select-live-filter">
                            <option value="">All Visited Offices</option>
                            <?php foreach ($offices_list as $off): ?>
                                <option value="<?php echo htmlspecialchars(trim($off['office_name'])); ?>">
                                    <?php echo htmlspecialchars(trim($off['office_name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select id="liveClientSelect" class="select-live-filter">
                            <option value="">All Client Roles</option>
                            <option value="citizen">Citizen</option>
                            <option value="business">Business</option>
                            <option value="government">Government</option>
                        </select>

                        <select id="liveRatingSelect" class="select-live-filter">
                            <option value="">All Ratings</option>
                            <option value="high">Positive (4.00 - 5.00)</option>
                            <option value="med">Satisfactory (3.00 - 3.99)</option>
                            <option value="low">Needs Action (&lt; 3.00)</option>
                        </select>
                    </div>
                    <div class="search-count-pill" id="searchCountBadge">
                        Showing <?php echo count($feedbacks); ?> of <?php echo count($feedbacks); ?> reviews
                    </div>
                </section>

            </div>

            <section class="panel-card">
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 140px;">Date &amp; Time</th>
                                <th>Office &amp; Service Accessed</th>
                                <th style="width: 130px;">Client Role</th>
                                <th style="width: 120px;">SQD Mean</th>
                                <th>Verbatim Feedback Snippet</th>
                                <th style="width: 130px; text-align: right;">Management</th>
                            </tr>
                        </thead>
                        <tbody id="feedbackTableBody">
                            <?php if (!empty($feedbacks)): ?>
                                <?php foreach ($feedbacks as $fb): ?>
                                    <?php
                                        $mean = (float)($fb['calculated_mean'] ?? 0);
                                        $pill_class = $mean >= 4.0 ? 'score-high' : ($mean >= 3.0 ? 'score-med' : ($mean > 0 ? 'score-low' : ''));
                                        $sentiment_tier = $mean >= 4.0 ? 'high' : ($mean >= 3.0 ? 'med' : ($mean > 0 ? 'low' : 'none'));
                                        $is_guest = empty($fb['user_id']);
                                    ?>
                                    <tr class="feedback-row"
                                        data-search="<?php echo strtolower(htmlspecialchars(trim($fb['office_name'] . ' ' . $fb['service_accessed'] . ' ' . $fb['client_type'] . ' ' . $fb['comments_recommendations']))); ?>"
                                        data-office="<?php echo strtolower(htmlspecialchars(trim($fb['office_name']))); ?>"
                                        data-client="<?php echo strtolower(htmlspecialchars(trim($fb['client_type'] ?? ''))); ?>"
                                        data-tier="<?php echo $sentiment_tier; ?>">

                                        <td style="white-space: nowrap; font-size: 12px; color: var(--text-muted);">
                                            <?php echo date('M d, Y h:i A', strtotime($fb['submission_timestamp'])); ?>
                                        </td>

                                        <td>
                                            <strong style="color: var(--text-heading); display: block;">
                                                <?php echo htmlspecialchars($fb['office_name']); ?>
                                            </strong>
                                            <small style="color: var(--text-muted);">
                                                <?php echo htmlspecialchars($fb['service_accessed']); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <span class="badge-type <?php echo $is_guest ? 'badge-guest' : 'badge-user'; ?>">
                                                <?php echo htmlspecialchars($fb['client_type'] ?? 'Citizen'); ?>
                                            </span>
                                            <span style="display: block; font-size: 10px; color: var(--text-muted); margin-top: 2px;">
                                                <?php echo $is_guest ? 'Guest Visitor' : 'UID: ' . htmlspecialchars($fb['user_id']); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if ($mean > 0): ?>
                                                <span class="score-pill <?php echo $pill_class; ?>">
                                                    ★ <?php echo number_format($mean, 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: var(--text-muted);">N/A</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if (!empty($fb['comments_recommendations'])): ?>
                                                <span class="comment-snippet" title="<?php echo htmlspecialchars($fb['comments_recommendations']); ?>">
                                                    "<?php echo htmlspecialchars($fb['comments_recommendations']); ?>"
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 11.5px; font-style: italic;">
                                                    No written comments recorded.
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td style="text-align: right;">
                                            <div class="action-btns">
                                                <button type="button" class="btn-table-action btn-view"
                                                    onclick='inspectFeedback(<?php echo json_encode($fb, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                                    Inspect
                                                </button>

                                                <form method="POST" action="admin_feedbacks.php" style="display: inline;"
                                                    onsubmit="return confirm('Permanently remove feedback #<?php echo $fb['feedback_id']; ?>? This cannot be undone.');">
                                                    <input type="hidden" name="action" value="delete_feedback">
                                                    <input type="hidden" name="feedback_id" value="<?php echo $fb['feedback_id']; ?>">
                                                    <button type="submit" class="btn-table-action btn-delete">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="noDataRow">
                                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                        No client feedback submissions recorded yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr id="noResultsRow" style="display: none;">
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    No evaluations match your search or filter settings.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

    <!-- ============================================================
         INSPECTION MODAL — REDESIGNED
         ============================================================ -->
    <div class="modal-overlay" id="feedbackDetailModal">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="feedbackModalTitle">

            <!-- Header -->
            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-header-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="modal-header-text">
                        <h3 id="feedbackModalTitle">ARTA Evaluation Inspection</h3>
                        <p>Full breakdown of the MC 2022-05 compliant submission</p>
                    </div>
                </div>
                <button type="button" class="btn-close-modal" onclick="closeModal('feedbackDetailModal')" aria-label="Close">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Form/body shell -->
            <div class="modal-form">
                <div class="modal-body">

                    <!-- SECTION 1 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">1</span>
                            <span>Transaction Information</span>
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Office / College</span>
                                <span class="info-val" id="modalOfficeName">—</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Service Accessed</span>
                                <span class="info-val" id="modalServiceAccessed">—</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Date &amp; Time Audited</span>
                                <span class="info-val" id="modalTimestamp">—</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Harmonized SQD Score</span>
                                <span class="info-val" id="modalMeanScore">—</span>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">2</span>
                            <span>Client Demographic Classification</span>
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Client Classification</span>
                                <span class="info-val" id="modalClientType">—</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Account Routing</span>
                                <span class="info-val" id="modalAccountType">—</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Age &amp; Sex</span>
                                <span class="info-val" id="modalAgeSex">—</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Region</span>
                                <span class="info-val" id="modalRegion">—</span>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">3</span>
                            <span>Citizen's Charter (CC) Triad Breakdown</span>
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">CC1: Awareness</span>
                                <span class="info-val" id="modalCC1">—</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">CC2: Visibility</span>
                                <span class="info-val" id="modalCC2">—</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">CC3: Helpfulness</span>
                                <span class="info-val" id="modalCC3">—</span>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">4</span>
                            <span>8 Harmonized Service Quality Dimensions (SQD)</span>
                        </div>
                        <div class="sqd-review-grid">
                            <div class="sqd-card-mini"><span>SQD1 · Time</span><strong id="modalSQD1">—</strong></div>
                            <div class="sqd-card-mini"><span>SQD2 · Requirements</span><strong id="modalSQD2">—</strong></div>
                            <div class="sqd-card-mini"><span>SQD3 · Steps</span><strong id="modalSQD3">—</strong></div>
                            <div class="sqd-card-mini"><span>SQD4 · Information</span><strong id="modalSQD4">—</strong></div>
                            <div class="sqd-card-mini"><span>SQD5 · Fees</span><strong id="modalSQD5">—</strong></div>
                            <div class="sqd-card-mini"><span>SQD6 · Integrity</span><strong id="modalSQD6">—</strong></div>
                            <div class="sqd-card-mini"><span>SQD7 · Staff</span><strong id="modalSQD7">—</strong></div>
                            <div class="sqd-card-mini"><span>SQD8 · Outcome</span><strong id="modalSQD8">—</strong></div>
                        </div>
                    </div>

                    <!-- SECTION 5 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">5</span>
                            <span>Client Remarks &amp; Recommendations</span>
                        </div>
                        <div class="verbatim-box" id="modalComments">
                            No verbatim feedback provided.
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('feedbackDetailModal')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         SCRIPTS
         ============================================================ -->
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
                <div class="toast-content">
                    <div class="toast-icon">${iconSvg}</div>
                    <span>${message}</span>
                </div>
                <button class="toast-close" onclick="dismissToast(this.parentElement)">&times;</button>
            `;

            container.appendChild(toast);
            setTimeout(() => dismissToast(toast), 4500);
        }

        function dismissToast(toastElement) {
            if (!toastElement) return;
            toastElement.style.animation = 'toastSlideOut 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards';
            setTimeout(() => {
                if (toastElement.parentElement) {
                    toastElement.parentElement.removeChild(toastElement);
                }
            }, 250);
        }

        <?php if (!empty($toast_message)): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showToast("<?php echo addslashes($toast_message); ?>", "<?php echo $toast_type; ?>");
            });
        <?php endif; ?>

        /* ============================================================
           INSPECTION MODAL
           ============================================================ */
        function inspectFeedback(data) {
            document.getElementById('modalOfficeName').textContent = data.office_name || 'Unassigned';
            document.getElementById('modalServiceAccessed').textContent = data.service_accessed || '—';
            document.getElementById('modalTimestamp').textContent = data.submission_timestamp || '—';

            const mean = parseFloat(data.calculated_mean || 0);
            document.getElementById('modalMeanScore').textContent = mean > 0 ? `★ ${mean.toFixed(2)} / 5.00` : 'N/A';

            document.getElementById('modalClientType').textContent = data.client_type || 'Citizen';
            document.getElementById('modalAccountType').textContent = data.user_id
                ? `Authenticated (ID: ${data.user_id})`
                : 'Guest Visitor';
            document.getElementById('modalAgeSex').textContent = `${data.age_group || '—'}, ${data.sex || '—'}`;
            document.getElementById('modalRegion').textContent = data.region || 'Region IX';

            const cc1Map = {1: '1. Saw Office CC', 2: '2. Knew CC, did not see', 3: '3. Learned upon visit', 4: '4. Did not know / see'};
            const cc2Map = {1: 'Easy to see', 2: 'Somewhat easy', 3: 'Difficult to see', 4: 'Not visible', 5: 'N/A'};
            const cc3Map = {1: 'Helped very much', 2: 'Somewhat helped', 3: 'Did not help', 4: 'N/A'};

            document.getElementById('modalCC1').textContent = cc1Map[data.cc1] || data.cc1 || '—';
            document.getElementById('modalCC2').textContent = cc2Map[data.cc2] || data.cc2 || '—';
            document.getElementById('modalCC3').textContent = cc3Map[data.cc3] || data.cc3 || '—';

            const formatSQD = val => parseInt(val) > 0 ? `${val} / 5` : 'N/A';
            document.getElementById('modalSQD1').textContent = formatSQD(data.sqd1_responsiveness);
            document.getElementById('modalSQD2').textContent = formatSQD(data.sqd2_reliability);
            document.getElementById('modalSQD3').textContent = formatSQD(data.sqd3_access_facilities);
            document.getElementById('modalSQD4').textContent = formatSQD(data.sqd4_communication);
            document.getElementById('modalSQD5').textContent = formatSQD(data.sqd5_costs);
            document.getElementById('modalSQD6').textContent = formatSQD(data.sqd6_integrity);
            document.getElementById('modalSQD7').textContent = formatSQD(data.sqd7_assurance);
            document.getElementById('modalSQD8').textContent = formatSQD(data.sqd8_outcome);

            const commentsBox = document.getElementById('modalComments');
            if (data.comments_recommendations && data.comments_recommendations.trim() !== '') {
                commentsBox.textContent = `"${data.comments_recommendations}"`;
                commentsBox.style.fontStyle = 'italic';
                commentsBox.style.borderLeftColor = 'var(--gold)';
            } else {
                commentsBox.textContent = 'No verbatim comments or recommendations provided.';
                commentsBox.style.fontStyle = 'italic';
                commentsBox.style.borderLeftColor = 'var(--border)';
            }

            document.getElementById('feedbackDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
        }

        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                closeModal(e.target.id);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('feedbackDetailModal');
                if (modal.classList.contains('active')) closeModal('feedbackDetailModal');
            }
        });

        /* ============================================================
           LIVE FILTER
           ============================================================ */
        const liveSearchInput = document.getElementById('liveSearchInput');
        const liveOfficeSelect = document.getElementById('liveOfficeSelect');
        const liveClientSelect = document.getElementById('liveClientSelect');
        const liveRatingSelect = document.getElementById('liveRatingSelect');
        const feedbackRows = document.querySelectorAll('#feedbackTableBody tr.feedback-row');
        const noResultsRow = document.getElementById('noResultsRow');
        const searchCountBadge = document.getElementById('searchCountBadge');

        function runLiveFilter() {
            const term = liveSearchInput.value.toLowerCase().trim();
            const office = liveOfficeSelect.value.toLowerCase().trim();
            const client = liveClientSelect.value.toLowerCase().trim();
            const tier = liveRatingSelect.value.toLowerCase().trim();
            let matches = 0;

            feedbackRows.forEach(row => {
                const rowSearch = row.getAttribute('data-search') || '';
                const rowOffice = row.getAttribute('data-office') || '';
                const rowClient = row.getAttribute('data-client') || '';
                const rowTier = row.getAttribute('data-tier') || '';

                const matchesTerm = (term === '') || rowSearch.includes(term);
                const matchesOffice = (office === '') || (rowOffice === office);
                const matchesClient = (client === '') || (rowClient === client);
                const matchesTier = (tier === '') || (rowTier === tier);

                if (matchesTerm && matchesOffice && matchesClient && matchesTier) {
                    row.style.display = '';
                    matches++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noResultsRow) {
                noResultsRow.style.display = (matches === 0 && feedbackRows.length > 0) ? '' : 'none';
            }
            if (searchCountBadge) {
                searchCountBadge.textContent = `Showing ${matches} of ${feedbackRows.length} reviews`;
            }
        }

        liveSearchInput.addEventListener('input', runLiveFilter);
        liveOfficeSelect.addEventListener('change', runLiveFilter);
        liveClientSelect.addEventListener('change', runLiveFilter);
        liveRatingSelect.addEventListener('change', runLiveFilter);
    </script>
</body>
</html>