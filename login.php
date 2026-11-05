<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Manila');

// 1. Role-aware redirect if already authenticated
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $role = $_SESSION['internal_user_type'] ?? '';
    
    if ($role === 'admin') {
        header("Location: admin_dashboard.php");
    } elseif ($role === 'zppsu_employee') {
        header("Location: faculty_dashboard.php");
    } else {
        header("Location: student_dashboard.php");
    }
    exit();
}

require_once 'db_connection.php';
mysqli_report(MYSQLI_REPORT_OFF);

$error_message = '';

// ===========================================================================
// PRE-CHECK 1: BOT DETECTION
// ===========================================================================
function is_suspicious_user_agent($ua) {
    if (empty($ua)) return true;
    return (bool)preg_match('/bot|crawl|spider|scraper|curl|wget|python|java|perl|php|scan|nikto|sqlmap/i', $ua);
}

// ===========================================================================
// PRE-CHECK 2: RATE LIMITING (Brute-force protection)
// ===========================================================================
function count_recent_failures($conn, $ip, $minutes = 10) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c FROM suspicious_activity
        WHERE ip_address = ? 
          AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    if (!$stmt) return 0;
    $stmt->bind_param("si", $ip, $minutes);
    $stmt->execute();
    $c = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $c;
}

$ip_address  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$device_info = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';
$is_rate_limited = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recent_failures = count_recent_failures($conn, $ip_address, 10);
    if ($recent_failures >= 5) {
        $is_rate_limited = true;
        $error_message = "Too many failed attempts from your IP. Please try again in 10 minutes.";
        log_suspicious_activity($conn, 'RATE_LIMIT', $ip_address, "Brute-force protection triggered: {$recent_failures} failures in 10 min.");
    }
}

// ===========================================================================
// MAIN LOGIN FLOW
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_rate_limited) {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check bot User-Agent
    if (is_suspicious_user_agent($device_info)) {
        log_suspicious_activity($conn, $identifier ?: 'UNKNOWN', $ip_address, "Suspicious User-Agent detected: " . mb_substr($device_info, 0, 100));
        log_security_event($conn, $identifier ?: null, 'LOGIN_BLOCKED', $ip_address, $device_info);
    }

    if (empty($identifier) || empty($password)) {
        $error_message = "Please enter both your ID / Email and password.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, full_name, email, password, internal_user_type, client_type, age_group, sex, region FROM users WHERE user_id = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                log_security_event($conn, $user['user_id'], 'LOGIN_SUCCESS', $ip_address, $device_info);

                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['student_number'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['client_type'] = $user['client_type'];
                $_SESSION['internal_user_type'] = $user['internal_user_type'];
                $_SESSION['email'] = $user['email'];

                $stmt->close();

                if ($user['internal_user_type'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['internal_user_type'] === 'zppsu_employee') {
                    header("Location: faculty_dashboard.php");
                } else {
                    header("Location: student_dashboard.php");
                }
                exit();
            } else {
                log_security_event($conn, $identifier, 'LOGIN_FAILED', $ip_address, $device_info);
                log_suspicious_activity($conn, $identifier, $ip_address, "Incorrect password attempt.");
                $error_message = "Invalid password. Please check your credentials.";
            }
        } else {
            log_security_event($conn, $identifier, 'LOGIN_FAILED', $ip_address, $device_info);
            log_suspicious_activity($conn, $identifier, $ip_address, "Non-existent user login attempt.");
            $error_message = "No account found with that ID or Email.";
        }

        if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
    }
}

// ===========================================================================
// LOGGING HELPERS
// ===========================================================================
function log_security_event($conn, $user_id, $event_type, $ip, $device) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS security_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(50) NULL,
            event_type VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NULL,
            device_info TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user (user_id),
            KEY idx_event (event_type),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB
    ");
    $stmt = $conn->prepare("INSERT INTO security_logs (user_id, event_type, ip_address, device_info) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssss", $user_id, $event_type, $ip, $device);
        $stmt->execute();
        $stmt->close();
    }
}

function log_suspicious_activity($conn, $username, $ip, $reason) {
    $stmt = $conn->prepare("INSERT INTO suspicious_activity (username, ip_address, reason) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sss", $username, $ip, $reason);
        $stmt->execute();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sign In | ZPPSU CSM</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --maroon: #7A0000;
            --maroon-dark: #520000;
            --gold: #F2A900;
            --gold-light: #FFF8E6;
            --bg: #F8FAFC;
            --surface: #FFFFFF;
            --border: #E2E8F0;
            --text-heading: #0F172A;
            --text-body: #475569;
            --text-muted: #64748B;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-text-size-adjust: 100%;
        }

        body {
            background-color: var(--bg);
            color: var(--text-heading);
            min-height: 100vh;
        }

        /* ============================================================
           SPLIT SCREEN LAYOUT
           ============================================================ */
        .login-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
            width: 100%;
        }

        /* ============================================================
           LEFT PANEL: VISUAL SHOWCASE
           ============================================================ */
        .visual-panel {
            position: relative;
            /* REDUCED OPACITY: Photo is now much more visible */
            background: 
                linear-gradient(135deg, rgba(30, 0, 0, 0.65) 0%, rgba(15, 23, 42, 0.75) 100%),
                url('bg_picture/campus.jpeg') center center / cover no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 60px;
            color: #FFFFFF;
            overflow: hidden;
        }

        .visual-panel::after {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(242, 169, 0, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .visual-top {
            display: flex;
            align-items: center;
            gap: 16px;
            z-index: 2;
        }

        .visual-top img {
            height: 60px;
            width: auto;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.5));
        }

        .visual-brand h2 {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #FFFFFF;
            line-height: 1.2;
        }

        .visual-brand span {
            font-size: 0.75rem;
            color: var(--gold);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .visual-center {
            max-width: 540px;
            z-index: 2;
            margin: 40px 0;
        }

        .compliance-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(242, 169, 0, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(242, 169, 0, 0.3);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--gold);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .visual-center h1 {
            font-size: 3.2rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -1.5px;
            margin-bottom: 20px;
            color: #FFFFFF;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .visual-center h1 span {
            color: var(--gold);
        }

        .visual-center p {
            font-size: 1.05rem;
            color: #E2E8F0;
            line-height: 1.6;
            font-weight: 500;
            max-width: 480px;
            text-shadow: 0 1px 4px rgba(0,0,0,0.3);
        }

        .visual-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            padding-top: 24px;
            z-index: 2;
        }

        .location-tag {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #CBD5E1;
            font-weight: 600;
        }

        .dot-pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10B981;
            box-shadow: 0 0 10px #10B981;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* ============================================================
           RIGHT PANEL: FORM
           ============================================================ */
        .form-panel {
            background-color: var(--surface);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            position: relative;
        }

        .form-wrapper {
            width: 100%;
            max-width: 420px;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-header {
            margin-bottom: 32px;
            text-align: center;
        }

        .form-header h2 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.8px;
            margin-bottom: 8px;
        }

        .form-header p {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .alert-error {
            background-color: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            margin-bottom: 24px;
            line-height: 1.45;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both;
        }

        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 8px;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: var(--text-muted);
            pointer-events: none;
            transition: color 0.2s;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 14px 14px 14px 42px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: inherit;
            font-size: 15px;
            background-color: #F8FAFC;
            color: var(--text-heading);
            transition: all 0.2s ease;
            outline: none;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--maroon);
            background-color: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(122, 0, 0, 0.08);
        }

        input[type="text"]:focus + .input-icon,
        input[type="password"]:focus + .input-icon {
            color: var(--maroon);
        }

        /* Password Toggle */
        .pwd-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: background 0.2s, color 0.2s;
        }

        .pwd-toggle:hover { background: #F1F5F9; color: var(--text-heading); }
        .pwd-toggle svg { width: 18px; height: 18px; }

        /* Options Row */
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            font-size: 13px;
        }

        .checkbox-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: var(--text-body);
            font-weight: 600;
        }

        .checkbox-wrap input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--maroon);
            cursor: pointer;
        }

        .form-options a {
            color: var(--maroon);
            text-decoration: none;
            font-weight: 700;
            transition: color 0.2s;
        }

        .form-options a:hover { text-decoration: underline; }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            background-color: var(--maroon);
            color: #FFFFFF;
            border: none;
            padding: 15px;
            font-size: 15px;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(122, 0, 0, 0.2);
        }

        .btn-submit:hover {
            background-color: var(--maroon-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(122, 0, 0, 0.3);
        }

        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 28px 0;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border);
        }

        .divider span { padding: 0 16px; }

        /* Google Button */
        .btn-google {
            width: 100%;
            background-color: #FFFFFF;
            color: var(--text-heading);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 13px;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }

        .btn-google:hover {
            background-color: #F8FAFC;
            border-color: #CBD5E1;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }

        .btn-google svg { flex-shrink: 0; }

        /* Footer Links */
        .links-stack {
            margin-top: 32px;
            text-align: center;
            font-size: 13.5px;
            color: var(--text-body);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .links-stack a {
            color: var(--maroon);
            text-decoration: none;
            font-weight: 700;
            transition: color 0.2s;
        }

        .links-stack a:hover { text-decoration: underline; }

        .compliance-footnote {
            margin-top: 32px;
            font-size: 11.5px;
            color: var(--text-muted);
            text-align: center;
            line-height: 1.6;
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1024px) {
            .login-container { grid-template-columns: 1fr; }
            
            /* RESTORED MOBILE HEADER */
            .visual-panel { 
                display: flex;
                height: 220px; /* Compact height for mobile header */
                padding: 24px;
                justify-content: center; /* Center the brand vertically */
            }
            
            /* Hide large text on mobile to save space */
            .visual-center, .visual-bottom { 
                display: none; 
            }
            
            .visual-top { 
                gap: 12px; 
            }
            .visual-top img { 
                height: 48px; 
            }
            .visual-brand h2 { 
                font-size: 1.1rem; 
            }
            .visual-brand span { 
                font-size: 0.7rem; 
            }
            
            .form-panel { 
                padding: 40px 24px; 
            }
        }

        @media (max-width: 480px) {
            .form-header h2 { font-size: 1.75rem; }
            .form-options { flex-direction: column; align-items: flex-start; gap: 12px; }
            .form-options a { margin-left: 24px; }
        }
    </style>
</head>
<body>

    <div class="login-container">
        
        <!-- Left Side / Mobile Header: Visual Showcase -->
        <div class="visual-panel">
            <div class="visual-top">
                <img src="logo/ZPPSU_LOGO.png" alt="ZPPSU Logo">
                <div class="visual-brand">
                    <h2>ZPPSU CSM</h2>
                    <span>Quality Assurance & Governance</span>
                </div>
            </div>

            <div class="visual-center">
                <div class="compliance-pill">
                    🛡️ ARTA R.A. 11032 Assessment
                </div>
                <h1>Empowering Service Through <span>Your Voice.</span></h1>
                <p>Sign in to rate university colleges and administrative service units with your auto-filled demographic profile and submission history.</p>
            </div>

            <div class="visual-bottom">
                <div class="location-tag">
                    <span class="dot-pulse"></span>
                    Main Campus • R.T. Lim Boulevard, Zamboanga City
                </div>
                <span style="font-size: 12px; color: #94A3B8; font-weight: 600;">ARTA MC 2022-05</span>
            </div>
        </div>

        <!-- Right Side: Authentication Form -->
        <div class="form-panel">
            <div class="form-wrapper">
                
                <div class="form-header">
                    <h2>Welcome Back</h2>
                    <p>Please enter your credentials to access the portal.</p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert-error">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    
                    <div class="form-group">
                        <label for="identifier">ID Number or Email Address</label>
                        <div class="input-wrap">
                            <input type="text" id="identifier" name="identifier" placeholder="email@zppsu.edu.ph" required autofocus autocomplete="username" <?php echo $is_rate_limited ? 'disabled' : ''; ?>>
                            <svg class="input-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password" <?php echo $is_rate_limited ? 'disabled' : ''; ?>>
                            <svg class="input-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            
                            <button type="button" class="pwd-toggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                                <svg id="eyeIcon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-wrap">
                            <input type="checkbox" name="remember">
                            Remember me
                        </label>
                        <a href="forgot_password.php">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-submit" id="btnSubmit" <?php echo $is_rate_limited ? 'disabled' : ''; ?>>
                        <?php if ($is_rate_limited): ?>
                            Temporarily Blocked
                        <?php else: ?>
                            Sign In to Portal
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        <?php endif; ?>
                    </button>
                </form>

                <div class="divider">
                    <span>or continue with</span>
                </div>

                <button type="button" class="btn-google" onclick="alert('Google Workspace Sign-In is currently in sandbox setup. Please use your standard ZPPSU credentials above.')">
                    <svg width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    Sign in with Google
                </button>

                <div class="links-stack">
                    <div>Don't have an account? <a href="register.php">Create an account</a></div>
                    <div><a href="index.php">← Back to Portal Home</a></div>
                </div>

                <div class="compliance-footnote">
                    In compliance with Republic Act No. 11032<br>
                    Ease of Doing Business and Efficient Government Service Delivery Act
                </div>

            </div>
        </div>

    </div>

    <script>
        function togglePassword() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            const showSvg = `<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
            const hideSvg = `<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>`;
            
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.innerHTML = hideSvg;
            } else {
                pwd.type = 'password';
                icon.innerHTML = showSvg;
            }
        }

        // Add loading state on submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnSubmit');
            if (!btn.disabled) {
                btn.innerHTML = `
                    <svg class="spinner" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Signing In...
                `;
                btn.style.opacity = '0.8';
                btn.style.pointerEvents = 'none';
            }
        });
    </script>
</body>
</html>