<?php
// admin_profile.php — Manage Admin Profile & Security

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
if (!in_array($_SESSION['internal_user_type'] ?? '', ['admin', 'zppsu_employee'])) {
    header("Location: student_dashboard.php"); exit();
}

require_once 'db_connection.php';
mysqli_report(MYSQLI_REPORT_OFF);

$admin_id      = $_SESSION['user_id'] ?? '';
$toast_message = '';
$toast_type    = '';

// ---------------------------------------------------------------------------
// FETCH CURRENT PROFILE
// ---------------------------------------------------------------------------
$profile = [];
$stmt = $conn->prepare("SELECT user_id, full_name, email, internal_user_type, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $profile = $res->fetch_assoc();
} else {
    header("Location: logout.php");
    exit();
}
$stmt->close();

// ---------------------------------------------------------------------------
// POST ACTIONS
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -------- UPDATE PROFILE INFO --------
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if (empty($full_name) || empty($email)) {
            $toast_message = "Name and Email cannot be empty.";
            $toast_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $toast_message = "Please enter a valid email address.";
            $toast_type = 'error';
        } else {
            // Check email uniqueness
            $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
            $chk->bind_param("ss", $email, $admin_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $toast_message = "This email is already in use by another account.";
                $toast_type = 'error';
            } else {
                $upd = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
                $upd->bind_param("sss", $full_name, $email, $admin_id);
                
                if ($upd->execute()) {
                    $_SESSION['full_name'] = $full_name; // Update session for sidebar/header
                    $profile['full_name']  = $full_name;
                    $profile['email']      = $email;
                    $toast_message = "Profile information updated successfully.";
                    $toast_type = 'success';
                } else {
                    $toast_message = "Update failed: " . $conn->error;
                    $toast_type = 'error';
                }
                $upd->close();
            }
            $chk->close();
        }
    }

    // -------- UPDATE PASSWORD --------
    elseif ($action === 'update_password') {
        $current_pwd = $_POST['current_password'] ?? '';
        $new_pwd     = $_POST['new_password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';

        if (empty($current_pwd) || empty($new_pwd) || empty($confirm_pwd)) {
            $toast_message = "Please fill out all password fields.";
            $toast_type = 'error';
        } elseif ($new_pwd !== $confirm_pwd) {
            $toast_message = "New password and confirmation do not match.";
            $toast_type = 'error';
        } elseif (strlen($new_pwd) < 6) {
            $toast_message = "New password must be at least 6 characters long.";
            $toast_type = 'error';
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->bind_param("s", $admin_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (password_verify($current_pwd, $row['password'])) {
                $hashed = password_hash($new_pwd, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $upd->bind_param("ss", $hashed, $admin_id);
                
                if ($upd->execute()) {
                    $toast_message = "Password changed successfully.";
                    $toast_type = 'success';
                } else {
                    $toast_message = "Failed to update password: " . $conn->error;
                    $toast_type = 'error';
                }
                $upd->close();
            } else {
                $toast_message = "Incorrect current password.";
                $toast_type = 'error';
            }
        }
    }
}

// Helper to get initials
$name_parts = explode(' ', $profile['full_name']);
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts)-1], 0, 1));
} else {
    $initials = strtoupper(substr($profile['full_name'], 0, 2));
}

// Page metadata
$page_title    = 'My Profile';
$page_crumb    = 'System & Security / My Profile';
$page_subtitle = 'Manage your personal credentials and security settings';
$unread_notifications = 0; // Can be populated from DB if needed
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
            width: 100%; max-width: 1000px;
            margin: 0 auto;
            padding: calc(var(--header-height) + 20px) 24px 40px;
            flex: 1; display: flex; flex-direction: column;
        }
        .fixed-header-deck {
            position: sticky; top: var(--header-height); z-index: 800;
            background: var(--bg); padding-top: 4px; padding-bottom: 12px;
        }

        /* TOOLBAR */
        .toolbar {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px 20px; margin-bottom: 20px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .toolbar-title h2 { font-size: 19px; font-weight: 800; color: var(--text-heading); margin: 0; }
        .toolbar-title p { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }

        /* LAYOUT */
        .profile-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 20px;
            align-items: start;
        }

        /* LEFT COLUMN: PROFILE CARD */
        .profile-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            text-align: center;
            position: sticky;
            top: calc(var(--header-height) + 20px);
        }
        .avatar-circle {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-dark) 100%);
            color: #FFFFFF; display: flex; align-items: center; justify-content: center;
            font-size: 28px; font-weight: 800; margin: 0 auto 16px;
            border: 3px solid #FEF2F2;
            box-shadow: 0 4px 10px rgba(122, 0, 0, 0.15);
        }
        .profile-name { font-size: 18px; font-weight: 800; color: var(--text-heading); margin: 0 0 4px; }
        .profile-role {
            display: inline-block; font-size: 11px; font-weight: 800;
            background: #FEF2F2; color: var(--maroon); border: 1px solid #FECACA;
            padding: 3px 10px; border-radius: 20px; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 16px;
        }
        .profile-meta {
            border-top: 1px dashed var(--border); padding-top: 16px; margin-top: 16px;
            text-align: left;
        }
        .meta-row { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 8px; }
        .meta-row span { color: var(--text-muted); font-weight: 600; }
        .meta-row strong { color: var(--text-heading); font-weight: 700; }

        /* RIGHT COLUMN: FORMS */
        .panel-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            margin-bottom: 20px;
        }
        .panel-title {
            font-size: 15px; font-weight: 800; color: var(--text-heading);
            margin: 0 0 20px; padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 8px;
        }
        .panel-title svg { width: 18px; height: 18px; color: var(--maroon); }

        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; font-size: 12px; font-weight: 700;
            color: var(--text-heading); margin-bottom: 6px;
        }
        .form-group input {
            width: 100%; padding: 10px 13px;
            border: 1.5px solid var(--border); border-radius: 9px;
            font-size: 13px; background: #F8FAFC; color: var(--text-heading);
            font-family: inherit; transition: border-color 0.18s, background 0.18s;
            height: 42px; outline: none;
        }
        .form-group input:focus {
            border-color: var(--maroon); background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(122, 0, 0, 0.08);
        }
        .form-group input[readonly] {
            background: #F1F5F9; color: var(--text-muted); cursor: not-allowed;
        }

        .btn-primary {
            background: var(--maroon); color: #FFFFFF; border: none;
            padding: 10px 20px; border-radius: 8px; font-size: 12.5px; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
            font-family: inherit; transition: background 0.18s, transform 0.12s;
            margin-top: 8px;
        }
        .btn-primary:hover { background: var(--maroon-dark); }
        .btn-primary:active { transform: scale(0.98); }

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
            justify-content: space-between; gap: 10px; animation: toastSlideIn 0.3s forwards;
        }
        .toast-error { border-left-color: var(--danger); }
        .toast-close { background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 18px; line-height: 1; }
        @keyframes toastSlideIn { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .admin-main-wrap { margin-left: 0; width: 100%; }
            .admin-container { padding: calc(var(--header-height) + 12px) 16px 40px; }
        }
        @media (max-width: 768px) {
            .profile-grid { grid-template-columns: 1fr; }
            .profile-card { position: static; margin-bottom: 20px; }
            .toolbar { flex-direction: column; align-items: stretch; padding: 14px 16px; }
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
    // Dynamic sidebar — loads faculty or admin version based on logged-in role
    if (($_SESSION['internal_user_type'] ?? '') === 'zppsu_employee') {
        $sidebar_path = __DIR__ . '/faculty_sidebar.php';
    } else {
        $sidebar_path = __DIR__ . '/admin_sidebar.php';
    }
    if (file_exists($sidebar_path)) require_once $sidebar_path;

    $header_path = __DIR__ . '/admin_header.php';
    if (file_exists($header_path)) require_once $header_path;
    ?>

    <div class="admin-main-wrap">
        <main class="admin-container">

            <div class="fixed-header-deck">
                <section class="toolbar">
                    <div class="toolbar-title">
                        <h2>My Profile</h2>
                        <p>Manage your personal credentials and security settings</p>
                    </div>
                </section>
            </div>

            <div class="profile-grid">
                
                <!-- LEFT COLUMN: SUMMARY -->
                <div class="profile-card">
                    <div class="avatar-circle">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>
                    <h3 class="profile-name"><?php echo htmlspecialchars($profile['full_name']); ?></h3>
                    <span class="profile-role"><?php echo htmlspecialchars(strtoupper($profile['internal_user_type'])); ?></span>
                    
                    <div class="profile-meta">
                        <div class="meta-row">
                            <span>Employee ID</span>
                            <strong><?php echo htmlspecialchars($profile['user_id']); ?></strong>
                        </div>
                        <div class="meta-row">
                            <span>Email</span>
                            <strong><?php echo htmlspecialchars($profile['email']); ?></strong>
                        </div>
                        <div class="meta-row">
                            <span>Joined</span>
                            <strong><?php echo date('M d, Y', strtotime($profile['created_at'])); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: FORMS -->
                <div>
                    <!-- Update Info -->
                    <div class="panel-card">
                        <div class="panel-title">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Personal Information
                        </div>
                        <form method="POST" action="admin_profile.php">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-group">
                                <label>Employee ID (Locked)</label>
                                <input type="text" value="<?php echo htmlspecialchars($profile['user_id']); ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($profile['full_name']); ?>" required autocomplete="off">
                            </div>
                            
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" required autocomplete="off">
                            </div>

                            <button type="submit" class="btn-primary">Save Changes</button>
                        </form>
                    </div>

                    <!-- Update Password -->
                    <div class="panel-card">
                        <div class="panel-title">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Security &amp; Password
                        </div>
                        <form method="POST" action="admin_profile.php" id="pwdForm">
                            <input type="hidden" name="action" value="update_password">
                            
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required autocomplete="current-password">
                            </div>
                            
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" id="newPassword" required minlength="6" autocomplete="new-password">
                            </div>
                            
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirmPassword" required minlength="6" autocomplete="new-password">
                            </div>

                            <button type="submit" class="btn-primary">Update Password</button>
                        </form>
                    </div>
                </div>

            </div>

        </main>
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
           CLIENT-SIDE PASSWORD VALIDATION
           ============================================================ */
        document.getElementById('pwdForm').addEventListener('submit', function(e) {
            const newPwd = document.getElementById('newPassword').value;
            const confirmPwd = document.getElementById('confirmPassword').value;

            if (newPwd !== confirmPwd) {
                e.preventDefault();
                showToast("New password and confirmation do not match.", "error");
            }
        });
    </script>
</body>
</html>