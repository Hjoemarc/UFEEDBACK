<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "feedback_system";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT rating1, rating2, rating3, rating4, rating5, rating6, rating7, rating8, rating9, comments FROM feedback WHERE office_name = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    // Output the error
    echo "Error in SQL query: " . $conn->error;
} else {
    $stmt->bind_param("s", $office_name);
    $stmt->execute();
    $result = $stmt->get_result();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Office Results</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Dark Mode Styles */
        body {
    margin: 0;
    font-family: 'Arial', sans-serif;
    background-color:rgb(229, 229, 229);
    color: #ffffff;
}

.content-wrapper {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 10px; /* Adjust this if needed */
    margin-top: -300px; /* Negative margin to bring it closer to the top */
    min-height: 100vh;
    overflow-y: auto;
}

.content {
    background-color:rgb(138, 138, 138);
    width: 100%;
    max-width: 1000px;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(136, 136, 136, 0.5);
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    overflow-x: auto;
    box-shadow: 0 0 20px rgba(190, 190, 190, 0.2);
}

        .chart-container {
            width: 100%;
            max-width: 800px;
            height: 400px;
            margin-bottom: 10px;
         
            box-shadow: 0 0 20px rgba(164, 164, 164, 0.2);
        }

        .back-btn, .print-btn {
            margin-top: 10px;
            background-color: #800000;
            padding: 12px 24px;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .back-btn:hover, .print-btn:hover {
            background-color: #800000;
        }

        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            overflow-x: auto;
            display: block;
            max-width: 100%;
            border-radius: 8px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #555;
        }

        th {
            background-color:rgb(71, 0, 0);
            color: white;
            font-weight: bold;
        }

        tr:nth-child(even) {
            background-color:rgb(146, 0, 0);
        }

        tr:hover {
            background-color:rgb(236, 0, 0);
        }

        @media screen and (max-width: 768px) {
            .content {
                width: 95%;
                padding: 20px;
            }

            .chart-container {
                height: 300px;
            }

            table {
                width: 100%;
                display: block;
                overflow-x: auto;
            }
        }

        @media print {
            .back-btn, .print-btn {
                display: none;
            }
        }
        .more-btn {
    background-color: #800000;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    text-decoration: none;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}



    </style>
</head>
<body>
<div class="container" style="position: relative;">
    <h3><?php echo $office_name; ?></h3>
    <div id="chart-container-<?php echo urlencode($office_name); ?>" style="height: 300px;"></div>
    
    

</div>


<div class="content-wrapper">
    <div class="content">
        <?php
        if (isset($_GET['office_name'])) {
            $office_name = urldecode($_GET['office_name']);
            echo "<h1> " . htmlspecialchars($office_name) . "</h1>";

            // Fetch the rating counts and feedback data for the selected office
            $sql = "
                SELECT 
                    SUM(CASE WHEN rating1 = 1 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating2 = 1 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating3 = 1 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating4 = 1 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating5 = 1 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating6 = 1 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating7 = 1 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating8 = 1 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating9 = 1 THEN 1 ELSE 0 END) AS rating1_count,

                    SUM(CASE WHEN rating1 = 2 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating2 = 2 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating3 = 2 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating4 = 2 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating5 = 2 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating6 = 2 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating7 = 2 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating8 = 2 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating9 = 2 THEN 1 ELSE 0 END) AS rating2_count,

                    SUM(CASE WHEN rating1 = 3 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating2 = 3 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating3 = 3 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating4 = 3 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating5 = 3 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating6 = 3 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating7 = 3 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating8 = 3 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating9 = 3 THEN 1 ELSE 0 END) AS rating3_count,

                    SUM(CASE WHEN rating1 = 4 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating2 = 4 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating3 = 4 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating4 = 4 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating5 = 4 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating6 = 4 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating7 = 4 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating8 = 4 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating9 = 4 THEN 1 ELSE 0 END) AS rating4_count,

                    SUM(CASE WHEN rating1 = 5 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating2 = 5 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating3 = 5 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating4 = 5 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating5 = 5 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating6 = 5 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating7 = 5 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating8 = 5 THEN 1 ELSE 0 END) + 
                    SUM(CASE WHEN rating9 = 5 THEN 1 ELSE 0 END) AS rating5_count
                FROM feedback
                WHERE office_name = ?;
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $office_name);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // Data for the chart
                $rating1_count = $row['rating1_count'];
                $rating2_count = $row['rating2_count'];
                $rating3_count = $row['rating3_count'];
                $rating4_count = $row['rating4_count'];
                $rating5_count = $row['rating5_count'];
        ?>
            <!-- Chart -->
            <div class="chart-container">
                <canvas id="ratingChart"></canvas>
            </div>
            <script>
                var ctx = document.getElementById('ratingChart').getContext('2d');
                var ratingChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Strongly Disagree', 'Disagree', 'Neither Agree/Disagree', 'Agree', 'Strongly Agree'],
                        datasets: [{
                            label: 'Ratings Count',
                            data: [<?php echo $rating1_count; ?>, <?php echo $rating2_count; ?>, <?php echo $rating3_count; ?>, <?php echo $rating4_count; ?>, <?php echo $rating5_count; ?>],
                            backgroundColor: ['#e74c3c', '#f39c12', '#f1c40f', '#2ecc71', '#3498db'],
                            borderColor: ['#e74c3c', '#f39c12', '#f1c40f', '#2ecc71', '#3498db'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            </script>

            <!-- Table for displaying feedback data -->
            <table>
                <thead>
                    <tr>
                        <th>Purpose</th> 
                        <th>Client Type</th>
                        <th>Age</th>
                        <th>Sex</th>
                        <th>CC1 Response</th>
                        <th>CC2 Response</th>
                        <th>CC3 Response</th>
                        <th>Comments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch the feedback data for the office
                    $sql = "SELECT * FROM feedback WHERE office_name = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $office_name);
                    $stmt->execute();
                    $feedback_result = $stmt->get_result();

                    while ($feedback_row = $feedback_result->fetch_assoc()) {
                        echo "<tr>"; 
                        echo "<td>" . htmlspecialchars($feedback_row['TOR']) . "</td>";
                        echo "<td>" . htmlspecialchars($feedback_row['client_type']) . "</td>";
                        echo "<td>" . htmlspecialchars($feedback_row['age']) . "</td>";
                        echo "<td>" . htmlspecialchars($feedback_row['sex']) . "</td>";
                        echo "<td>" . htmlspecialchars($feedback_row['question1']) . "</td>";
                        echo "<td>" . htmlspecialchars($feedback_row['question2']) . "</td>";
                        echo "<td>" . htmlspecialchars($feedback_row['question3']) . "</td>";
                        echo "<td>" . htmlspecialchars($feedback_row['comments']) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>

            <!-- Back and Print Buttons -->
            <div>
                <button class="back-btn" onclick="window.location.href='dashboard.php';">Back to Dashboard</button>
                <button class="print-btn" onclick="window.print();">Print</button>
            </div>

        <?php
            }
        }
        ?>
    </div>
</div>

</body>
</html>
