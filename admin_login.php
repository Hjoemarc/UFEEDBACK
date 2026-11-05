<?php
session_start();
// ZPPSU FeedBackSystem in bisaya programming language
// Database connection
$host = "localhost";
$dbusername = "root"; 
$dbpassword = ""; 
$dbname = "feedback_system";
$conn = new mysqli($host, $dbusername, $dbpassword, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize attempt count if not already set
if (!isset($_SESSION['attempts'])) {
    $_SESSION['attempts'] = 0;
}

// Check if the user is locked out for 5 seconds
$lockedOut = false;
if (isset($_SESSION['lock_time']) && time() - $_SESSION['lock_time'] < 3600) {
    $lockedOut = true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !$lockedOut) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Verify the entered password with the hashed password in the database
        if (password_verify($password, $row['password'])) {
            // Password is correct
            $_SESSION['admin_username'] = $username; 
            session_regenerate_id(true); // Regenerate session ID for security
            $_SESSION['attempts'] = 0; // Reset failed attempts on successful login
            header("Location: dashboard.php"); // Redirect to dashboard
            exit;
        } else {
            // Incorrect password
            $_SESSION['attempts']++;
            if ($_SESSION['attempts'] >= 7) {
                $_SESSION['lock_time'] = time(); // Lock out for 1 hour
                $error_message = "Too many failed attempts. Please try again after 1 hour.";
                $lockedOut = true; // Trigger modal display
                
                // Log the suspicious activity
                $ipAddress = $_SERVER['REMOTE_ADDR'];  // Get the user's IP address
                $timestamp = time();  // Get the current time
                $reason = "Too many failed login attempts for username: $username";

                $stmt = $conn->prepare("INSERT INTO suspicious_activity (username, ip_address, reason, timestamp) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $username, $ipAddress, $reason, $timestamp);
                $stmt->execute();
            } else {
                $error_message = "Invalid username or password.";
            }
        }
    } else {
        // Username not found
        $_SESSION['attempts']++;
        $error_message = "Invalid username or password.";
        
        // Log the suspicious activity (invalid username)
        $ipAddress = $_SERVER['REMOTE_ADDR'];  // Get the user's IP address
        $timestamp = time();  // Get the current time
        $reason = "Invalid username: $username";

        $stmt = $conn->prepare("INSERT INTO suspicious_activity (username, ip_address, reason, timestamp) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $username, $ipAddress, $reason, $timestamp);
        $stmt->execute();
    }


    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        body {
            background-color: rgb(195, 195, 195);
            color: rgb(255, 255, 255);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: Arial, sans-serif;
        }

        .container {
            background-color: rgb(80, 80, 80);
            padding: 20px;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        input {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
            border: none;
            border-radius: 5px;
            background-color: rgb(255, 255, 255);
            color: rgb(0, 0, 0);
        }

        input:focus {
            outline: none;
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #800000;
            border: none;
            border-radius: 5px;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background-color: rgb(193, 0, 0);
        }

        .link {
            color: #800000;
            text-decoration: none;
        }

        .link:hover {
            text-decoration: underline;
            color: rgb(214, 0, 0);
        }

        .error {
            color: #ff5252;
            margin-bottom: 10px;
        }

        .signup-link {
            margin-top: 15px;
        }

        .faculty-link {
            margin-top: 10px;
        }

        /* Modal styles */
        .modal {
            display: none; /* Initially hidden */
            justify-content: center;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
        }

        .modal-content {
            background-color: #fff;
            color: #333;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 400px;
            text-align: center;
        }

        /* Show modal when triggered */
        .modal.show {
            display: flex;
        }

    </style>
</head>
<body>

    <div class="container">
        <h2>Admin Login</h2>
        
        <!-- Login Form -->
        <form method="POST" action="admin_login.php">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Log In</button>
        </form>
        
        <?php if (isset($error_message)) { ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php } ?>

        <p class="signup-link">
            Don't have an account? <a href="admin_signup.php" class="link">Sign up</a>
        </p>
        <p class="faculty-link">
            Are you a faculty staff? <a href="faculty_login.php" class="link">Log in or Sign up here</a>
        </p>

    </div>

    <!-- Modal for Lockout Warning -->
    <div id="lockModal" class="modal <?php echo $lockedOut ? 'show' : ''; ?>">
        <div class="modal-content">
            <p>You have made too many failed attempts. Please wait a moment and try again.</p>
        </div>
    </div>

    <script>
        // Automatically close the modal after 5 seconds if it's visible
        if (document.getElementById('lockModal').classList.contains('show')) {
            setTimeout(function() {
                document.getElementById('lockModal').classList.remove('show');
            }, 5000); // Close after 5 seconds
        }
    </script>

</body>
</html>
