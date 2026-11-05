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

// Self-healing: Ensure office_classifications table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS office_classifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Auto-sync existing classifications
$conn->query("
    INSERT IGNORE INTO office_classifications (name)
    SELECT DISTINCT TRIM(office_type)
    FROM offices
    WHERE office_type IS NOT NULL AND TRIM(office_type) != ''
");

$toast_message = '';
$toast_type = '';

// 2. Handle CRUD Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- OFFICE ACTIONS ---
    if ($action === 'create_office') {
        $office_name = trim($_POST['office_name'] ?? '');
        $office_type = trim($_POST['office_type'] ?? '');

        if (empty($office_name) || empty($office_type)) {
            $toast_message = "Office name and classification are required.";
            $toast_type = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO offices (office_name, office_type) VALUES (?, ?)");
            $stmt->bind_param("ss", $office_name, $office_type);
            if ($stmt->execute()) {
                $toast_message = "Office unit '{$office_name}' added successfully.";
                $toast_type = 'success';
            } else {
                $toast_message = "Database error: " . $conn->error;
                $toast_type = 'error';
            }
            $stmt->close();
        }
    } elseif ($action === 'update_office') {
        $office_id   = intval($_POST['office_id'] ?? 0);
        $office_name = trim($_POST['office_name'] ?? '');
        $office_type = trim($_POST['office_type'] ?? '');

        if ($office_id <= 0 || empty($office_name) || empty($office_type)) {
            $toast_message = "All fields are required to update this office.";
            $toast_type = 'error';
        } else {
            $stmt = $conn->prepare("UPDATE offices SET office_name = ?, office_type = ? WHERE id = ?");
            $stmt->bind_param("ssi", $office_name, $office_type, $office_id);
            if ($stmt->execute()) {
                $toast_message = "Office updated successfully.";
                $toast_type = 'success';
            } else {
                $toast_message = "Update failed: " . $conn->error;
                $toast_type = 'error';
            }
            $stmt->close();
        }
    } elseif ($action === 'delete_office') {
        $office_id = intval($_POST['office_id'] ?? 0);

        if ($office_id > 0) {
            $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM arta_feedback WHERE office_id = ?");
            $check_stmt->bind_param("i", $office_id);
            $check_stmt->execute();
            $check_res = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if (($check_res['count'] ?? 0) > 0) {
                $toast_message = "Cannot delete: This office contains " . number_format($check_res['count']) . " recorded feedback reviews.";
                $toast_type = 'error';
            } else {
                $del_stmt = $conn->prepare("DELETE FROM offices WHERE id = ?");
                $del_stmt->bind_param("i", $office_id);
                if ($del_stmt->execute()) {
                    $toast_message = "Office removed successfully.";
                    $toast_type = 'success';
                } else {
                    $toast_message = "Deletion failed: " . $conn->error;
                    $toast_type = 'error';
                }
                $del_stmt->close();
            }
        }
    }

    // --- CLASSIFICATION CRUD ACTIONS ---
    elseif ($action === 'create_classification') {
        $class_name = trim($_POST['classification_name'] ?? '');
        if (empty($class_name)) {
            $toast_message = "Classification name cannot be blank.";
            $toast_type = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO office_classifications (name) VALUES (?)");
            $stmt->bind_param("s", $class_name);
            if ($stmt->execute()) {
                $toast_message = "Classification '{$class_name}' added.";
                $toast_type = 'success';
            } else {
                $toast_message = "Failed to add classification (it may already exist).";
                $toast_type = 'error';
            }
            $stmt->close();
        }
    } elseif ($action === 'update_classification') {
        $class_id = intval($_POST['classification_id'] ?? 0);
        $new_name = trim($_POST['classification_name'] ?? '');
        $old_name = trim($_POST['old_classification_name'] ?? '');

        if ($class_id <= 0 || empty($new_name) || empty($old_name)) {
            $toast_message = "Valid classification title is required.";
            $toast_type = 'error';
        } else {
            $stmt = $conn->prepare("UPDATE office_classifications SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $new_name, $class_id);
            if ($stmt->execute()) {
                $cascade = $conn->prepare("UPDATE offices SET office_type = ? WHERE office_type = ?");
                $cascade->bind_param("ss", $new_name, $old_name);
                $cascade->execute();
                $cascade->close();

                $toast_message = "Classification updated and synced across all offices.";
                $toast_type = 'success';
            } else {
                $toast_message = "Update failed: " . $conn->error;
                $toast_type = 'error';
            }
            $stmt->close();
        }
    } elseif ($action === 'delete_classification') {
        $class_id   = intval($_POST['classification_id'] ?? 0);
        $class_name = trim($_POST['classification_name'] ?? '');

        if ($class_id > 0 && !empty($class_name)) {
            $chk_offices = $conn->prepare("SELECT COUNT(*) AS total_offices FROM offices WHERE office_type = ?");
            $chk_offices->bind_param("s", $class_name);
            $chk_offices->execute();
            $assigned_offices = (int)$chk_offices->get_result()->fetch_assoc()['total_offices'];
            $chk_offices->close();

            $chk_reviews = $conn->prepare("
                SELECT COUNT(f.feedback_id) AS total_reviews
                FROM arta_feedback f
                JOIN offices o ON f.office_id = o.id
                WHERE o.office_type = ?
            ");
            $chk_reviews->bind_param("s", $class_name);
            $chk_reviews->execute();
            $total_reviews = (int)$chk_reviews->get_result()->fetch_assoc()['total_reviews'];
            $chk_reviews->close();

            if ($assigned_offices > 0 || $total_reviews > 0) {
                $block_reasons = [];
                if ($assigned_offices > 0) $block_reasons[] = "{$assigned_offices} assigned office(s)";
                if ($total_reviews > 0) $block_reasons[] = "{$total_reviews} recorded review(s)";
                $toast_message = "Cannot delete '{$class_name}': It has " . implode(" and ", $block_reasons) . ". Reassign or resolve them first.";
                $toast_type = 'error';
            } else {
                $stmt = $conn->prepare("DELETE FROM office_classifications WHERE id = ?");
                $stmt->bind_param("i", $class_id);
                if ($stmt->execute()) {
                    $toast_message = "Classification deleted successfully.";
                    $toast_type = 'success';
                } else {
                    $toast_message = "Deletion failed: " . $conn->error;
                    $toast_type = 'error';
                }
                $stmt->close();
            }
        }
    }
}

// 3. Fetch Master Data
$class_query = "
    SELECT
        c.id,
        c.name,
        COUNT(DISTINCT o.id) AS assigned_count,
        COUNT(f.feedback_id) AS review_count
    FROM office_classifications c
    LEFT JOIN offices o ON o.office_type = c.name
    LEFT JOIN arta_feedback f ON f.office_id = o.id
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
";
$classifications = $conn->query($class_query)->fetch_all(MYSQLI_ASSOC);

// 4. Offices Query with dynamic SQD denominator
$offices_query = "
    SELECT
        o.id,
        o.office_name,
        o.office_type,
        COUNT(f.feedback_id) AS total_evaluations,
        ROUND(AVG(
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
        ), 2) AS mean_score
    FROM offices o
    LEFT JOIN arta_feedback f ON o.id = f.office_id
    GROUP BY o.id, o.office_name, o.office_type
    ORDER BY o.office_name ASC
";
$offices = $conn->query($offices_query)->fetch_all(MYSQLI_ASSOC);

// Page metadata for admin_header.php
$page_title    = 'Offices & Service Units';
$page_crumb    = 'Institutional Setup / Offices';
$page_subtitle = 'Directory and classification setup for ARTA frontline evaluation';
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
        .input-live-search { min-width: 250px; flex: 1; }
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
            min-width: 580px;
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

        .btn-edit { background: #EFF6FF; color: #2563EB; border-color: #BFDBFE; }
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
            max-width: 560px;
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
        .modal-box.modal-lg { max-width: 640px; }

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
            min-height: 0;             /* CRITICAL */
            overflow: hidden;
        }

        .modal-body {
            flex: 1 1 auto;
            min-height: 0;             /* CRITICAL: body scrolls, not parent */
            overflow-y: auto;
            overflow-x: hidden;
            padding: 22px;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }

        /* Section cards */
        .form-section { margin-bottom: 20px; }
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

        /* Form fields */
        .modal-form-group { margin-bottom: 14px; }
        .modal-form-group:last-child { margin-bottom: 0; }

        .modal-form-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 6px;
            letter-spacing: -0.1px;
        }
        .modal-form-group label .req { color: var(--danger); }

        .modal-form-group input,
        .modal-form-group select {
            width: 100%;
            padding: 10px 13px;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-size: 13px;
            outline: none;
            background: #F8FAFC;
            color: var(--text-heading);
            font-family: inherit;
            transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
            height: 42px;
        }
        .modal-form-group input:focus,
        .modal-form-group select:focus {
            border-color: var(--maroon);
            background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(122, 0, 0, 0.08);
        }

        .field-hint {
            display: block;
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 5px;
            line-height: 1.4;
        }

        /* Inline add row (classification add) */
        .inline-add {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        .inline-add input {
            flex: 1;
            padding: 10px 13px;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-size: 13px;
            background: #F8FAFC;
            color: var(--text-heading);
            outline: none;
            font-family: inherit;
            transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
        }
        .inline-add input:focus {
            border-color: var(--maroon);
            background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(122, 0, 0, 0.08);
        }
        .inline-add button {
            flex-shrink: 0;
            padding: 10px 18px;
        }

        /* Classification list */
        .class-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .class-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 10px;
            gap: 10px;
            transition: border-color 0.18s, background 0.18s;
        }
        .class-item:hover {
            background: #FFFFFF;
            border-color: var(--border-strong);
        }

        .class-item-details {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1;
            min-width: 0;
        }
        .class-item-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-heading);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .class-item-stats {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .class-item-actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
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

            /* Hide "Showing X of Y" pill on small screens */
            #searchCountBadge { display: none !important; }

            .modal-overlay { padding: 14px; }
            .modal-box { max-height: calc(100dvh - 28px); }
        }

        /* ============================================================
           MOBILE — fullscreen modal + iOS zoom fix
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
                gap: 8px;
            }
            .modal-footer .btn-primary,
            .modal-footer .btn-secondary {
                flex: 1;
                min-width: 0;
            }

            .form-section { margin-bottom: 18px; }

            /* Prevent iOS auto-zoom on focus */
            .modal-box input,
            .modal-box select,
            .modal-box textarea,
            .inline-add input {
                font-size: 16px;
                height: 46px;
            }

            /* Bigger tap targets on mobile */
            .class-item { padding: 14px; }
            .class-item-actions .btn-table-action { padding: 8px 12px; }
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
                        <h2>Offices &amp; Service Units</h2>
                        <p>Directory and classification setup for ARTA frontline evaluation routing</p>
                    </div>

                    <div class="toolbar-actions">
                        <button class="btn-secondary" onclick="openClassificationModal()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                            Manage Classifications
                        </button>
                        <button class="btn-primary" onclick="openCreateOfficeModal()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Add New Office
                        </button>
                    </div>
                </section>

                <section class="filter-panel">
                    <div class="filter-controls">
                        <input type="text" id="liveSearchInput" class="input-live-search" placeholder="Type to search offices in real time..." autocomplete="off">

                        <select id="liveTypeSelect" class="select-live-filter">
                            <option value="">All Classifications</option>
                            <?php foreach ($classifications as $c): ?>
                                <option value="<?php echo htmlspecialchars(trim($c['name'])); ?>">
                                    <?php echo htmlspecialchars(trim($c['name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="search-count-pill" id="searchCountBadge">
                        Showing <?php echo count($offices); ?> of <?php echo count($offices); ?> units
                    </div>
                </section>

            </div>

            <section class="panel-card">
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Office / Unit Designation</th>
                                <th style="width: 220px;">Classification</th>
                                <th style="width: 140px;">Recorded Reviews</th>
                                <th style="width: 130px;">Harmonized Mean</th>
                                <th style="width: 140px; text-align: right;">Management</th>
                            </tr>
                        </thead>
                        <tbody id="officesTableBody">
                            <?php if (!empty($offices)): ?>
                                <?php foreach ($offices as $off): ?>
                                    <?php
                                        $score = (float)($off['mean_score'] ?? 0);
                                        $pill_class = $score >= 4.0 ? 'score-high' : ($score >= 3.0 ? 'score-med' : ($score > 0 ? 'score-low' : ''));
                                        $office_type_val = trim($off['office_type'] ?? 'Unassigned');
                                    ?>
                                    <tr class="office-row"
                                        data-name="<?php echo strtolower(htmlspecialchars(trim($off['office_name']))); ?>"
                                        data-type="<?php echo strtolower(htmlspecialchars($office_type_val)); ?>">
                                        <td>
                                            <strong style="color: var(--text-heading);"><?php echo htmlspecialchars($off['office_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge-type"><?php echo htmlspecialchars($office_type_val); ?></span>
                                        </td>
                                        <td>
                                            <?php echo number_format($off['total_evaluations']); ?> reviews
                                        </td>
                                        <td>
                                            <?php if ($score > 0): ?>
                                                <span class="score-pill <?php echo $pill_class; ?>">
                                                    ★ <?php echo number_format($score, 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: var(--text-muted);">No reviews</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <div class="action-btns">
                                                <button class="btn-table-action btn-edit"
                                                    onclick="openEditOfficeModal(<?php echo $off['id']; ?>, '<?php echo htmlspecialchars(addslashes($off['office_name'])); ?>', '<?php echo htmlspecialchars(addslashes($office_type_val)); ?>')">
                                                    Edit
                                                </button>

                                                <form method="POST" action="admin_offices.php" style="display:inline;" onsubmit="return confirm('Remove office \'<?php echo htmlspecialchars(addslashes($off['office_name'])); ?>\'?');">
                                                    <input type="hidden" name="action" value="delete_office">
                                                    <input type="hidden" name="office_id" value="<?php echo $off['id']; ?>">
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
                                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                        No office units configured in database yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr id="noResultsRow" style="display: none;">
                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    No offices match your search criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

    <!-- ============================================================
         MODAL 1: Office Create / Edit — REDESIGNED
         ============================================================ -->
    <div class="modal-overlay" id="officeModal">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="officeModalTitle">

            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-header-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div class="modal-header-text">
                        <h3 id="officeModalTitle">Add Office Unit</h3>
                        <p id="officeModalSubtitle">Register a new service unit in the CSM portal</p>
                    </div>
                </div>
                <button type="button" class="btn-close-modal" onclick="closeModal('officeModal')" aria-label="Close">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form method="POST" action="admin_offices.php" class="modal-form">
                <input type="hidden" name="action" id="officeFormAction" value="create_office">
                <input type="hidden" name="office_id" id="officeFormId" value="">

                <div class="modal-body">

                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">1</span>
                            <span>Office Identification</span>
                        </div>

                        <div class="modal-form-group">
                            <label for="office_name">Office / Unit Name <span class="req">*</span></label>
                            <input type="text" id="office_name" name="office_name" placeholder="e.g. Admission Office" required autocomplete="off">
                            <small class="field-hint">Use the official designation as it appears in the Citizen's Charter.</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">2</span>
                            <span>Classification</span>
                        </div>

                        <div class="modal-form-group">
                            <label for="office_type">Institutional Classification <span class="req">*</span></label>
                            <select id="office_type" name="office_type" required>
                                <option value="" disabled selected>Select a classification...</option>
                                <?php foreach ($classifications as $c): ?>
                                    <option value="<?php echo htmlspecialchars(trim($c['name'])); ?>">
                                        <?php echo htmlspecialchars(trim($c['name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-hint">Manage the list in "Manage Classifications".</small>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('officeModal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="btnSaveOffice">Save Office</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
         MODAL 2: Classification Management — REDESIGNED
         ============================================================ -->
    <div class="modal-overlay" id="classificationModal">
        <div class="modal-box modal-lg" role="dialog" aria-modal="true" aria-labelledby="classModalTitle">

            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-header-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M7 7h.01M7 3h5a2 2 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                    </div>
                    <div class="modal-header-text">
                        <h3 id="classModalTitle">Manage Office Classifications</h3>
                        <p>Add, rename, or remove classification types across all offices</p>
                    </div>
                </div>
                <button type="button" class="btn-close-modal" onclick="closeModal('classificationModal')" aria-label="Close">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="modal-form">
                <div class="modal-body">

                    <!-- SECTION 1: Add -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">1</span>
                            <span>Add New Classification</span>
                        </div>

                        <form method="POST" action="admin_offices.php" class="inline-add">
                            <input type="hidden" name="action" value="create_classification">
                            <input type="text" name="classification_name" placeholder="e.g. Specialized Unit" required autocomplete="off">
                            <button type="submit" class="btn-primary">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                Add
                            </button>
                        </form>
                    </div>

                    <!-- SECTION 2: Existing -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">2</span>
                            <span>Existing Classifications (<?php echo count($classifications); ?>)</span>
                        </div>

                        <div class="class-list">
                            <?php if (empty($classifications)): ?>
                                <div style="text-align: center; color: var(--text-muted); padding: 20px; font-size: 12.5px;">
                                    No classifications configured yet.
                                </div>
                            <?php else: ?>
                                <?php foreach ($classifications as $cl): ?>
                                    <div class="class-item">
                                        <div class="class-item-details">
                                            <span class="class-item-name"><?php echo htmlspecialchars($cl['name']); ?></span>
                                            <span class="class-item-stats">
                                                <?php echo $cl['assigned_count']; ?> office(s) &bull; <?php echo number_format($cl['review_count']); ?> review(s)
                                            </span>
                                        </div>

                                        <div class="class-item-actions">
                                            <button type="button" class="btn-table-action btn-edit"
                                                onclick="promptEditClassification(<?php echo $cl['id']; ?>, '<?php echo htmlspecialchars(addslashes($cl['name'])); ?>')">
                                                Rename
                                            </button>

                                            <form method="POST" action="admin_offices.php" style="display: inline;"
                                                onsubmit="return confirmDeleteClassification('<?php echo htmlspecialchars(addslashes($cl['name'])); ?>', <?php echo (int)$cl['assigned_count']; ?>, <?php echo (int)$cl['review_count']; ?>);">
                                                <input type="hidden" name="action" value="delete_classification">
                                                <input type="hidden" name="classification_id" value="<?php echo $cl['id']; ?>">
                                                <input type="hidden" name="classification_name" value="<?php echo htmlspecialchars($cl['name']); ?>">
                                                <button type="submit" class="btn-table-action btn-delete">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Hidden form for rename -->
                    <form id="editClassificationForm" method="POST" action="admin_offices.php" style="display: none;">
                        <input type="hidden" name="action" value="update_classification">
                        <input type="hidden" name="classification_id" id="editClassId">
                        <input type="hidden" name="old_classification_name" id="editClassOldName">
                        <input type="hidden" name="classification_name" id="editClassNewName">
                    </form>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('classificationModal')">Close</button>
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
           CLASSIFICATION DELETE GUARD
           ============================================================ */
        function confirmDeleteClassification(className, assignedCount, reviewCount) {
            if (assignedCount > 0 || reviewCount > 0) {
                let reasons = [];
                if (assignedCount > 0) reasons.push(`${assignedCount} assigned office(s)`);
                if (reviewCount > 0) reasons.push(`${reviewCount} recorded review(s)`);

                showToast(`Cannot delete '${className}': It has ${reasons.join(' and ')}. Reassign or resolve them first.`, 'error');
                return false;
            }
            return confirm(`Are you sure you want to permanently delete classification '${className}'?`);
        }

        /* ============================================================
           MODAL OPEN/CLOSE
           ============================================================ */
        function openCreateOfficeModal() {
            document.getElementById('officeModalTitle').textContent = "Add Office Unit";
            document.getElementById('officeModalSubtitle').textContent = "Register a new service unit in the CSM portal";
            document.getElementById('officeFormAction').value = "create_office";
            document.getElementById('officeFormId').value = "";
            document.getElementById('office_name').value = "";
            document.getElementById('office_type').selectedIndex = 0;
            document.getElementById('btnSaveOffice').textContent = "Save Office";
            document.getElementById('officeModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('office_name').focus(), 100);
        }

        function openEditOfficeModal(id, name, type) {
            document.getElementById('officeModalTitle').textContent = "Edit Office Designation";
            document.getElementById('officeModalSubtitle').textContent = "Update name or classification";
            document.getElementById('officeFormAction').value = "update_office";
            document.getElementById('officeFormId').value = id;
            document.getElementById('office_name').value = name;

            const select = document.getElementById('office_type');
            let matched = false;
            const targetType = type.trim().toLowerCase();

            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value.trim().toLowerCase() === targetType) {
                    select.selectedIndex = i;
                    matched = true;
                    break;
                }
            }

            if (!matched && type.trim() !== "") {
                const opt = document.createElement('option');
                opt.value = type.trim();
                opt.textContent = type.trim();
                opt.selected = true;
                select.appendChild(opt);
            }

            document.getElementById('btnSaveOffice').textContent = "Save Changes";
            document.getElementById('officeModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function openClassificationModal() {
            document.getElementById('classificationModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
        }

        function promptEditClassification(id, currentName) {
            const newName = prompt("Enter updated classification title:", currentName);
            if (newName && newName.trim() !== "" && newName.trim() !== currentName) {
                document.getElementById('editClassId').value = id;
                document.getElementById('editClassOldName').value = currentName;
                document.getElementById('editClassNewName').value = newName.trim();
                document.getElementById('editClassificationForm').submit();
            }
        }

        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                closeModal(e.target.id);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => {
                    m.classList.remove('active');
                });
                document.body.style.overflow = '';
            }
        });

        /* ============================================================
           LIVE FILTER
           ============================================================ */
        const liveSearchInput = document.getElementById('liveSearchInput');
        const liveTypeSelect = document.getElementById('liveTypeSelect');
        const officeRows = document.querySelectorAll('#officesTableBody tr.office-row');
        const noResultsRow = document.getElementById('noResultsRow');
        const searchCountBadge = document.getElementById('searchCountBadge');

        function runLiveFilter() {
            const term = liveSearchInput.value.toLowerCase().trim();
            const selectedClass = liveTypeSelect.value.toLowerCase().trim();
            let matches = 0;

            officeRows.forEach(row => {
                const name = (row.getAttribute('data-name') || '').trim();
                const type = (row.getAttribute('data-type') || '').trim();

                const matchesTerm = (term === '') || name.includes(term);
                const matchesClass = (selectedClass === '') || (type === selectedClass);

                if (matchesTerm && matchesClass) {
                    row.style.display = '';
                    matches++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noResultsRow) {
                noResultsRow.style.display = (matches === 0 && officeRows.length > 0) ? '' : 'none';
            }
            if (searchCountBadge) {
                searchCountBadge.textContent = `Showing ${matches} of ${officeRows.length} units`;
            }
        }

        liveSearchInput.addEventListener('input', runLiveFilter);
        liveTypeSelect.addEventListener('change', runLiveFilter);
    </script>
</body>
</html>