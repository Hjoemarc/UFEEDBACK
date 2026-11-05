<?php
header("Content-Type: application/json");
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "feedback_system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$student_number = isset($_POST['student_number']) ? $_POST['student_number'] : null;
$full_name = isset($_POST['full_name']) ? $_POST['full_name'] : null;
$email = isset($_POST['email']) ? $_POST['email'] : null;
$password = isset($_POST['password']) ? $_POST['password'] : null;

if (empty($email) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Email and password are required.']);
    exit;
}

// Check if email already exists
$query = "SELECT * FROM students WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Email is already registered.']);
    exit;
}

// Insert into database
$query = "INSERT INTO students (student_number, full_name, email, password) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($query);
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$stmt->bind_param("ssss", $student_number, $full_name, $email, $hashedPassword);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Signup successful!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to register user.']);
}

$stmt->close();
$conn->close();
?>
