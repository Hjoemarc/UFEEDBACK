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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $officeName = mysqli_real_escape_string($conn, $_POST['officeName']);
    $availabilityDate = mysqli_real_escape_string($conn, $_POST['availabilityDate']);
    $applyToAll = isset($_POST['applyToAll']) ? 1 : 0;

    if ($applyToAll) {
        // Apply to all offices
        $query = "UPDATE offices SET availability_date = '$availabilityDate'";
    } else {
        // Apply only to the selected office
        $query = "UPDATE offices SET availability_date = '$availabilityDate' WHERE office_name = '$officeName'";
    }

    if (mysqli_query($conn, $query)) {
        $successMessage = "Office availability updated successfully.";
    } else {
        $errorMessage = "Error updating availability: " . mysqli_error($conn);
    }
}

// Search functionality for office names (AJAX-style)
if (isset($_GET['query'])) {
    $query = mysqli_real_escape_string($conn, $_GET['query']);
    $sql = "SELECT office_name FROM offices WHERE office_name LIKE '%$query%' LIMIT 5";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<option value="' . $row['office_name'] . '">' . $row['office_name'] . '</option>';
        }
    } else {
        echo '<option value="">No offices found</option>';
    }
    exit;
}

// Close database connection (after everything is done)
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Office Availability</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>

        .modal-title
        {
            color: white;

        }
        .modal-backdrop {
            display: none; 
        }
        .modal-content {
            background-color: white;
        }
        .office-dropdown {
            display: none;
            position: absolute;
            z-index: 1;
            width: 100%;
            max-height: 150px;
            overflow-y: auto;
        }
        .modal-body {
            padding: 30px;
        }
        .form-control {
            margin-bottom: 10px;
        }

        /* Custom button with maroon background */
.btn-primary {
    background-color: #800000; /* Maroon color */
    border-color: #800000; /* Border color matches the background */
    color: white; /* Text color */
}

/* Change to light maroon on hover */
.btn-primary:hover {
    background-color: #b22222; /* Light maroon */
    border-color: #b22222; /* Border color matches the hover background */
    color: white; /* Keep text white */
}

.modal-header
{
    background-color: maroon;
}

    </style>
</head>
<body>
    <div class="container mt-5">
        <!-- Modal -->
        <div class="modal fade show" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="false" style="display: block;">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="scheduleModalLabel">Set Feedback Schedule</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="window.location.href='offices.php';"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Success/Error message inside the modal -->
                        <?php if (isset($successMessage)): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo $successMessage; ?>
                            </div>
                        <?php elseif (isset($errorMessage)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $errorMessage; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Form -->
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="officeName" class="form-label">Office Name</label>
                                <input type="text" class="form-control" id="officeName" name="officeName" autocomplete="off" required>
                                <select class="form-select office-dropdown" id="officeDropdown" name="officeDropdown" style="display: none;">
                                    <!-- Options will be loaded here from the database -->
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="availabilityDate" class="form-label">Availability Date & Time</label>
                                <input type="datetime-local" class="form-control" id="availabilityDate" name="availabilityDate" required>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="applyToAll" name="applyToAll">
                                <label class="form-check-label" for="applyToAll">
                                    Apply to all offices
                                </label>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="window.location.href='setup.php';">Close</button>
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const officeInput = document.getElementById('officeName');
        const officeDropdown = document.getElementById('officeDropdown');

        officeInput.addEventListener('input', function() {
            const query = officeInput.value;

            if (query.length >= 2) {
                // Fetch matching office names from the server (AJAX request)
                fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?query=${query}`)
                    .then(response => response.text())
                    .then(data => {
                        officeDropdown.innerHTML = data;
                        officeDropdown.style.display = 'block';
                    });
            } else {
                officeDropdown.style.display = 'none';
            }
        });

        officeDropdown.addEventListener('click', function(event) {
            const selectedOffice = event.target.value;
            officeInput.value = selectedOffice;  // Set the selected office name to the input field
            officeDropdown.style.display = 'none'; // Hide dropdown after selection
        });

        document.addEventListener('click', function(event) {
            // Close dropdown if clicking outside of the input or dropdown
            if (!officeInput.contains(event.target) && !officeDropdown.contains(event.target)) {
                officeDropdown.style.display = 'none';
            }
        });
    </script>
</body>
</html>
