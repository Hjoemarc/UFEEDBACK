<?php
$current_page = basename($_SERVER['PHP_SELF']);
$faculty_display_name = $_SESSION['full_name'] ?? 'Faculty Member';
$faculty_initial = strtoupper(mb_substr($faculty_display_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZPPSU | Faculty Console</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --sidebar-width: 272px;
        --maroon: #7A0000;
        --gold: #F2A900;
        --bg-base: #10141A;
        --bg-header: #0B0E12;
        --bg-elevated: #1A2029;
        --text-body: #A4B0C0;
        --text-white: #FFFFFF;
        --line: rgba(255, 255, 255, 0.07);
        --ease: cubic-bezier(0.4, 0, 0.2, 1);
    }
    .admin-sidebar, .admin-sidebar * {
        box-sizing: border-box; margin: 0; padding: 0;
        font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    }
    .admin-sidebar {
        position: fixed; inset: 0 auto 0 0; width: var(--sidebar-width);
        height: 100vh; height: 100dvh;
        background: var(--bg-base); color: var(--text-body);
        display: flex; flex-direction: column; z-index: 1000;
        border-right: 1px solid var(--line);
        transform: translateX(0);
        transition: transform 0.35s var(--ease);
        overflow: hidden;
    }

    /* ===== BRAND ===== */
    .sidebar-brand {
        flex-shrink: 0; display: flex; align-items: center; gap: 12px;
        padding: 24px 18px 22px; background: var(--bg-header);
        border-bottom: 1px solid var(--line);
    }
    .sidebar-brand img { height: 44px; width: auto; flex-shrink: 0; }
    .brand-text { line-height: 1.15; min-width: 0; }
    .brand-text h3 { font-size: 15.5px; font-weight: 800; color: var(--text-white); white-space: nowrap; }
    .brand-text span { display: block; margin-top: 4px; font-size: 10px; font-weight: 700; letter-spacing: 1.1px; text-transform: uppercase; color: var(--gold); }

    /* Mobile close button */
    .sidebar-close {
        margin-left: auto; display: none;
        width: 32px; height: 32px; border-radius: 8px;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: var(--text-body); cursor: pointer;
        align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .sidebar-close:hover { background: rgba(255, 255, 255, 0.12); color: var(--text-white); }

    /* ===== USER ===== */
    .sidebar-user {
        flex-shrink: 0; display: flex; align-items: center; gap: 11px;
        margin: 16px 14px 0; padding: 11px 12px;
        background: var(--bg-elevated); border: 1px solid var(--line); border-radius: 10px;
    }
    .user-avatar {
        width: 36px; height: 36px; border-radius: 9px;
        background: linear-gradient(135deg, var(--maroon) 0%, #4A0000 100%);
        color: #FFF; display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 13px; flex-shrink: 0;
        border: 1px solid rgba(242, 169, 0, 0.35);
    }
    .user-meta { min-width: 0; line-height: 1.25; }
    .user-meta .name { font-size: 13px; font-weight: 700; color: var(--text-white); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-meta .role { font-size: 10.5px; font-weight: 600; letter-spacing: 0.4px; text-transform: uppercase; color: var(--gold); margin-top: 1px; }

    /* ===== NAV ===== */
    .sidebar-nav {
        flex: 1; overflow-y: auto; overflow-x: hidden;
        padding: 18px 12px 24px; display: flex; flex-direction: column; gap: 22px;
    }
    .nav-group { display: flex; flex-direction: column; gap: 4px; }
    .nav-group-title {
        display: flex; align-items: center; gap: 8px;
        padding: 0 10px 8px; font-size: 9.5px; font-weight: 800;
        letter-spacing: 1.3px; text-transform: uppercase; color: #7B879B;
    }
    .nav-group-title::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, rgba(255,255,255,0.12) 0%, transparent 100%); }

    .nav-link {
        position: relative; display: flex; align-items: center; gap: 11px;
        padding: 9px 11px; border-radius: 8px; color: var(--text-body);
        text-decoration: none; font-size: 12.8px; font-weight: 500;
        transition: background 0.18s var(--ease), color 0.18s var(--ease);
        cursor: pointer; min-height: 38px;
    }
    .nav-link:hover { background: rgba(255, 255, 255, 0.05); color: #E6EAF0; }
    .nav-link:hover .nav-icon { color: var(--gold); }
    .nav-link.active {
        background: linear-gradient(90deg, var(--maroon) 0%, #5A0000 100%);
        color: var(--text-white); font-weight: 700;
        box-shadow: 0 8px 20px -12px rgba(122, 0, 0, 0.95);
    }
    .nav-link.active .nav-icon { color: var(--gold); }
    .nav-link.active::before {
        content: ''; position: absolute; left: -12px; top: 50%;
        transform: translateY(-50%); width: 3px; height: 22px;
        background: var(--gold); border-radius: 0 4px 4px 0;
    }
    .nav-icon { width: 18px; height: 18px; flex-shrink: 0; color: #7B879B; transition: color 0.18s var(--ease); }

    /* ===== FOOTER ===== */
    .sidebar-footer {
        flex-shrink: 0; padding: 12px 12px 14px;
        background: var(--bg-header); border-top: 1px solid var(--line);
        display: flex; flex-direction: column; gap: 4px;
    }
    .footer-btn {
        display: flex; align-items: center; gap: 10px;
        padding: 9px 11px; border-radius: 8px;
        font-size: 12.5px; font-weight: 600; text-decoration: none;
        transition: background 0.18s var(--ease), color 0.18s var(--ease);
        min-height: 36px;
    }
    .footer-btn svg { width: 16px; height: 16px; flex-shrink: 0; }
    .btn-logout { color: #F87171; background: rgba(239, 68, 68, 0.08); }
    .btn-logout:hover { background: rgba(239, 68, 68, 0.18); color: #FCA5A5; }

    /* ===== OVERLAY ===== */
    .sidebar-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(6, 10, 16, 0.7);
        -webkit-backdrop-filter: blur(3px); backdrop-filter: blur(3px);
        z-index: 999; opacity: 0; transition: opacity 0.3s var(--ease);
    }
    .sidebar-overlay.active { display: block; opacity: 1; }

    /* ===== MOBILE / TABLET RESPONSIVE ===== */
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
        .nav-group-title { font-size: 9px; padding: 0 8px 7px; }
        .nav-link { font-size: 12.4px; padding: 9px 10px; gap: 10px; min-height: 36px; }
        .nav-icon { width: 17px; height: 17px; }
        .sidebar-footer { padding: 10px 10px 12px; }
        .footer-btn { font-size: 12px; padding: 8px 10px; min-height: 34px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .admin-sidebar, .sidebar-overlay, .nav-link { transition: none !important; }
    }
</style>
</head>
<body>

    <!-- Overlay: clicking outside the sidebar closes it on mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleAdminSidebar()"></div>

    <aside class="admin-sidebar" id="adminSidebar" aria-label="Faculty navigation">

        <!-- BRAND -->
        <div class="sidebar-brand">
            <img src="logo/ZPPSU_LOGO.png" alt="Logo">
            <div class="brand-text">
                <h3>ZPPSU CSM</h3>
                <span>Faculty Console</span>
            </div>
            <!-- Close button — only visible on mobile -->
            <button class="sidebar-close" aria-label="Close navigation" onclick="toggleAdminSidebar()">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- USER -->
        <div class="sidebar-user">
            <div class="user-avatar"><?php echo htmlspecialchars($faculty_initial); ?></div>
            <div class="user-meta">
                <div class="name"><?php echo htmlspecialchars($faculty_display_name); ?></div>
                <div class="role">Staff / Faculty</div>
            </div>
        </div>

        <!-- NAV -->
        <nav class="sidebar-nav">
            <div class="nav-group">
                <div class="nav-group-title">Workspace</div>
                <a href="faculty_dashboard.php" class="nav-link <?php echo $current_page === 'faculty_dashboard.php' ? 'active' : ''; ?>">
                    <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard & Analytics
                </a>
                <a href="faculty_tickets.php" class="nav-link <?php echo $current_page === 'faculty_tickets.php' ? 'active' : ''; ?>">
                    <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    My Assigned Tickets
                </a>
            </div>
        </nav>

        <!-- FOOTER -->
        <div class="sidebar-footer">
            <a href="logout.php" class="footer-btn btn-logout">
                <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Sign Out
            </a>
        </div>
    </aside>

    <script>
        // Mobile sidebar toggle — matches admin_sidebar.php behavior
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