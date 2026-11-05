<?php
session_start();

// Ensure the faculty or admin is logged in
if (isset($_SESSION['admin_username'])) {
    // Admin profile logic
    $username = $_SESSION['admin_username'];
    $user_type = 'admin';
} elseif (isset($_SESSION['faculty_username'])) {
    // Faculty profile logic
    $username = $_SESSION['faculty_username'];
    $user_type = 'faculty';
} else {
    echo "You must be logged in to view your profile.";
    exit();
}

// Database connection
$host = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "feedback_system";
$conn = new mysqli($host, $dbusername, $dbpassword, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query to fetch the data based on username
if ($user_type == 'admin') {
    $sql = "SELECT * FROM admins WHERE username = ?";
} elseif ($user_type == 'faculty') {
    $sql = "SELECT * FROM faculty_staff WHERE full_name = ?";  // Adjusted for faculty lookup
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

// Check if the query was successful
if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    // Set the profile picture or use default if not set
    $profile_pic = isset($user_data['profile_pic']) && !empty($user_data['profile_pic']) ? $user_data['profile_pic'] : 'default.jpg';
} else {
    echo "User not found.";
    exit();
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic'])) {
    $target_dir = "img/";  // Directory to save the uploaded file
    $target_file = $target_dir . basename($_FILES["profile_pic"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

    // Validate image
    if (getimagesize($_FILES["profile_pic"]["tmp_name"])) {
        if (in_array($imageFileType, $allowed_types)) {
            if ($_FILES["profile_pic"]["error"] === UPLOAD_ERR_OK) {
                // Move the uploaded file to the target directory
                if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
                    // Update the database with the new profile picture path
                    if ($user_type == 'admin') {
                        $update_sql = "UPDATE admins SET profile_pic = ? WHERE username = ?";
                    } elseif ($user_type == 'faculty') {
                        $update_sql = "UPDATE faculty_staff SET profile_pic = ? WHERE full_name = ?";
                    }

                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ss", $target_file, $username);
                    if ($update_stmt->execute()) {
                        // Refresh the page after successful upload
                        header("Location: profile.php");
                        exit();
                    } else {
                        echo "Error updating profile picture: " . $conn->error;
                    }
                } else {
                    echo "Sorry, there was an error uploading your file.";
                }
            } else {
                echo "File upload error: " . $_FILES["profile_pic"]["error"];
            }
        } else {
            echo "Please upload a valid image file (JPG, JPEG, PNG, GIF).";
        }
    } else {
        echo "Uploaded file is not a valid image.";
    }
}

// Handle username/email edit
if (isset($_POST['edit_username']) && isset($_POST['edit_email'])) {
    $new_username = $_POST['edit_username'];
    $new_email = $_POST['edit_email'];

    // Update the database with new username and email
    if ($user_type == 'admin') {
        $update_sql = "UPDATE admins SET username = ?, email = ? WHERE username = ?";
    } elseif ($user_type == 'faculty') {
        $update_sql = "UPDATE faculty_staff SET full_name = ?, email = ? WHERE full_name = ?";
    }

    $update_stmt = $conn->prepare($update_sql);
    if ($user_type == 'admin') {
        $update_stmt->bind_param("sss", $new_username, $new_email, $username);
    } else {
        $update_stmt->bind_param("sss", $new_username, $new_email, $username);
    }

    if ($update_stmt->execute()) {
        // Update session data after successful change
        $_SESSION['admin_username'] = $new_username;
        header("Location: profile.php");
        exit();
    } else {
        echo "Error updating profile: " . $conn->error;
    }
}

// Close the connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($user_type); ?> Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color:rgb(255, 255, 255);
            color: #black;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 70%;
            margin: 50px auto;
            background-color:rgb(176, 176, 176);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 8px rgba(90, 90, 90, 0.3);
        }

        h1 {
            text-align: center;
            color: #800000;
        }

        .profile-pic {
            display: block;
            margin: 0 auto 20px;
            border-radius: 50%;
            width: 150px;
            height: 150px;
            object-fit: cover;
        }

        .profile-info {
            margin-bottom: 20px;
        }

        .profile-info p {
            font-size: 16px;
            margin: 8px 0;
        }

        .profile-pic-container {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }

        .profile-pic:hover {
            opacity: 0.7;
            cursor: pointer;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(103, 103, 103, 0.4);
        }

        .modal-content {
            background-color:rgb(95, 95, 95);
            margin: 15% auto;
            padding: 20px;
            border-radius: 10px;
            width: 50%;
        }

        .modal-header {
            font-size: 24px;
            margin-bottom: 15px;
            color: #fff;
        }

        .btn {
            background-color: #800000;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn:hover {
            background-color:rgb(211, 0, 0);
        }

        input[type="file"] {
            margin-top: 10px;
            font-size: 16px;
            color: #fff;
            background-color: #800000;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
        }

        input[type="file"]:hover {
            background-color:rgb(234, 0, 0);
        }

        .edit-form {
            text-align: center;
            margin-top: 20px;
         
        }

        input[type="text"], input[type="email"] {
            padding: 10px;
            border-radius: 5px;
            width: 100%;
            margin-bottom: 10px;
            border: 1px solid #800000;
            background-color:rgb(82, 82, 82);
            color: #fff;
        }

        .submit-btn {
            background-color: #800000;
            padding: 10px 20px;
            color: white;
            border: none;
            border-radius: 5px;
        }

        .submit-btn:hover {
            background-color:rgb(223, 0, 0);
        }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="btn">← Back</a>
    <h1><?php echo ucfirst($user_type); ?> Profile</h1>

    <!-- Profile Picture -->
    <div class="profile-pic-container">
        <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onclick="openModal()">
    </div>

    <!-- Profile Information -->
    <div class="profile-info">
        <?php if (isset($_SESSION['admin_username'])): ?>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($user_data['username']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?></p>
        <?php elseif (isset($_SESSION['faculty_username'])): ?>
            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user_data['full_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?></p>
        <?php endif; ?>
    </div>

   <!-- Edit Profile Form -->
<div class="edit-form">
    <form action="profile.php" method="POST">
        <?php if ($user_type == 'admin'): ?>
            <input type="text" name="edit_username" value="<?php echo isset($user_data['username']) ? htmlspecialchars($user_data['username']) : ''; ?>" placeholder="Edit Username">
            <input type="email" name="edit_email" value="<?php echo isset($user_data['email']) ? htmlspecialchars($user_data['email']) : ''; ?>" placeholder="Edit Email">
        <?php else: ?>
            <!-- Faculty/Staff cannot edit their username or email -->
            <input type="text" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" disabled>
            <input type="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" disabled>
        <?php endif; ?>
        <input type="submit" value="Save Changes" class="submit-btn" <?php echo ($user_type == 'faculty') ? 'disabled' : ''; ?>>
    </form>
</div>


<!-- Profile Picture Modal -->
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <span onclick="closeModal()" style="color: white; font-size: 30px; cursor: pointer;">&times;</span>
        <h2 class="modal-header">Upload Profile Picture</h2>
        <form action="profile.php" method="POST" enctype="multipart/form-data">
            <input type="file" name="profile_pic" accept="image/*" required>
            <input type="submit" value="Upload Picture" class="submit-btn">
        </form>
    </div>
</div>

<script>
    // Open modal
    function openModal() {
        document.getElementById('uploadModal').style.display = "block";
    }

    // Close modal
    function closeModal() {
        document.getElementById('uploadModal').style.display = "none";
    }
</script>

</body>
</html>
