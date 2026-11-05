<?php
header('Content-Type: application/json');

// Include the database connection file
require 'db_connection.php';

// Get data from POST
$email = $_POST['email'];
$password = $_POST['password'];

// Check if email exists and password matches
$sql = "SELECT * FROM students WHERE email = ? AND password = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $email, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Login successful
    echo json_encode(array("status" => "success", "message" => "Login successful"));
} else {
    // Invalid credentials
    echo json_encode(array("status" => "error", "message" => "Invalid credentials"));
}

$stmt->close();
$conn->close();
?>
