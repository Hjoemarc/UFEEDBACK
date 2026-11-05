<?php
// Database connection settings
$host     = "localhost";
$username = "root";
$password = "";
$dbname   = "feedback_system";

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query to select all columns from the history table
$query = "SELECT 
            comments, 
            office_name, 
            created_at, 
            student_number, 
            rating1, rating2, rating3, rating4, rating5, rating6, rating7, rating8, rating9, 
            client_type, 
            age, 
            sex, 
            total_score, 
            question1, 
            question2, 
            question3, 
            TOR 
          FROM history";
$result = $conn->query($query);
if (!$result) {
    die("Error executing query: " . $conn->error);
}

// Mapping function for ratings 0-5:
// 0 => N/A, 1 => Strongly Disagree, 2 => Disagree, 3 => Neither Agree/Disagree, 4 => Agree, 5 => Strongly Agree
function mapRating($rating) {
    switch ($rating) {
        case 0:
            return "N/A";
        case 1:
            return "Strongly Disagree";
        case 2:
            return "Disagree";
        case 3:
            return "Neither Agree/Disagree";
        case 4:
            return "Agree";
        case 5:
            return "Strongly Agree";
        default:
            return "Unknown";
    }
}

// Fetch only one feedback record (the first record)
$feedback = ($result->num_rows > 0) ? $result->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Feedback Record</title>
  <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700" rel="stylesheet">
  <style>
    body {
      font-family: 'Roboto', sans-serif;
      background-color: #f4f6f9;
      color: #333;
      margin: 0;
      padding: 0px;
    }
    .container {
      max-width: 600px;
      margin: 0 auto;
      background-color: #fff;
      padding: 20px 25px; /* Reduced padding for a smaller container */
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      position: relative;
    }
    /* Button container in header */
    .button-container {
      position: absolute;
      top: 15px;
      left: 20px;
      right: 20px;
      display: flex;
      justify-content: space-between;
    }
    .button-container button {
      padding: 8px 16px;
      font-size: 14px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    .back-btn {
      background-color: #2980b9;
      color: #fff;
    }
    .back-btn:hover {
      background-color: #3498db;
    }
    .print-btn {
      background-color: rgb(142, 8, 8);
      color: #fff;
    }
    .print-btn:hover {
      background-color: rgb(251, 0, 0);
    }
    .header {
      text-align: center;
      margin-top: 40px; /* Adjusted to account for button container */
      margin-bottom: 20px;
    }
    .header h1 {
      color: #800000;
      margin: 0;
    }
    .form-group {
      margin-bottom: 15px;
    }
    .form-group label {
      display: block;
      font-weight: 500;
      color: #555;
      margin-bottom: 4px;
    }
    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 8px;
      font-size: 14px;
      border: 1px solid #ccc;
      border-radius: 4px;
      background-color: #f7f7f7;
    }
    .form-group input[readonly],
    .form-group textarea[readonly] {
      background-color: #e0e0e0;
    }
    .no-data {
      text-align: center;
      color: #e74c3c;
      font-size: 18px;
      margin-top: 20px;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Button container: Back on left, Print on right -->
    <div class="button-container">
      <button class="back-btn" onclick="window.location.href='history.php'">← Back</button>
      <button class="print-btn" onclick="window.print()">Print</button>
    </div>
    <?php if ($feedback): ?>
      <!-- Office Name as the header -->
      <div class="header">
        <h1><?= htmlspecialchars($feedback['office_name']) ?></h1>
      </div>
      <div class="form-group">
        <label>Student Number</label>
        <input type="text" value="<?= htmlspecialchars($feedback['student_number']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>Question 1</label>
        <input type="text" value="<?= htmlspecialchars($feedback['question1']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>Question 2</label>
        <input type="text" value="<?= htmlspecialchars($feedback['question2']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>Question 3</label>
        <input type="text" value="<?= htmlspecialchars($feedback['question3']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>TOR</label>
        <input type="text" value="<?= htmlspecialchars($feedback['TOR']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>Age</label>
        <input type="text" value="<?= htmlspecialchars($feedback['age']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>Sex</label>
        <input type="text" value="<?= htmlspecialchars($feedback['sex']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>Client Type</label>
        <input type="text" value="<?= htmlspecialchars($feedback['client_type']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>Date Submitted</label>
        <input type="text" value="<?= htmlspecialchars($feedback['created_at']) ?>" readonly>
      </div>
      <!-- SDQ Ratings -->
      <div class="form-group">
        <label>SDQ0</label>
        <input type="text" value="<?= mapRating($feedback['rating1']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>SDQ1</label>
        <input type="text" value="<?= mapRating($feedback['rating2']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>SDQ2</label>
        <input type="text" value="<?= mapRating($feedback['rating3']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>SDQ3</label>
        <input type="text" value="<?= mapRating($feedback['rating4']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>SDQ4</label>
        <input type="text" value="<?= mapRating($feedback['rating5']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>SDQ5</label>
        <input type="text" value="<?= mapRating($feedback['rating6']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>SDQ6</label>
        <input type="text" value="<?= mapRating($feedback['rating7']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>SDQ7</label>
        <input type="text" value="<?= mapRating($feedback['rating8']) ?>" readonly>
      </div>
      <div class="form-group">
        <label>SDQ8</label>
        <input type="text" value="<?= mapRating($feedback['rating9']) ?>" readonly>
      </div>
      <!-- Comments at the bottom -->
      <div class="form-group full-width">
        <label>Comments</label>
        <textarea rows="4" readonly><?= htmlspecialchars($feedback['comments']) ?></textarea>
      </div>
    <?php else: ?>
      <p class="no-data">No feedback record found.</p>
    <?php endif; ?>
  </div>
  <?php $conn->close(); ?>
</body>
</html>
