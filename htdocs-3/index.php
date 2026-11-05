<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ZPPSU | Client Satisfaction Measurement Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --maroon: #7A0000;
            --maroon-dark: #550000;
            --maroon-light: #941B1B;
            --gold: #F2A900;
            --gold-dark: #C68A00;
            --gold-soft: #FFF9EB;
            --canvas: #F8FAFC;
            --surface: #FFFFFF;
            --text-main: #0F172A;
            --text-body: #475569;
            --text-muted: #64748B;
            --border: #E2E8F0;
            --border-hover: #CBD5E1;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-text-size-adjust: 100%;
        }

        body {
            background-color: var(--canvas);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Utility Bar */
        .top-utility-bar {
            background-color: var(--maroon-dark);
            color: rgba(255, 255, 255, 0.85);
            padding: 7px 4%;
            font-size: 11px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--gold);
        }

        .utility-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .utility-tag {
            background: rgba(242, 169, 0, 0.2);
            color: var(--gold);
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Site Header */
        .site-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 10px 4%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: var(--shadow-sm);
        }

        .brand-link {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-link img {
            height: 42px;
            width: auto;
        }

        .brand-title {
            line-height: 1.15;
        }

        .brand-title h1 {
            font-size: 16px;
            font-weight: 800;
            color: var(--maroon);
            letter-spacing: -0.3px;
        }

        .brand-title span {
            font-size: 10px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-btn-secondary {
            text-decoration: none;
            color: var(--text-body);
            font-size: 12px;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        .nav-btn-secondary:hover {
            background: var(--canvas);
            border-color: var(--border-hover);
        }

        .nav-btn-primary {
            text-decoration: none;
            color: #FFFFFF;
            background: var(--maroon);
            font-size: 12px;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .nav-btn-primary:hover {
            background: var(--maroon-dark);
        }

        /* Compact Hero Split Layout */
        .hero-section {
            padding: 24px 4% 30px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1.55fr;
            gap: 40px;
            align-items: center;
        }

        .hero-content {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .hero-heading {
            font-size: 2.35rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.15;
            letter-spacing: -1px;
            margin-top: 10px;
        }

        .hero-heading span {
            color: var(--maroon);
        }

        .hero-subheading {
            font-size: 0.95rem;
            color: var(--text-body);
            line-height: 1.5;
            max-width: 540px;
        }

        /* Action Cards */
        .action-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-top: 5px;
        }

        .action-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 16px 18px;
            text-decoration: none;
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
        }

        .action-card.card-guest:hover { border-color: var(--gold); }
        .action-card.card-login:hover { border-color: var(--maroon); }

        .action-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .action-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-guest { background: var(--gold-soft); color: var(--gold-dark); }
        .icon-login { background: #FEF2F2; color: var(--maroon); }

        .action-badge {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 3px 6px;
            border-radius: 4px;
        }

        .badge-guest { background: #FEF3C7; color: #92400E; }
        .badge-login { background: #FEE2E2; color: var(--maroon); }

        .action-card h3 {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 4px;
        }

        .action-card p {
            font-size: 11.5px;
            color: var(--text-body);
            line-height: 1.45;
            margin-bottom: 14px;
        }

        .action-btn-link {
            font-size: 12px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-link-guest { color: #B45309; }
        .btn-link-login { color: var(--maroon); }

        /* Wide Landscape Visual Showcase */
        .hero-visual-frame {
            position: relative;
            width: 100%;
            height: 440px;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 15px 35px -8px rgba(122, 0, 0, 0.2);
            border: 1px solid var(--border);
        }

        .hero-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 40%;
            display: block;
            transition: transform 6s ease;
        }

        .hero-visual-frame:hover .hero-img {
            transform: scale(1.02);
        }

        .visual-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.05) 0%, rgba(122, 0, 0, 0.75) 100%);
            pointer-events: none;
        }

        .chip-location {
            position: absolute;
            top: 16px;
            left: 16px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            border-radius: 20px;
            padding: 6px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-md);
            font-size: 11px;
            font-weight: 700;
            color: var(--text-main);
        }

        .chip-dot {
            width: 7px;
            height: 7px;
            background: #10B981;
            border-radius: 50%;
            box-shadow: 0 0 6px #10B981;
        }

        .floating-stat-card {
            position: absolute;
            bottom: 16px;
            left: 16px;
            right: 16px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border-radius: 10px;
            padding: 12px 18px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        .stat-item h4 {
            font-size: 18px;
            font-weight: 800;
            color: var(--maroon);
            line-height: 1.1;
            margin-bottom: 2px;
        }

        .stat-item span {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* Section 2: ARTA Standards */
        .dimensions-section {
            background: var(--surface);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 50px 4%;
            width: 100%;
        }

        .section-header {
            max-width: 750px;
            margin: 0 auto 35px;
            text-align: center;
        }

        .section-header h2 {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 8px;
            letter-spacing: -0.4px;
        }

        .section-header p {
            font-size: 13px;
            color: var(--text-body);
            line-height: 1.55;
        }

        .dimensions-grid {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }

        .dim-card {
            background: var(--canvas);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px;
            transition: all 0.2s;
        }

        .dim-card:hover {
            background: #FFFFFF;
            border-color: var(--gold);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .dim-number {
            font-size: 11px;
            font-weight: 800;
            color: var(--maroon);
            text-transform: uppercase;
            margin-bottom: 6px;
            display: inline-block;
            background: #FEF2F2;
            padding: 2px 8px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }

        .dim-card h4 {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .dim-card p {
            font-size: 12px;
            color: var(--text-body);
            line-height: 1.5;
        }

        /* Footer */
        footer {
            background: #0F172A;
            color: #94A3B8;
            padding: 35px 4% 25px;
            font-size: 12px;
            line-height: 1.6;
            margin-top: auto;
            width: 100%;
        }

        .footer-inner {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 20px;
            margin-bottom: 16px;
        }

        .footer-brand h4 {
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .footer-brand p {
            font-size: 12px;
            color: #94A3B8;
        }

        .footer-links {
            display: flex;
            gap: 20px;
        }

        .footer-links a {
            color: #CBD5E1;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--gold);
        }

        .footer-bottom {
            width: 100%;
            text-align: center;
            font-size: 11px;
            color: #64748B;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1200px) {
            .hero-section { grid-template-columns: 1fr 1.3fr; gap: 30px; }
            .hero-heading { font-size: 2.1rem; }
            .hero-visual-frame { height: 400px; }
        }

        @media (max-width: 980px) {
            .hero-section {
                display: grid;
                grid-template-columns: 1fr;
                gap: 35px;
                padding-top: 25px;
            }
            .hero-visual-frame { 
                order: 1; /* Pushes image to the top on mobile */
                height: 350px; 
            }
            .hero-content {
                order: 2; /* Content falls below the image */
            }
            .hero-heading { font-size: 2.2rem; margin-top: 0; }
            .dimensions-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .top-utility-bar { flex-direction: column; gap: 4px; text-align: center; }
            .hero-heading { font-size: 1.85rem; }
            .action-cards { grid-template-columns: 1fr; }
            .hero-visual-frame { height: 260px; }
            .floating-stat-card { padding: 10px; gap: 6px; }
            .stat-item h4 { font-size: 15px; }
            .stat-item span { font-size: 8.5px; }
            .dimensions-grid { grid-template-columns: 1fr; }
            .footer-inner { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

    <!-- Utility Bar -->
    <div class="top-utility-bar">
        <div class="utility-left">
            <span class="utility-tag">Official Government Portal</span>
            <span>Republic of the Philippines</span>
        </div>
        <div>
            <span>Republic Act No. 11032 • ARTA Memorandum Circular 2022-05</span>
        </div>
    </div>

    <!-- Site Header -->
    <header class="site-header">
        <a href="index.php" class="brand-link">
            <img src="logo/ZPPSU_LOGO.png" alt="ZPPSU Logo">
            <div class="brand-title">
                <h1>ZPPSU CSM</h1>
                <span>Client Satisfaction Measurement</span>
            </div>
        </a>

        <div class="nav-links">
            <a href="arta_feedback_form.php" class="nav-btn-secondary">Guest Form</a>
            <a href="login.php" class="nav-btn-primary">Sign In</a>
        </div>
    </header>

    <!-- Hero Section -->
    <main class="hero-section">
        
        <div class="hero-content">
            <h1 class="hero-heading">
                Quality Service, Measured by <span>Your Voice.</span>
            </h1>

            <p class="hero-subheading">
                Zamboanga Peninsula Polytechnic State University is committed to transparent, efficient, and courteous frontline government delivery. Select an entry route below to submit your official evaluation.
            </p>

            <div class="action-cards">
                <!-- Guest Route -->
                <a href="arta_feedback_form.php" class="action-card card-guest">
                    <div>
                        <div class="action-card-top">
                            <div class="action-icon icon-guest">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </div>
                            <span class="action-badge badge-guest">Direct Rating</span>
                        </div>
                        <h3>Guest Feedback</h3>
                        <p>For visitors, walk-in applicants, alumni, and external transacting clients. No login required.</p>
                    </div>
                    <div class="action-btn-link btn-link-guest">
                        Continue as Guest →
                    </div>
                </a>

                <!-- Logged In Route -->
                <a href="login.php" class="action-card card-login">
                    <div>
                        <div class="action-card-top">
                            <div class="action-icon icon-login">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <span class="action-badge badge-login">Institutional</span>
                        </div>
                        <h3>Student & Staff</h3>
                        <p>Sign in with your ZPPSU account to rate visited offices with auto-filled profiles and history.</p>
                    </div>
                    <div class="action-btn-link btn-link-login">
                        Portal Login →
                    </div>
                </a>
            </div>
        </div>

        <!-- Landscape Campus Frame -->
        <div class="hero-visual-frame">
            <img src="bg_picture/campus.jpeg" alt="ZPPSU Campus Grounds" class="hero-img">
            <div class="visual-overlay"></div>

            <div class="chip-location">
                <span class="chip-dot"></span>
                <span>Main Campus • R.T. Lim Boulevard</span>
            </div>

            <div class="floating-stat-card">
                <div class="stat-item">
                    <h4>8</h4>
                    <span>Service Dimensions</span>
                </div>
                <div class="stat-item">
                    <h4>100%</h4>
                    <span>ARTA Compliant</span>
                </div>
                <div class="stat-item">
                    <h4>R.A. 11032</h4>
                    <span>Ease of Doing Business</span>
                </div>
            </div>
        </div>

    </main>

    <!-- ARTA Service Dimensions Breakdown -->
    <section class="dimensions-section">
        <div class="section-header">
            <h2>Harmonized Service Quality Standard</h2>
            <p>Every evaluation tracks the 8 dimensions prescribed by the national government to ensure fair, prompt, and accountable delivery.</p>
        </div>

        <div class="dimensions-grid">
            <div class="dim-card">
                <div class="dim-number">SQD1</div>
                <h4>Responsiveness</h4>
                <p>Reasonable processing time and minimal queuing during office transactions.</p>
            </div>
            <div class="dim-card">
                <div class="dim-number">SQD2</div>
                <h4>Reliability</h4>
                <p>Accurate implementation of office guidelines and published Citizen's Charters.</p>
            </div>
            <div class="dim-card">
                <div class="dim-number">SQD3</div>
                <h4>Access & Steps</h4>
                <p>Easy, straightforward procedures with clear facility directions.</p>
            </div>
            <div class="dim-card">
                <div class="dim-number">SQD4</div>
                <h4>Communication</h4>
                <p>Transparent and easily understandable service details and requirements.</p>
            </div>
            <div class="dim-card">
                <div class="dim-number">SQD5</div>
                <h4>Costs & Fees</h4>
                <p>Official, reasonable fees with complete exemption for academic services.</p>
            </div>
            <div class="dim-card">
                <div class="dim-number">SQD6</div>
                <h4>Integrity</h4>
                <p>Zero corruption, fair processing, and strictly no preferential treatment.</p>
            </div>
            <div class="dim-card">
                <div class="dim-number">SQD7</div>
                <h4>Assurance</h4>
                <p>Courteous, knowledgeable, and competent frontline university personnel.</p>
            </div>
            <div class="dim-card">
                <div class="dim-number">SQD8</div>
                <h4>Outcome</h4>
                <p>Successful delivery of the requested document, clearance, or service.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-inner">
            <div class="footer-brand">
                <h4>Zamboanga Peninsula Polytechnic State University</h4>
                <p>Anti-Red Tape Authority • Zamboanga City, Philippines</p>
            </div>
            <div class="footer-links">
                <a href="arta_feedback_form.php">Direct Evaluation</a>
                <a href="login.php">Portal Sign In</a>
                <a href="register.php">Create Account</a>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?php echo date('Y'); ?> ZPPSU. In compliance with Republic Act No. 11032 and ARTA MC 2022-05.
        </div>
    </footer>

</body>
</html>