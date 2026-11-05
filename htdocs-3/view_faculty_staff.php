<?php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_username'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
$host = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "feedback_system";
$conn = new mysqli($host, $dbusername, $dbpassword, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle delete request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    $delete_id = $conn->real_escape_string($_POST['delete_id']);
    $conn->query("DELETE FROM faculty_staff WHERE id_number = '$delete_id'");
}

// Handle edit request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_id'])) {
    $id = $conn->real_escape_string($_POST['edit_id']);
    $name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $office = $conn->real_escape_string($_POST['office']);

    $conn->query("UPDATE faculty_staff SET full_name='$name', email='$email', office='$office' WHERE id_number='$id'");
}

// Fetch faculty data
$result = $conn->query("SELECT id_number, full_name, email, office FROM faculty_staff ORDER BY full_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty / Staff</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: white;
            color: black;
            margin: 0;
            padding: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid black;
        }

        th {
            background-color: #800000;
            color: white;
        }

        tr:nth-child(even) {
            background-color: rgb(220, 220, 220);
        }

        tr:hover {
            background-color:rgba(213, 0, 0, 0.62);
            color: white;
        }

        .btn {
            padding: 2px 0px;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        .edit-btn {
            background-color: #007BFF;
        }

        .delete-btn {
            background-color: #DC3545;
        }

        .btn:hover {
            opacity: 0.8;
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
}.modal-content h2 {
    margin-bottom: 20px;
}
.modal-content label {
    display: block;
    margin: 10px 0 5px;
    text-align: left;
}
.modal-content input {
    width: 100%;
    padding: 8px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 5px;
}
.modal-btn {
    padding: 10px 15px;
    background-color: #008CBA;
    color: white;
    border: none;
    cursor: pointer;
    border-radius: 5px;
}
.modal-btn:hover {
    background-color: #0073a6;
}

        .close {
            float: right;
            font-size: 24px;
            cursor: pointer;
        }

        .close:hover {
            color: red;
        }

        input, button {
            width: 90%;
            margin: 10px 0;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            background-color: #800000;
            color: white;
            border: none;
        }

        button:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <h1>Faculty / Staff List</h1>
    <table>
        <thead>
            <tr>
                <th>ID Number</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Office</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id_number']) ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['office']) ?></td>
                    <td>
                        <button class="btn edit-btn" onclick="openEditModal('<?= $row['id_number'] ?>', '<?= htmlspecialchars($row['full_name']) ?>', '<?= htmlspecialchars($row['email']) ?>', '<?= htmlspecialchars($row['office']) ?>')">Edit</button>
                        <button class="btn delete-btn" onclick="openDeleteModal('<?= $row['id_number'] ?>')">Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <a href="dashboard.php">Back</a>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Faculty</h2>
            <form method="POST">
                <input type="hidden" name="edit_id" id="editId">
                <input type="text" name="full_name" id="editName" required>
                <input type="email" name="email" id="editEmail" required>
                <input type="text" name="office" id="editOffice" required>
                <button type="submit">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDeleteModal()">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this faculty?</p>
            <form method="POST">
                <input type="hidden" name="delete_id" id="deleteId">
                <button type="submit">Yes, Delete</button>
                <button type="button" onclick="closeDeleteModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, name, email, office) {
            document.getElementById("editId").value = id;
            document.getElementById("editName").value = name;
            document.getElementById("editEmail").value = email;
            document.getElementById("editOffice").value = office;
            document.getElementById("editModal").style.display = "flex";
        }

        function closeEditModal() {
            document.getElementById("editModal").style.display = "none";
        }

        function openDeleteModal(id) {
            document.getElementById("deleteId").value = id;
            document.getElementById("deleteModal").style.display = "flex";
        }

        function closeDeleteModal() {
            document.getElementById("deleteModal").style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target === document.getElementById("editModal")) closeEditModal();
            if (event.target === document.getElementById("deleteModal")) closeDeleteModal();
        };
    </script>
</body>
</html>

<?php $conn->close(); ?>
