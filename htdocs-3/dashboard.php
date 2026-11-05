<?php
include('refresh.php');
session_start();

// Check if the user is logged in as an admin or faculty
$is_admin = isset($_SESSION['admin_username']);
$is_faculty_staff = isset($_SESSION['faculty_username']);  // Checking if faculty is logged in

// Redirect to login page if neither admin nor faculty is logged in
if (!$is_admin && !$is_faculty_staff) {
    header("Location: faculty_login.php"); // Redirect to faculty login if not logged in
    exit;
}

// Database connection
$host = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "feedback_system";
$conn = new mysqli($host, $dbusername, $dbpassword, $dbname);

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filters
$semester = isset($_GET['semester']) ? $_GET['semester'] : 'all';
$month = isset($_GET['month']) ? $_GET['month'] : 'all';
$week = isset($_GET['week']) ? $_GET['week'] : 'all';
$year = isset($_GET['year']) ? $_GET['year'] : 'all';
$page = isset($_GET['page']) ? $_GET['page'] : 1;

$filter_condition = '';

// Sanitize and build the filter conditions
if ($semester === '1st') {
    $filter_condition .= " AND MONTH(feedback.created_at) BETWEEN 8 AND 12"; // 1st Semester
} elseif ($semester === '2nd') {
    $filter_condition .= " AND MONTH(feedback.created_at) BETWEEN 1 AND 5"; // 2nd Semester
}

if ($month !== 'all') {
    $filter_condition .= " AND MONTH(feedback.created_at) = '$month'";
}

if ($week !== 'all') {
    $filter_condition .= " AND WEEK(feedback.created_at) = '$week'";
}

if ($year !== 'all') {
    // If a specific year is selected, use it directly in the condition
    $filter_condition .= " AND YEAR(feedback.created_at) = '$year'";
} else {
    // If 'all' is selected, allow feedback from any year from 2024 to 9999
    $filter_condition .= " AND YEAR(feedback.created_at) BETWEEN 2024 AND 9999";
}

if (isset($_GET['school_year'])) {
    $_SESSION['school_year'] = $_GET['school_year'];
} elseif (!isset($_SESSION['school_year'])) {
    $_SESSION['school_year'] = 'all'; // Default value
}

// Use the session value
$school_year = $_SESSION['school_year'];

$filter_condition = ""; // Initialize filter condition

if ($school_year !== 'all') {
    $years = explode('-', $school_year);
    if (count($years) === 2) {
        $start_year = (int)$years[0];
        $end_year = min((int)$years[1], 9999); // Prevent exceeding 9999

        // Ensure filtering within school year (Aug-July format)
        $filter_condition .= " AND (
            (YEAR(feedback.created_at) = $start_year AND MONTH(feedback.created_at) >= 8) OR 
            (YEAR(feedback.created_at) = $end_year AND MONTH(feedback.created_at) <= 7)
        )";
    }
} else {
    // Allow feedback from any school year
    $filter_condition .= " AND (
        (YEAR(feedback.created_at) BETWEEN 2024 AND 9999) AND 
        (MONTH(feedback.created_at) >= 8 OR MONTH(feedback.created_at) <= 7)
    )";
}

// Include in SQL query
$sql = "SELECT * FROM feedback WHERE 1 $filter_condition ORDER BY feedback.created_at DESC";



$feedbacks_per_page = 15;
$records_per_page = 15; // Number of records per page
$offset = ($page - 1) * $records_per_page;

// SQL to count total feedback records
$count_sql = "SELECT COUNT(*) AS total FROM feedback WHERE 1=1 $filter_condition";
$count_result = $conn->query($count_sql);
$row = $count_result->fetch_assoc();
$totalFeedbacks = $row['total'];

// Calculate the total number of pages
$totalPages = ceil($totalFeedbacks / $feedbacks_per_page);

// Query to fetch feedback data
$sql = "SELECT feedback.student_number, offices.office_name, offices.colleges, 
                (feedback.rating1 + feedback.rating2 + feedback.rating3 + feedback.rating4 + feedback.rating5) AS total_score,
                feedback.comments, feedback.created_at 
        FROM feedback 
        INNER JOIN offices ON feedback.office_name = offices.office_name
        WHERE 1=1 $filter_condition";  // Ensuring the condition starts properly

// If the user is a faculty member, filter by the faculty's office
if ($is_faculty_staff) {
    // Fetch the faculty's office using the 'office' column
    $faculty_sql = "SELECT office FROM faculty_staff WHERE full_name = ?";
    $faculty_stmt = $conn->prepare($faculty_sql);
    $faculty_stmt->bind_param("s", $_SESSION['faculty_username']);
    
    if (!$faculty_stmt->execute()) {
        die("Error fetching faculty office: " . $faculty_stmt->error);
    }

    $faculty_result = $faculty_stmt->get_result();
    
    if ($faculty_result && $faculty_result->num_rows > 0) {
        $faculty_info = $faculty_result->fetch_assoc();
        $faculty_office = $faculty_info['office'];

        // Add office filter to the feedback query for faculty
        $sql .= " AND feedback.office_name = '$faculty_office'"; // Append the filter
    } else {
        die("Faculty data not found for username: " . $_SESSION['faculty_username']);
    }
}

// Continue with the query and the limit/offset
$sql .= " ORDER BY feedback.created_at DESC LIMIT $feedbacks_per_page OFFSET $offset";

// Prepare the statement
$stmt = $conn->prepare($sql);

// Check if the query execution was successful
if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}

$result = $stmt->get_result();

// Check if result is valid before calling fetch_assoc
if ($result === false) {
    die("Error fetching data: " . $stmt->error);
}

// Fetch vote counts by office
$votes_sql = "SELECT office_name, COUNT(*) AS votes FROM feedback WHERE 1=1 $filter_condition GROUP BY office_name";
$votes_result = $conn->query($votes_sql);

// Check if the result is valid
if ($votes_result === false) {
    die("Error fetching votes data: " . $conn->error);
}

$votes_data = [];
while ($row = $votes_result->fetch_assoc()) {
    $votes_data[] = $row;
}

// Function to generate a random color
function generateRandomColor() {
    return sprintf('#%02X%02X%02X', mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
}

// Initialize office names (or you can dynamically fetch from database)
$offices = [
    "Office A",
    "Office B",
    "Office C",
    "Office D",
    "Office E",
    "Office F"
];

// Generate unique random color for each office
$office_colors = [];
foreach ($offices as $office) {
    $office_colors[$office] = generateRandomColor();
}

// Handle feedback closure
if (isset($_POST['confirm_close'])) {
    $insert_sql = "INSERT INTO close_history (student_number, office_name, colleges, rating1, rating2, rating3, rating4, rating5, total_score, comments, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    // Bind and execute your close feedback query here
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin/ Faculty Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <style>
        body {
            display: flex;
            margin: 0;
            font-family: Arial, sans-serif;
            background-color:rgb(255, 255, 255);
            color: #ffffff;
            height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: #800000;
            padding: 20px;
            box-shadow: 5px 0 5px rgba(189, 189, 189, 0.5);
            display: flex;
            flex-direction: column;
        }

        .sidebar h2 {
            margin: 0 0 20px;
            font-size: 1.5rem;
            color:rgb(0, 0, 0);
        }

        .sidebar a {
            text-decoration: none;
            color: #ffffff;
            padding: 10px 0;
            border-bottom: 1px solid rgb(252, 252, 252);
            margin-bottom: 10px;
            display: block;
            transition: 0.3s;
        }

        .sidebar a:hover {
            color:rgb(234, 0, 0);
        }

        .content {
            flex: 1;
            display: flex;
            flex-direction: row;
            padding: 20px;
            overflow-y: auto;
        }

        .content h1 {
            margin-bottom: 20px;
        }

        .left {
            flex: 2;
            margin-right: 20px;
        }

        .right {
            flex: 1;
            background-color:rgb(160, 160, 160);
            padding: 20px;
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color:rgb(239, 239, 239);
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid rgb(174, 0, 0);
        }

        th {
            background-color:rgb(203, 0, 0);
            color:rgb(0, 0, 0);
        }

        tr:nth-child(even) {
            background-color:rgb(231, 231, 231);
        }

        tr:hover {
            background-color:rgba(189, 0, 0, 0.63);
        }

        .logout-btn {
            margin-top: auto;
            text-align: center;
        }

        .logout-btn a {
            color: #ff5252;
        }

        .chart-container {
            width: 100%;
            height: 400px;
            position: relative;
        }

        .table-chart {
            margin-top: 20px;
        }

        .table-chart table {
            width: 50%;
            text-align: center;
        }

        .table-chart th, .table-chart td {
            padding: 5px;
        }

        #printButton {
            display: block;
            margin-top: 10px;
            background-color: #800000;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
        }

       /* Style for print */
@media print {
    body * {
        visibility: hidden;
    }

    #printContent, #printContent * {
        visibility: visible;
    }

    #printContent {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }

    /* Optional: Adjust chart styling for printing */
    .chart-container {
        visibility: visible;
        display: block;
        margin-top: 20px; /* Add margin to space out the chart */
    }

    canvas {
        width: 100% !important;
        height: auto !important;
    }

    /* Ensure the table is visible too */
    table {
        visibility: visible;
        display: block;
        margin-top: 20px; /* Add margin to space out from other content */
    }

    /* Optional: Adjust table for print */
    table th, table td {
        padding: 5px;
        border: 1px solid #000;
    }
}

        /* Modal styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(151, 151, 151, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    width: 400px;
    box-shadow: 0 2px 10px rgba(178, 178, 178, 0.2);
}

button {
    padding: 10px 20px;
    margin: 10px;
    cursor: pointer;
    border: none;
    border-radius: 5px;
}

button.btn-confirm {
    background-color: red;
    color: white;
}

button:hover {
    background-color:rgb(172, 0, 0);
}

button:focus {
    outline: none;
}

/* Close Feedback button */
.close-feedback-btn button {
    background-color: red;
    color: white;
}

.close-feedback-btn button:hover {
    background-color:rgb(187, 1, 1);
}

/* Container for the pagination */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
}

/* Styling for the pagination buttons */
.pagination-button {
    background-color:rgb(173, 0, 0);
    color: #fff;
    padding: 10px 15px;
    border-radius: 5px;
    text-decoration: none;
    display: flex;
    align-items: center;
    font-size: 14px;
    transition: background-color 0.3s;
}

.pagination-button:hover {
    background-color:rgb(234, 0, 0);
}

/* Styling for the "Previous" button */
.prev-button {
    justify-content: flex-start;
}

/* Styling for the "Next" button */
.next-button {
    justify-content: flex-end;
}

/* Page info in the middle */
.pagination-info {
    font-size: 16px;
    font-weight: bold;
    text-align: center;
}

/* Add icons for the arrows */
.fas {
    margin: 0 5px;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .pagination-button {
        font-size: 12px;
        padding: 8px 12px;
    }

    .pagination-info {
        font-size: 14px;
    }
}

/* Style for student number and office data */
.student-number a,
.office-name a {
    color: black; /* Set text color to white */
}

/* Style for the notification "!" */


/* Office name cell should be relative to position the notification "!" */
.office-name {
    position: relative;
}

/* Optional hover effect */
.office-name a:hover {
    color:rgb(255, 255, 255); /* Change to blue on hover */
}
.student-number a:hover {
    color:rgb(255, 255, 255); /* Change to blue on hover */
}


body {
    font-family: Trebuchet MS;
    background-color: rgb(255, 255, 255);
    color: rgb(0, 0, 0);
    text-shadow: 0px 0px 1px black; /* Small black text shadow */
}

button:hover {
        transform: scale(1.1);
        transition: transform 0.5s;
    }

    #transferModal h2 {
        text-shadow: 0 0 10px rgb(46, 0, 63);
        
    }

    #transferModal {
        animation: neon-glow 1.0s infinite alternate;
        
    }

    @keyframes neon-glow {
        from {
            box-shadow: 0 0 10px rgb(136, 0, 160);
        }
        to {
            box-shadow: 0 0 20px rgb(98, 0, 98), 0 0 30px rgb(74, 5, 74);
        }
    }
    .content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    margin: 0px;
}

form {
    display: flex;
    justify-content: flex-start; /* Ensures items align to the left */
    gap: 20px; /* Adds spacing between the filter elements */
    flex-wrap: wrap; /* Allows wrapping for smaller screens */
}

form label {
    font-size: 16px;
    margin-right: 5px;
}

form select {
    padding: 5px 10px;
    margin-bottom: 10px;
    width: 70px; /* Adjust width to better suit the form layout */
    border-radius: 5px; /* Rounded corners for a modern look */
    border: 1px solid #ccc; /* Light border for separation */
    background-color:rgb(204, 204, 204); /* Light background for better contrast */
}

form select:focus {
    outline: none;
    border-color:rgb(192, 0, 0); /* Highlight the border when the select is focused */
    background-color: #e0e0e0; /* Slight background change on focus */
}

form div {
    display: flex;
    align-items: center;
}

form div:last-child {
    margin-bottom: 0;
}


    </style>
     <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
</head>
<body>


<div class="sidebar">
    <h1>Admin/ Faculty Panel</h1>
    <h2>  <?php echo htmlspecialchars($_SESSION['admin_username'] ?? $_SESSION['faculty_username'] ?? 'Guest'); ?></h2>
    <a href="results.php">Overall Feedback</a>
    <a href="dashboard.php">Individual Feedback</a>
    <?php if ($is_admin): ?>
    <a href="setup.php">Setup</a>
    <?php endif; ?>
    <a href="qr_code.php">QR Code</a>
    <a href="history.php">History</a>
    <?php if ($is_admin): ?>
    <a href="track_alert.php">Failed Login Attempts</a>
   
    <?php endif; ?>
    <a href="profile.php">Account</a>
    <a href="logout.php">Log Out</a>
</div>



    <div class="content">
        <div class="left">
            <h1>Feedback Data</h1>
            
            <form method="GET" action="">
    <label for="semester">Semester:</label>
    <select name="semester" id="semester" onchange="this.form.submit()">
        <option value="all" <?php if ($semester === 'all') echo 'selected'; ?>>All</option>
        <option value="1st" <?php if ($semester === '1st') echo 'selected'; ?>>1st Semester</option>
        <option value="2nd" <?php if ($semester === '2nd') echo 'selected'; ?>>2nd Semester</option>
    </select>
    <br><br>

    <label for="month">Month:</label>
    <select name="month" id="month" onchange="this.form.submit()">
        <option value="all" <?php if ($month === 'all') echo 'selected'; ?>>All</option>
        <?php 
        for ($m = 1; $m <= 12; $m++) {
            $monthName = date("F", mktime(0, 0, 0, $m, 1));
            echo "<option value='$m' " . ($month == $m ? 'selected' : '') . ">$monthName</option>";
        }
        ?>
    </select>
    <br><br>

    <label for="week">Week:</label>
    <select name="week" id="week" onchange="this.form.submit()">
        <option value="all" <?php if ($week === 'all') echo 'selected'; ?>>All</option>
        <?php 
        for ($w = 1; $w <= 4; $w++) {
            echo "<option value='$w' " . ($week == $w ? 'selected' : '') . ">Week $w</option>";
        }
        ?>
    </select>
    <br><br>

    <label for="year">Year:</label>
    <select name="year" id="year" onchange="this.form.submit()">
        <option value="all" <?php if (!isset($_GET['year']) || $_GET['year'] === 'all') echo 'selected'; ?>>All</option>
        <?php
        $current_year = date("Y");
        for ($year = 2024; $year <= $current_year; $year++) {
            echo "<option value='$year' " . (isset($_GET['year']) && $_GET['year'] == $year ? 'selected' : '') . ">$year</option>";
        }
        ?>
    </select>
    <br><br>

    <label for="school_year">School Year:</label>
<select name="school_year" id="school_year" onchange="this.form.submit()">
    <option value="all" <?php if ($school_year === 'all') echo 'selected'; ?>>All</option>
    <?php
    $start_year = 2024;
    $end_year = 9999;

    for ($year = $start_year; $year <= $end_year; $year++) {
        $schoolYear = "$year-" . ($year + 1);
        echo "<option value='$schoolYear' " . ($school_year == $schoolYear ? 'selected' : '') . ">$schoolYear</option>";
    }
    ?>
</select>

</form>


<label for="searchInput">Search:</label>
<input type="text" id="searchInput" placeholder="Search by College." onkeyup="filterTable()">


<div class="pagination">
    <!-- Previous Button with Left Arrow -->
    <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>&year=<?php echo isset($_GET['year']) ? $_GET['year'] : 'all'; ?>&semester=<?php echo isset($_GET['semester']) ? $_GET['semester'] : ''; ?>&month=<?php echo isset($_GET['month']) ? $_GET['month'] : ''; ?>&week=<?php echo isset($_GET['week']) ? $_GET['week'] : ''; ?>" class="pagination-button prev-button">
            <i class="fas fa-chevron-left"></i> <!-- Left Arrow Icon -->
            Previous
        </a>
    <?php endif; ?>

    <!-- Page Info -->
    <div class="pagination-info">
        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
    </div>

    <!-- Next Button with Right Arrow -->
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page + 1; ?>&year=<?php echo isset($_GET['year']) ? $_GET['year'] : 'all'; ?>&semester=<?php echo isset($_GET['semester']) ? $_GET['semester'] : ''; ?>&month=<?php echo isset($_GET['month']) ? $_GET['month'] : ''; ?>&week=<?php echo isset($_GET['week']) ? $_GET['week'] : ''; ?>" class="pagination-button next-button">
            Next <i class="fas fa-chevron-right"></i> <!-- Right Arrow Icon -->
        </a>
    <?php endif; ?>
</div>





<!-- Modal for Unauthorized Access -->
<div id="modal" class="modal" style="display:none;">
    <div class="modal-content">
        <p>Only admins can view this page.</p>
        <button class="btn-confirm" onclick="closeModal()">Close</button>
    </div>
</div>

<?php if ($is_admin): ?>
    <div style="display: flex; justify-content: flex-end; margin-bottom: 3px;">
        <button onclick="openModal()" 
            style="padding: 10px 15px; background-color: red; color: white; 
                   border: none; border-radius: 5px; cursor: pointer; 
                   box-shadow: 0 0 10px red; font-family: Arial, sans-serif; 
                   font-weight: bold; text-transform: uppercase;">
            Back Up / Clear Data
        </button>
    </div>
<?php endif; ?>



<div id="printContent">
<table>
    <thead>
        <tr>
            <th>Student / ID Number</th>
            <th>Office</th>
            <th>Others</th>
            <th>Total Score</th>
            <th>Comments</th>
            <th>Date Submitted</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr class="clickable-row" data-student-number="<?php echo htmlspecialchars($row['student_number']); ?>" data-office-name="<?php echo urlencode($row['office_name']); ?>">
                <td class="student-number">
                    <a href="view_feedback.php?student_number=<?php echo htmlspecialchars($row['student_number']); ?>&office_name=<?php echo urlencode($row['office_name']); ?>" class="feedback-link">
                        <?php echo htmlspecialchars($row['student_number']); ?>
                    </a>
                </td>
                <td class="office-name" style="color:<?php echo $office_colors[$row['office_name']] ?? '#000000'; ?>">
                    <a href="view_feedback.php?student_number=<?php echo htmlspecialchars($row['student_number']); ?>&office_name=<?php echo urlencode($row['office_name']); ?>" class="feedback-link">
                        <?php echo htmlspecialchars($row['office_name']); ?>
                    </a>
                   
                </td>
                <td><?php echo htmlspecialchars($row['colleges']); ?></td>
                <td><?php echo htmlspecialchars($row['total_score']); ?></td>
                <td><?php echo htmlspecialchars($row['comments']); ?></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>



    
    <br>

    <?php if ($is_admin): // To check if the user is an admin, and if not then don't show feedback chart ?>
    <h2>Feedback Chart</h2>
    
    <div class="chart-container">
        <canvas id="feedbackChart"></canvas>
    </div>
<?php endif; ?>


    <br>

    <div class="table-chart">
    <h2>Report / Result</h2>
    <table>
        <thead>
            <tr>
                <th>Office Name</th>
                <th>Number Of Students / Clients</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($votes_data as $vote): ?>
                <tr>
                    <td>
                        <?php
                        // Define the color for the faculty's own office (light green)
                        $office_color = '#0ADD0A'; // Light green for own office
                        // Define the color for other offices (gray for non-clickable)
                        $disabled_color = '#FF0000'; 

                        // Check if the user is a faculty member and if the office matches their own office
                        if ($is_faculty_staff) {
                            // Faculty's office should be fetched from the database
                            if (!isset($faculty_office)) {
                                // Handle case where faculty office is not set
                                // Fetch faculty's office if not already set
                                $faculty_sql = "SELECT office FROM faculty_staff WHERE full_name = ?";
                                $faculty_stmt = $conn->prepare($faculty_sql);
                                $faculty_stmt->bind_param("s", $_SESSION['faculty_username']);
                                 
                                if (!$faculty_stmt->execute()) {
                                    die("Error fetching faculty office: " . $faculty_stmt->error);
                                }

                                $faculty_result = $faculty_stmt->get_result();
                                 
                                if ($faculty_result && $faculty_result->num_rows > 0) {
                                    $faculty_info = $faculty_result->fetch_assoc();
                                    $faculty_office = $faculty_info['office'];
                                } else {
                                    die("Faculty data not found for username: " . $_SESSION['faculty_username']);
                                }
                            }

                            // Check if the office is not the faculty's own
                            if ($vote['office_name'] !== $faculty_office) {
                                // Display office as non-clickable (plain text) with a disabled gray color
                                echo '<span style="color: ' . $disabled_color . ';">' . htmlspecialchars($vote['office_name']) . '</span>';
                            } else {
                                // Display office as clickable with light green color for the faculty's own office
                                echo '<a href="office_results.php?office_name=' . urlencode($vote['office_name']) . '" style="color: ' . $office_color . ';">' . htmlspecialchars($vote['office_name']) . '</a>';
                            }
                        } else {
                            // For admins or non-faculty users, display all offices as clickable with their assigned colors
                            echo '<a href="office_results.php?office_name=' . urlencode($vote['office_name']) . '" style="color: ' . ($office_colors[$vote['office_name']] ?? '#000000') . ';">' . htmlspecialchars($vote['office_name']) . '</a>';
                        }
                        ?>
                    </td>
                    <td>
                    <?php
            // Second column: Display votes based on user type
            if ($is_faculty_staff) {
                // Faculty/staff: Only their office is clickable
                if ($vote['office_name'] === $faculty_office) {
                    echo '<a href="feedback_ratings_table.php?office_name=' . urlencode($vote['office_name']) . '" style="color: rgb(0, 254, 0);">' . htmlspecialchars($vote['votes']) . '</a>';
                } else {
                    // Non-clickable for other offices
                    echo '<span style="color: ' . $disabled_color . ';">' . htmlspecialchars($vote['votes']) . '</span>';
                }
            } else {
                // Admins: All votes are clickable
                echo '<a href="feedback_ratings_table.php?office_name=' . urlencode($vote['office_name']) . '" style="color: rgb(0, 0, 0);">' . htmlspecialchars($vote['votes']) . '</a>';
            }
            ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>




    <button id="printButton" onclick="window.print()">Print</button>
</div>

      
    <script>


        const ctx = document.getElementById('feedbackChart').getContext('2d');
        const data = {
            labels: <?php echo json_encode(array_column($votes_data, 'office_name')); ?>,
            datasets: [{
                label: 'Students / Cients',
                data: <?php echo json_encode(array_column($votes_data, 'votes')); ?>,
                backgroundColor: [
                    '#06402B', '#3357FF', '#6699CC', '#800000'
                ],
                borderColor: [
                    '#06402B', '#3357FF', '#6699CC', '#800000'
                ],
                borderWidth: 1
            }]
        };

        const config = {
            type: 'bar',
            data: data,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        enabled: true
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        };

        const feedbackChart = new Chart(ctx, config);

        function printPage() {
            window.print();
        }

        window.onload = function () {
    // Example of rendering a chart
    var ctx = document.getElementById('myChart').getContext('2d');
    var myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Office A', 'Office B', 'Office C','Office D', 'Office E'],
            datasets: [{
                label: 'Votes',
                data: [12, 19, 3, 5],
                backgroundColor: [
                    '#06402B',
                    '#3357FF',
                    '#6699CC',
                    '#FFFF00',
                    '#800000'
                ]
            }]
        }
    });
};

let currentPage = <?php echo $page; ?>;
    const totalPages = <?php echo $totalPages; ?>;
    
    function swipeLeft() {
        if (currentPage < totalPages) {
            currentPage++;
            window.location.href = `dashboard.php?page=${currentPage}`;
        }
    }

    function swipeRight() {
        if (currentPage > 1) {
            currentPage--;
            window.location.href = `dashboard.php?page=${currentPage}`;
        }
    }

    document.addEventListener("keydown", function(event) {
        if (event.key === "ArrowLeft") {
            swipeRight();
        } else if (event.key === "ArrowRight") {
            swipeLeft();
        }
    });


    <?php if ($is_faculty_staff): ?>
    // Check if the URL is for view_admins or view_students
    const currentPage = window.location.pathname;
    if (currentPage.includes("view_admins.php") || currentPage.includes("view_students.php")) {
        document.getElementById("modal").style.display = "flex"; // Show the modal
    }
<?php endif; ?>

// Function to close the modal
function closeModal() {
    document.getElementById("modal").style.display = "none"; // Hide the modal
}

document.addEventListener("DOMContentLoaded", function() {
    // Get all rows in the table
    const rows = document.querySelectorAll('.clickable-row');

    rows.forEach(row => {
        
        const rowId = row.getAttribute('data-id');

      
        if (localStorage.getItem('clicked_' + rowId)) {
            // Hide the notification if this row was clicked previously
            row.querySelector('.notification').style.display = 'none';
        }

        row.addEventListener('click', function() {
            // Find the notification inside the clicked row
            const notification = row.querySelector('.notification');
            
           
            if (notification) {
                notification.style.display = 'none';
            }

            // Store the row's clicked state in localStorage
            localStorage.setItem('clicked_' + rowId, 'true');
        });
    });
});

// Open Modal
function openModal() {
        document.getElementById('transferModal').style.display = 'block';
    }

    // Close Modal
    function closeModal() {
        document.getElementById('transferModal').style.display = 'none';
    }

    // Confirm Transfer (This will trigger the transfer in transfer.php)
    function confirmTransfer() {
        // Perform the AJAX call or redirect to trigger the transfer
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "transfer.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        // Sending a POST request with a query parameter to indicate transfer action
        xhr.onload = function() {
            if (xhr.status === 200) {
                alert('Data successfully moved to history!');
                closeModal(); // Close the modal after success
            } else {
                alert('Error occurred while transferring data.');
            }
        };

        // Sending a POST request with a transferData value
        xhr.send("transferData=yes");
    }

    document.addEventListener("DOMContentLoaded", function() {
    var input = document.getElementById("searchInput");

    // Load search value from localStorage
    if (localStorage.getItem("searchValue")) {
        input.value = localStorage.getItem("searchValue");
        filterTable(); // Apply filter on page load
    }

    input.addEventListener("keyup", function() {
        localStorage.setItem("searchValue", input.value); // Store search term
        filterTable();
    });
});

function filterTable() {
    var input, filter, table, tr, tdCollege, tdOffice, i, txtCollege, txtOffice;
    
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    table = document.querySelector("table");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) { // Skip header row
        tdCollege = tr[i].getElementsByTagName("td")[2]; // 3rd column = College
        tdOffice = tr[i].getElementsByTagName("td")[3];  // 4th column = Office

        if (tdCollege && tdOffice) {
            txtCollege = tdCollege.textContent || tdCollege.innerText;
            txtOffice = tdOffice.textContent || tdOffice.innerText;

            if (txtCollege.toUpperCase().indexOf(filter) > -1 || txtOffice.toUpperCase().indexOf(filter) > -1 || filter === "") {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}
    
</script>

<!-- Transfer Confirmation Modal -->
<div id="transferModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(200, 0, 0, 0.5); z-index: 9999; overflow: auto;">
    <div style="margin: 10% auto; width: 80%; max-width: 700px; background: linear-gradient(135deg,rgb(148, 0, 0),rgb(153, 0, 0),rgb(122, 0, 0)); padding: 20px; border-radius: 10px; color: white; box-shadow: 0 0 20pxrgb(218, 4, 0);">
        <h2 style="text-align: center; font-family: 'Courier New', Courier, monospace; color:rgb(255, 255, 255);" >Backup / Clear Data</h2>

        <div id="modalContent" style="padding: 10px; border: 2px dashed rgb(255, 255, 255); border-radius: 10px; overflow-y: auto; max-height: 400px;">
            <!-- Content from transfer.php will load here -->
            <p style="text-align: center; color: white;">Back up data will transfer records to history repository. This action cannot be undone.</p>
        </div>

        <div style="text-align: center; margin-top: 10px;">
            <!-- Yes and No buttons -->
            <button onclick="confirmTransfer()" style="padding: 8px 15px; background-color: rgb(0, 128, 0); border: none; color: white; border-radius: 5px; cursor: pointer; box-shadow: 0 0 5px rgb(234, 0, 0);">Yes</button>
            <button onclick="closeModal()" style="padding: 8px 15px; background-color: rgb(255, 0, 0); border: none; color: white; border-radius: 5px; cursor: pointer; box-shadow: 0 0 5pxrgb(234, 0, 0);">No</button>
        </div>
    </div>
</div>


   
    </div>
</div>

</body>
</html>

<?php

$conn->close();
?>
