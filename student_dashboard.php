<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Manila');

// 1. Authentication Guard
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// 2. SECURITY FIX: Block Admins from viewing the Student Portal
if (($_SESSION['internal_user_type'] ?? '') === 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

require_once 'db_connection.php';

$user_id = $_SESSION['user_id'] ?? $_SESSION['student_number'] ?? null;

if (!$user_id) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// 3. Fetch User Profile
$user_stmt = $conn->prepare("SELECT full_name, email, internal_user_type, client_type, region FROM users WHERE user_id = ? LIMIT 1");
$user_stmt->bind_param("s", $user_id);
$user_stmt->execute();
$user_res = $user_stmt->get_result();
$user_data = $user_res ? $user_res->fetch_assoc() : null;
$user_stmt->close();

$full_name   = $user_data['full_name'] ?? 'User';
$role_badge  = ucwords(str_replace('_', ' ', $user_data['internal_user_type'] ?? 'student'));
$client_type = $user_data['client_type'] ?? 'Citizen';

// 4. Fetch User Feedback Metrics
$stats_stmt = $conn->prepare("SELECT COUNT(*) AS total_submitted, MAX(submission_timestamp) AS last_submission FROM arta_feedback WHERE user_id = ?");
$stats_stmt->bind_param("s", $user_id);
$stats_stmt->execute();
$stats_res = $stats_stmt->get_result();
$stats = $stats_res ? $stats_res->fetch_assoc() : [];
$stats_stmt->close();

$total_submitted = (int)($stats['total_submitted'] ?? 0);
$last_submission = !empty($stats['last_submission']) ? date('M d, Y', strtotime($stats['last_submission'])) : 'None yet';

// 5. Fetch Recent Submissions
$recent_stmt = $conn->prepare("
    SELECT f.feedback_id, f.service_accessed, f.submission_timestamp, o.office_name, o.office_type 
    FROM arta_feedback f
    JOIN offices o ON f.office_id = o.id
    WHERE f.user_id = ?
    ORDER BY f.submission_timestamp DESC 
    LIMIT 4
");
$recent_stmt->bind_param("s", $user_id);
$recent_stmt->execute();
$recent_res = $recent_stmt->get_result();
$recent_feedbacks = [];

if ($recent_res) {
    while ($row = $recent_res->fetch_assoc()) {
        $recent_feedbacks[] = $row;
    }
}
$recent_stmt->close();

// 6. Fetch All Offices and Review Status
$offices_query = "SELECT id, office_name, office_description, office_type FROM offices ORDER BY office_name ASC";
$offices_res = $conn->query($offices_query);

$completed_ids = [];
$feedback_ids_query = $conn->prepare("SELECT office_id FROM arta_feedback WHERE user_id = ?");
$feedback_ids_query->bind_param("s", $user_id);
$feedback_ids_query->execute();
$fb_res = $feedback_ids_query->get_result();

if ($fb_res) {
    while ($r = $fb_res->fetch_assoc()) {
        $completed_ids[] = (int)$r['office_id'];
    }
}
$feedback_ids_query->close();

$total_offices = $offices_res ? $offices_res->num_rows : 0;
$pending_offices = max(0, $total_offices - $total_submitted);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Client Portal | ZPPSU CSM</title>
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
            color: var(--text-body);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ============================================================
           TOP NAVIGATION
           ============================================================ */
        .navbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .nav-brand img {
            height: 36px;
            width: auto;
        }

        .brand-text h1 {
            font-size: 15px;
            font-weight: 800;
            color: var(--maroon);
            letter-spacing: -0.3px;
            line-height: 1.1;
        }

        .brand-text span {
            font-size: 9.5px;
            font-weight: 700;
            color: var(--gold);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 12px;
            background: var(--bg);
            border-radius: 30px;
            border: 1px solid var(--border);
        }

        .avatar {
            width: 28px;
            height: 28px;
            background: var(--maroon);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 11px;
        }

        .user-meta { text-align: left; line-height: 1.2; }
        .user-name { font-size: 12px; font-weight: 700; color: var(--text-heading); }
        .user-id { font-size: 10px; color: var(--text-muted); }

        .btn-signout {
            color: var(--maroon);
            text-decoration: none;
            font-size: 11.5px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 6px;
            border: 1.5px solid #FECACA;
            background: #FEF2F2;
            transition: all 0.18s;
        }

        .btn-signout:hover { background: #FEE2E2; border-color: #FCA5A5; }

        /* ============================================================
           MAIN CONTAINER
           ============================================================ */
        .dashboard-container {
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            padding: 24px 24px 60px;
        }

        /* ============================================================
           HERO BANNER
           ============================================================ */
        .hero-banner {
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-dark) 100%);
            border-radius: 16px;
            padding: 28px 32px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(122, 0, 0, 0.25);
            margin-bottom: 20px;
        }

        .hero-banner::after {
            content: '';
            position: absolute;
            right: -40px;
            bottom: -50px;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(242, 169, 0, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-content { position: relative; z-index: 2; }
        .hero-content h2 { font-size: 22px; font-weight: 800; margin-bottom: 6px; letter-spacing: -0.5px; }
        .hero-content p { font-size: 13px; color: #F1F5F9; max-width: 650px; line-height: 1.5; opacity: 0.95; }

        .hero-badges { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; }
        .hero-tag {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10.5px;
            font-weight: 700;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #FFF;
        }

        /* ============================================================
           METRICS GRID
           ============================================================ */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr); /* Full width 4-col on laptop */
            gap: 16px;
            margin-bottom: 28px;
        }

        .metric-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.05);
            border-color: var(--border-strong);
        }

        .metric-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .metric-icon.gold { background: var(--gold-light); color: var(--gold); }
        .metric-icon.maroon { background: #FEF2F2; color: var(--maroon); }
        .metric-icon.green { background: var(--success-bg); color: var(--success); }
        .metric-icon.blue { background: #EFF6FF; color: #2563EB; }

        .metric-data h3 { font-size: 20px; font-weight: 800; color: var(--text-heading); line-height: 1.1; letter-spacing: -0.5px; }
        .metric-data span { font-size: 10px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; display: block; }

        /* ============================================================
           LAYOUT
           ============================================================ */
        .dashboard-split {
            display: grid;
            grid-template-columns: 2.2fr 1fr;
            gap: 20px;
        }

        .card-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .panel-title { font-size: 16px; font-weight: 800; color: var(--text-heading); letter-spacing: -0.3px; margin: 0; }

        /* ============================================================
           FILTERS & SEARCH
           ============================================================ */
        .filter-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .search-field { flex-grow: 1; min-width: 200px; position: relative; }

        .search-field input {
            width: 100%;
            padding: 9px 12px 9px 34px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 12.5px;
            background: #F8FAFC;
            outline: none;
            transition: all 0.18s;
        }

        .search-field input:focus {
            background: #FFFFFF;
            border-color: var(--maroon);
            box-shadow: 0 0 0 4px rgba(122, 0, 0, 0.08);
        }

        .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            width: 14px;
            height: 14px;
        }

        .tabs-group {
            display: flex;
            gap: 4px;
            background: #F1F5F9;
            padding: 3px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 6px 12px;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-muted);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.18s;
        }

        .tab-btn:hover { color: var(--text-heading); }
        .tab-btn.active { background: var(--surface); color: var(--maroon); box-shadow: 0 1px 3px rgba(0,0,0,0.08); }

        /* ============================================================
           OFFICE DIRECTORY
           ============================================================ */
        .offices-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            max-height: 600px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .offices-grid::-webkit-scrollbar { width: 5px; }
        .offices-grid::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }

        .office-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.18s;
        }

        .office-card:hover {
            border-color: var(--border-strong);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        .office-card.completed {
            background: #FAFAFA;
            border-color: var(--success);
            border-style: dashed;
        }

        .office-info h4 { font-size: 13px; font-weight: 700; color: var(--text-heading); margin: 6px 0 0 0; line-height: 1.3; }

        .office-type-pill {
            display: inline-block;
            font-size: 9.5px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .pill-academic { background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A; }
        .pill-admin { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }

        .office-actions {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-evaluate {
            background: var(--maroon);
            color: white;
            text-decoration: none;
            padding: 7px 14px;
            font-size: 11.5px;
            font-weight: 700;
            border-radius: 6px;
            transition: all 0.18s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            width: 100%;
            justify-content: center;
        }

        .btn-evaluate:hover { background: var(--maroon-dark); transform: translateY(-1px); }
        .btn-evaluate:active { transform: translateY(0); }

        .badge-done {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--success);
            background: var(--success-bg);
            padding: 7px 14px;
            border-radius: 6px;
            width: 100%;
        }

        .empty-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 30px 20px;
            color: var(--text-muted);
        }

        /* ============================================================
           SIDE STACK (Recent Activity)
           ============================================================ */
        .side-stack { display: flex; flex-direction: column; gap: 16px; }

        .activity-feed { display: flex; flex-direction: column; gap: 10px; }

        .feed-item {
            padding: 10px 12px;
            background: var(--bg);
            border-radius: 8px;
            border-left: 3px solid var(--maroon);
            transition: background 0.18s;
        }
        .feed-item:hover { background: #F1F5F9; }

        .feed-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2px; gap: 8px;}
        .feed-office { font-size: 12px; font-weight: 700; color: var(--text-heading); line-height: 1.3; }
        .feed-date { font-size: 10px; color: var(--text-muted); font-weight: 600; white-space: nowrap; }
        .feed-service { font-size: 11px; color: var(--text-body); line-height: 1.4; }

        .arta-callout {
            background: var(--gold-light);
            border: 1px solid #FDE68A;
            border-radius: 12px;
            padding: 16px;
            color: #713F12;
        }

        .arta-callout h4 { font-size: 12px; font-weight: 800; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .arta-callout p { font-size: 11px; line-height: 1.45; color: #854D0E; }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1024px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px; }
            .dashboard-split { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .dashboard-container { padding: 16px 14px 40px; }
            .metrics-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 16px; }
            .metric-card { padding: 10px; gap: 8px; flex-direction: column; align-items: flex-start; }
            .metric-icon { width: 30px; height: 30px; border-radius: 8px; }
            .metric-icon svg { width: 14px; height: 14px; }
            .metric-data h3 { font-size: 16px; }
            .metric-data span { font-size: 9px; margin-top: 2px; }
            
            .hero-banner { padding: 16px 16px; margin-bottom: 16px; }
            .hero-content h2 { font-size: 18px; margin-bottom: 4px; }
            .hero-content p { font-size: 11.5px; line-height: 1.4; }
            .hero-badges { margin-top: 10px; gap: 6px; }
            .hero-tag { padding: 3px 8px; font-size: 9.5px; }
            
            .card-panel { padding: 16px; }
            .filter-controls { flex-direction: column; align-items: stretch; gap: 8px; }
            .tabs-group { width: 100%; justify-content: space-between; }
            .tab-btn { flex: 1; text-align: center; padding: 5px 8px; }
            .offices-grid { grid-template-columns: 1fr; gap: 10px; }
        }

        @media (max-width: 640px) {
            .navbar { padding: 8px 12px; }
            .nav-brand img { height: 30px; }
            .brand-text h1 { font-size: 13px; }
            .brand-text span { font-size: 8.5px; }
            .user-meta { display: none; }
            .avatar { width: 26px; height: 26px; font-size: 10px; }
            .btn-signout { padding: 5px 10px; font-size: 11px; }
        }
    </style>
</head>
<body>

    <header class="navbar">
        <a href="student_dashboard.php" class="nav-brand">
            <img src="logo/ZPPSU_LOGO.png" alt="ZPPSU Logo" onerror="this.style.display='none'">
            <div class="brand-text">
                <h1>ZPPSU PORTAL</h1>
                <span>ARTA Client Experience</span>
            </div>
        </a>

        <div class="nav-user">
            <div class="user-pill">
                <div class="avatar"><?php echo strtoupper(substr($full_name, 0, 1)); ?></div>
                <div class="user-meta">
                    <div class="user-name"><?php echo htmlspecialchars($full_name); ?></div>
                    <div class="user-id"><?php echo htmlspecialchars($user_id); ?></div>
                </div>
            </div>
            <a href="logout.php" class="btn-signout">Sign Out</a>
        </div>
    </header>

    <main class="dashboard-container">
        
        <section class="hero-banner">
            <div class="hero-content">
                <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', trim($full_name))[0]); ?>! 👋</h2>
                <p>Track your completed evaluations and provide direct feedback to ZPPSU academic colleges and service units in compliance with Republic Act No. 11032.</p>
                <div class="hero-badges">
                    <span class="hero-tag">Role: <?php echo htmlspecialchars($role_badge); ?></span>
                    <span class="hero-tag">ARTA Classification: <?php echo htmlspecialchars($client_type); ?></span>
                    <span class="hero-tag">Campus: Main Campus</span>
                </div>
            </div>
        </section>

        <section class="metrics-grid">
            <div class="metric-card">
                <div class="metric-icon green">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="metric-data">
                    <h3><?php echo $total_submitted; ?></h3>
                    <span>Evaluations</span>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon maroon">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="metric-data">
                    <h3><?php echo $pending_offices; ?></h3>
                    <span>Pending</span>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon gold">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <div class="metric-data">
                    <h3 style="font-size: 13px; margin-top: 2px;"><?php echo $last_submission; ?></h3>
                    <span>Last Submittal</span>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon blue">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <div class="metric-data">
                    <h3>ARTA</h3>
                    <span>Compliant</span>
                </div>
            </div>
        </section>

        <div class="dashboard-split">
            
            <section class="card-panel">
                <div class="panel-header">
                    <h3 class="panel-title">University Offices & Colleges</h3>
                </div>

                <div class="filter-controls">
                    <div class="search-field">
                        <svg class="search-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <input type="text" id="directorySearch" placeholder="Search by office or college name..." onkeyup="filterDirectory()">
                    </div>

                    <div class="tabs-group">
                        <button class="tab-btn active" onclick="setCategoryFilter('all', this)">All</button>
                        <button class="tab-btn" onclick="setCategoryFilter('Academic', this)">Colleges</button>
                        <button class="tab-btn" onclick="setCategoryFilter('Administrative', this)">Admin</button>
                    </div>
                </div>

                <div class="offices-grid" id="officesContainer">
                    <?php if ($offices_res && $offices_res->num_rows > 0): ?>
                        <?php while ($office = $offices_res->fetch_assoc()): ?>
                            <?php 
                                $is_done = in_array((int)$office['id'], $completed_ids, true);
                                $type_class = ($office['office_type'] === 'Academic') ? 'pill-academic' : 'pill-admin';
                            ?>
                            <div class="office-card <?php echo $is_done ? 'completed' : ''; ?>" 
                                 data-name="<?php echo strtolower(htmlspecialchars($office['office_name'])); ?>"
                                 data-type="<?php echo htmlspecialchars($office['office_type']); ?>">
                                
                                <div class="office-info">
                                    <span class="office-type-pill <?php echo $type_class; ?>">
                                        <?php echo htmlspecialchars($office['office_type']); ?>
                                    </span>
                                    <h4><?php echo htmlspecialchars($office['office_name']); ?></h4>
                                </div>

                                <div class="office-actions">
                                    <?php if ($is_done): ?>
                                        <span class="badge-done">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                            Evaluated
                                        </span>
                                    <?php else: ?>
                                        <a href="arta_feedback_form.php?office_id=<?php echo $office['id']; ?>" class="btn-evaluate">
                                            Rate Office →
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    
                    <div class="empty-results" id="emptyResults" style="display: none;">
                        <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom: 8px; color: #CBD5E1;"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <h4>No offices found</h4>
                        <p>Try adjusting your search or filter settings.</p>
                    </div>
                </div>
            </section>

            <aside class="side-stack">
                
                <div class="card-panel">
                    <h3 class="panel-title" style="margin-bottom: 12px; font-size: 14px;">Recent Submissions</h3>
                    
                    <div class="activity-feed">
                        <?php if (!empty($recent_feedbacks)): ?>
                            <?php foreach ($recent_feedbacks as $fb): ?>
                                <div class="feed-item">
                                    <div class="feed-header">
                                        <span class="feed-office"><?php echo htmlspecialchars($fb['office_name']); ?></span>
                                        <span class="feed-date"><?php echo date('M d', strtotime($fb['submission_timestamp'])); ?></span>
                                    </div>
                                    <div class="feed-service">Service: <?php echo htmlspecialchars($fb['service_accessed']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="font-size: 12px; color: var(--text-muted); text-align: center; padding: 10px 0; line-height: 1.5;">
                                No feedback submitted yet. Select an office on the left to get started!
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="arta-callout">
                    <h4>⚖️ Republic Act No. 11032</h4>
                    <p>Your authentic responses directly contribute to institutional rankings, processing time audits, and public service continuous quality improvement.</p>
                </div>

            </aside>
        </div>
    </main>

    <script>
        let currentType = 'all';

        function setCategoryFilter(type, element) {
            currentType = type;
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            element.classList.add('active');
            filterDirectory();
        }

        function filterDirectory() {
            const query = document.getElementById('directorySearch').value.toLowerCase();
            const cards = document.querySelectorAll('#officesContainer .office-card');
            const emptyState = document.getElementById('emptyResults');
            let visibleCount = 0;

            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                const type = card.getAttribute('data-type');

                const matchesSearch = name.includes(query);
                const matchesType = (currentType === 'all') || (type === currentType);

                if (matchesSearch && matchesType) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (visibleCount === 0) {
                emptyState.style.display = 'block';
            } else {
                emptyState.style.display = 'none';
            }
        }
    </script>
</body>
</html>