<?php
// admin_users.php — Master User Directory (Students, Faculty, Admins)

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
$toast_type = '';

// ---------------------------------------------------------------------------
// FETCH OFFICES FOR DROPDOWN
// ---------------------------------------------------------------------------
$offices_list = [];
$o_res = $conn->query("SELECT id, office_name FROM offices ORDER BY office_name ASC");
if ($o_res) {
    while ($r = $o_res->fetch_assoc()) $offices_list[] = $r;
}

// ---------------------------------------------------------------------------
// HANDLE CRUD ACTIONS
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $user_id            = trim($_POST['user_id'] ?? '');
        $full_name          = trim($_POST['full_name'] ?? '');
        $email              = trim($_POST['email'] ?? '');
        $password           = $_POST['password'] ?? '';
        $internal_user_type = $_POST['internal_user_type'] ?? 'student';
        $client_type        = $_POST['client_type'] ?? 'Citizen';
        $age_group          = $_POST['age_group'] ?? '';
        $sex                = $_POST['sex'] ?? '';
        $region             = $_POST['region'] ?? 'Region IX - Zamboanga Peninsula';
        $office_id          = (int)($_POST['office_id'] ?? 0);
        $office_id          = $office_id > 0 ? $office_id : null;

        if (empty($user_id) || empty($full_name) || empty($email) || empty($password)) {
            $toast_message = "ID, Name, Email, and Password are required.";
            $toast_type = 'error';
        } else {
            $chk = $conn->prepare("SELECT user_id, email FROM users WHERE user_id = ? OR email = ? LIMIT 1");
            $chk->bind_param("ss", $user_id, $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $toast_message = "A user with this ID or Email already exists.";
                $toast_type = 'error';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    INSERT INTO users 
                    (user_id, full_name, email, password, internal_user_type, office_id, client_type, age_group, sex, region) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssssissss", $user_id, $full_name, $email, $hashed_password, $internal_user_type, $office_id, $client_type, $age_group, $sex, $region);

                if ($stmt->execute()) {
                    $toast_message = "Account for '{$full_name}' created successfully.";
                    $toast_type = 'success';
                } else {
                    $toast_message = "Database error: " . $conn->error;
                    $toast_type = 'error';
                }
                $stmt->close();
            }
            $chk->close();
        }
    } elseif ($action === 'update_user') {
        $target_user_id     = trim($_POST['target_user_id'] ?? '');
        $full_name          = trim($_POST['full_name'] ?? '');
        $email              = trim($_POST['email'] ?? '');
        $password           = $_POST['password'] ?? '';
        $internal_user_type = $_POST['internal_user_type'] ?? 'student';
        $client_type        = $_POST['client_type'] ?? 'Citizen';
        $age_group          = $_POST['age_group'] ?? '';
        $sex                = $_POST['sex'] ?? '';
        $region             = $_POST['region'] ?? 'Region IX - Zamboanga Peninsula';
        $office_id          = (int)($_POST['office_id'] ?? 0);
        $office_id          = $office_id > 0 ? $office_id : null;

        if (empty($target_user_id) || empty($full_name) || empty($email)) {
            $toast_message = "Name and Email cannot be empty.";
            $toast_type = 'error';
        } else {
            $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
            $chk->bind_param("ss", $email, $target_user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $toast_message = "This email is already in use by another account.";
                $toast_type = 'error';
            } else {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, password=?, internal_user_type=?, office_id=?, client_type=?, age_group=?, sex=?, region=? WHERE user_id=?");
                    $stmt->bind_param("ssssisssss", $full_name, $email, $hashed_password, $internal_user_type, $office_id, $client_type, $age_group, $sex, $region, $target_user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, internal_user_type=?, office_id=?, client_type=?, age_group=?, sex=?, region=? WHERE user_id=?");
                    $stmt->bind_param("sssisssss", $full_name, $email, $internal_user_type, $office_id, $client_type, $age_group, $sex, $region, $target_user_id);
                }

                if ($stmt->execute()) {
                    $toast_message = "User account '{$target_user_id}' updated successfully.";
                    $toast_type = 'success';
                } else {
                    $toast_message = "Update failed: " . $conn->error;
                    $toast_type = 'error';
                }
                $stmt->close();
            }
            $chk->close();
        }
    } elseif ($action === 'delete_user') {
        $target_user_id = trim($_POST['target_user_id'] ?? '');

        if ($target_user_id === $_SESSION['user_id']) {
            $toast_message = "You cannot delete your own active administrator account.";
            $toast_type = 'error';
        } elseif (!empty($target_user_id)) {
            // Check feedback
            $chk_fb = $conn->prepare("SELECT COUNT(*) AS total FROM arta_feedback WHERE user_id = ?");
            $chk_fb->bind_param("s", $target_user_id);
            $chk_fb->execute();
            $fb_count = (int)$chk_fb->get_result()->fetch_assoc()['total'];
            $chk_fb->close();

            // Check action plans
            $chk_ap = $conn->prepare("SELECT COUNT(*) AS total FROM action_plans WHERE assigned_staff_id = ?");
            $chk_ap->bind_param("s", $target_user_id);
            $chk_ap->execute();
            $ap_count = (int)$chk_ap->get_result()->fetch_assoc()['total'];
            $chk_ap->close();

            if ($fb_count > 0) {
                $toast_message = "Cannot delete account: User has {$fb_count} recorded ARTA review(s).";
                $toast_type = 'error';
            } elseif ($ap_count > 0) {
                $toast_message = "Cannot delete account: Staff member has {$ap_count} assigned action plan(s).";
                $toast_type = 'error';
            } else {
                $del = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $del->bind_param("s", $target_user_id);
                if ($del->execute()) {
                    $toast_message = "User account deleted permanently.";
                    $toast_type = 'success';
                } else {
                    $toast_message = "Delete failed: " . $conn->error;
                    $toast_type = 'error';
                }
                $del->close();
            }
        }
    }
}

// ---------------------------------------------------------------------------
// FETCH ALL USERS
// ---------------------------------------------------------------------------
$users = [];
$users_res = $conn->query("
    SELECT 
        u.user_id, u.full_name, u.email, u.internal_user_type, 
        u.office_id, o.office_name,
        u.client_type, u.age_group, u.sex, u.region,
        COUNT(f.feedback_id) AS total_evaluations
    FROM users u
    LEFT JOIN offices o ON u.office_id = o.id
    LEFT JOIN arta_feedback f ON u.user_id = f.user_id
    GROUP BY 
        u.user_id, u.full_name, u.email, u.internal_user_type, 
        u.office_id, o.office_name,
        u.client_type, u.age_group, u.sex, u.region
    ORDER BY u.full_name ASC
");
if ($users_res instanceof mysqli_result) {
    while ($r = $users_res->fetch_assoc()) $users[] = $r;
}

$role_map = [
    'admin' => 'System Admin',
    'student' => 'Student',
    'zppsu_employee' => 'Staff / Faculty',
    'external_client' => 'External Client'
];

// Page metadata
$page_title    = 'User Accounts & Roles';
$page_crumb    = 'Administration / User Accounts';
$page_subtitle = 'Manage student, faculty, and administrative profiles';
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
            --maroon: #7A0000; --maroon-dark: #580000;
            --bg: #F8FAFC; --surface: #FFFFFF;
            --border: #E2E8F0; --border-strong: #CBD5E1;
            --text-heading: #0F172A; --text-body: #475569; --text-muted: #94A3B8;
            --success: #10B981; --danger: #EF4444;
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

        /* TABLE */
        .panel-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px; flex: 1;
            display: flex; flex-direction: column; min-height: 0;
        }
        .table-scroll-wrap {
            flex: 1; min-height: 400px; max-height: calc(100vh - 320px);
            overflow-y: auto; overflow-x: auto;
            border: 1px solid var(--border); border-radius: 8px; -webkit-overflow-scrolling: touch;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 900px; }
        .table-scroll-wrap thead th {
            position: sticky; top: 0; background-color: #F1F5F9; z-index: 10;
            box-shadow: 0 1px 0 var(--border);
        }
        th {
            background-color: #F1F5F9; color: var(--text-muted); font-weight: 700;
            padding: 11px 14px; border-bottom: 1px solid var(--border);
            text-transform: uppercase; font-size: 10.5px; text-align: left;
        }
        td { padding: 12px 14px; border-bottom: 1px solid var(--border); color: var(--text-body); vertical-align: top; }

        .badge-type {
            display: inline-block; font-size: 11px; font-weight: 700;
            padding: 3px 8px; border-radius: 4px;
            background: #F1F5F9; color: var(--text-body); border: 1px solid #E2E8F0;
        }
        .badge-admin { background: #FEF2F2; color: var(--maroon); border-color: #FECACA; }
        .badge-student { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
        .badge-staff { background: #F0FDF4; color: #15803D; border-color: #BBF7D0; }
        .badge-ext { background: #FFFBEB; color: #B45309; border-color: #FDE68A; }

        .action-btns { display: flex; align-items: center; gap: 6px; justify-content: flex-end; }
        .btn-table-action {
            padding: 6px 12px; border-radius: 6px; font-size: 11.5px;
            font-weight: 600; cursor: pointer; border: 1px solid transparent;
            transition: filter 0.18s; font-family: inherit;
        }
        .btn-table-action:hover { filter: brightness(0.95); }
        .btn-edit { background: #EFF6FF; color: #2563EB; border-color: #BFDBFE; }
        .btn-delete { background: #FEF2F2; color: var(--danger); border-color: #FECACA; }
        .btn-delete:disabled { opacity: 0.5; cursor: not-allowed; }

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
            position: fixed; inset: 0; background: rgba(15, 23, 42, 0.68);
            -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px);
            z-index: 1050; display: none; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: var(--surface); border-radius: 18px; width: 100%; max-width: 680px;
            max-height: calc(100vh - 40px); max-height: calc(100dvh - 40px);
            border: 1px solid var(--border);
            box-shadow: 0 30px 60px -12px rgba(15, 23, 42, 0.35);
            display: flex; flex-direction: column; overflow: hidden;
            animation: modalIn 0.28s cubic-bezier(0.34, 1.3, 0.64, 1);
        }
        @keyframes modalIn { from { opacity: 0; transform: translateY(14px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }

        .modal-header {
            flex-shrink: 0; padding: 18px 22px; border-bottom: 1px solid var(--border);
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
        .modal-header-text h3 { font-size: 16.5px; font-weight: 800; color: var(--text-heading); margin: 0; line-height: 1.2; }
        .modal-header-text p { font-size: 12px; color: var(--text-muted); margin: 3px 0 0; }

        .btn-close-modal {
            width: 34px; height: 34px; flex-shrink: 0; border-radius: 9px;
            background: transparent; border: 1px solid transparent; color: var(--text-muted);
            cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
            transition: background 0.18s, color 0.18s, border-color 0.18s; padding: 0;
        }
        .btn-close-modal:hover { background: #F1F5F9; color: var(--text-heading); border-color: var(--border); }
        .btn-close-modal svg { width: 18px; height: 18px; }

        .modal-form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; overflow: hidden; }
        .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 22px; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }

        .form-section { margin-bottom: 22px; }
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
            font-size: 12px; font-weight: 700; color: var(--text-heading); margin-bottom: 6px;
        }
        .modal-form-group label .req { color: var(--danger); }
        .modal-form-group input,
        .modal-form-group select {
            width: 100%; padding: 10px 13px; border: 1.5px solid var(--border); border-radius: 9px;
            font-size: 13px; outline: none; background: #F8FAFC; color: var(--text-heading);
            font-family: inherit; transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
            height: 42px;
        }
        .modal-form-group input:focus,
        .modal-form-group select:focus {
            border-color: var(--maroon); background: #FFFFFF; box-shadow: 0 0 0 4px rgba(122, 0, 0, 0.08);
        }
        .modal-form-group input[readonly] { background: #F1F5F9; color: var(--text-muted); cursor: not-allowed; padding-right: 60px; }

        .input-with-chip { position: relative; }
        .input-chip {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            background: #E2E8F0; color: #64748B; font-size: 9.5px; font-weight: 800;
            letter-spacing: 0.6px; padding: 3px 7px; border-radius: 5px; text-transform: uppercase; pointer-events: none;
        }

        .password-wrap { position: relative; }
        .password-wrap input { padding-right: 44px; }
        .pwd-toggle {
            position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
            width: 32px; height: 32px; border: none; background: transparent; color: var(--text-muted);
            cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 6px; transition: background 0.18s, color 0.18s;
        }
        .pwd-toggle:hover { background: #F1F5F9; color: var(--text-heading); }
        .pwd-toggle svg { width: 16px; height: 16px; }
        .field-hint { display: block; font-size: 11px; color: var(--text-muted); margin-top: 5px; line-height: 1.4; }

        .modal-footer {
            flex-shrink: 0; padding: 14px 22px; background: #FAFBFC; border-top: 1px solid var(--border);
            display: flex; justify-content: flex-end; gap: 10px;
        }
        .modal-footer .btn-primary, .modal-footer .btn-secondary { min-width: 110px; justify-content: center; }

        /* RESPONSIVE */
        @media (max-width: 1024px) { .admin-main-wrap { margin-left: 0; width: 100%; } }
        @media (max-width: 768px) {
            .admin-container { padding: calc(var(--header-height) + 12px) 14px 18px; }
            .toolbar { flex-direction: column; align-items: stretch; padding: 14px 16px; }
            .toolbar-actions .btn-primary { width: 100%; justify-content: center; }
            .input-live-search, .select-live-filter { width: 100%; min-width: 0; }
            .table-scroll-wrap { max-height: calc(100vh - 340px); }
            #searchCountBadge { display: none !important; }
            .modal-overlay { padding: 14px; }
            .modal-box { max-height: calc(100dvh - 28px); }
        }
        @media (max-width: 640px) {
            .modal-overlay {
                padding: 0; align-items: stretch; justify-content: stretch;
                background: #FFFFFF; -webkit-backdrop-filter: none; backdrop-filter: none;
            }
            .modal-box {
                max-width: 100%; width: 100%; max-height: 100dvh; height: 100dvh;
                border-radius: 0; border: none; box-shadow: none;
                animation: modalSlideUp 0.3s cubic-bezier(0.32, 0.72, 0, 1);
            }
            @keyframes modalSlideUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
            .modal-header { padding: 16px; padding-top: calc(16px + env(safe-area-inset-top, 0px)); }
            .modal-header-icon { width: 38px; height: 38px; border-radius: 10px; }
            .modal-header-icon svg { width: 18px; height: 18px; }
            .modal-header-text h3 { font-size: 15.5px; }
            .modal-header-text p { font-size: 11.5px; }
            .modal-body { padding: 18px 16px; -webkit-overflow-scrolling: touch; }
            .modal-footer { padding: 12px 16px; padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px)); gap: 8px; }
            .modal-footer .btn-primary, .modal-footer .btn-secondary { flex: 1; min-width: 0; }
            .form-grid { grid-template-columns: 1fr; gap: 12px; }
            .form-section { margin-bottom: 18px; }
            .modal-form-group input, .modal-form-group select { font-size: 16px; height: 46px; }
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
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
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
                        <h2>User Accounts &amp; Roles</h2>
                        <p>Manage student, faculty, and administrative profiles for the CSM Portal</p>
                    </div>
                    <div class="toolbar-actions">
                        <button class="btn-primary" onclick="openUserModal()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Add New User
                        </button>
                    </div>
                </section>

                <section class="filter-panel">
                    <div class="filter-controls">
                        <input type="text" id="liveSearchInput" class="input-live-search" placeholder="Search by name, ID, or email..." autocomplete="off">
                        <select id="liveRoleSelect" class="select-live-filter">
                            <option value="">All Institutional Roles</option>
                            <option value="admin">System Admin</option>
                            <option value="student">Student</option>
                            <option value="zppsu_employee">Staff / Faculty</option>
                            <option value="external_client">External Client</option>
                        </select>
                    </div>
                    <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 600;" id="searchCountBadge">
                        Showing <?php echo count($users); ?> user(s)
                    </div>
                </section>
            </div>

            <section class="panel-card">
                <div class="table-scroll-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Account Identifier</th>
                                <th style="width: 250px;">Full Name &amp; Role</th>
                                <th style="width: 200px;">Office / Profile</th>
                                <th style="width: 140px;">Submissions</th>
                                <th style="width: 120px; text-align: right;">Management</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $u): ?>
                                    <?php
                                        $role = $u['internal_user_type'];
                                        $badge_class = 'badge-type';
                                        if ($role === 'admin') $badge_class = 'badge-admin';
                                        elseif ($role === 'student') $badge_class = 'badge-student';
                                        elseif ($role === 'zppsu_employee') $badge_class = 'badge-staff';
                                        elseif ($role === 'external_client') $badge_class = 'badge-ext';
                                    ?>
                                    <tr class="user-row"
                                        data-search="<?php echo strtolower(htmlspecialchars($u['user_id'] . ' ' . $u['full_name'] . ' ' . $u['email'])); ?>"
                                        data-role="<?php echo strtolower($role); ?>">

                                        <td>
                                            <strong style="color: var(--text-heading); display: block;"><?php echo htmlspecialchars($u['user_id']); ?></strong>
                                            <small style="color: var(--text-muted); font-size: 11px;"><?php echo htmlspecialchars($u['email']); ?></small>
                                        </td>

                                        <td>
                                            <span style="font-weight: 600; display: block; color: var(--text-heading);"><?php echo htmlspecialchars($u['full_name']); ?></span>
                                            <span class="badge-type <?php echo $badge_class; ?>" style="margin-top: 4px;">
                                                <?php echo htmlspecialchars($role_map[$role] ?? 'Unknown'); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if ($role === 'admin' || $role === 'zppsu_employee'): ?>
                                                <span style="font-weight: 600; color: var(--text-heading); font-size: 12px; display: block;">
                                                    <?php echo htmlspecialchars($u['office_name'] ?? 'Unassigned'); ?>
                                                </span>
                                                <small style="color: var(--text-muted); font-size: 11px;">Assigned Office</small>
                                            <?php else: ?>
                                                <span style="font-weight: 600; color: var(--text-heading); font-size: 12px; display: block;">
                                                    <?php echo htmlspecialchars($u['client_type']); ?>
                                                </span>
                                                <small style="color: var(--text-muted); font-size: 11px;">
                                                    <?php echo htmlspecialchars($u['sex'] . ' • ' . $u['age_group']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>

                                        <td><?php echo number_format($u['total_evaluations']); ?> logs</td>

                                        <td style="text-align: right;">
                                            <div class="action-btns">
                                                <button class="btn-table-action btn-edit"
                                                    onclick='openEditUserModal(<?php echo json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                                    Edit
                                                </button>

                                                <form method="POST" action="admin_users.php" style="display:inline;"
                                                    onsubmit="return confirmDeleteUser('<?php echo htmlspecialchars(addslashes($u['user_id'])); ?>', <?php echo (int)$u['total_evaluations']; ?>, '<?php echo $role; ?>');">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="target_user_id" value="<?php echo htmlspecialchars($u['user_id']); ?>">
                                                    <button type="submit" class="btn-table-action btn-delete"
                                                        <?php echo ($u['user_id'] === $_SESSION['user_id']) ? 'disabled title="Cannot delete your own active session."' : ''; ?>>
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
                                        No user accounts registered.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr id="noResultsRow" style="display: none;">
                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                    No accounts match your search or filter settings.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

    <!-- MODAL: Create / Edit User -->
    <div class="modal-overlay" id="userModal">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-header-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div class="modal-header-text">
                        <h3 id="modalTitle">Add New User</h3>
                        <p id="modalSubtitle">Create a new account in the CSM Portal</p>
                    </div>
                </div>
                <button type="button" class="btn-close-modal" onclick="closeModal('userModal')" aria-label="Close">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="admin_users.php" class="modal-form" id="userForm">
                <input type="hidden" name="action" id="formAction" value="create_user">
                <input type="hidden" name="target_user_id" id="targetUserId" value="">

                <div class="modal-body">
                    <!-- SECTION 1 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">1</span>
                            <span>Account Credentials</span>
                        </div>
                        <div class="form-grid">
                            <div class="modal-form-group">
                                <label>ID Number <span class="req">*</span></label>
                                <div class="input-with-chip">
                                    <input type="text" id="user_id" name="user_id" placeholder="e.g. 2021141338" required autocomplete="off">
                                    <span class="input-chip" id="idLockChip" style="display:none;">Locked</span>
                                </div>
                            </div>
                            <div class="modal-form-group">
                                <label>Full Name <span class="req">*</span></label>
                                <input type="text" id="full_name" name="full_name" placeholder="Dela Cruz, Juan P." required autocomplete="off">
                            </div>
                            <div class="modal-form-group full">
                                <label>Email Address <span class="req">*</span></label>
                                <input type="email" id="email" name="email" placeholder="name@zppsu.edu.ph" required autocomplete="off">
                            </div>
                            <div class="modal-form-group full">
                                <label>Password <span class="req" id="pwdReq">*</span></label>
                                <div class="password-wrap">
                                    <input type="password" id="password" name="password" placeholder="Minimum 6 characters" required autocomplete="new-password">
                                    <button type="button" class="pwd-toggle" onclick="togglePasswordVisibility()" aria-label="Show password">
                                        <svg id="eyeIcon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </div>
                                <small class="field-hint" id="pwdHint" style="display:none;">Leave blank to keep the current password.</small>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="section-num">2</span>
                            <span>Role &amp; Assignment</span>
                        </div>
                        <div class="form-grid">
                            <div class="modal-form-group">
                                <label>Institutional Role <span class="req">*</span></label>
                                <select id="internal_user_type" name="internal_user_type" required onchange="toggleOfficeField()">
                                    <option value="student">Student</option>
                                    <option value="zppsu_employee">Staff / Faculty</option>
                                    <option value="external_client">External Client</option>
                                    <option value="admin">System Admin</option>
                                </select>
                            </div>

                            <!-- Hidden unless Staff/Admin -->
                            <div class="modal-form-group" id="officeGroup" style="display:none;">
                                <label>Assigned Office</label>
                                <select id="office_id" name="office_id">
                                    <option value="">— Unassigned —</option>
                                    <?php foreach ($offices_list as $o): ?>
                                        <option value="<?php echo (int)$o['id']; ?>"><?php echo htmlspecialchars($o['office_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Hidden unless Student/External -->
                            <div class="modal-form-group" id="clientTypeGroup">
                                <label>ARTA Classification <span class="req">*</span></label>
                                <select id="client_type" name="client_type" required>
                                    <option value="Citizen">Citizen</option>
                                    <option value="Business">Business</option>
                                    <option value="Government">Government</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3 (Demographics) -->
                    <div class="form-section" id="demographicsSection">
                        <div class="form-section-title">
                            <span class="section-num">3</span>
                            <span>Demographic Profile</span>
                        </div>
                        <div class="form-grid">
                            <div class="modal-form-group">
                                <label>Age Group <span class="req">*</span></label>
                                <select id="age_group" name="age_group" required>
                                    <option value="19 or lower">19 or lower</option>
                                    <option value="20-34">20-34</option>
                                    <option value="35-49">35-49</option>
                                    <option value="50-64">50-64</option>
                                    <option value="65 or higher">65 or higher</option>
                                </select>
                            </div>
                            <div class="modal-form-group">
                                <label>Sex <span class="req">*</span></label>
                                <select id="sex" name="sex" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Prefer not to say">Prefer not to say</option>
                                </select>
                            </div>
                            <div class="modal-form-group full">
                                <label>Region <span class="req">*</span></label>
                                <select id="region" name="region" required>
                                    <option value="Region IX - Zamboanga Peninsula" selected>Region IX - Zamboanga Peninsula</option>
                                    <option value="Region I - Ilocos Region">Region I - Ilocos Region</option>
                                    <option value="Region II - Cagayan Valley">Region II - Cagayan Valley</option>
                                    <option value="Region III - Central Luzon">Region III - Central Luzon</option>
                                    <option value="Region IV-A - CALABARZON">Region IV-A - CALABARZON</option>
                                    <option value="MIMAROPA Region">MIMAROPA Region</option>
                                    <option value="Region V - Bicol Region">Region V - Bicol Region</option>
                                    <option value="Region VI - Western Visayas">Region VI - Western Visayas</option>
                                    <option value="Region VII - Central Visayas">Region VII - Central Visayas</option>
                                    <option value="Region VIII - Eastern Visayas">Region VIII - Eastern Visayas</option>
                                    <option value="Region X - Northern Mindanao">Region X - Northern Mindanao</option>
                                    <option value="Region XI - Davao Region">Region XI - Davao Region</option>
                                    <option value="Region XII - SOCCSKSARGEN">Region XII - SOCCSKSARGEN</option>
                                    <option value="Region XIII - Caraga">Region XIII - Caraga</option>
                                    <option value="NCR - National Capital Region">NCR - National Capital Region</option>
                                    <option value="CAR - Cordillera Administrative Region">CAR - Cordillera Administrative Region</option>
                                    <option value="BARMM - Bangsamoro Autonomous Region in Muslim Mindanao">BARMM - Bangsamoro Autonomous Region in Muslim Mindanao</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('userModal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="btnSubmit">Save Account</button>
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
            setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4000);
        }

        <?php if (!empty($toast_message)): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showToast("<?php echo addslashes($toast_message); ?>", "<?php echo $toast_type; ?>");
            });
        <?php endif; ?>

        /* ============================================================
           DELETE CONFIRM
           ============================================================ */
        function confirmDeleteUser(userId, fbCount, role) {
            if (fbCount > 0) {
                showToast(`Cannot delete ID ${userId}: User has ${fbCount} recorded ARTA review(s).`, 'error');
                return false;
            }
            if (role === 'admin') {
                return confirm(`WARNING: You are about to delete an Administrator account (${userId}). Proceed?`);
            }
            return confirm(`Are you sure you want to permanently delete account '${userId}'?`);
        }

        /* ============================================================
           DYNAMIC FIELDS LOGIC
           ============================================================ */
        function toggleOfficeField() {
            const role = document.getElementById('internal_user_type').value;
            const officeGroup = document.getElementById('officeGroup');
            const clientTypeGroup = document.getElementById('clientTypeGroup');

            if (role === 'zppsu_employee' || role === 'admin') {
                officeGroup.style.display = 'block';
                clientTypeGroup.style.display = 'none';
            } else {
                officeGroup.style.display = 'none';
                clientTypeGroup.style.display = 'block';
            }
        }

        /* ============================================================
           MODAL OPEN / CLOSE
           ============================================================ */
        function openUserModal() {
            document.getElementById('modalTitle').textContent = "Add New User";
            document.getElementById('modalSubtitle').textContent = "Create a new account in the CSM Portal";
            document.getElementById('formAction').value = "create_user";

            const idField = document.getElementById('user_id');
            idField.value = "";
            idField.readOnly = false;
            document.getElementById('idLockChip').style.display = "none";

            document.getElementById('targetUserId').value = "";
            document.getElementById('full_name').value = "";
            document.getElementById('email').value = "";
            document.getElementById('password').required = true;
            document.getElementById('password').value = "";
            document.getElementById('pwdReq').style.display = "inline";
            document.getElementById('pwdHint').style.display = "none";

            document.getElementById('internal_user_type').value = "student";
            document.getElementById('office_id').value = "";
            document.getElementById('client_type').value = "Citizen";
            document.getElementById('age_group').selectedIndex = 0;
            document.getElementById('sex').selectedIndex = 0;
            document.getElementById('region').value = "Region IX - Zamboanga Peninsula";

            toggleOfficeField(); // Reset visibility

            document.getElementById('btnSubmit').textContent = "Save Account";
            document.getElementById('userModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('user_id').focus(), 100);
        }

        function openEditUserModal(user) {
            document.getElementById('modalTitle').textContent = "Edit User Account";
            document.getElementById('modalSubtitle').textContent = "Update role, contact, and demographic profile";
            document.getElementById('formAction').value = "update_user";

            const idField = document.getElementById('user_id');
            idField.value = user.user_id;
            idField.readOnly = true;
            document.getElementById('idLockChip').style.display = "inline-block";

            document.getElementById('targetUserId').value = user.user_id;
            document.getElementById('full_name').value = user.full_name;
            document.getElementById('email').value = user.email;

            document.getElementById('password').required = false;
            document.getElementById('password').value = "";
            document.getElementById('pwdReq').style.display = "none";
            document.getElementById('pwdHint').style.display = "block";

            document.getElementById('internal_user_type').value = user.internal_user_type;
            document.getElementById('office_id').value = user.office_id || "";
            document.getElementById('client_type').value = user.client_type || "Citizen";

            let ageSel = document.getElementById('age_group');
            for (let i = 0; i < ageSel.options.length; i++) { if (ageSel.options[i].value === user.age_group) ageSel.selectedIndex = i; }

            let sexSel = document.getElementById('sex');
            for (let i = 0; i < sexSel.options.length; i++) { if (sexSel.options[i].value === user.sex) sexSel.selectedIndex = i; }

            let regSel = document.getElementById('region');
            for (let i = 0; i < regSel.options.length; i++) { if (regSel.options[i].value === user.region) regSel.selectedIndex = i; }

            toggleOfficeField(); // Show/Hide based on role

            document.getElementById('btnSubmit').textContent = "Update Account";
            document.getElementById('userModal').classList.add('active');
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
                const modal = document.getElementById('userModal');
                if (modal.classList.contains('active')) closeModal('userModal');
            }
        });

        /* ============================================================
           PASSWORD VISIBILITY TOGGLE
           ============================================================ */
        function togglePasswordVisibility() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            const showSvg = `<path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
            const hideSvg = `<path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>`;

            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.innerHTML = hideSvg;
            } else {
                pwd.type = 'password';
                icon.innerHTML = showSvg;
            }
        }

        /* ============================================================
           LIVE TABLE FILTER
           ============================================================ */
        const liveSearchInput = document.getElementById('liveSearchInput');
        const liveRoleSelect = document.getElementById('liveRoleSelect');
        const rows = document.querySelectorAll('#usersTableBody tr.user-row');
        const badge = document.getElementById('searchCountBadge');
        const noResults = document.getElementById('noResultsRow');

        function runLiveFilter() {
            const term = liveSearchInput.value.toLowerCase().trim();
            const role = liveRoleSelect.value.toLowerCase().trim();
            let matches = 0;

            rows.forEach(row => {
                const rSearch = (row.getAttribute('data-search') || '');
                const rRole = (row.getAttribute('data-role') || '');

                const matchesTerm = (term === '' || rSearch.includes(term));
                const matchesRole = (role === '' || rRole === role);

                if (matchesTerm && matchesRole) {
                    row.style.display = '';
                    matches++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noResults) noResults.style.display = (matches === 0 && rows.length > 0) ? '' : 'none';
            if (badge) badge.textContent = `Showing ${matches} user(s)`;
        }

        liveSearchInput.addEventListener('input', runLiveFilter);
        liveRoleSelect.addEventListener('change', runLiveFilter);
    </script>
</body>
</html>