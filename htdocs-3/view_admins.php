<?php
session_start();

// Check if the admin is logged in (by checking the session for the admin's username)
if (!isset($_SESSION['admin_username'])) {
    // If admin is not logged in, redirect to the login page
    header("Location: admin_login.php");
    exit();
}

// Database connection
$host = "localhost";
$dbusername = "root"; // Database username
$dbpassword = ""; // Database password
$dbname = "feedback_system";
$conn = new mysqli($host, $dbusername, $dbpassword, $dbname);

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch admin data
$sql = "SELECT username, email FROM admins ORDER BY username ASC";
$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Admins</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color:rgb(253, 253, 253);
            color:rgb(0, 0, 0);
            margin: 0;
            padding: 20px;
        }
        table {
    width: 90%;  /* Sets the width of the table */
    border-collapse: collapse;  /* Ensures table borders collapse into a single border */
    margin-top: 20px;  /* Adjust the top margin for spacing */
    margin-left: 265px;  /* Centers the table horizontally */
    margin-right: auto;  /* Centers the table horizontally */
}

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid rgb(0, 0, 0);
        }
        th {
            background-color: #800000;
            color: #ffffff;
        }
        tr:nth-child(even) {
            background-color:rgb(191, 191, 191);
        }
        tr:hover {
            background-color:rgb(214, 0, 0);
        }
        .back-btn {
    display: inline-block;
    position: fixed;  /* Fixes the button at the top of the screen */
    top: 5px;  /* Adjust this value to control the distance from the top */
    left: 5%;  /* Centers the button horizontally */
    transform: translateX(-50%);  /* Corrects the centering */
    padding: 10px 20px;
    background-color: #800000;  /* Maroon background for the button */
    color: #ffffff;
    text-decoration: none;
    border-radius: 4px;
    z-index: 10;  /* Ensures it stays on top of other elements */
}


        .back-btn:hover {
            background-color:rgb(231, 0, 0); /* Darker purple on hover */
        }


        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0,0,0);
            background-color: rgba(0,0,0,0.4);
            padding-top: 60px;
        }

        .modal-content {
            background-color: #333;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            color: white;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }

        h1 {
    margin-top: 30px;  /* Adjust the top margin for spacing */
    text-align: center;  /* Centers the h1 element horizontally */
}

.sidebar {
    width: 250px;
    background-color: #800000;
    padding: 20px;
    box-shadow: 5px 0 5px rgba(189, 189, 189, 0.5);
    display: flex;
    flex-direction: column;
    position: fixed;
    height: 1000px;
    top: -20px;
    left: -8px; /* Moves the sidebar 20px to the left */
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
</head>
<body>

<div class="sidebar">
    <h1>Admin/ Faculty Panel</h1>
    <h2>  <?php echo htmlspecialchars($_SESSION['admin_username'] ?? $_SESSION['faculty_username'] ?? 'Guest'); ?></h2>

    <a href="profile.php">Overall Feedback</a>
    <a href="dashboard.php">Individual Feedback</a>
    <a href="setup.php">Setup</a>
    <a href="history.php">History</a>
    <a href="track_alert.php">Failed Login Attempts</a>
   

    <a href="logout.php">Log Out</a>
</div>



    <h1>Admins List</h1>
    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr class='admin-row' data-username='" . htmlspecialchars($row['username']) . "'>";
                    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='2'>No admins found</td></tr>";
            }
            ?>
        </tbody>
    </table>

    

    <!-- Modal for Admin Profile -->
    <div id="adminModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Admin Profile</h2>
            <div id="adminProfileDetails">
                <!-- Admin profile details will be shown here -->
            </div>
        </div>
    </div>

    <script>
        // Get the modal
        var modal = document.getElementById("adminModal");
        var closeModal = document.getElementsByClassName("close")[0];

        // Add click event to each row to open modal
        document.querySelectorAll(".admin-row").forEach(function(row) {
            row.addEventListener("click", function() {
                var username = this.getAttribute("data-username");

                // Fetch and display admin profile details
                fetchAdminProfile(username);
            });
        });

// Function to fetch admin profile
function fetchAdminProfile(username) {
    fetch("get_admin_profile.php?username=" + username)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var profileHTML = "<p><strong>Username:</strong> " + data.username + "</p>";
                profileHTML += "<p><strong>Email:</strong> " + data.email + "</p>";

                // Check if the profile picture exists
                if (data.profile_pic) {
                    profileHTML += "<p><strong>Profile Picture:</strong><br><img src='" + data.profile_pic + "' alt='Profile Picture' class='profile-img'></p>";
                } else {
                    profileHTML += "<p><strong>Profile Picture:</strong><br>No picture available</p>";
                }

                document.getElementById("adminProfileDetails").innerHTML = profileHTML;
                modal.style.display = "block";
            } else {
                alert("Admin profile not found.");
            }
        })
        .catch(error => {
            console.error("Error fetching admin profile:", error);
            alert("Failed to fetch profile.");
        });
}

        // When the user clicks on <span> (x), close the modal
        closeModal.onclick = function() {
            modal.style.display = "none";
        }

        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target === modal) {
                modal.style.display = "none";
            }
        }

        
    </script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>
