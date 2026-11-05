<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Redirect if already authenticated
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (($_SESSION['internal_user_type'] ?? '') === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: student_dashboard.php");
    }
    exit();
}

require_once 'db_connection.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id            = trim($_POST['user_id'] ?? '');
    $full_name          = trim($_POST['full_name'] ?? '');
    $email              = trim($_POST['email'] ?? '');
    $password           = $_POST['password'] ?? '';
    $confirm_password   = $_POST['confirm_password'] ?? '';
    $internal_user_type = $_POST['internal_user_type'] ?? 'student';
    $client_type        = $_POST['client_type'] ?? 'Citizen';
    $age_group          = $_POST['age_group'] ?? '';
    $sex                = $_POST['sex'] ?? '';
    $region             = $_POST['region'] ?? 'Region IX - Zamboanga Peninsula';

    // Validation
    if (empty($user_id) || empty($full_name) || empty($email) || empty($password) || empty($age_group) || empty($sex)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid institutional or personal email address.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match. Please verify.";
    } else {
        // Check for duplicates
        $check_stmt = $conn->prepare("SELECT user_id, email FROM users WHERE user_id = ? OR email = ? LIMIT 1");
        $check_stmt->bind_param("ss", $user_id, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result && $check_result->num_rows > 0) {
            $existing = $check_result->fetch_assoc();
            if ($existing['user_id'] === $user_id) {
                $error_message = "An account with this ID Number already exists.";
            } else {
                $error_message = "An account with this Email address already exists.";
            }
            $check_stmt->close();
        } else {
            $check_stmt->close();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $insert_stmt = $conn->prepare("INSERT INTO users (user_id, full_name, email, password, internal_user_type, client_type, age_group, sex, region) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param(
                "sssssssss",
                $user_id,
                $full_name,
                $email,
                $hashed_password,
                $internal_user_type,
                $client_type,
                $age_group,
                $sex,
                $region
            );

            if ($insert_stmt->execute()) {
                $insert_stmt->close();

                // Auto-login session initialization
                $_SESSION['logged_in']          = true;
                $_SESSION['user_id']            = $user_id;
                $_SESSION['student_number']     = $user_id;
                $_SESSION['full_name']          = $full_name;
                $_SESSION['client_type']        = $client_type;
                $_SESSION['internal_user_type'] = $internal_user_type;

                if ($internal_user_type === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: student_dashboard.php");
                }
                exit();
            } else {
                $error_message = "Registration failed: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Register | ZPPSU CSM</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --zppsu-maroon: #7A0000;
            --zppsu-maroon-dark: #520000;
            --zppsu-gold: #F2A900;
            --zppsu-gold-light: #FFF8E6;
            --border: #E2E8F0;
            --bg-canvas: #F8FAFC;
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
            background-color: var(--bg-canvas);
            color: var(--text-heading);
            min-height: 100vh;
        }

        /* Split Screen Container */
        .split-layout {
            display: grid;
            grid-template-columns: 1fr 1.25fr;
            min-height: 100vh;
            width: 100%;
        }

        /* Left Side: Campus Visual Showcase with Lighter Gradient Overlay */
        .visual-panel {
            position: relative;
            background: 
                linear-gradient(135deg, rgba(74, 0, 0, 0.45) 0%, rgba(15, 23, 42, 0.55) 100%),
                url('bg_picture/campus.jpeg') center center / cover no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 50px 60px;
            color: #FFFFFF;
            overflow: hidden;
        }

        .visual-top {
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 2;
        }

        .visual-top img {
            height: 52px;
            width: auto;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.4));
        }

        .visual-brand h2 {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #FFFFFF;
            line-height: 1.2;
            text-shadow: 0 2px 6px rgba(0,0,0,0.5);
        }

        .visual-brand span {
            font-size: 0.75rem;
            color: var(--zppsu-gold);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-shadow: 0 1px 4px rgba(0,0,0,0.6);
        }

        .visual-center {
            max-width: 520px;
            z-index: 2;
            margin: 40px 0;
        }

        .compliance-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--zppsu-gold-light);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 22px;
        }

        .visual-center h1 {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -1px;
            margin-bottom: 18px;
            color: #FFFFFF;
            text-shadow: 0 3px 12px rgba(0,0,0,0.6);
        }

        .visual-center h1 span {
            color: var(--zppsu-gold);
        }

        .visual-center p {
            font-size: 0.98rem;
            color: #F8FAFC;
            line-height: 1.6;
            font-weight: 500;
            text-shadow: 0 2px 6px rgba(0,0,0,0.6);
        }

        .visual-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid rgba(255, 255, 255, 0.25);
            padding-top: 24px;
            z-index: 2;
        }

        .location-tag {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            color: #FFFFFF;
            font-weight: 600;
            text-shadow: 0 1px 4px rgba(0,0,0,0.6);
        }

        .dot-pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10B981;
            box-shadow: 0 0 8px #10B981;
        }

        /* Right Side: Form Panel */
        .form-panel {
            background-color: #FFFFFF;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            padding: 45px 60px 60px;
            overflow-y: auto;
        }

        .form-wrapper {
            width: 100%;
            max-width: 580px;
        }

        .form-header {
            margin-bottom: 24px;
        }

        .form-header h2 {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.6px;
            margin-bottom: 6px;
        }

        .form-header p {
            font-size: 0.92rem;
            color: var(--text-body);
            line-height: 1.5;
        }

        /* Section Headings */
        .section-header {
            font-size: 12px;
            font-weight: 800;
            color: var(--zppsu-maroon);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding-bottom: 8px;
            margin: 24px 0 16px 0;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-error {
            background-color: #FEF2F2;
            border: 1px solid #FCA5A5;
            color: #991B1B;
            padding: 12px 14px;
            border-radius: 6px;
            font-size: 13.5px;
            margin-bottom: 20px;
            line-height: 1.45;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-heading);
            margin-bottom: 6px;
        }

        label span.req {
            color: #DC2626;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 15px;
            background-color: #F8FAFC;
            color: var(--text-heading);
            transition: all 0.2s ease;
            outline: none;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
            border-color: var(--zppsu-maroon);
            background-color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(122, 0, 0, 0.08);
        }

        .btn-submit {
            width: 100%;
            background-color: var(--zppsu-maroon);
            color: #FFFFFF;
            border: none;
            padding: 14px;
            font-size: 14.5px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            letter-spacing: 0.4px;
            transition: all 0.2s ease;
            touch-action: manipulation;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background-color: var(--zppsu-maroon-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(122, 0, 0, 0.2);
        }

        .links-stack {
            margin-top: 26px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 13.5px;
            color: var(--text-body);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .links-stack a {
            color: var(--zppsu-maroon);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .links-stack a:hover {
            text-decoration: underline;
        }

        .compliance-footnote {
            margin-top: 28px;
            font-size: 11.5px;
            color: var(--text-muted);
            text-align: center;
            line-height: 1.55;
        }

        /* Mobile / Tablet Responsive */
        @media (max-width: 992px) {
            .split-layout {
                grid-template-columns: 1fr;
                min-height: 100vh;
            }

            .visual-panel {
                height: 175px;
                min-height: 175px;
                padding: 20px 24px;
                justify-content: center;
                background-position: center 30%;
            }

            .visual-top {
                gap: 12px;
            }

            .visual-top img {
                height: 44px;
            }

            .visual-brand h2 {
                font-size: 1.05rem;
            }

            .visual-brand span {
                font-size: 0.7rem;
            }

            .visual-center,
            .visual-bottom {
                display: none;
            }

            .form-panel {
                padding: 30px 20px 50px;
            }

            .form-header h2 {
                font-size: 1.45rem;
            }
        }

        @media (max-width: 640px) {
            .grid-2 {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>

    <div class="split-layout">
        
        <!-- Left Side: Campus Visual Showcase -->
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
                    🛡️ ARTA R.A. 11032 Registration
                </div>
                <h1>Join the University <span>Quality Journey.</span></h1>
                <p>Register once with your institutional ID to auto-fill your ARTA demographic profile, track feedback histories, and rate campus frontline units seamlessly.</p>
            </div>

            <div class="visual-bottom">
                <div class="location-tag">
                    <span class="dot-pulse"></span>
                    Main Campus • R.T. Lim Boulevard, Zamboanga City
                </div>
                <span style="font-size: 12px; color: #FFFFFF; font-weight: 600; text-shadow: 0 1px 4px rgba(0,0,0,0.6);">ARTA MC 2022-05</span>
            </div>
        </div>

        <!-- Right Side: Registration Form -->
        <div class="form-panel">
            <div class="form-wrapper">
                
                <div class="form-header">
                    <h2>Create an Account</h2>
                    <p>Enter your institutional details and ARTA demographic classification.</p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert-error">
                        <span>⚠</span>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    
                    <!-- Section 1 -->
                    <div class="section-header">
                        <span>01</span> Account Credentials
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label for="user_id">ID Number <span class="req">*</span></label>
                            <input type="text" id="user_id" name="user_id" placeholder="e.g. 2021141338 or Emp-012" value="<?php echo htmlspecialchars($_POST['user_id'] ?? ''); ?>" required autocomplete="username">
                        </div>

                        <div class="form-group">
                            <label for="full_name">Full Name <span class="req">*</span></label>
                            <input type="text" id="full_name" name="full_name" placeholder="Last Name, First Name M.I." value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required autocomplete="name">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="email">Institutional / Personal Email <span class="req">*</span></label>
                            <input type="email" id="email" name="email" placeholder="example@zppsu.edu.ph" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autocomplete="email">
                        </div>

                        <div class="form-group">
                            <label for="internal_user_type">Institutional Role <span class="req">*</span></label>
                            <select id="internal_user_type" name="internal_user_type" required>
                                <option value="student" <?php echo (($_POST['internal_user_type'] ?? '') === 'student') ? 'selected' : ''; ?>>Student</option>
                                <option value="zppsu_employee" <?php echo (($_POST['internal_user_type'] ?? '') === 'zppsu_employee') ? 'selected' : ''; ?>>ZPPSU Employee / Faculty</option>
                                <option value="external_client" <?php echo (($_POST['internal_user_type'] ?? '') === 'external_client') ? 'selected' : ''; ?>>External Client</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="password">Password <span class="req">*</span></label>
                            <input type="password" id="password" name="password" placeholder="Min. 6 characters" required autocomplete="new-password">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span class="req">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required autocomplete="new-password">
                        </div>
                    </div>

                    <!-- Section 2 -->
                    <div class="section-header">
                        <span>02</span> ARTA Demographic Classification
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="client_type">Client Classification <span class="req">*</span></label>
                            <select id="client_type" name="client_type" required>
                                <option value="Citizen" <?php echo (($_POST['client_type'] ?? '') === 'Citizen') ? 'selected' : ''; ?>>Citizen</option>
                                <option value="Business" <?php echo (($_POST['client_type'] ?? '') === 'Business') ? 'selected' : ''; ?>>Business</option>
                                <option value="Government" <?php echo (($_POST['client_type'] ?? '') === 'Government') ? 'selected' : ''; ?>>Government (Employee/Agency)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="age_group">Age Group <span class="req">*</span></label>
                            <select id="age_group" name="age_group" required>
                                <option value="" disabled <?php echo empty($_POST['age_group']) ? 'selected' : ''; ?>>Select Age Group...</option>
                                <option value="19 or lower" <?php echo (($_POST['age_group'] ?? '') === '19 or lower') ? 'selected' : ''; ?>>19 or lower</option>
                                <option value="20-34" <?php echo (($_POST['age_group'] ?? '') === '20-34') ? 'selected' : ''; ?>>20-34</option>
                                <option value="35-49" <?php echo (($_POST['age_group'] ?? '') === '35-49') ? 'selected' : ''; ?>>35-49</option>
                                <option value="50-64" <?php echo (($_POST['age_group'] ?? '') === '50-64') ? 'selected' : ''; ?>>50-64</option>
                                <option value="65 or higher" <?php echo (($_POST['age_group'] ?? '') === '65 or higher') ? 'selected' : ''; ?>>65 or higher</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="sex">Sex <span class="req">*</span></label>
                            <select id="sex" name="sex" required>
                                <option value="" disabled <?php echo empty($_POST['sex']) ? 'selected' : ''; ?>>Select Sex...</option>
                                <option value="Male" <?php echo (($_POST['sex'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (($_POST['sex'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Prefer not to say" <?php echo (($_POST['sex'] ?? '') === 'Prefer not to say') ? 'selected' : ''; ?>>Prefer not to say</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="region">Region of Residence <span class="req">*</span></label>
                            <select id="region" name="region" required>
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

                    <button type="submit" class="btn-submit">Complete Account Registration</button>
                </form>

                <div class="links-stack">
                    <div>Already have an account? <a href="login.php">Sign in here</a></div>
                    <div><a href="index.php">← Back to Portal Home</a></div>
                </div>

                <div class="compliance-footnote">
                    In compliance with Republic Act No. 11032<br>
                    Ease of Doing Business and Efficient Government Service Delivery Act
                </div>

            </div>
        </div>

    </div>

</body>
</html>