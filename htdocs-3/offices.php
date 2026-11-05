<?php
session_start();
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

// Handle Add Office
if (isset($_POST['add_office'])) {
    $office_name = $conn->real_escape_string($_POST['office_name']);
    $college_name = $conn->real_escape_string($_POST['colleges']);
    $office_type = isset($_POST['office_type']) ? $conn->real_escape_string($_POST['office_type']) : '';

    // Ensure office_type is not empty
    if (empty($office_type)) {
        echo "<script>alert('Please select an office type.'); window.location.href='offices.php';</script>";
        exit();
    }

    $add_sql = "INSERT INTO offices (office_name, colleges, office_type, created_at) 
                VALUES ('$office_name', '$college_name', '$office_type', NOW())";
    if ($conn->query($add_sql) === TRUE) {
        header("Location: offices.php");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}

// Handle Delete Office
if (isset($_GET['delete'])) {
    $office_name = $_GET['delete'];
    $delete_sql = "DELETE FROM offices WHERE office_name = '$office_name'";
    if ($conn->query($delete_sql) === TRUE) {
        header("Location: offices.php");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}

// Handle Edit Office
if (isset($_POST['edit_office'])) {
    $original_name = $conn->real_escape_string($_POST['original_name']);
    $updated_name = $conn->real_escape_string($_POST['updated_name']);
    $updated_college = $conn->real_escape_string($_POST['updated_college']);
    $updated_type = $conn->real_escape_string($_POST['updated_office_type']);

    $edit_sql = "UPDATE offices 
                 SET office_name = '$updated_name', colleges = '$updated_college', office_type = '$updated_type' 
                 WHERE office_name = '$original_name'";
    if ($conn->query($edit_sql) === TRUE) {
        header("Location: offices.php");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}

// Handle Add College
if (isset($_POST['add_college'])) {
    $college_name = $conn->real_escape_string($_POST['college_name']);

    $add_college_sql = "INSERT INTO colleges (name) VALUES ('$college_name')";
    if ($conn->query($add_college_sql) === TRUE) {
        header("Location: offices.php");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Modify the SQL query to filter by office name or college
$sql = "SELECT office_name, colleges, office_type, created_at 
        FROM offices 
        WHERE office_name LIKE '%$search%' OR colleges LIKE '%$search%' 
        ORDER BY created_at DESC";
$result = $conn->query($sql);

// Fetch Colleges for Dropdown
$colleges_sql = "SELECT * FROM colleges ORDER BY name";
$colleges_result = $conn->query($colleges_sql);

// Check for query errors
if (!$result || !$colleges_result) {
    die("Query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Offices</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color:rgb(255, 255, 255);
        color:rgb(0, 0, 0);
        padding: 0px;
    }
    table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    
    
}
th, td {
    padding: 5px;
    border: 1px solid #444;
    
}
th {
    background-color: #800000;
    color: #ffffff;
}
tr:nth-child(even) {
    background-color: rgb(156, 156, 156);
}
tr:hover {
    background-color: rgba(195, 18, 5, 0.7);
}

    .btn {
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        text-align: center;
        display: inline-block;
        transition: background-color 0.3s ease;
        margin: 5px 0;
    }
    .btn.add {
        background-color: #800000;
        color: white;
    }
    .btn.delete {
        background-color: #dc3545;
        color: white;
        
    }
    .btn.edit {
        background-color: #ffc107;
        color: black;
    }
    .btn:hover {
        opacity: 0.8;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(129, 129, 129, 0.4);
        padding-top: 60px;
    }

    .modal-content {
        background-color: #333;
        margin: 5% auto;
        padding: 30px;
        border-radius: 8px;
        border: 1px solid #888;
        width: 80%;
        max-width: 500px;
        color: white;
        box-shadow: 0 4px 8px rgba(126, 126, 126, 0.2);
    }

    /* Modal Close Button */
    .close {
        color: #aaa;
        float: right;
        font-size: 30px;
        font-weight: bold;
    }

    .close:hover,
    .close:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }

    /* Form Input Styles inside Modal */
    .modal-content input[type="text"],
    .modal-content input[type="email"] {
        width: 100%;
        padding: 12px;
        margin: 10px 0;
        border-radius: 5px;
        border: 1px solid #444;
        background-color:rgb(106, 106, 106);
        color: #fff;
    }

    .modal-content input[type="text"]:focus,
    .modal-content input[type="email"]:focus {
        outline: none;
        border-color: #800000;
        background-color: #333;
    }

    /* Button inside modal */
    .modal-content button {
        padding: 12px 20px;
        background-color: #800000;
        color: white;
        border-radius: 5px;
        border: none;
        cursor: pointer;
        width: 100%;
        transition: background-color 0.3s ease;
    }

    .modal-content button:hover {
        background-color: #800000;
    }
</style>

</head>
<body>
<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
    <form method="GET" action="offices.php">
        <input type="text" name="search" placeholder="Search Offices..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" style="padding: 10px; width: 300px; border-radius: 4px; border: 1px solid #444; background-color:rgb(255, 255, 255); color: black;">
        <button type="submit" style="padding: 10px 20px; background-color: #800000; color: white; border-radius: 4px; cursor: pointer;">Search</button>
    </form>




</div>

<button class="btn add" onclick="window.location.href='setup.php'">← Back</button>

    <h1>Manage Offices</h1>
    <button class="btn add" onclick="openAddOfficeModal()">Add New Office</button>
    <button class="btn add" onclick="openAddCollegeModal()">Add New College / Description</button>

    <div style="max-height: 520px; overflow: auto;">
    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
    <thead>
        <tr>
            <th>Office Name</th>
            <th>College / Description</th>  <!--college table in db-->
            <th>Type Of Office</th>
            <th>Date Created</th>
            <th>Update/Delete</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['office_name']); ?></td>
                <td><?php echo htmlspecialchars($row['colleges']); ?></td>
                <td><?php echo htmlspecialchars($row['office_type']); ?></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td>
                    <button type="button" class="btn edit" onclick="openEditModal('<?php echo htmlspecialchars($row['office_name']); ?>', '<?php echo htmlspecialchars($row['colleges']); ?>')">Edit</button>
                    <a href="?delete=<?php echo urlencode($row['office_name']); ?>" class="btn delete">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>


    
<!-- Add Office Modal -->
<div id="add-office-modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddOfficeModal()">&times;</span>
        <h2>Add New Office</h2>
        <form method="POST">
            <label for="office_name">Name:</label>
            <input type="text" id="office_name" name="office_name" required>

            <label for="colleges">Select If College or Office:</label>
            <select id="colleges" name="colleges" required>
                <option value=""></option>
                <?php
                $colleges_result->data_seek(0);
                while ($college = $colleges_result->fetch_assoc()):
                ?>
                    <option value="<?php echo $college['name']; ?>"><?php echo $college['name']; ?></option>
                <?php endwhile; ?>
                <option value="Office">Office</option>
            </select>

            <label for="office_type">Office Type:</label>
            <select id="office_type" name="office_type" required>
                <option value="">-- Select Office Type --</option>
                <option value="Administrative">Administrative</option>
                <option value="Academic">Academic</option>
            </select>

            <button type="submit" name="add_office">Add Office</button>
        </form>
    </div>
</div>


    <!-- Edit Office Modal -->
<div id="edit-office-modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2>Edit Office</h2>
        <form method="POST">
            <input type="hidden" id="original_name" name="original_name">

            <label for="updated_name">New Office Name:</label>
            <input type="text" id="updated_name" name="updated_name" required>

            <label for="updated_college">New College/Office:</label>
            <select id="updated_college" name="updated_college" required>
                <?php
                // Reset colleges result for dropdown
                $colleges_result->data_seek(0); 
                while ($college = $colleges_result->fetch_assoc()):
                ?>
                    <option value="<?php echo $college['name']; ?>"><?php echo $college['name']; ?></option>
                <?php endwhile; ?>
                <option value="Office">Office</option>
            </select>

            <label for="updated_office_type">Office Type:</label>
            <select id="updated_office_type" name="updated_office_type" required>
                <option value="">-- Select Office Type --</option>
                <option value="Administrative">Administrative</option>
                <option value="Academic">Academic</option>
            </select>

            <button type="submit" name="edit_office">Update Office</button>
        </form>
    </div>
</div>


    <!-- Add College Modal -->
    <div id="add-college-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddCollegeModal()">&times;</span>
            <h2>Add New College / Description</h2>
            <form method="POST">
                <label for="college_name">Name:</label>
                <input type="text" id="college_name" name="college_name" required>
                <button type="submit" name="add_college">College</button>
            </form>
        </div>
    </div>

    <script>
    // Modal functions
    function openAddOfficeModal() {
        document.getElementById("add-office-modal").style.display = "block";
    }

    function closeAddOfficeModal() {
        document.getElementById("add-office-modal").style.display = "none";
    }

    function openEditModal(officeName, college) {
        document.getElementById("edit-office-modal").style.display = "block";
        document.getElementById("original_name").value = officeName;
        document.getElementById("updated_name").value = officeName;
        document.getElementById("updated_college").value = college;
    }

    function closeEditModal() {
        document.getElementById("edit-office-modal").style.display = "none";
    }

    function openAddCollegeModal() {
        document.getElementById("add-college-modal").style.display = "block";
    }

    function closeAddCollegeModal() {
        document.getElementById("add-college-modal").style.display = "none";
    }
    </script>
</body>
</html>
