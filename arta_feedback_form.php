<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$success_message = '';
$error_message = '';

try {
    require_once 'db_connection.php'; 
    
    if (!isset($conn)) {
        throw new Exception("Database connection variable \$conn is missing.");
    }

    // 1. Self-Healing Table: Ensure services catalog exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            office_id INT NOT NULL,
            service_name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_office (office_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Auto-seed existing services from historical feedback logs
    $chk_services = $conn->query("SELECT COUNT(*) AS total FROM services");
    if ($chk_services && (int)$chk_services->fetch_assoc()['total'] === 0) {
        $conn->query("
            INSERT IGNORE INTO services (office_id, service_name)
            SELECT DISTINCT office_id, TRIM(service_accessed)
            FROM arta_feedback
            WHERE office_id IS NOT NULL AND office_id > 0 AND TRIM(service_accessed) != ''
        ");
    }

    $is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    $user_id = $is_logged_in ? ($_SESSION['user_id'] ?? $_SESSION['student_number'] ?? null) : null;

    $office_id_from_url = isset($_GET['office_id']) ? intval($_GET['office_id']) : 0;
    $office_name = "";
    $office_type_from_url = "";
    $offices_list = [];

    // Fetch Target Office if provided via URL
    if ($office_id_from_url > 0) {
        $stmt = $conn->prepare("SELECT office_name, office_type FROM offices WHERE id = ?");
        $stmt->bind_param("i", $office_id_from_url);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $office_name = $row['office_name'];
            $office_type_from_url = $row['office_type'];
        } else {
            $error_message = "Invalid Office Selected in URL.";
        }
        $stmt->close();
    } else {
        $result = $conn->query("SELECT id, office_name, office_type FROM offices ORDER BY office_name ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $offices_list[] = $row;
            }
        }
    }

    // 2. Fetch Catalog of Services Grouped by Office ID
    $services_by_office = [];
    $srv_res = $conn->query("SELECT office_id, service_name FROM services ORDER BY service_name ASC");
    if ($srv_res) {
        while ($s_row = $srv_res->fetch_assoc()) {
            $oid = (int)$s_row['office_id'];
            if (!isset($services_by_office[$oid])) {
                $services_by_office[$oid] = [];
            }
            $services_by_office[$oid][] = trim($s_row['service_name']);
        }
    }

    // 3. Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $final_office_id  = isset($_POST['office_id']) && !empty($_POST['office_id']) ? intval($_POST['office_id']) : $office_id_from_url;
        $service_accessed = trim($_POST['service_accessed'] ?? '');
        $other_service    = trim($_POST['other_service'] ?? '');

        // Resolve "Other" choice
        if ($service_accessed === 'Other' || empty($service_accessed)) {
            $service_accessed = !empty($other_service) ? $other_service : 'Other / Unspecified';
        }

        $transaction_date   = date('Y-m-d');
        // Fetch the new service_start_date from the form
        $service_start_date = $_POST['service_start_date'] ?? null;
        
        if ($is_logged_in && $user_id) {
            $stmt = $conn->prepare("SELECT client_type, age_group, sex, region FROM users WHERE user_id = ?");
            $stmt->bind_param("s", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($user_data = $result->fetch_assoc()) {
                $client_type = $user_data['client_type'];
                $age_group   = $user_data['age_group'];
                $sex         = $user_data['sex'];
                $region      = $user_data['region'];
            } else {
                throw new Exception("Logged-in user data not found.");
            }
            $stmt->close();
        } else {
            $client_type = $_POST['client_type'] ?? '';
            $age_group   = $_POST['age_group'] ?? '';
            $sex         = $_POST['sex'] ?? '';
            $region      = $_POST['region'] ?? 'Region IX - Zamboanga Peninsula';
        }

        $cc1 = intval($_POST['cc1'] ?? 0);
        $cc2 = intval($_POST['cc2'] ?? 0);
        $cc3 = intval($_POST['cc3'] ?? 0);

        $sqd1 = intval($_POST['sqd1'] ?? 0);
        $sqd2 = intval($_POST['sqd2'] ?? 0);
        $sqd3 = intval($_POST['sqd3'] ?? 0);
        $sqd4 = intval($_POST['sqd4'] ?? 0);
        $sqd5 = intval($_POST['sqd5'] ?? 0);
        $sqd6 = intval($_POST['sqd6'] ?? 0);
        $sqd7 = intval($_POST['sqd7'] ?? 0);
        $sqd8 = intval($_POST['sqd8'] ?? 0);
        $comments = trim($_POST['comments'] ?? '');

        if ($final_office_id > 0) {
            // Updated SQL statement to include service_start_date
            $sql = "INSERT INTO arta_feedback (
                        user_id, office_id, service_accessed, transaction_date, service_start_date,
                        client_type, age_group, sex, region, 
                        cc1, cc2, cc3, 
                        sqd1_responsiveness, sqd2_reliability, sqd3_access_facilities, 
                        sqd4_communication, sqd5_costs, sqd6_integrity, sqd7_assurance, sqd8_outcome, 
                        comments_recommendations
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
            $stmt = $conn->prepare($sql);
            // Updated bind param string to 21 values (added one 's' for the new date)
            $stmt->bind_param("sisssssssiiiiiiiiiiis", 
                $user_id, $final_office_id, $service_accessed, $transaction_date, $service_start_date,
                $client_type, $age_group, $sex, $region,
                $cc1, $cc2, $cc3,
                $sqd1, $sqd2, $sqd3, $sqd4, $sqd5, $sqd6, $sqd7, $sqd8,
                $comments
            );

            if ($stmt->execute()) {
                $success_message = "Thank you! Your feedback has been officially recorded.";

                // Self-learning catalog: Save custom service into services table if not yet present
                if (!empty($other_service) && $other_service !== 'Other / Unspecified') {
                    $chk_srv = $conn->prepare("SELECT id FROM services WHERE office_id = ? AND LOWER(service_name) = LOWER(?) LIMIT 1");
                    $chk_srv->bind_param("is", $final_office_id, $other_service);
                    $chk_srv->execute();
                    if ($chk_srv->get_result()->num_rows === 0) {
                        $ins_srv = $conn->prepare("INSERT INTO services (office_id, service_name) VALUES (?, ?)");
                        $ins_srv->bind_param("is", $final_office_id, $other_service);
                        $ins_srv->execute();
                        $ins_srv->close();
                    }
                    $chk_srv->close();
                }

            } else {
                $error_message = "Database insertion failed: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Please search and select a valid Office/College from the list.";
        }
    }
} catch (Exception $e) {
    $error_message = "System Error: " . $e->getMessage();
} catch (Error $e) {
    $error_message = "Fatal PHP Error: " . $e->getMessage() . " on line " . $e->getLine();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ARTA CSM Form | ZPPSU</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --maroon: #7A0000;
            --maroon-dark: #580000;
            --maroon-light: #FEF2F2;
            --gold: #F2A900;
            --bg: #F8FAFC;
            --surface: #FFFFFF;
            --border: #E2E8F0;
            --border-strong: #CBD5E1;
            --text-heading: #0F172A;
            --text-body: #475569;
            --text-muted: #94A3B8;
            --success: #10B981;
            --danger: #EF4444;
        }

        * {
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-text-size-adjust: 100%;
        }

        body {
            background-color: var(--bg);
            color: var(--text-body);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ===== TOP NAVIGATION ===== */
        .public-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 16px 24px;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-heading);
        }

        .header-brand img {
            height: 38px;
            width: auto;
        }

        .header-brand-text h1 {
            font-size: 16px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.3px;
        }
        .header-brand-text p {
            font-size: 11px;
            color: var(--text-muted);
            margin: 2px 0 0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #FFFFFF;
            color: var(--text-heading);
            border: 1.5px solid var(--border);
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.18s;
        }
        .btn-back:hover {
            background: #F1F5F9;
            border-color: var(--border-strong);
        }
        .btn-back svg {
            width: 15px;
            height: 15px;
        }

        /* ===== FORM CONTAINER ===== */
        .form-wrapper {
            max-width: 820px;
            margin: 32px auto 60px;
            padding: 0 20px;
            width: 100%;
        }

        .page-intro {
            text-align: center;
            margin-bottom: 28px;
        }
        .page-intro h2 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0 0 8px;
            letter-spacing: -0.5px;
        }
        .page-intro p {
            font-size: 14px;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.5;
        }

        /* ===== SECTIONS ===== */
        .section-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 800;
            color: var(--maroon);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px dashed var(--border);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .section-num {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--maroon);
            color: #FFFFFF;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            flex-shrink: 0;
        }

        /* ===== FORM CONTROLS ===== */
        label {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12.5px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-heading);
        }
        label .req { color: var(--danger); }

        /* FIXED: Added min-width and max-width to prevent date input from overflowing on iOS */
        input[type="text"], 
        input[type="date"], 
        select, 
        textarea {
            width: 100%;
            min-width: 0; 
            max-width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-family: inherit;
            font-size: 14px;
            background-color: #F8FAFC;
            color: var(--text-heading);
            outline: none;
            transition: all 0.18s ease;
            height: 44px;
            display: block;
        }

        /* Specific native appearance reset for date to enforce width constraints */
        input[type="date"] {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        
        textarea {
            height: auto;
            min-height: 100px;
            resize: vertical;
            line-height: 1.5;
        }

        input[type="text"]:focus, input[type="date"]:focus, select:focus, textarea:focus {
            border-color: var(--maroon);
            background-color: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(122, 0, 0, 0.08);
        }

        input:disabled {
            background-color: #F1F5F9 !important;
            color: var(--text-muted) !important;
            cursor: not-allowed;
            border-color: var(--border);
        }

        .grid-2 { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 16px; 
            margin-bottom: 16px; 
        }
        .form-group { margin-bottom: 18px; width: 100%; }
        .form-group:last-child { margin-bottom: 0; }

        /* ===== SEARCHABLE DROPDOWN ===== */
        .searchable-dropdown { position: relative; }
        .dropdown-list {
            position: absolute; 
            top: calc(100% + 4px); 
            left: 0; 
            right: 0; 
            background: #FFFFFF;
            border: 1.5px solid var(--maroon); 
            max-height: 240px;
            overflow-y: auto; 
            z-index: 1000; 
            display: none; 
            margin: 0; 
            padding: 4px; 
            list-style: none;
            border-radius: 9px; 
            box-shadow: 0 12px 28px -8px rgba(15, 23, 42, 0.22);
        }
        .dropdown-list li {
            padding: 10px 12px; 
            cursor: pointer; 
            font-size: 13px; 
            border-radius: 6px;
            transition: background 0.15s ease;
            line-height: 1.35;
        }
        .dropdown-list li:hover { 
            background-color: var(--maroon-light); 
            color: var(--maroon); 
            font-weight: 600;
        }
        .dropdown-list li.option-other {
            color: var(--maroon);
            font-weight: 700;
            background: #FFFDF7;
            border-top: 1px dashed #FDE68A;
            margin-top: 4px;
        }
        .dropdown-list li.option-other:hover {
            background: var(--maroon);
            color: #FFFFFF;
        }

        /* ===== SQD RATING SCALE ===== */
        .sqd-item { margin-bottom: 24px; border-bottom: 1px dashed var(--border); padding-bottom: 20px; }
        .sqd-item:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .sqd-desc { font-size: 13.5px; margin-bottom: 12px; font-weight: 600; line-height: 1.5; color: var(--text-heading); }
        
        .rating-options { display: flex; gap: 8px; flex-wrap: wrap; }
        .rating-options input[type="radio"] { display: none; }
        .rating-options label {
            flex: 1; 
            min-width: 80px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center;
            padding: 12px 6px; 
            border: 1.5px solid var(--border); 
            border-radius: 9px;
            cursor: pointer; 
            background: #F8FAFC; 
            transition: all 0.18s ease; 
            margin: 0;
            touch-action: manipulation;
        }
        .rating-options label .emoji { font-size: 24px; margin-bottom: 6px; filter: grayscale(100%); opacity: 0.6; transition: all 0.18s; }
        .rating-options label .text { font-size: 10.5px; font-weight: 700; color: var(--text-muted); text-align: center; line-height: 1.2; }
        
        .rating-options input[type="radio"]:checked + label {
            border-color: var(--maroon);
            background-color: var(--maroon-light);
            box-shadow: 0 0 0 4px rgba(122, 0, 0, 0.08);
        }
        .rating-options input[type="radio"]:checked + label .emoji { filter: grayscale(0%); opacity: 1; transform: scale(1.15); }
        .rating-options input[type="radio"]:checked + label .text { color: var(--maroon); }

        .disabled-block { opacity: 0.6; pointer-events: none; background: #F1F5F9; padding: 16px; border-radius: 9px; border: 1.5px dashed var(--border-strong); }

        /* ===== SUBMIT BUTTON ===== */
        .btn-submit {
            background-color: var(--maroon); 
            color: white;
            border: none; 
            padding: 16px 24px; 
            font-size: 14px; 
            font-weight: 800;
            border-radius: 9px; 
            width: 100%; 
            cursor: pointer; 
            text-transform: uppercase;
            letter-spacing: 0.6px;
            transition: background-color 0.18s, transform 0.12s;
            touch-action: manipulation;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(122, 0, 0, 0.15);
        }
        .btn-submit:hover { background-color: var(--maroon-dark); }
        .btn-submit:active { transform: scale(0.99); }

        /* ===== ALERTS ===== */
        .alert-error { background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; padding: 16px; border-radius: 9px; margin-bottom: 20px; font-size: 13.5px; font-weight: 600; }
        .alert-success { background: #ECFDF5; border: 1px solid #A7F3D0; color: #047857; padding: 40px 24px; border-radius: 14px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .alert-success svg { width: 54px; height: 54px; margin-bottom: 16px; color: var(--success); }
        .alert-success h3 { margin: 0 0 8px; font-size: 20px; font-weight: 800; color: var(--text-heading); }
        .alert-success p { font-size: 14px; margin: 0 0 24px; color: var(--text-body); line-height: 1.5; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 640px) {
            .public-header { padding: 12px 16px; }
            .header-brand img { height: 32px; }
            .header-brand-text h1 { font-size: 14px; }
            .header-brand-text p { display: none; }
            .btn-back span { display: none; }
            .btn-back { padding: 9px 12px; }
            
            .form-wrapper { margin: 20px auto 40px; padding: 0 12px; }
            .page-intro h2 { font-size: 22px; }
            .section-card { padding: 20px 16px; }
            .grid-2 { grid-template-columns: 1fr; gap: 12px; }
            
            .rating-options { gap: 6px; }
            .rating-options label { min-width: calc(33.333% - 6px); padding: 10px 4px; }
            .rating-options label .emoji { font-size: 22px; }
            .rating-options label .text { font-size: 9.5px; }
            
            /* Prevent iOS auto-zoom */
            input[type="text"], input[type="date"], select, textarea { font-size: 16px; }
        }
    </style>
</head>
<body>

<!-- ===== TOP NAVIGATION ===== -->
<header class="public-header">
    <a href="index.php" class="header-brand">
        <img src="logo/ZPPSU_LOGO.png" alt="ZPPSU Logo" onerror="this.style.display='none'">
        <div class="header-brand-text">
            <h1>ZPPSU CSM</h1>
            <p>ARTA Feedback Portal</p>
        </div>
    </a>
    
    <?php if ($is_logged_in): ?>
        <a href="student_dashboard.php" class="btn-back">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Return to Dashboard</span>
        </a>
    <?php else: ?>
        <a href="index.php" class="btn-back">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Return to Home</span>
        </a>
    <?php endif; ?>
</header>

<main class="form-wrapper">
    
    <?php if ($error_message): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert-success">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3>Feedback Recorded</h3>
            <p><?php echo $success_message; ?></p>
            <a href="<?php echo $is_logged_in ? 'student_dashboard.php' : 'index.php'; ?>" 
               style="display:inline-flex; align-items:center; gap:6px; padding:12px 24px; background:var(--maroon); color:white; text-decoration:none; border-radius:8px; font-weight:700; font-size:13.5px;">
                <?php echo $is_logged_in ? 'Return to Dashboard' : 'Return to Home'; ?>
            </a>
        </div>
    <?php else: ?>
    
    <div class="page-intro">
        <h2>Client Satisfaction Measurement</h2>
        <p>Your feedback helps us improve our frontline services in compliance with ARTA regulations.</p>
    </div>

    <form method="POST" action="" id="artaForm">
        
        <!-- SECTION 1: Transaction Details -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-num">1</span>
                Transaction Details
            </div>
            
            <!-- Office Selection -->
            <?php if ($office_name): ?>
                <div class="form-group">
                    <label>Office / College Visited</label>
                    <input type="text" value="<?php echo htmlspecialchars($office_name); ?>" readonly style="background:#F1F5F9; color:#334155; font-weight:700; cursor:default;">
                    <input type="hidden" name="office_id" id="officeIdInput" value="<?php echo $office_id_from_url; ?>">
                </div>
            <?php else: ?>
                <div class="form-group searchable-dropdown">
                    <label>Search and Select Office / College Visited <span class="req">*</span></label>
                    <input type="text" id="officeSearchBox" placeholder="Type to search offices..." autocomplete="off" required>
                    <input type="hidden" name="office_id" id="officeIdInput" required>
                    <ul class="dropdown-list" id="officeDropdown">
                        <?php foreach ($offices_list as $off): ?>
                            <li data-id="<?php echo $off['id']; ?>" data-type="<?php echo htmlspecialchars($off['office_type']); ?>">
                                <?php echo htmlspecialchars($off['office_name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Dynamic Searchable Service Dropdown -->
            <div class="form-group searchable-dropdown" style="margin-top: 16px;">
                <label>Specific Service Accessed <span class="req">*</span></label>
                <input type="text" id="serviceSearchBox" placeholder="<?php echo ($office_id_from_url > 0) ? 'Type or select a service accessed...' : 'Select an office above first...'; ?>" autocomplete="off" required <?php echo ($office_id_from_url > 0) ? '' : 'disabled'; ?>>
                <input type="hidden" name="service_accessed" id="serviceAccessedInput" required>
                <ul class="dropdown-list" id="serviceDropdown">
                    <!-- Populated dynamically based on selected office -->
                </ul>
            </div>

            <!-- "Other" Service Specification Field -->
            <div class="form-group" id="otherServiceGroup" style="display: none; margin-top: 16px;">
                <label for="otherServiceInput">Specify Service / Transaction Name <span class="req">*</span></label>
                <input type="text" id="otherServiceInput" name="other_service" placeholder="e.g. Request for Form 137, Signing of Clearance, Inquiries">
            </div>

            <!-- Date started tracking for SLA -->
            <div class="form-group" style="margin-top: 16px;">
                <label>Date you started this transaction <span class="req">*</span></label>
                <input type="date" name="service_start_date" max="<?php echo date('Y-m-d'); ?>" required>
                <small style="display:block; font-size:11px; color:var(--text-muted); margin-top:6px;">Used to measure processing times against ARTA standards.</small>
            </div>
        </div>

        <!-- SECTION 2: Client Profile (If Guest) -->
        <?php if (!$is_logged_in): ?>
        <div class="section-card">
            <div class="section-header">
                <span class="section-num">2</span>
                Client Profile
            </div>
            <div class="grid-2">
                <div>
                    <label>Client Type <span class="req">*</span></label>
                    <select name="client_type" required>
                        <option value="" disabled selected>Select...</option>
                        <option value="Citizen">Citizen</option>
                        <option value="Business">Business</option>
                        <option value="Government">Government</option>
                    </select>
                </div>
                <div>
                    <label>Age <span class="req">*</span></label>
                    <select name="age_group" required>
                        <option value="" disabled selected>Select...</option>
                        <option value="19 or lower">19 or lower</option>
                        <option value="20-34">20-34</option>
                        <option value="35-49">35-49</option>
                        <option value="50-64">50-64</option>
                        <option value="65 or higher">65 or higher</option>
                    </select>
                </div>
                <div>
                    <label>Sex <span class="req">*</span></label>
                    <select name="sex" required>
                        <option value="" disabled selected>Select...</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Prefer not to say">Prefer not to say</option>
                    </select>
                </div>
                <div>
                    <label>Region <span class="req">*</span></label>
                    <select name="region" required>
                        <option value="Region I - Ilocos Region">Region I - Ilocos Region</option>
                        <option value="Region II - Cagayan Valley">Region II - Cagayan Valley</option>
                        <option value="Region III - Central Luzon">Region III - Central Luzon</option>
                        <option value="Region IV-A - CALABARZON">Region IV-A - CALABARZON</option>
                        <option value="MIMAROPA Region">MIMAROPA Region</option>
                        <option value="Region V - Bicol Region">Region V - Bicol Region</option>
                        <option value="Region VI - Western Visayas">Region VI - Western Visayas</option>
                        <option value="Region VII - Central Visayas">Region VII - Central Visayas</option>
                        <option value="Region VIII - Eastern Visayas">Region VIII - Eastern Visayas</option>
                        <option value="Region IX - Zamboanga Peninsula" selected>Region IX - Zamboanga Peninsula</option>
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
        <?php endif; ?>

        <!-- SECTION 3: Citizen's Charter -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-num"><?php echo $is_logged_in ? '2' : '3'; ?></span>
                Citizen's Charter (CC) Awareness
            </div>
            <div class="form-group">
                <label>CC1: Which best describes your awareness of the Citizen's Charter?</label>
                <select name="cc1" required>
                    <option value="" disabled selected>Select an answer...</option>
                    <option value="1">1. I know what a CC is and I saw this office's CC.</option>
                    <option value="2">2. I know what a CC is but I did NOT see this office's CC.</option>
                    <option value="3">3. I learned of the CC only when I saw this office's CC.</option>
                    <option value="4">4. I do not know what a CC is and did not see one.</option>
                </select>
            </div>
            <div class="form-group">
                <label>CC2: If aware of the CC (answered 1-3 above), describe its visibility:</label>
                <select name="cc2" required>
                    <option value="" disabled selected>Select an answer...</option>
                    <option value="1">1. Easy to see</option>
                    <option value="2">2. Somewhat easy to see</option>
                    <option value="3">3. Difficult to see</option>
                    <option value="4">4. Not visible at all</option>
                    <option value="5">5. N/A</option>
                </select>
            </div>
            <div class="form-group">
                <label>CC3: If aware of the CC (answered 1-3 above), how much did it help you?</label>
                <select name="cc3" required>
                    <option value="" disabled selected>Select an answer...</option>
                    <option value="1">1. Helped very much</option>
                    <option value="2">2. Somewhat helped</option>
                    <option value="3">3. Did not help</option>
                    <option value="4">4. N/A</option>
                </select>
            </div>
        </div>

        <!-- SECTION 4: SQD Dimensions -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-num"><?php echo $is_logged_in ? '3' : '4'; ?></span>
                Service Quality Dimensions (SQD)
            </div>
            
            <?php
            $sqd_questions = [
                1 => "SQD1. I spent a reasonable amount of time for my transaction.",
                2 => "SQD2. The office followed the transaction's requirements and steps.",
                3 => "SQD3. The steps (including payment) I needed to do for my transaction were easy and simple.",
                4 => "SQD4. I easily found information about my transaction.",
                5 => "SQD5. I paid a reasonable amount of fees for my transaction.",
                6 => "SQD6. I feel the office was fair to everyone (walang palakasan).",
                7 => "SQD7. I was treated courteously by the staff.",
                8 => "SQD8. I got what I needed from the government office."
            ];

            foreach ($sqd_questions as $index => $question):
            ?>
            <div class="sqd-item" id="sqd-container-<?php echo $index; ?>">
                <div class="sqd-desc" id="sqd-text-<?php echo $index; ?>"><?php echo $question; ?></div>
                <div class="rating-options">
                    <?php 
                    $scales = [
                        ['val'=>5, 'emoji'=>'😍', 'label'=>'Strongly Agree'],
                        ['val'=>4, 'emoji'=>'🙂', 'label'=>'Agree'],
                        ['val'=>3, 'emoji'=>'😐', 'label'=>'Neutral'],
                        ['val'=>2, 'emoji'=>'😟', 'label'=>'Disagree'],
                        ['val'=>1, 'emoji'=>'😡', 'label'=>'Strongly Disagree'],
                        ['val'=>0, 'emoji'=>'🚫', 'label'=>'N/A']
                    ];
                    foreach ($scales as $scale): 
                    ?>
                    <input type="radio" name="sqd<?php echo $index; ?>" id="sqd<?php echo $index; ?>_<?php echo $scale['val']; ?>" value="<?php echo $scale['val']; ?>" required>
                    <label for="sqd<?php echo $index; ?>_<?php echo $scale['val']; ?>">
                        <span class="emoji"><?php echo $scale['emoji']; ?></span>
                        <span class="text"><?php echo $scale['label']; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- SECTION 5: Additional Feedback -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-num"><?php echo $is_logged_in ? '4' : '5'; ?></span>
                Additional Feedback
            </div>
            <textarea name="comments" rows="4" placeholder="Please provide any additional comments or recommendations here (Optional)."></textarea>
        </div>

        <button type="submit" class="btn-submit">Submit Official Feedback</button>
    </form>
    <?php endif; ?>
</main>

<script>
    // Master Services Catalog JSON passed from PHP
    const OFFICE_SERVICES = <?php echo json_encode($services_by_office); ?>;

    // Elements
    const officeSearchBox = document.getElementById('officeSearchBox');
    const officeDropdown = document.getElementById('officeDropdown');
    const officeIdInput = document.getElementById('officeIdInput');

    const serviceSearchBox = document.getElementById('serviceSearchBox');
    const serviceDropdown = document.getElementById('serviceDropdown');
    const serviceAccessedInput = document.getElementById('serviceAccessedInput');
    const otherServiceGroup = document.getElementById('otherServiceGroup');
    const otherServiceInput = document.getElementById('otherServiceInput');

    // 1. Office Searchable Dropdown
    if (officeSearchBox) {
        const officeItems = officeDropdown.getElementsByTagName('li');

        officeSearchBox.addEventListener('focus', () => { 
            officeDropdown.style.display = 'block'; 
        });

        officeSearchBox.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase().trim();
            for (let i = 0; i < officeItems.length; i++) {
                const txt = officeItems[i].textContent || officeItems[i].innerText;
                officeItems[i].style.display = txt.toLowerCase().includes(filter) ? '' : 'none';
            }
        });

        for (let i = 0; i < officeItems.length; i++) {
            officeItems[i].addEventListener('click', function() {
                const oid = this.getAttribute('data-id');
                const otype = this.getAttribute('data-type');
                
                officeSearchBox.value = this.innerText.trim();
                officeIdInput.value = oid;
                officeDropdown.style.display = 'none';

                // Load and enable services for this office
                loadServicesForOffice(oid);
                handleSmartLogic(otype);
            });
        }
    }

    // 2. Load Services for Selected Office
    function loadServicesForOffice(officeId) {
        serviceSearchBox.disabled = false;
        serviceSearchBox.value = '';
        serviceAccessedInput.value = '';
        serviceSearchBox.placeholder = 'Type to search or select service...';
        
        hideOtherField();
        renderServiceList(officeId, '');
    }

    function renderServiceList(officeId, filterText) {
        serviceDropdown.innerHTML = '';
        const list = OFFICE_SERVICES[officeId] || [];
        const filter = filterText ? filterText.toLowerCase().trim() : '';

        // Render known services for this office
        list.forEach(service => {
            if (filter === '' || service.toLowerCase().includes(filter)) {
                const li = document.createElement('li');
                li.textContent = service;
                li.addEventListener('click', () => {
                    selectStandardService(service);
                });
                serviceDropdown.appendChild(li);
            }
        });

        // Always append the "Other" option
        const otherLi = document.createElement('li');
        otherLi.className = 'option-other';
        otherLi.innerHTML = `+ Other (Please specify)${filter ? `: "<em>${escapeHtml(filterText)}</em>"` : ''}`;
        otherLi.addEventListener('click', () => {
            selectOtherService(filterText);
        });
        serviceDropdown.appendChild(otherLi);
    }

    // 3. Service Searchbox Handlers (Re-populates when deleting or clearing)
    serviceSearchBox.addEventListener('focus', function() {
        const oid = officeIdInput.value;
        if (oid) {
            // If the box contains "Other (Please specify)", select the text so typing or backspacing immediately clears it
            if (this.value === 'Other (Please specify)') {
                this.select();
                renderServiceList(oid, '');
            } else {
                renderServiceList(oid, this.value);
            }
            serviceDropdown.style.display = 'block';
        }
    });

    // Detect backspacing, deletion, or cut/paste clearing
    serviceSearchBox.addEventListener('input', function() {
        const oid = officeIdInput.value;
        if (!oid) return;

        const val = this.value.trim();

        // If the user cleared the box or deleted/backspaced out of "Other"
        if (val === '' || !val.toLowerCase().startsWith('other')) {
            hideOtherField();
            serviceAccessedInput.value = val;
            
            // Re-render full list if empty, or filter by remaining search term
            renderServiceList(oid, val);
        } else if (val.toLowerCase() === 'other' || val.toLowerCase().includes('please specify')) {
            // User typed 'other' manually
            renderServiceList(oid, val);
        } else {
            renderServiceList(oid, val);
        }

        serviceDropdown.style.display = 'block';
    });

    function selectStandardService(serviceName) {
        serviceSearchBox.value = serviceName;
        serviceAccessedInput.value = serviceName;
        serviceDropdown.style.display = 'none';
        hideOtherField();
    }

    function selectOtherService(initialText = '') {
        serviceSearchBox.value = 'Other (Please specify)';
        serviceAccessedInput.value = 'Other';
        serviceDropdown.style.display = 'none';
        
        // Show free-text input
        otherServiceGroup.style.display = 'block';
        otherServiceInput.required = true;
        if (initialText && initialText.trim() !== '' && initialText !== 'Other (Please specify)') {
            otherServiceInput.value = initialText.trim();
        }
        otherServiceInput.focus();
    }

    function hideOtherField() {
        otherServiceGroup.style.display = 'none';
        otherServiceInput.required = false;
        otherServiceInput.value = '';
    }

    // Close open dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (officeSearchBox && e.target !== officeSearchBox && e.target !== officeDropdown) {
            officeDropdown.style.display = 'none';
        }
        if (e.target !== serviceSearchBox && e.target !== serviceDropdown) {
            serviceDropdown.style.display = 'none';
        }
    });

    function escapeHtml(str) {
        return str.replace(/[&<>"']/g, m => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m]));
    }

    // 4. Smart ARTA Logic for Academic Offices (Disables payment SQD5)
    function handleSmartLogic(officeType) {
        const sqd5Container = document.getElementById('sqd-container-5');
        const sqd3Text = document.getElementById('sqd-text-3');
        const sqd5NA = document.getElementById('sqd5_0');

        const isAcademic = officeType && officeType.toLowerCase().includes('academic');

        if (isAcademic) {
            if (sqd3Text) sqd3Text.innerText = "SQD3. The steps I needed to do for my transaction were easy and simple.";
            if (sqd5Container) {
                sqd5Container.classList.add('disabled-block');
                if (sqd5NA) sqd5NA.checked = true;
            }
        } else {
            if (sqd3Text) sqd3Text.innerText = "SQD3. The steps (including payment) I needed to do for my transaction were easy and simple.";
            if (sqd5Container) {
                sqd5Container.classList.remove('disabled-block');
                if (sqd5NA) sqd5NA.checked = false;
            }
        }
    }

    // Pre-initialization if office is supplied via URL parameter
    <?php if ($office_id_from_url > 0): ?>
        document.addEventListener('DOMContentLoaded', () => {
            loadServicesForOffice(<?php echo $office_id_from_url; ?>);
            handleSmartLogic("<?php echo addslashes($office_type_from_url); ?>");
        });
    <?php endif; ?>
</script>

</body>
</html>