<?php
// admin_settings.php — System Settings & Configuration

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

$admin_id      = $_SESSION['user_id'] ?? '';
$toast_message = '';
$toast_type    = '';

// ---------------------------------------------------------------------------
// 1. SELF-HEALING TABLE + SEED (Runs automatically if table is missing)
// ---------------------------------------------------------------------------
$conn->query("
    CREATE TABLE IF NOT EXISTS settings (
        setting_key varchar(100) NOT NULL,
        setting_value text DEFAULT NULL,
        setting_type enum('text','number','email','textarea','boolean','select') NOT NULL DEFAULT 'text',
        setting_group varchar(50) NOT NULL DEFAULT 'general',
        setting_options text DEFAULT NULL,
        updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        updated_by varchar(50) DEFAULT NULL,
        PRIMARY KEY (setting_key),
        KEY idx_group (setting_group)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$chk = $conn->query("SELECT COUNT(*) AS c FROM settings");
if ($chk && (int)$chk->fetch_assoc()['c'] === 0) {
    $defaults = [
        ['portal_name', 'ZPPSU Client Satisfaction Measurement', 'text', 'general', null],
        ['school_year', date('Y') . '-' . (date('Y') + 1), 'text', 'general', null],
        ['active_semester', 'First Semester', 'select', 'general', json_encode(['First Semester','Second Semester','Summer'])],
        ['contact_email', 'csm@zppsu.edu.ph', 'email', 'general', null],
        ['campus_location', 'R.T. Lim Boulevard, Zamboanga City', 'text', 'general', null],

        ['arta_satisfaction_target', '80', 'number', 'arta', null],
        ['arta_low_rating_threshold', '2.0', 'number', 'arta', null],
        ['arta_min_responses_for_ranking', '3', 'number', 'arta', null],
        ['default_sla_days', '3', 'number', 'arta', null],
        ['max_daily_feedback', '10', 'number', 'arta', null],

        ['notify_low_rating', '1', 'boolean', 'notifications', null],
        ['notify_new_feedback', '1', 'boolean', 'notifications', null],
        ['notify_resolution_overdue', '1', 'boolean', 'notifications', null],
        ['notify_email_enabled', '0', 'boolean', 'notifications', null],
        ['resolution_reminder_days', '7', 'number', 'notifications', null],

        ['session_timeout_minutes', '120', 'number', 'security', null],
        ['max_login_attempts', '5', 'number', 'security', null],
        ['password_min_length', '6', 'number', 'security', null],
        ['force_password_reset_days', '90', 'number', 'security', null],

        ['maintenance_mode', '0', 'boolean', 'maintenance', null],
        ['maintenance_message', 'The CSM Portal is currently undergoing scheduled maintenance. Please check back shortly.', 'textarea', 'maintenance', null],
    ];

    $ins = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, setting_options) VALUES (?, ?, ?, ?, ?)");
    foreach ($defaults as $d) {
        $ins->bind_param("sssss", $d[0], $d[1], $d[2], $d[3], $d[4]);
        $ins->execute();
    }
    $ins->close();
}

// ---------------------------------------------------------------------------
// 2. HANDLE SAVE
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    $existing = [];
    $r = $conn->query("SELECT setting_key, setting_value, setting_type, setting_options FROM settings");
    if ($r) while ($row = $r->fetch_assoc()) $existing[$row['setting_key']] = $row;

    $changed = 0;
    $errors  = [];

    $upd = $conn->prepare("UPDATE settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");

    foreach ($existing as $key => $s) {
        $current = (string)($s['setting_value'] ?? '');
        $new_value = null;

        if ($s['setting_type'] === 'boolean') {
            $new_value = (isset($_POST[$key]) && $_POST[$key] === '1') ? '1' : '0';
        } elseif (isset($_POST[$key])) {
            $raw = trim((string)$_POST[$key]);

            switch ($s['setting_type']) {
                case 'number':
                    if ($raw === '' || !is_numeric($raw)) {
                        $errors[] = "Invalid number for '{$key}'";
                    } else {
                        $new_value = $raw;
                    }
                    break;
                case 'email':
                    if ($raw === '' || !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Invalid email for '{$key}'";
                    } else {
                        $new_value = $raw;
                    }
                    break;
                case 'select':
                    $opts = json_decode($s['setting_options'] ?? '[]', true) ?: [];
                    if (in_array($raw, $opts, true)) {
                        $new_value = $raw;
                    } else {
                        $errors[] = "Invalid option for '{$key}'";
                    }
                    break;
                default:
                    $new_value = $raw;
            }
        }

        if ($new_value !== null && $new_value !== $current) {
            $upd->bind_param("sss", $new_value, $admin_id, $key);
            if ($upd->execute()) {
                $changed++;
            } else {
                $errors[] = "Failed to save '{$key}'";
            }
        }
    }
    $upd->close();

    if (!empty($errors)) {
        $toast_message = implode(' • ', $errors);
        $toast_type = 'error';
    } elseif ($changed > 0) {
        $toast_message = "{$changed} setting(s) saved successfully.";
        $toast_type = 'success';
    } else {
        $toast_message = "No changes detected.";
        $toast_type = 'success';
    }
}

// ---------------------------------------------------------------------------
// 3. LOAD SETTINGS
// ---------------------------------------------------------------------------
$all_settings = [];
$r = $conn->query("SELECT * FROM settings ORDER BY setting_group ASC, setting_key ASC");
if ($r instanceof mysqli_result) {
    while ($row = $r->fetch_assoc()) $all_settings[$row['setting_key']] = $row;
}

// Meta for labels/descriptions
$meta = [
    'portal_name' => ['label' => 'Portal Name', 'desc' => 'Full name shown on public pages and admin header.'],
    'school_year' => ['label' => 'Active School Year', 'desc' => 'Format: 2026-2027'],
    'active_semester' => ['label' => 'Active Semester', 'desc' => 'Semester currently shown across the portal.'],
    'contact_email' => ['label' => 'Contact Email', 'desc' => 'Publicly displayed contact address.'],
    'campus_location' => ['label' => 'Campus Location', 'desc' => 'Shown on the landing page hero.'],

    'arta_satisfaction_target' => ['label' => 'CSAT Target (%)', 'desc' => 'Minimum satisfaction rate for the "ARTA Compliant" badge.'],
    'arta_low_rating_threshold' => ['label' => 'Low-Rating Threshold (SQD)', 'desc' => 'Mean score below this triggers a low-rating alert.'],
    'arta_min_responses_for_ranking' => ['label' => 'Min. Responses for Ranking', 'desc' => 'Offices/services need this many responses to appear in leaderboards.'],
    'default_sla_days' => ['label' => 'Default SLA (Days)', 'desc' => 'Processing time applied to newly created services.'],
    'max_daily_feedback' => ['label' => 'Max Daily Feedback per User', 'desc' => 'Prevents spam by limiting daily submissions.'],

    'notify_low_rating' => ['label' => 'Low-Rating Alerts', 'desc' => 'Notify admins when negative feedback is recorded.'],
    'notify_new_feedback' => ['label' => 'New Feedback Alerts', 'desc' => 'Notify admins when new evaluations arrive.'],
    'notify_resolution_overdue' => ['label' => 'Overdue Ticket Alerts', 'desc' => 'Notify admins when action plans exceed reminder days.'],
    'notify_email_enabled' => ['label' => 'Email Notifications', 'desc' => 'Send alerts via email (requires SMTP configuration).'],
    'resolution_reminder_days' => ['label' => 'Resolution Reminder (Days)', 'desc' => 'Flag action plans older than this threshold.'],

    'session_timeout_minutes' => ['label' => 'Session Timeout (Minutes)', 'desc' => 'Auto-logout after this much inactivity.'],
    'max_login_attempts' => ['label' => 'Max Login Attempts', 'desc' => 'Lock the account after this many failed attempts.'],
    'password_min_length' => ['label' => 'Minimum Password Length', 'desc' => 'Enforced when users create or reset passwords.'],
    'force_password_reset_days' => ['label' => 'Force Password Reset (Days)', 'desc' => 'Prompt users to change their password after this period.'],

    'maintenance_mode' => ['label' => 'Maintenance Mode', 'desc' => 'Block public and student access while active.'],
    'maintenance_message' => ['label' => 'Maintenance Message', 'desc' => 'Shown to visitors when maintenance mode is on.'],
];

$group_meta = [
    'general' => [
        'title' => 'General Portal Settings',
        'desc'  => 'Core identification, semester, and contact information.',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
    ],
    'arta' => [
        'title' => 'ARTA Compliance Thresholds',
        'desc'  => 'Targets, thresholds, and default service standards.',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
    ],
    'notifications' => [
        'title' => 'Notification Preferences',
        'desc'  => 'Control what triggers admin alerts and reminders.',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>',
    ],
    'security' => [
        'title' => 'Security & Sessions',
        'desc'  => 'Account protection and session management defaults.',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>',
    ],
    'maintenance' => [
        'title' => 'Maintenance Mode',
        'desc'  => 'Take the portal offline for scheduled work.',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/>',
    ],
];

$grouped = ['general' => [], 'arta' => [], 'notifications' => [], 'security' => [], 'maintenance' => []];
foreach ($all_settings as $key => $s) {
    $g = $s['setting_group'];
    if (!isset($grouped[$g])) $grouped[$g] = [];
    $grouped[$g][] = array_merge($s, ['key' => $key]);
}

$last_updated_at = null;
$last_updated_by = null;
foreach ($all_settings as $s) {
    if (!empty($s['updated_at']) && (!$last_updated_at || $s['updated_at'] > $last_updated_at)) {
        $last_updated_at = $s['updated_at'];
        $last_updated_by = $s['updated_by'];
    }
}

$page_title    = 'Portal Settings';
$page_crumb    = 'System & Security / Settings';
$page_subtitle = 'Configure ARTA thresholds, notifications, and security defaults';
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
            --success: #10B981; --danger: #EF4444;
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
        
        /* FIXED: Full width layout, no max-width constraint */
        .admin-container {
            width: 100%; max-width: 100%;
            padding: calc(var(--header-height) + 20px) 24px 40px;
            flex: 1; display: flex; flex-direction: column;
        }

        /* Sticky toolbar */
        .fixed-header-deck {
            position: -webkit-sticky; position: sticky;
            top: var(--header-height); z-index: 800;
            background: var(--bg); padding-top: 10px; padding-bottom: 12px;
            box-shadow: 0 8px 15px -10px rgba(0,0,0,0.08);
        }
        .toolbar {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px 20px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .toolbar-title h2 { font-size: 19px; font-weight: 800; color: var(--text-heading); margin: 0; }
        .toolbar-title p { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }

        .btn-save {
            background: var(--maroon); color: #FFF; border: none;
            padding: 10px 20px; border-radius: 8px; font-size: 12.5px;
            font-weight: 700; cursor: pointer; font-family: inherit;
            display: inline-flex; align-items: center; gap: 8px;
            transition: background 0.18s, transform 0.12s;
            white-space: nowrap;
        }
        .btn-save:hover { background: var(--maroon-dark); }
        .btn-save:active { transform: scale(0.98); }
        .btn-save svg { width: 15px; height: 15px; }

        /* Sections */
        .settings-section {
            margin-bottom: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .section-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 1px dashed var(--border);
        }
        .section-icon {
            width: 36px; height: 36px; border-radius: 9px;
            background: #FEF2F2; color: var(--maroon);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .section-icon svg { width: 18px; height: 18px; }
        .section-title-block { min-width: 0; flex: 1; }
        .section-title-block h3 {
            font-size: 14px; font-weight: 800; color: var(--text-heading);
            margin: 0 0 2px; letter-spacing: -0.2px;
        }
        .section-title-block p {
            font-size: 11.5px; color: var(--text-muted); margin: 0; line-height: 1.4;
        }

        /* Fields grid */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .setting-field {
            background: #FAFBFC;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }
        .setting-field.full { grid-column: 1 / -1; }

        .setting-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .setting-input {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            font-size: 13px;
            background: #FFFFFF;
            color: var(--text-heading);
            font-family: inherit;
            outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
            min-width: 0;
        }
        .setting-input:focus {
            border-color: var(--maroon);
            box-shadow: 0 0 0 4px rgba(122, 0, 0, 0.08);
        }
        textarea.setting-input {
            min-height: 80px;
            resize: vertical;
            line-height: 1.5;
        }

        .setting-hint {
            font-size: 10.5px;
            color: var(--text-muted);
            line-height: 1.45;
            margin: 0;
        }

        /* Toggle switch row */
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 2px 0;
        }
        .toggle-text { min-width: 0; flex: 1; }
        .toggle-text .label {
            font-size: 12px; font-weight: 700;
            color: var(--text-heading);
        }
        .toggle-text .desc {
            font-size: 10.5px; color: var(--text-muted);
            margin-top: 2px; line-height: 1.4;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 42px;
            height: 24px;
            flex-shrink: 0;
            cursor: pointer;
        }
        .switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .switch-track {
            position: absolute;
            inset: 0;
            background: #CBD5E1;
            border-radius: 24px;
            transition: background 0.2s;
        }
        .switch-track::before {
            content: '';
            position: absolute;
            height: 18px; width: 18px;
            left: 3px; top: 3px;
            background: #FFFFFF;
            border-radius: 50%;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .switch input:checked + .switch-track {
            background: var(--maroon);
        }
        .switch input:checked + .switch-track::before {
            transform: translateX(18px);
        }

        /* Save footer */
        .save-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 20px;
            margin-top: 12px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            flex-wrap: wrap;
        }
        .footer-info {
            font-size: 11.5px;
            color: var(--text-muted);
            font-weight: 600;
            line-height: 1.5;
        }
        .footer-info strong { color: var(--text-heading); font-weight: 700; }

        /* Toast */
        .toast-container {
            position: fixed;
            top: calc(var(--header-height) + 16px);
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 420px;
            pointer-events: none;
        }
        .toast {
            pointer-events: auto;
            background: #FFFFFF; border-radius: 8px;
            padding: 12px 16px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            border-left: 4px solid var(--success);
            font-size: 13px; font-weight: 600;
            color: var(--text-heading);
            display: flex; align-items: center;
            justify-content: space-between; gap: 10px;
            animation: toastSlideIn 0.3s forwards;
        }
        .toast-error { border-left-color: var(--danger); }
        .toast-close {
            background: transparent; border: none;
            color: var(--text-muted); cursor: pointer;
            font-size: 18px; line-height: 1;
        }
        @keyframes toastSlideIn {
            from { opacity: 0; transform: translateX(40px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .admin-main-wrap { margin-left: 0; width: 100%; }
            .admin-container { padding: calc(var(--header-height) + 12px) 16px 40px; }
        }
        @media (max-width: 768px) {
            .toolbar { flex-direction: column; align-items: stretch; }
            .btn-save { justify-content: center; }
            .settings-grid { grid-template-columns: 1fr; }
            .save-footer { flex-direction: column; align-items: stretch; }
            .save-footer .btn-save { justify-content: center; }
            .settings-section { padding: 16px; }
            .setting-field { padding: 12px; }
        }
        @media (max-width: 640px) {
            :root { --header-height: 66px; }
            .admin-container { padding: calc(var(--header-height) + 10px) 12px 30px; }
            .toolbar-title h2 { font-size: 17px; }
            .section-title-block h3 { font-size: 13px; }
            .section-title-block p { font-size: 11px; }
            .toast-container { top: calc(var(--header-height) + 10px); right: 10px; left: 10px; max-width: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
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
                        <h2>Portal Settings</h2>
                        <p>Configure ARTA thresholds, notifications, and security defaults</p>
                    </div>
                    <button type="submit" form="settingsForm" class="btn-save">
                        <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Changes
                    </button>
                </section>
            </div>

            <form method="POST" id="settingsForm">
                <input type="hidden" name="action" value="save_settings">

                <?php foreach ($group_meta as $group_key => $gmeta): ?>
                    <?php if (empty($grouped[$group_key])) continue; ?>

                    <section class="settings-section">
                        <div class="section-header">
                            <div class="section-icon">
                                <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <?php echo $gmeta['icon']; ?>
                                </svg>
                            </div>
                            <div class="section-title-block">
                                <h3><?php echo htmlspecialchars($gmeta['title']); ?></h3>
                                <p><?php echo htmlspecialchars($gmeta['desc']); ?></p>
                            </div>
                        </div>

                        <div class="settings-grid">
                            <?php foreach ($grouped[$group_key] as $s): ?>
                                <?php
                                    $key       = $s['key'];
                                    $type      = $s['setting_type'];
                                    $value     = (string)($s['setting_value'] ?? '');
                                    $m         = $meta[$key] ?? ['label' => ucwords(str_replace('_', ' ', $key)), 'desc' => ''];
                                    $is_full   = in_array($type, ['textarea'], true);
                                ?>

                                <?php if ($type === 'boolean'): ?>
                                    <div class="setting-field <?php echo $is_full ? 'full' : ''; ?>">
                                        <div class="toggle-row">
                                            <div class="toggle-text">
                                                <div class="label"><?php echo htmlspecialchars($m['label']); ?></div>
                                                <div class="desc"><?php echo htmlspecialchars($m['desc']); ?></div>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="<?php echo htmlspecialchars($key); ?>" value="1" <?php echo $value === '1' ? 'checked' : ''; ?>>
                                                <span class="switch-track"></span>
                                            </label>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="setting-field <?php echo $is_full ? 'full' : ''; ?>">
                                        <label class="setting-label" for="set-<?php echo htmlspecialchars($key); ?>">
                                            <?php echo htmlspecialchars($m['label']); ?>
                                        </label>

                                        <?php if ($type === 'textarea'): ?>
                                            <textarea
                                                class="setting-input"
                                                id="set-<?php echo htmlspecialchars($key); ?>"
                                                name="<?php echo htmlspecialchars($key); ?>"
                                                rows="3"><?php echo htmlspecialchars($value); ?></textarea>

                                        <?php elseif ($type === 'select'): ?>
                                            <?php $opts = json_decode($s['setting_options'] ?? '[]', true) ?: []; ?>
                                            <select
                                                class="setting-input"
                                                id="set-<?php echo htmlspecialchars($key); ?>"
                                                name="<?php echo htmlspecialchars($key); ?>">
                                                <?php foreach ($opts as $opt): ?>
                                                    <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $value === $opt ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($opt); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                        <?php elseif ($type === 'number'): ?>
                                            <input
                                                type="number" step="any"
                                                class="setting-input"
                                                id="set-<?php echo htmlspecialchars($key); ?>"
                                                name="<?php echo htmlspecialchars($key); ?>"
                                                value="<?php echo htmlspecialchars($value); ?>">

                                        <?php elseif ($type === 'email'): ?>
                                            <input
                                                type="email"
                                                class="setting-input"
                                                id="set-<?php echo htmlspecialchars($key); ?>"
                                                name="<?php echo htmlspecialchars($key); ?>"
                                                value="<?php echo htmlspecialchars($value); ?>">

                                        <?php else: ?>
                                            <input
                                                type="text"
                                                class="setting-input"
                                                id="set-<?php echo htmlspecialchars($key); ?>"
                                                name="<?php echo htmlspecialchars($key); ?>"
                                                value="<?php echo htmlspecialchars($value); ?>">
                                        <?php endif; ?>

                                        <p class="setting-hint"><?php echo htmlspecialchars($m['desc']); ?></p>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

                <div class="save-footer">
                    <div class="footer-info">
                        <?php if ($last_updated_at): ?>
                            Last updated <strong><?php echo date('M j, Y \a\t g:i A', strtotime($last_updated_at)); ?></strong>
                            <?php if ($last_updated_by): ?>
                                by <strong><?php echo htmlspecialchars($last_updated_by); ?></strong>
                            <?php endif; ?>
                        <?php else: ?>
                            No settings have been changed yet.
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn-save">
                        <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                        Save All Changes
                    </button>
                </div>
            </form>

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
            toast.innerHTML = `
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="color:${type === 'success' ? 'var(--success)' : 'var(--danger)'}; display:flex;">${iconSvg}</div>
                    <span>${message}</span>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            `;
            container.appendChild(toast);
            setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4500);
        }

        <?php if (!empty($toast_message)): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showToast(<?php echo json_encode($toast_message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($toast_type); ?>);
            });
        <?php endif; ?>
    </script>
</body>
</html>