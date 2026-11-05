<?php
// Determine the current page for dynamic 'active' link highlighting
$current_page       = basename($_SERVER['PHP_SELF']);
$admin_display_name = $_SESSION['full_name'] ?? 'Administrator';
$admin_role         = ucfirst($_SESSION['internal_user_type'] ?? 'Admin');
$admin_initial      = strtoupper(mb_substr($admin_display_name, 0, 1));

/* ============================================================
   BADGE COUNTS — computed fresh on every page load.
   No AJAX, no auto-refresh, no cache.
   ============================================================ */
$sidebar_badges = [
    'action_plans'  => 0,
    'notifications' => 0,
    'low_ratings'   => 0,
];

if (isset($conn) && $conn instanceof mysqli) {

    $badge_admin_id = $_SESSION['user_id'] ?? '';

    // --- 1) Pending action plans ---
    $r = @$conn->query("SELECT COUNT(*) AS c FROM action_plans WHERE status != 'Resolved'");
    if ($r instanceof mysqli_result) {
        $sidebar_badges['action_plans'] = (int)($r->fetch_assoc()['c'] ?? 0);
        $r->free();
    }

    // --- 2) Unread notifications for this admin ---
    if ($badge_admin_id !== '') {
        $stmt = @$conn->prepare(
            "SELECT COUNT(*) AS c FROM notifications
             WHERE recipient_type = 'Admin'
               AND recipient_id   = ?
               AND is_read        = 0"
        );
        if ($stmt) {
            $stmt->bind_param("s", $badge_admin_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                $sidebar_badges['notifications'] = (int)($res->fetch_assoc()['c'] ?? 0);
            }
            $stmt->close();
        }
    }

    // --- 3) Low-rated feedback (mean <= 2.0 over valid SQD dims) ---
    $low_sql = "
        SELECT COUNT(*) AS c FROM arta_feedback f
        WHERE f.is_archived = 0
          AND (
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
                 CASE WHEN f.sqd2_reliability     > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd3_access_facilities > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd4_communication   > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd5_costs           > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd6_integrity       > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd7_assurance       > 0 THEN 1 ELSE 0 END +
                 CASE WHEN f.sqd8_outcome         > 0 THEN 1 ELSE 0 END
                ), 0
            )
          ) <= 2.0
    ";
    $r = @$conn->query($low_sql);
    if ($r instanceof mysqli_result) {
        $sidebar_badges['low_ratings'] = (int)($r->fetch_assoc()['c'] ?? 0);
        $r->free();
    }
}

// Convenience vars used by the markup
$pending_action_plans = $sidebar_badges['action_plans'];
$unread_notifications = $sidebar_badges['notifications'];
$low_rating_alerts    = $sidebar_badges['low_ratings'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZPPSU | Admin Console</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --sidebar-width: 272px;

        --maroon:        #7A0000;
        --maroon-hover:  #941B1B;
        --gold:          #F2A900;
        --gold-soft:     rgba(242, 169, 0, 0.14);

        --bg-base:       #10141A;
        --bg-header:     #0B0E12;
        --bg-elevated:   #1A2029;
        --bg-hover:      rgba(255, 255, 255, 0.05);
        --bg-active:     linear-gradient(90deg, #7A0000 0%, #5A0000 100%);

        --text-dim:      #7B879B;
        --text-body:     #A4B0C0;
        --text-bright:   #E6EAF0;
        --text-white:    #FFFFFF;

        --line:          rgba(255, 255, 255, 0.07);
        --line-strong:   rgba(255, 255, 255, 0.12);

        --r-sm: 8px;
        --r-md: 10px;
        --r-lg: 14px;
        --ease: cubic-bezier(0.4, 0, 0.2, 1);
    }

    .admin-sidebar,
    .admin-sidebar * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        -webkit-font-smoothing: antialiased;
    }

    .admin-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: var(--sidebar-width);
        height: 100vh;
        height: 100dvh;
        background: var(--bg-base);
        color: var(--text-body);
        display: flex;
        flex-direction: column;
        z-index: 1000;
        border-right: 1px solid var(--line);
        transform: translateX(0);
        transition: transform 0.35s var(--ease);
        overflow: hidden;
    }

    .sidebar-nav::-webkit-scrollbar { width: 6px; }
    .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
    .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.08);
        border-radius: 99px;
    }
    .sidebar-nav::-webkit-scrollbar-thumb:hover {
        background: rgba(242, 169, 0, 0.35);
    }
    .sidebar-nav { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.1) transparent; }

    /* ---------- BRAND HEADER ---------- */
    .sidebar-brand {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 24px 18px 22px;
        background: var(--bg-header);
        border-bottom: 1px solid var(--line);
        position: relative;
    }
    .sidebar-brand::after {
        content: '';
        position: absolute;
        left: 18px; right: 18px; bottom: -1px;
        height: 1px;
        background: linear-gradient(90deg, var(--gold) 0%, transparent 70%);
        opacity: 0.45;
    }

    .sidebar-brand img {
        height: 44px;
        width: auto;
        flex-shrink: 0;
        filter: drop-shadow(0 4px 10px rgba(0,0,0,0.45));
    }

    .brand-text { line-height: 1.15; min-width: 0; }
    .brand-text h3 {
        font-size: 15.5px;
        font-weight: 800;
        color: var(--text-white);
        letter-spacing: -0.3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .brand-text span {
        display: block;
        margin-top: 4px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1.1px;
        text-transform: uppercase;
        color: var(--gold);
    }

    .sidebar-close {
        margin-left: auto;
        display: none;
        width: 32px;
        height: 32px;
        border-radius: var(--r-sm);
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid var(--line-strong);
        color: var(--text-body);
        cursor: pointer;
        align-items: center;
        justify-content: center;
        transition: background 0.2s var(--ease);
    }
    .sidebar-close:hover {
        background: rgba(255, 255, 255, 0.12);
        color: var(--text-white);
    }

    /* ---------- USER CARD ---------- */
    .sidebar-user {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 11px;
        margin: 16px 14px 0;
        padding: 11px 12px;
        background: var(--bg-elevated);
        border: 1px solid var(--line);
        border-radius: var(--r-md);
        position: relative;
        overflow: hidden;
    }
    .sidebar-user::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 3px; height: 100%;
        background: var(--gold);
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        background: linear-gradient(135deg, var(--maroon) 0%, #4A0000 100%);
        color: #FFFFFF;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 13px;
        flex-shrink: 0;
        border: 1px solid rgba(242, 169, 0, 0.35);
        box-shadow: 0 4px 12px -4px rgba(122, 0, 0, 0.9);
    }

    .user-meta { min-width: 0; line-height: 1.25; }
    .user-meta .name {
        font-size: 13px;
        font-weight: 700;
        color: var(--text-white);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .user-meta .role {
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        color: var(--gold);
        margin-top: 1px;
    }

    /* ---------- NAVIGATION ---------- */
    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 18px 12px 24px;
        display: flex;
        flex-direction: column;
        gap: 22px;
    }

    .nav-group { display: flex; flex-direction: column; gap: 4px; }

    .nav-group-title {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0 10px 8px;
        font-size: 9.5px;
        font-weight: 800;
        letter-spacing: 1.3px;
        text-transform: uppercase;
        color: var(--text-dim);
    }
    .nav-group-title::after {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(90deg, var(--line-strong) 0%, transparent 100%);
    }

    .nav-list { list-style: none; display: flex; flex-direction: column; gap: 2px; }

    .nav-link {
        position: relative;
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 9px 11px;
        border-radius: var(--r-sm);
        color: var(--text-body);
        text-decoration: none;
        font-size: 12.8px;
        font-weight: 500;
        letter-spacing: -0.1px;
        transition:
            background 0.18s var(--ease),
            color 0.18s var(--ease),
            transform 0.18s var(--ease);
        cursor: pointer;
        min-height: 38px;
    }

    .nav-link:hover {
        background: var(--bg-hover);
        color: var(--text-bright);
    }
    .nav-link:hover .nav-icon { color: var(--gold); }
    .nav-link:active { transform: scale(0.985); }

    .nav-icon {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
        color: var(--text-dim);
        transition: color 0.18s var(--ease);
    }

    .nav-label {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .nav-link.active {
        background: var(--bg-active);
        color: var(--text-white);
        font-weight: 700;
        box-shadow: 0 8px 20px -12px rgba(122, 0, 0, 0.95);
    }
    .nav-link.active::before {
        content: '';
        position: absolute;
        left: -12px; top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 22px;
        background: var(--gold);
        border-radius: 0 4px 4px 0;
    }
    .nav-link.active .nav-icon { color: var(--gold); }

    /* ---------- BADGES ---------- */
    .nav-badge {
        flex-shrink: 0;
        min-width: 20px;
        height: 18px;
        padding: 0 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.2px;
        border-radius: 99px;
        line-height: 1;
    }
    .nav-badge.badge-gold {
        background: var(--gold);
        color: #3D2000;
        box-shadow: 0 0 0 3px rgba(242, 169, 0, 0.16);
    }
    .nav-badge.badge-red {
        background: #DC2626;
        color: #FFFFFF;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.18);
    }
    .nav-badge.badge-muted {
        background: rgba(255, 255, 255, 0.08);
        color: var(--text-dim);
        font-weight: 700;
    }

    .nav-divider {
        height: 1px;
        margin: 6px 10px;
        background: var(--line);
    }

    /* ---------- FOOTER ---------- */
    .sidebar-footer {
        flex-shrink: 0;
        padding: 12px 12px 14px;
        background: var(--bg-header);
        border-top: 1px solid var(--line);
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .footer-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 11px;
        border-radius: var(--r-sm);
        font-size: 12.5px;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.18s var(--ease), color 0.18s var(--ease);
        min-height: 36px;
    }
    .footer-btn svg { width: 16px; height: 16px; flex-shrink: 0; }

    .btn-portal { color: var(--text-body); background: transparent; }
    .btn-portal:hover {
        background: rgba(255, 255, 255, 0.05);
        color: var(--text-white);
    }

    .btn-logout { color: #F87171; background: rgba(239, 68, 68, 0.08); }
    .btn-logout:hover {
        background: rgba(239, 68, 68, 0.18);
        color: #FCA5A5;
    }

    /* ---------- OVERLAY ---------- */
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(6, 10, 16, 0.7);
        -webkit-backdrop-filter: blur(3px);
        backdrop-filter: blur(3px);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s var(--ease);
    }
    .sidebar-overlay.active { display: block; opacity: 1; }

    /* ---------- RESPONSIVE ---------- */
    @media (max-width: 1024px) {
        .admin-sidebar {
            width: min(280px, 78vw);
            transform: translateX(-100%);
        }
        .admin-sidebar.open {
            transform: translateX(0);
            box-shadow: 0 0 40px -8px rgba(0, 0, 0, 0.55);
        }
        .sidebar-close { display: inline-flex; }
        .sidebar-nav { padding-top: 16px; }
    }

    @media (max-width: 640px) {
        .admin-sidebar { width: min(260px, 74vw); }

        .sidebar-brand { padding: 20px 14px 18px; gap: 10px; }
        .sidebar-brand img { height: 38px; }
        .brand-text h3 { font-size: 14px; }
        .brand-text span { font-size: 9px; letter-spacing: 0.9px; }

        .sidebar-user { margin: 12px 12px 0; padding: 10px 11px; gap: 10px; }
        .user-avatar { width: 32px; height: 32px; font-size: 12px; }
        .user-meta .name { font-size: 12.5px; }
        .user-meta .role { font-size: 10px; }

        .sidebar-nav { padding: 14px 10px 20px; gap: 18px; }
        .nav-group-title { font-size: 9px; letter-spacing: 1.1px; padding: 0 8px 7px; }

        .nav-link { font-size: 12.4px; padding: 9px 10px; gap: 10px; min-height: 36px; }
        .nav-icon { width: 17px; height: 17px; }

        .nav-badge { min-width: 18px; height: 17px; font-size: 9.5px; padding: 0 5px; }

        .sidebar-footer { padding: 10px 10px 12px; }
        .footer-btn { font-size: 12px; padding: 8px 10px; min-height: 34px; }
    }

    @media (max-width: 480px) {
        .admin-sidebar { width: min(240px, 72vw); }
        .sidebar-brand { padding: 18px 12px 16px; }
        .sidebar-brand img { height: 34px; }
        .brand-text h3 { font-size: 13px; }
        .brand-text span { font-size: 8.5px; letter-spacing: 0.8px; }

        .sidebar-user { margin: 10px 10px 0; padding: 9px 10px; }
        .user-avatar { width: 30px; height: 30px; font-size: 11px; }

        .sidebar-nav { padding: 12px 8px 18px; gap: 16px; }
        .nav-link { font-size: 12px; padding: 8px 9px; gap: 9px; }
        .nav-icon { width: 16px; height: 16px; }

        .nav-badge { min-width: 17px; height: 16px; font-size: 9px; padding: 0 4px; }

        .footer-btn { font-size: 11.5px; padding: 8px 9px; }
        .footer-btn svg { width: 15px; height: 15px; }
    }

    @media (max-width: 360px) {
        .admin-sidebar { width: min(220px, 82vw); }
        .brand-text span { display: none; }
        .nav-group-title::after { display: none; }
        .user-meta .role { font-size: 9.5px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .admin-sidebar, .nav-link, .sidebar-overlay { transition: none !important; }
    }
</style>
</head>
<body>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleAdminSidebar()"></div>

<aside class="admin-sidebar" id="adminSidebar" aria-label="Admin navigation">

    <!-- ============ BRAND ============ -->
    <div class="sidebar-brand">
        <img src="logo/ZPPSU_LOGO.png" alt="ZPPSU Logo">
        <div class="brand-text">
            <h3>ZPPSU CSM</h3>
            <span>Executive Console</span>
        </div>
        <button class="sidebar-close" aria-label="Close navigation" onclick="toggleAdminSidebar()">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- ============ USER ============ -->
    <div class="sidebar-user">
        <div class="user-avatar"><?php echo htmlspecialchars($admin_initial); ?></div>
        <div class="user-meta">
            <div class="name"><?php echo htmlspecialchars($admin_display_name); ?></div>
            <div class="role"><?php echo htmlspecialchars($admin_role); ?></div>
        </div>
    </div>

    <!-- ============ NAVIGATION ============ -->
    <nav class="sidebar-nav">

        <!-- ---------- GROUP 1 ---------- -->
        <div class="nav-group">
            <div class="nav-group-title">Performance Monitoring</div>
            <ul class="nav-list">
                <li>
                    <a href="admin_dashboard.php" class="nav-link <?php echo ($current_page === 'admin_dashboard.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        <span class="nav-label">CSM Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="admin_feedbacks.php" class="nav-link <?php echo ($current_page === 'admin_feedbacks.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                        <span class="nav-label">Client Feedback Feed</span>
                    </a>
                </li>
                <li>
                    <a href="admin_resolutions.php" class="nav-link <?php echo ($current_page === 'admin_resolutions.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3l-6.93-12a2 2 0 00-3.48 0L2.33 16a2 2 0 001.74 3z"/>
                        </svg>
                        <span class="nav-label">Actions & Resolutions</span>
                        <?php if ($low_rating_alerts > 0): ?>
                            <span class="nav-badge badge-red"><?php echo (int)$low_rating_alerts; ?></span>
                        <?php endif; ?>
                    </a>
               
            </ul>
        </div>

        <!-- ---------- GROUP 2 ---------- -->
        <div class="nav-group">
            <div class="nav-group-title">ARTA Compliance</div>
            <ul class="nav-list">
                <li>
                    <a href="admin_reports.php" class="nav-link <?php echo ($current_page === 'admin_reports.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="nav-label">MC 2022-05 Reports</span>
                    </a>
                </li>
                <li>
                    <a href="admin_action_plans.php" class="nav-link <?php echo ($current_page === 'admin_action_plans.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        <span class="nav-label">Action Plan Tracker</span>
                        <?php if ($pending_action_plans > 0): ?>
                            <span class="nav-badge badge-gold"><?php echo (int)$pending_action_plans; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="admin_charter.php" class="nav-link <?php echo ($current_page === 'admin_charter.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 12l2 2 4-4M12 21a9 9 0 100-18 9 9 0 000 18z"/>
                        </svg>
                        <span class="nav-label">Citizen's Charter</span>
                    </a>
                </li>
                <li>
                    <a href="admin_processing.php" class="nav-link <?php echo ($current_page === 'admin_processing.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="nav-label">SLA &amp; Processing Time</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- ---------- GROUP 3 ---------- -->
        <div class="nav-group">
            <div class="nav-group-title">Analytics &amp; Insights</div>
            <ul class="nav-list">
                <li>
                    <a href="admin_rankings.php" class="nav-link <?php echo ($current_page === 'admin_rankings.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <span class="nav-label">Office Rankings</span>
                    </a>
                </li>
                <li>
                    <a href="admin_demographics.php" class="nav-link <?php echo ($current_page === 'admin_demographics.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6-4a3 3 0 10-3-3 3 3 0 003 3zM3 16a3 3 0 116 0"/>
                        </svg>
                        <span class="nav-label">Demographic Analytics</span>
                    </a>
                </li>
                <li>
                    <a href="admin_analytics.php" class="nav-link <?php echo ($current_page === 'admin_analytics.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M3 17l6-6 4 4 8-8M21 7h-6m6 0v6"/>
                        </svg>
                        <span class="nav-label">Trend Analysis</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- ---------- GROUP 4 ---------- -->
        <div class="nav-group">
            <div class="nav-group-title">Institutional Setup</div>
            <ul class="nav-list">
                <li>
                    <a href="admin_offices.php" class="nav-link <?php echo ($current_page === 'admin_offices.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <span class="nav-label">Offices &amp; Units</span>
                    </a>
                </li>
              
            </ul>
        </div>

        <!-- ---------- GROUP 5 ---------- -->
        <div class="nav-group">
            <div class="nav-group-title">System &amp; Security</div>
            <ul class="nav-list">
                <li>
                    <a href="admin_notifications.php" class="nav-link <?php echo ($current_page === 'admin_notifications.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span class="nav-label">Notifications Center</span>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="nav-badge badge-gold"><?php echo (int)$unread_notifications; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="admin_users.php" class="nav-link <?php echo ($current_page === 'admin_users.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <span class="nav-label">User Accounts</span>
                    </a>
                </li>
                <li>
                    <a href="admin_security_logs.php" class="nav-link <?php echo ($current_page === 'admin_security_logs.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span class="nav-label">Security &amp; Login Logs</span>
                    </a>
                </li>
                <li>
                    <a href="admin_system_logs.php" class="nav-link <?php echo ($current_page === 'admin_system_logs.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="nav-label">System Audit Trail</span>
                    </a>
                </li>
                <li>
                    <a href="admin_suspicious.php" class="nav-link <?php echo ($current_page === 'admin_suspicious.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3l-6.93-12a2 2 0 00-3.48 0L2.33 16a2 2 0 001.74 3z"/>
                        </svg>
                        <span class="nav-label">Suspicious Activity</span>
                    </a>
                </li>
                <li>
                    <a href="admin_settings.php" class="nav-link <?php echo ($current_page === 'admin_settings.php') ? 'active' : ''; ?>">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="nav-label">Portal Settings</span>
                    </a>
                </li>
            </ul>
        </div>

    </nav>

    <!-- ============ FOOTER ============ -->
    <div class="sidebar-footer">
        <a href="student_dashboard.php" class="footer-btn btn-portal">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
            Client Directory View
        </a>
        <a href="logout.php" class="footer-btn btn-logout">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Sign Out
        </a>
    </div>

</aside>

<script>
    function toggleAdminSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const isOpen  = sidebar.classList.toggle('open');
        overlay.classList.toggle('active', isOpen);
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1024) {
            document.getElementById('adminSidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const sidebar = document.getElementById('adminSidebar');
            if (sidebar.classList.contains('open')) toggleAdminSidebar();
        }
    });
</script>

</body>
</html>