<?php
// Start the session to check for admin status
session_start();

// Assuming you have a session variable 'user_role' to track if the user is an admin
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admins'; // Changed to 'admins'

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$dbname = "feedback_system";

$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Only proceed if it's an admin action and the transferData is set to 'yes'
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['transferData']) && $_POST['transferData'] == 'yes') {
    $conn->begin_transaction();

    try {
        // Transfer data from feedback table to history table
        $transfer_query = "
        INSERT INTO history (
            id, comments, office_name, created_at, student_number, rating1, rating2, rating3, 
            rating4, rating5, rating6, rating7, rating8, rating9, client_type, age, sex, 
            rating_na, total_score, question1, question2, question3, TOR
        )
        SELECT 
            feedback_id AS id, comments, office_name, created_at, student_number, rating1, rating2, rating3, 
            rating4, rating5, rating6, rating7, rating8, rating9, client_type, age, sex, 
            rating_na, total_score, question1, question2, question3, TOR
        FROM feedback";

        // Execute the insert query
        if ($conn->query($transfer_query) === FALSE) {
            throw new Exception("Error transferring data: " . $conn->error);
        }

        // After successful insert, delete the transferred records from the feedback table
        $delete_query = "
        DELETE FROM feedback
        WHERE feedback_id IN (
            SELECT feedback_id FROM feedback
        )";

        // Execute the delete query
        if ($conn->query($delete_query) === FALSE) {
            throw new Exception("Error deleting data from feedback: " . $conn->error);
        }

        // Commit the transaction
        $conn->commit();
        echo "Data successfully moved from feedback to history.";
    } catch (Exception $e) {
        // Rollback the transaction if any error occurs
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }

    // Close the connection
    $conn->close();
}
?>
