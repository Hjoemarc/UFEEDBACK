<?php
$admin_display_name = $_SESSION['full_name'] ?? 'Administrator';
$raw_role           = $_SESSION['internal_user_type'] ?? 'admin';

// Map roles to human-readable names
$role_map = [
    'admin'           => 'System Admin',
    'zppsu_employee'  => 'Staff / Faculty',
    'student'         => 'Student',
    'external_client' => 'External Client'
];
$admin_role = $role_map[$raw_role] ?? 'Administrator';

$admin_initial        = strtoupper(mb_substr($admin_display_name, 0, 1));
$admin_email          = $_SESSION['email'] ?? 'admin@zppsu.edu.ph';
$page_title           = $page_title    ?? 'CSM Dashboard';
$page_crumb           = $page_crumb    ?? 'Overview';
$page_subtitle        = $page_subtitle ?? 'Client Satisfaction Measurement Portal';
$unread_notifications = $unread_notifications ?? 0;
?>
<style>
    :root {
        --header-height: 72px;
        --sidebar-width: 272px;
    }

    /* ============================================================
       GLOBAL MOBILE FIX — prevent iOS auto-zoom when typing
       Applies to every input/select/textarea on every page
       ============================================================ */
    @media screen and (max-width: 768px) {
        input, select, textarea, .input-live-search, .select-live-filter,
        .modal-form-group input, .modal-form-group select {
            font-size: 16px !important;
        }
    }

    /* Kill double-tap zoom delay on all interactive elements */
    button, a, input, select, textarea, .icon-btn, .header-toggle {
        touch-action: manipulation;
    }

    /* ============ HEADER SHELL ============ */
    .admin-header {
        position: fixed;
        top: 0;
        left: var(--sidebar-width);
        right: 0;
        height: var(--header-height);
        background: rgba(255, 255, 255, 0.94);
        -webkit-backdrop-filter: blur(14px);
        backdrop-filter: blur(14px);
        border-bottom: 1px solid #E6E9EF;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 0 24px;
        z-index: 900;
        transition: left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        -webkit-font-smoothing: antialiased;
    }

    .admin-header::after {
        content: '';
        position: absolute;
        left: 0; right: 0; bottom: -1px;
        height: 1px;
        background: linear-gradient(90deg, #F2A900 0%, transparent 42%);
        opacity: 0.55;
        pointer-events: none;
    }

    /* ============================================================
       HAMBURGER — LEFT side, mobile/tablet only
       ============================================================ */
    .header-toggle {
        display: none;                     /* hidden on desktop */
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: #7A0000;
        color: #FFFFFF;
        border: 1px solid rgba(242, 169, 0, 0.35);
        cursor: pointer;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        padding: 0;
        transition: background 0.2s, transform 0.2s;
        box-shadow: 0 6px 14px -6px rgba(122, 0, 0, 0.55);
    }
    .header-toggle:hover  { background: #941B1B; }
    .header-toggle:active { transform: scale(0.94); }

    .header-toggle svg {
        width: 20px;
        height: 20px;
        display: block;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .header-toggle.is-open svg { transform: rotate(90deg); }

    /* ============ TITLE BLOCK ============ */
    .header-title {
        display: flex;
        flex-direction: column;
        min-width: 0;
        flex: 1;
        line-height: 1.15;
    }
    .header-crumb {
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 1.1px;
        text-transform: uppercase;
        color: #94A3B8;
        line-height: 1.2;
    }
    .header-crumb .brand { color: #F2A900; }

    .header-title h1 {
        font-size: 17px;
        font-weight: 800;
        letter-spacing: -0.3px;
        color: #0F172A;
        line-height: 1.2;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .header-subtitle {
        font-size: 11.5px;
        font-weight: 500;
        color: #64748B;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
    }

    /* ============ RIGHT-SIDE ACTIONS ============ */
    .header-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }

    .icon-btn {
        position: relative;
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: transparent;
        border: 1px solid transparent;
        color: #475569;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: background 0.18s, color 0.18s, border-color 0.18s;
    }
    .icon-btn:hover {
        background: #F1F5F9;
        color: #0F172A;
        border-color: #E2E8F0;
    }
    .icon-btn svg { width: 20px; height: 20px; }

    .icon-btn .badge {
        position: absolute;
        top: 4px; right: 4px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        background: #DC2626;
        color: #FFFFFF;
        font-size: 10px;
        font-weight: 800;
        border-radius: 99px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #FFFFFF;
        line-height: 1;
        transform: scale(0);
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .icon-btn .badge.show { transform: scale(1); }
    .icon-btn .badge.pulse {
        animation: badgePulse 1.6s ease-in-out;
    }
    @keyframes badgePulse {
        0%, 100% { transform: scale(1); }
        50%      { transform: scale(1.2); }
    }

    .header-divider {
        width: 1px;
        height: 26px;
        background: #E2E8F0;
        margin: 0 4px;
        flex-shrink: 0;
    }

    /* ============ USER MENU ============ */
    .user-menu { position: relative; flex-shrink: 0; }

    .user-trigger {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 5px 10px 5px 5px;
        background: transparent;
        border: 1px solid transparent;
        border-radius: 999px;
        cursor: pointer;
        font-family: inherit;
        transition: background 0.18s, border-color 0.18s;
    }
    .user-trigger:hover {
        background: #F1F5F9;
        border-color: #E2E8F0;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #7A0000 0%, #4A0000 100%);
        color: #FFFFFF;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 13px;
        flex-shrink: 0;
        border: 2px solid #FFFFFF;
        box-shadow: 0 0 0 1px #E2E8F0;
    }

    .user-info {
        display: flex;
        flex-direction: column;
        text-align: left;
        line-height: 1.2;
        min-width: 0;
        max-width: 140px;
    }
    .user-info .name {
        font-size: 12.5px;
        font-weight: 700;
        color: #0F172A;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .user-info .role {
        font-size: 10px;
        font-weight: 600;
        color: #64748B;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .user-trigger .chev {
        width: 14px;
        height: 14px;
        color: #94A3B8;
        transition: transform 0.22s;
        flex-shrink: 0;
    }
    .user-menu.open .chev { transform: rotate(180deg); }

    /* ============ DROPDOWNS ============ */
    .user-dropdown,
    .icon-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        background: #FFFFFF;
        border: 1px solid #E6E9EF;
        border-radius: 12px;
        box-shadow: 0 20px 40px -18px rgba(15, 23, 42, 0.22);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-6px);
        transition: opacity 0.2s, transform 0.2s, visibility 0.2s;
        z-index: 100;
        overflow: hidden;
    }
    .user-menu.open  .user-dropdown,
    .icon-menu.open  .icon-dropdown {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .user-dropdown { min-width: 240px; padding: 6px; }
    .icon-dropdown { min-width: 340px; max-width: 92vw; }
    .icon-menu { position: relative; }

    .dropdown-header {
        padding: 10px 12px 12px;
        border-bottom: 1px solid #E6E9EF;
        margin-bottom: 6px;
    }
    .dropdown-header .name {
        font-size: 13px;
        font-weight: 700;
        color: #0F172A;
    }
    .dropdown-header .email {
        font-size: 11.5px;
        color: #64748B;
        margin-top: 2px;
        word-break: break-all;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 12px;
        border-radius: 8px;
        color: #475569;
        text-decoration: none;
        font-size: 12.8px;
        font-weight: 600;
        transition: background 0.18s, color 0.18s;
    }
    .dropdown-item:hover { background: #F8FAFC; color: #0F172A; }
    .dropdown-item svg {
        width: 16px;
        height: 16px;
        color: #94A3B8;
        flex-shrink: 0;
        transition: color 0.18s;
    }
    .dropdown-item:hover svg { color: #7A0000; }

    .dropdown-item.danger { color: #DC2626; }
    .dropdown-item.danger svg { color: #F87171; }
    .dropdown-item.danger:hover { background: #FEF2F2; color: #B91C1C; }
    .dropdown-item.danger:hover svg { color: #DC2626; }

    .dropdown-sep {
        height: 1px;
        background: #E6E9EF;
        margin: 6px 8px;
    }

    /* ============ NOTIFICATION DROPDOWN ============ */
    .icon-dropdown-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 14px;
        border-bottom: 1px solid #E6E9EF;
    }
    .icon-dropdown-head h4 {
        font-size: 13px;
        font-weight: 800;
        color: #0F172A;
    }
    .icon-dropdown-head button {
        font-size: 11px;
        font-weight: 700;
        color: #7A0000;
        background: transparent;
        border: none;
        cursor: pointer;
        padding: 0;
        font-family: inherit;
    }
    .icon-dropdown-head button:hover { color: #F2A900; }

    .icon-dropdown-body {
        max-height: 340px;
        overflow-y: auto;
    }

    .notif-item {
        display: flex;
        gap: 12px;
        padding: 12px 14px;
        border-bottom: 1px solid #F1F5F9;
        text-decoration: none;
        color: inherit;
        cursor: pointer;
        transition: background 0.18s;
    }
    .notif-item:hover { background: #F8FAFC; }
    .notif-item:last-child { border-bottom: none; }

    .notif-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #F2A900;
        flex-shrink: 0;
        margin-top: 5px;
        box-shadow: 0 0 0 3px rgba(242, 169, 0, 0.18);
    }
    .notif-dot.read {
        background: #CBD5E1;
        box-shadow: none;
    }

    .notif-body { min-width: 0; flex: 1; }
    .notif-body .title {
        font-size: 12.5px;
        font-weight: 700;
        color: #0F172A;
        line-height: 1.35;
    }
    .notif-body .desc {
        font-size: 11.5px;
        color: #64748B;
        margin-top: 2px;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .notif-body .time {
        font-size: 10.5px;
        font-weight: 600;
        color: #94A3B8;
        margin-top: 4px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .notif-empty {
        padding: 32px 16px;
        text-align: center;
        color: #94A3B8;
        font-size: 12.5px;
    }
    .notif-empty svg {
        width: 32px; height: 32px;
        margin-bottom: 8px;
        color: #CBD5E1;
    }

    .icon-dropdown-foot {
        padding: 10px 14px;
        border-top: 1px solid #E6E9EF;
        text-align: center;
        background: #FAFBFC;
    }
    .icon-dropdown-foot a {
        font-size: 11.5px;
        font-weight: 700;
        color: #7A0000;
        text-decoration: none;
    }
    .icon-dropdown-foot a:hover { color: #F2A900; }

    /* ============ RESPONSIVE ============ */
    @media (max-width: 1024px) {
        .admin-header {
            left: 0;
            padding: 0 16px;
        }
        .header-toggle { display: inline-flex; }
    }

    @media (max-width: 768px) {
        .user-info     { display: none; }
        .user-trigger  { padding: 4px; }
        .user-trigger .chev { display: none; }
        .header-title h1   { font-size: 15.5px; }
        .header-subtitle   { font-size: 11px; }
    }

    @media (max-width: 480px) {
        :root { --header-height: 66px; }
        .admin-header { padding: 0 12px; gap: 10px; }
        .header-toggle { width: 38px; height: 38px; }
        .header-toggle svg { width: 18px; height: 18px; }
        .header-crumb  { display: none; }
        .header-title h1 { font-size: 14.5px; }
        .header-subtitle { font-size: 10.5px; }
        .icon-btn { width: 38px; height: 38px; }
        .user-avatar { width: 32px; height: 32px; font-size: 12px; }
        .icon-dropdown { min-width: 290px; right: -50px; }
        .header-divider { display: none; }
    }

    @media (max-width: 360px) {
        .admin-header { padding: 0 10px; gap: 8px; }
        .header-title h1 { font-size: 13.5px; }
        .header-subtitle { display: none; }
        .icon-btn { width: 36px; height: 36px; }
        .user-avatar { width: 30px; height: 30px; font-size: 11px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .admin-header, .header-toggle, .header-toggle svg, .icon-btn,
        .user-trigger, .user-trigger .chev, .icon-btn .badge,
        .user-dropdown, .icon-dropdown {
            transition: none !important;
            animation: none !important;
        }
    }
</style>

<header class="admin-header">

    <!-- ============================================================
         HAMBURGER — far LEFT, mobile/tablet only
         ============================================================ -->
    <button class="header-toggle" id="headerToggle"
            aria-label="Toggle navigation"
            aria-expanded="false"
            aria-controls="adminSidebar"
            onclick="toggleAdminSidebar()">
        <svg fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>

    <!-- Title block -->
    <div class="header-title">
        <div class="header-crumb">
            <span class="brand">ZPPSU CSM</span> &nbsp;/&nbsp; <?php echo htmlspecialchars($page_crumb); ?>
        </div>
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
        <?php if (!empty($page_subtitle)): ?>
            <div class="header-subtitle"><?php echo htmlspecialchars($page_subtitle); ?></div>
        <?php endif; ?>
    </div>

    <!-- Right-side actions -->
    <div class="header-actions">

        <!-- Notifications (live, auto-refreshing) -->
        <div class="icon-menu" id="notifMenu">
            <button class="icon-btn" id="notifBell"
                    aria-label="Notifications"
                    onclick="toggleDropdown('notifMenu')">
                <svg fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span class="badge" id="notifBadge"<?php if ($unread_notifications > 0) echo ' style="transform:scale(1)"'; ?>>
                    <?php echo (int)$unread_notifications; ?>
                </span>
            </button>

            <div class="icon-dropdown">
                <div class="icon-dropdown-head">
                    <h4>Notifications</h4>
                    <button type="button" id="markAllReadBtn" onclick="markAllNotificationsRead()">
                        Mark all read
                    </button>
                </div>
                <div class="icon-dropdown-body" id="notifBody">
                    <div class="notif-empty">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <div>Loading notifications…</div>
                    </div>
                </div>
                <div class="icon-dropdown-foot">
                    <a href="admin_notifications.php">View all notifications →</a>
                </div>
            </div>
        </div>

        <div class="header-divider"></div>

        <!-- User menu -->
        <div class="user-menu" id="userMenu">
            <button class="user-trigger" aria-label="Account menu" onclick="toggleDropdown('userMenu')">
                <div class="user-avatar"><?php echo htmlspecialchars($admin_initial); ?></div>
                <div class="user-info">
                    <div class="name"><?php echo htmlspecialchars($admin_display_name); ?></div>
                    <div class="role"><?php echo htmlspecialchars($admin_role); ?></div>
                </div>
                <svg class="chev" fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M6 9l6 6 6-6"/>
                </svg>
            </button>
            <div class="user-dropdown">
                <div class="dropdown-header">
                    <div class="name"><?php echo htmlspecialchars($admin_display_name); ?></div>
                    <div class="email"><?php echo htmlspecialchars($admin_email); ?></div>
                </div>
                <a href="admin_profile.php" class="dropdown-item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    My Profile
                </a>
                
                <!-- PORTAL SETTINGS: Hidden for faculty, visible for admins -->
                <?php if ($raw_role === 'admin'): ?>
                <a href="admin_settings.php" class="dropdown-item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Portal Settings
                </a>
                <?php endif; ?>

                <a href="student_dashboard.php" class="dropdown-item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    Client Directory View
                </a>
                <div class="dropdown-sep"></div>
                <a href="logout.php" class="dropdown-item danger">
                    <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Sign Out
                </a>
            </div>
        </div>

    </div>
</header>

<script>
    /* ============================================================
       SIDEBAR TOGGLE — keeps hamburger icon in sync
       ============================================================ */
    (function () {
        if (typeof window.toggleAdminSidebar !== 'function') {
            window.toggleAdminSidebar = function () {
                const sidebar = document.getElementById('adminSidebar');
                const overlay = document.getElementById('sidebarOverlay');
                if (!sidebar) return;
                const isOpen = sidebar.classList.toggle('open');
                if (overlay) overlay.classList.toggle('active', isOpen);
                document.body.style.overflow = isOpen ? 'hidden' : '';
                syncHamburgerIcon(isOpen);
            };
        } else {
            const original = window.toggleAdminSidebar;
            window.toggleAdminSidebar = function () {
                original.apply(this, arguments);
                const sidebar = document.getElementById('adminSidebar');
                if (sidebar) syncHamburgerIcon(sidebar.classList.contains('open'));
            };
        }

        function syncHamburgerIcon(isOpen) {
            const btn = document.getElementById('headerToggle');
            if (!btn) return;
            btn.classList.toggle('is-open', !!isOpen);
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        document.addEventListener('click', (e) => {
            if (e.target && e.target.id === 'sidebarOverlay') syncHamburgerIcon(false);
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const sidebar = document.getElementById('adminSidebar');
                if (sidebar && !sidebar.classList.contains('open')) syncHamburgerIcon(false);
            }
        });
    })();

    /* ============================================================
       DROPDOWNS
       ============================================================ */
    function toggleDropdown(id) {
        const menu   = document.getElementById(id);
        const isOpen = menu.classList.contains('open');

        document.querySelectorAll('.user-menu.open, .icon-menu.open').forEach(el => {
            if (el.id !== id) el.classList.remove('open');
        });

        menu.classList.toggle('open', !isOpen);

        // When opening notifications, refresh immediately
        if (id === 'notifMenu' && !isOpen) {
            fetchNotifications();
        }
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.user-menu') && !e.target.closest('.icon-menu')) {
            document.querySelectorAll('.user-menu.open, .icon-menu.open').forEach(el => {
                el.classList.remove('open');
            });
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.user-menu.open, .icon-menu.open').forEach(el => {
                el.classList.remove('open');
            });
        }
    });

    /* ============================================================
       LIVE NOTIFICATIONS — polling every 20s
       ============================================================ */
    let lastUnreadCount = <?php echo (int)$unread_notifications; ?>;

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function timeAgo(isoString) {
        // MySQL timestamps come as "YYYY-MM-DD HH:MM:SS"; treat as UTC
        const ts   = new Date(String(isoString).replace(' ', 'T') + 'Z').getTime();
        const diff = Math.max(0, Math.floor((Date.now() - ts) / 1000));
        if (diff < 60)     return diff + 's ago';
        if (diff < 3600)   return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400)  return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return new Date(ts).toLocaleDateString();
    }

    async function fetchNotifications() {
        try {
            const res = await fetch('admin_notifications_api.php?action=fetch', {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!res.ok) return;
            const data = await res.json();
            if (!data || !data.ok) return;

            renderNotifications(data.notifications || [], data.unread_count || 0);
        } catch (err) {
            // Silent fail — network blip, next poll will retry
        }
    }

    function renderNotifications(items, unreadCount) {
        const badge = document.getElementById('notifBadge');
        const body  = document.getElementById('notifBody');
        if (!badge || !body) return;

        // ---- Badge ----
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.transform = 'scale(1)';
            badge.classList.add('show');

            // Pulse if count increased since last check
            if (unreadCount > lastUnreadCount) {
                badge.classList.add('pulse');
                setTimeout(() => badge.classList.remove('pulse'), 1700);
            }
        } else {
            badge.style.transform = 'scale(0)';
            badge.classList.remove('show');
        }
        lastUnreadCount = unreadCount;

        // ---- List ----
        if (!items.length) {
            body.innerHTML = `
                <div class="notif-empty">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    <div>You're all caught up.</div>
                </div>`;
            return;
        }

        body.innerHTML = items.map(n => `
            <div class="notif-item" onclick="openNotification(${n.id})">
                <span class="notif-dot ${n.is_read ? 'read' : ''}"></span>
                <div class="notif-body">
                    <div class="title">${escapeHtml(n.title)}</div>
                    <div class="desc">${escapeHtml(n.message)}</div>
                    <div class="time">${escapeHtml(timeAgo(n.created_at))}</div>
                </div>
            </div>
        `).join('');
    }

    async function markAllNotificationsRead() {
        try {
            const res = await fetch('admin_notifications_api.php?action=mark_all_read', {
                method: 'POST',
                credentials: 'same-origin'
            });
            const data = await res.json();
            if (data && data.ok) {
                fetchNotifications(); // refresh immediately
            }
        } catch (err) {}
    }

    async function openNotification(id) {
        try {
            await fetch('admin_notifications_api.php?action=mark_read&id=' + encodeURIComponent(id), {
                method: 'POST',
                credentials: 'same-origin'
            });
            fetchNotifications();
        } catch (err) {}
    }

    // ---- Boot & poll ----
    document.addEventListener('DOMContentLoaded', () => {
        fetchNotifications();
        // Poll every 20 seconds
        setInterval(fetchNotifications, 20000);
        // Also poll when the tab regains focus
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) fetchNotifications();
        });
    });
</script>