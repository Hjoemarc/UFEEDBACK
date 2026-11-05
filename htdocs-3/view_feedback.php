<?php

// Database connection setup
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "feedback_system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch the student_number and office_name from the URL (using GET)
if (isset($_GET['student_number']) && isset($_GET['office_name'])) {
    $student_number = $_GET['student_number'];
    $office_name = $_GET['office_name'];
} else {
    die("Student number and office name are required to view feedback.");
}

// Fetch feedback based on the student_number and office_name
$sql = "SELECT * FROM feedback WHERE student_number = ? AND office_name = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $student_number, $office_name);
$stmt->execute();
$result = $stmt->get_result();

// Function to map rating values to their descriptions
function mapRating($rating) {
    switch ($rating) {
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
        case 0:
        default:
            return "N/A";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> <?php echo htmlspecialchars($office_name); ?></title>
    <style>
     /* Reset margin and padding */
html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: #ecf0f1;
}

/* Container styles */
.container {
    width: 95%;
    max-width: 700px; /* Maximum width for the container */
    height: 95vh; /* Adjust height to be 90% of the viewport height */
    padding: 30px; /* Reduce padding to make it shorter */
    background-color: white;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    border-radius: 10px;
    font-size: 16px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: center;
    overflow-y: auto;
    box-sizing: border-box; /* Include padding in height/width */
}

/* Adjust header styling */




/* Feedback item styling */
.feedback-item {
    margin-bottom: 20px;
  
}

.feedback-item label {
    font-weight: bold;
    color: #34495e;
}

.feedback-item input,
.feedback-item select,
.feedback-item textarea {
    width: 100%;
    padding: 12px;
    margin-top: 8px;
    border: 1px solid #bdc3c7;
    border-radius: 5px;
    background-color: #f7f7f7;
    font-size: 14px;
    color:rgb(0, 0, 0);
}

.feedback-item input[readonly],
.feedback-item select[disabled],
.feedback-item textarea[readonly] {
    background-color: #e0e0e0;
}

.back {

    margin-bottom: 20px;
    position: absolute;
    top: 0;
    left: 0;
}

.back-btn {
    width: 100%;
    padding: 12px;
    background-color: #2980b9;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
}

.back-btn:hover {
    background-color: #3498db;
}

/* No feedback styling */
.no-feedback {
    text-align: center;
    font-size: 18px;
    color: #e74c3c;
}

/* Print button styles */
.print-btn {
    position: fixed;  /* Fix the position relative to the viewport */
    top: 20px;        /* Distance from the top of the page */
    right: 20px;      /* Distance from the right side of the page */
    padding: 5px 10px;  /* Smaller padding */
    background-color:rgb(142, 8, 8);
    color: white;
    border: none;
    cursor: pointer;
    font-size: 14px;     /* Smaller font size */
    border-radius: 5px;
    z-index: 1000;       /* Ensure the button stays on top of other content */
}

.print-btn:hover {
    background-color:rgb(251, 0, 0);
}

/* Styling for logo */
.header-logo {
    flex-shrink: 0;  /* Ensures logo doesn't shrink */
}

.header-logo .logo {
    width: 80px;  /* Small size for the logo */
    height: auto;
    margin-right: 800px; /* Space between logo and other content */
}

/* Scrollbar styling */
.container::-webkit-scrollbar {
    width: 8px;
}

.container::-webkit-scrollbar-thumb {
    background-color: #800000;
    border-radius: 6px;
}
@media print {
    /* Remove fixed positioning and ensure the print area is full */
    .container {
        width: 100%;
        height: auto;  /* Ensure the container takes the content's height */
        padding: 0px; /* Adjust padding if necessary */
        box-shadow: none; /* Optional: Remove box shadow for printing */
        border-radius: 0; /* Optional: Remove border radius for printing */
    }

    /* Print button should be hidden during printing */
    .print-btn {
        display: none;
    }

    /* Ensure the whole page is printed */
    body, html {
        height: auto;  /* Allow the content to take the height of the container */
        margin: 0;
        padding: 0;
    }
}
.header-container {
            display: flex;
            align-items: center; /* Vertically align the items */
            width: 100%;
            max-width: 900px;
            padding: -10px;
            margin: 0 auto;
        }

        /* Logo Styling */
        .logo-container {
            margin-right: 10px; /* Space between logo and office name */
            flex-shrink: 0; /* Prevents the logo from shrinking */
        }

        .logo-container img {
            height: 80px; /* Set the height of the logo */
        }

        /* Office Name Styling */
        h1 {
    font-size: 24px;
    margin-top: -30px; /* Moves the h1 higher */
    margin-bottom: 90px; /* Adds space below the h1 */
    color: #2c3e50;
    text-align: center; /* Center the office name */
}

    </style>
</head>
<body>
<div class='back'>
    <button type='button' class='back-btn' onclick="window.location.href='dashboard.php'">← Back</button>
</div>

<div class="container">
    
    <div class="header">
    <div class="header-logo">
    <header style="display: flex; align-items: center;">
        <!-- Logo Image -->
        
        <!-- Office Name -->
       
    </header>
       
    <div class="header-container">
        <!-- Logo Image (Positioned to the left of the office name) -->
        <div class="logo-container">
            <img src="logo/ZPPSU LOGO.png" alt="ZPPSU Logo">
        </div>
        
    </div>

    <!-- Display the office name as the main title -->
    <h1> <?php echo htmlspecialchars($office_name); ?></h1>

    <?php
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<form>";
            echo "<div class='feedback-item'>
        <label for='student_number'>Student Number</label>
        <input type='text' id='student_number' name='student_number' value='" . htmlspecialchars($row['student_number']) . "' readonly>
    </div>";

          
            echo "<div class='feedback-item'>
                    <label for='client_type'>Client Type</label>
                    <input type='text' id='client_type' name='client_type' value='" . htmlspecialchars($row['client_type']) . "' readonly>
                </div>";
            echo "<div class='feedback-item'>
                    <label for='age'>Age</label>
                    <input type='text' id='age' name='age' value='" . htmlspecialchars($row['age']) . "' readonly>
                </div>";
            echo "<div class='feedback-item'>
                    <label for='sex'>Sex</label>
                    <input type='text' id='sex' name='sex' value='" . htmlspecialchars($row['sex']) . "' readonly>
                </div>";
            echo "<div class='feedback-item'>
                    <label for='purpose'>Purpose</label>
                    <input type='text' id='purpose' name='purpose' value='" . htmlspecialchars($row['TOR']) . "' readonly>
                </div>";

            // Add cc1, cc2, cc3 for question1, question2, question3
            echo "<div class='feedback-item'>
                    <label for='cc1'>CC1. Which of the following best describes your awareness of the Citizen’s Charter (CC)?</label>
                    <input type='text' id='cc1' name='cc1' value='" . htmlspecialchars($row['question1']) . "' readonly>
                </div>";
            echo "<div class='feedback-item'>
                    <label for='cc2'>CC2. If aware of the CC (answered 1-3 in CC1), how would you describe the visibility of this office's CC?</label>
                    <input type='text' id='cc2' name='cc2' value='" . htmlspecialchars($row['question2']) . "' readonly>
                </div>";
            echo "<div class='feedback-item'>
                    <label for='cc3'>CC3. If aware of the CC (answered codes 1-3 in CC1), how much did the CC help you in your transaction?</label>
                    <input type='text' id='cc3' name='cc3' value='" . htmlspecialchars($row['question3']) . "' readonly>
                </div>";

                echo "<div class='feedback-item'>
                <label for='rating1'>1. I am satisfied with the service that I availed.</label>
                <input type='text' id='rating1' name='rating1' value='" . mapRating($row['rating1']) . "' readonly>
              </div>";
        
        echo "<div class='feedback-item'>
                <label for='rating2'>2. I spent a reasonable amount of time for my transaction.</label>
                <input type='text' id='rating2' name='rating2' value='" . mapRating($row['rating2']) . "' readonly>
              </div>";
        
        echo "<div class='feedback-item'>
                <label for='rating3'>3. The office followed the transaction's requirements and steps based on the information provided.</label>
                <input type='text' id='rating3' name='rating3' value='" . mapRating($row['rating3']) . "' readonly>
              </div>";
        
        echo "<div class='feedback-item'>
                <label for='rating4'>4.The steps (including payment) I needed to do for my transaction were easy and simple.</label>
                <input type='text' id='rating4' name='rating4' value='" . mapRating($row['rating4']) . "' readonly>
              </div>";
        
        echo "<div class='feedback-item'>
                <label for='rating5'>5. I easily found information about my transaction from the office or its website.</label>
                <input type='text' id='rating5' name='rating5' value='" . mapRating($row['rating5']) . "' readonly>
              </div>";
        
        echo "<div class='feedback-item'>
                <label for='rating6'>6.I paid a reasonable amount of fees for my transaction. (If the service was free, select the N/A column.)</label>
                <input type='text' id='rating6' name='rating6' value='" . mapRating($row['rating6']) . "' readonly>
              </div>";
        
        echo "<div class='feedback-item'>
                <label for='rating7'>7. I feel the office was fair to everyone, or walang palakasan, during my transaction.</label>
                <input type='text' id='rating7' name='rating7' value='" . mapRating($row['rating7']) . "' readonly>
              </div>";
        
        echo "<div class='feedback-item'>
                <label for='rating8'>8. I was treated courteously by the staff, and (if asked for help) the staff was helpful.</label>
                <input type='text' id='rating8' name='rating8' value='" . mapRating($row['rating8']) . "' readonly>
              </div>";
        
        echo "<div class='feedback-item'>
                <label for='rating9'>9.I got what I needed from the government office, or (if denied) the denial of my request was justified.</label>
                <input type='text' id='rating9' name='rating9' value='" . mapRating($row['rating9']) . "' readonly>
              </div>";

         

            echo "<div class='feedback-item'>
                    <label for='comments'>Comments</label>
                    <textarea id='comments' name='comments' rows='4' readonly>" . htmlspecialchars($row['comments']) . "</textarea>
                </div>";

            
        }
    } else {
        echo "<p class='no-feedback'>No feedback found for this student and office.</p>";
    }

    $conn->close();
    ?>
    
   <button class="print-btn" onclick="window.print()">Print</button>

</div>
<script>
        function printContainer() {
            const container = document.getElementById('existing-container');
            const newWindow = window.open('', '', 'width=800,height=600');
            newWindow.document.write('<html><head><title>Print</title></head><body>');
            newWindow.document.write(container.outerHTML);
            newWindow.document.write('</body></html>');
            newWindow.document.close();
            newWindow.focus();
            newWindow.print();
            newWindow.close();
        }
    </script>
</body>
</html>
