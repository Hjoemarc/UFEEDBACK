<?php
include('refresh.php');
session_start();

// Check if the user is logged in as an admin or faculty staff
$is_admin = isset($_SESSION['admin_username']);
$is_faculty_staff = isset($_SESSION['faculty_username']);

if (!$is_admin && !$is_faculty_staff) {
    header("Location: faculty_login.php");
    exit;
}

// Database connection
$host = "localhost";
$user = "root";  // Change if needed
$password = "";  // Change if needed
$database = "feedback_system";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch faculty staff's college if logged in as faculty staff
$faculty_college = null;

if ($is_faculty_staff) {
    $faculty_full_name = $_SESSION['faculty_username'];

    $query = "SELECT office FROM faculty_staff WHERE full_name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $faculty_full_name);
    $stmt->execute();
    $stmt->bind_result($faculty_college);
    $stmt->fetch();
    $stmt->close();
}

// Fetch data from offices table and count students from feedback table
if ($is_admin) {
    $query = "SELECT o.office_name, o.colleges, o.office_type,
                (SELECT COUNT(DISTINCT f.student_number) 
                 FROM feedback f 
                 WHERE f.office_name = o.office_name) AS student_count 
              FROM offices o";
    $stmt = $conn->prepare($query);
} else {
    $query = "SELECT o.office_name, o.colleges, o.office_type,
                (SELECT COUNT(DISTINCT f.student_number) 
                 FROM feedback f 
                 WHERE f.office_name = o.office_name) AS student_count 
              FROM offices o 
              WHERE o.office_name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $faculty_college);
}

$stmt->execute();
$result = $stmt->get_result();
$offices_data = [];
$labels = [];
$data_points = [];
$office_types = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $offices_data[] = $row;
        $labels[] = $row['office_name'];
        $data_points[] = $row['student_count'];
        $office_types[] = strtolower($row['office_type']); // Store office type
    }
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Office List</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
        }
        .sidebar {
            width: 220px;
            background-color: #800000;
            padding: 20px;
            box-shadow: 5px 0 5px rgba(189, 189, 189, 0.5);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100%;
            top: 0;
            left: 0;
            text-align: left;
        }

        .sidebar h2 {
            margin: 0 0 20px;
            font-size: 1.5rem;
            color: rgb(0, 0, 0);
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
            color: rgb(234, 0, 0);
        }
        .content {
            margin-left: 250px;
            padding: 15px;
            width: calc(100% - 270px);
        }
        .container {
    background: white;
    padding: 20px;
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    margin-bottom: 20px;
    overflow-x: auto;
    height: 60vh; /* Set height to 50% of the viewport height */
}

        table {
            width: 100%;
            border-collapse: collapse;
         
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: maroon;
            color: white;
        }
        tr:hover {
            background-color: rgba(219, 2, 2, 0.63);
            cursor: pointer;
        }
        .chart-container {
    width: 100%;
    max-width: 1000px; /* Increased max width for better spacing */
    height: 400px; /* Added height */
    margin: auto;
    padding: 30px;
    background: white;
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
    border-radius: 8px;
}

        @media screen and (max-width: 600px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                text-align: center;
            }
            .content {
                margin-left: 0;
                width: 100%;
            }
        }

        .filter-container {
    text-align: center;
    margin-bottom: 10px;
}

#chartFilter {
    padding: 8px;
    border-radius: 5px;
    border: 1px solid #800000;
    background-color: white;
    cursor: pointer;
}

    </style>
    <script>
        function redirectToCalculations(officeName) {
            window.location.href = 'calculations.php?office_name=' + encodeURIComponent(officeName);
        }
    </script>
</head>
<body>

<div class="sidebar">
    <h1>Admin / Faculty Panel</h1>
    <h2><?= htmlspecialchars($_SESSION['admin_username'] ?? $_SESSION['faculty_username'] ?? 'Guest'); ?></h2>
    <a href="results.php">Overall Feedback</a>
    <a href="dashboard.php">Individual Feedback</a>
    <?php if ($is_admin): ?>
    <a href="setup.php">Setup</a>
    <a href="history.php">History</a>
    <a href="track_alert.php">Failed Login Attempts</a>
    <?php endif; ?>
    <a href="profile.php">Account</a>
    <a href="logout.php">Log Out</a>
</div>

<div class="content">
    <h2>Office Results</h2>

    <div class="container">
        <table>
            <thead>
                <tr>
                    <th>College / Office</th>
                    <th>Description</th>
                    <th>Student Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($offices_data as $office): ?>
                    <tr onclick="redirectToCalculations('<?= htmlspecialchars($office['office_name']) ?>')">
                        <td><?= htmlspecialchars($office['office_name']) ?></td>
                        <td><?= htmlspecialchars($office['colleges']) ?></td>
                        <td><?= htmlspecialchars($office['student_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="filter-container">
    <label for="chartFilter">Filter: </label>
    <select id="chartFilter" onchange="filterChart()">
        <option value="all">All</option>
        <option value="academic">Academic</option>
        <option value="administrative">Administrative</option>
    </select>
</div>



    
    <h2>Student / Client Count per Office</h2>
<div class="chart-container">
    <canvas id="studentChart"></canvas>
</div>

<style>
    .chart-container {
        width: 100%;
        max-width: 800px;
        margin: auto;
        padding: 20px;
        background: white;
        box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
        border-radius: 8px;
    }
</style>

<script>
    var ctx = document.getElementById('studentChart').getContext('2d');

    var allLabels = <?= json_encode($labels); ?>;
    var allData = <?= json_encode($data_points); ?>;
    var officeTypes = <?= json_encode($office_types); ?>; // Office types (Academic/Admin)

    function getRandomColor() {
        return `rgba(${Math.floor(Math.random() * 255)}, ${Math.floor(Math.random() * 255)}, ${Math.floor(Math.random() * 255)}, 0.7)`;
    }

    function filterChart() {
        let selectedType = document.getElementById('chartFilter').value;
        let filteredLabels = [];
        let filteredData = [];

        allLabels.forEach((label, index) => {
            if (selectedType === 'all' || officeTypes[index] === selectedType) {
                filteredLabels.push(label);
                filteredData.push(allData[index]);
            }
        });

        updateChart(filteredLabels, filteredData);
    }

    function updateChart(labels, data) {
        var colors = labels.map(() => getRandomColor());

        if (window.myChart) {
            window.myChart.destroy();
        }

        window.myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Student / Client Count',
                    data: data,
                    backgroundColor: colors,
                    borderColor: colors.map(color => color.replace('0.7', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Load all data by default
    filterChart();
</script>

</body>
</html>
