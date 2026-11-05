<?php
session_start();

// Database connection setup
$host = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "feedback_system";
$conn = new mysqli($host, $dbusername, $dbpassword, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle student update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_student'])) {
    $student_number = $_POST['student_number'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $user_type = $_POST['user_type']; // Ensure user_type is received from form

    // Prepare the SQL query
    $update_query = "UPDATE students SET full_name = ?, email = ?, user_type = ? WHERE student_number = ?";
    $stmt = $conn->prepare($update_query);

    if ($stmt) {
        $stmt->bind_param("ssss", $full_name, $email, $user_type, $student_number);

        if ($stmt->execute()) {
            echo "<script>alert('Student updated successfully!'); window.location='view_students.php';</script>";
        } else {
            echo "<script>alert('Failed to update student.');</script>";
        }

        $stmt->close();
    } else {
        echo "<script>alert('Error in preparing the statement.');</script>";
    }
}


// Handle student deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_student'])) {
    $student_number = $_POST['student_number'];

    $delete_query = "DELETE FROM students WHERE student_number = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("s", $student_number);

    if ($stmt->execute()) {
        echo "<script>alert('Student deleted successfully!'); window.location='view_students.php';</script>";
    } else {
        echo "<script>alert('Failed to delete student.');</script>";
    }
    $stmt->close();
}

// Search functionality
$search_query = "";
if (isset($_GET['search'])) {
    $search_query = $_GET['search'];
    $sql = "SELECT student_number, full_name, email, created_at FROM students 
            WHERE full_name LIKE ? OR student_number LIKE ? OR email LIKE ? 
            ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $search_like = "%" . $search_query . "%";
    $stmt->bind_param("sss", $search_like, $search_like, $search_like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT student_number, full_name, email, user_type, created_at FROM students ORDER BY created_at DESC";

    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students / Clients</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #ffffff;
            color: black;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 80%;
            margin: 25px auto;
            margin-left: 265px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid gray;
        }
        th {
            background-color: #800000;
            color: white;
        }
        tr:nth-child(even) {
            background-color: lightgray;
        }
        tr:hover {
            background-color: #800000;
            color: white;
        }
        .button {
            padding: 8px 12px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            margin: 5px;
        }
        .edit-btn {
            background-color: #008CBA;
            color: white;
        }
        .delete-btn {
            background-color: #f44336;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            width: 40%;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        .modal-btn {
            padding: 10px 15px;
            background-color: #008CBA;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }
        .close {
            float: right;
            cursor: pointer;
        }
        .sidebar {
            width: 220px;
            background-color: #800000;
            padding: 20px;
            position: fixed;
            height: 100%;
            top: 0;
        }
        .sidebar h2 {
            color: white;
        }
        .sidebar a {
            text-decoration: none;
            color: white;
            padding: 10px 0;
            display: block;
            border-bottom: 1px solid white;
        }
        form {
        display: flex;
        justify-content: left;
        margin-top: 10px;
    }

    /* Input field styling */
    input[type="text"] {
        width: 300px;
        padding: 8px;
        border: 2px solid #ccc;
        border-radius: 5px;
        font-size: 16px;
        transition: border-color 0.3s ease;
    }

    /* Change border color on focus */
    input[type="text"]:focus {
        border-color: #007BFF;
        outline: none;
    }
    </style>
</head>
<body>
<div class="sidebar">
    <h1>Admin/ Faculty Panel</h1>
    <h2>  <?php echo htmlspecialchars($_SESSION['admin_username'] ?? $_SESSION['faculty_username'] ?? 'Guest'); ?></h2>

    <a href="results.php">Overall Feedback</a>
    <a href="dashboard.php">Individual Feedback</a>
    <a href="setup.php">Setup</a>
    <a href="history.php">History</a>
    <a href="track_alert.php">Failed Login Attempts</a>
    <a href="profile.php">Account</a>
    <a href="logout.php">Log Out</a>
</div>

<div class="container">
    <h1>Students / Clients</h1>
    <form method="GET">
        <input type="text" name="search" placeholder="Search" value="<?php echo htmlspecialchars($search_query); ?>">
    </form>

    <?php if ($result->num_rows > 0): ?>
        <table>
        <thead>
    <tr>
        <th>Student Number</th>
        <th>Full Name</th>
        <th>Email / Contact No.</th>
        <th>User Type</th> <!-- Added column -->
        <th>Created At</th>
        <th>Actions</th>
    </tr>
</thead>

            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                    <td><?php echo htmlspecialchars($row['student_number']); ?></td>
<td><?php echo htmlspecialchars($row['full_name']); ?></td>
<td><?php echo htmlspecialchars($row['email']); ?></td>
<td><?php echo htmlspecialchars($row['user_type']); ?></td> <!-- Display user type -->
<td><?php echo htmlspecialchars($row['created_at']); ?></td>

                        <td>
                            <button class="button edit-btn" onclick="openEditModal('<?php echo $row['student_number']; ?>', '<?php echo $row['full_name']; ?>', '<?php echo $row['email']; ?>')">Edit</button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="student_number" value="<?php echo $row['student_number']; ?>">
                                <button type="submit" name="delete_student" class="button delete-btn" onclick="return confirm('Are you sure you want to delete this student?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No students found.</p>
    <?php endif; ?>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editModal')">&times;</span>
        <h2>Edit Student</h2>
        <form method="POST">
            <input type="hidden" name="student_number" id="edit_student_number">

            <label for="edit_full_name">Name:</label>
            <input type="text" name="full_name" id="edit_full_name" required>

            <label for="edit_email">Email / Contact No.:</label>
            <input type="text" name="email" id="edit_email" required>


            <button type="submit" name="update_student" class="modal-btn">Update</button>
        </form>
    </div>
</div>

<script>
function openEditModal(studentNumber, fullName, email) {
    document.getElementById("edit_student_number").value = studentNumber;
    document.getElementById("edit_full_name").value = fullName;
    document.getElementById("edit_email").value = email;
    document.getElementById("editModal").style.display = "flex";
}
function closeModal(id) {
    document.getElementById(id).style.display = "none";
}
</script>

</body>
</html>

<?php $conn->close(); ?>
