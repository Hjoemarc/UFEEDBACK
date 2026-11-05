<?php
session_start();

// Database connection
$host = "localhost";
$dbusername = "root"; 
$dbpassword = ""; 
$dbname = "feedback_system";
$conn = new mysqli($host, $dbusername, $dbpassword, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// If someone has attempted a malicious login (e.g., after too many failed attempts)
if (isset($_SESSION['attempts']) && $_SESSION['attempts'] >= 7) {
    // Get the user's real IP address
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
    }
    
    $timestamp = time();  // Get the current time
    $reason = 'Too many failed login attempts';

    // Insert the malicious attempt into the database
    $stmt = $conn->prepare("INSERT INTO suspicious_activity (ip_address, reason, timestamp) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $ipAddress, $reason, $timestamp);
    $stmt->execute();
    $stmt->close();
}

// Fetch suspicious activity from the database
$sql = "SELECT * FROM suspicious_activity ORDER BY timestamp DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suspicious Activity Alert</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
            
        }

        .alert {
            background-color: #f8d7da;
            color: #721c24;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-left: 300px;
        }

        .alert h2 {
            margin-top: 0;
        }

        .activity-list-container {
            max-height: 400px;
            overflow-y: scroll;
            margin-top: 20px;
            margin-left: 300px;
        }

        .activity-list {
            width: 100%;
            border-collapse: collapse;
        }

        .activity-list th, .activity-list td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .activity-list th {
            background-color: #f2f2f2;
        }

        /* Search bar styles */
        #searchBar {
            margin: 10px 0;
            padding: 5px;
            width: 30%;
            font-size: 16px;
            margin-left: 300px;
        }

        
        .sidebar {
            width: 250px;
            background-color: #800000;
            padding: 30px;
            box-shadow: 5px 0 5px rgba(189, 189, 189, 0.5);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 1000px;
            top: -20px;
            left: -8px;
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

    </style>
  <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
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


    <div class="alert">
        <h2>Brute Force Protection.</h2>
        <p>One or more users have been flagged for suspicious activity. Below are the details of the latest attempts.</p>
    </div>

    <!-- Search Bar -->
    <input type="text" id="searchBar" placeholder="Search by IP Address, Reason, or Timestamp..." onkeyup="filterTable()" />

    <div class="activity-list-container">
        <table class="activity-list" id="activityTable">
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Reason</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    // Set the timezone to Manila
                    date_default_timezone_set('Asia/Manila');
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>{$row['ip_address']}</td>
                                <td>{$row['reason']}</td>
                                <td>" . date("Y-m-d h:i:s A", $row['timestamp']) . "</td>
                            </tr>";
                    }
                } else {
                    echo "<tr><td colspan='3'>No suspicious activity detected.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <script>
        // Function to filter the table based on the search input
        function filterTable() {
            let input = document.getElementById("searchBar").value.toUpperCase();
            let table = document.getElementById("activityTable");
            let rows = table.getElementsByTagName("tr");

            for (let i = 1; i < rows.length; i++) {
                let cells = rows[i].getElementsByTagName("td");
                let matchFound = false;

                // Check if the search term matches any column (IP Address, Reason, or Timestamp)
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j] && cells[j].innerText.toUpperCase().indexOf(input) > -1) {
                        matchFound = true;
                        break;
                    }
                }

                // Show/hide the row based on the match
                if (matchFound) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        }
    </script>

</body>
</html>

<?php
$conn->close();
?>
