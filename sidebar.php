

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Sidebar </title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <style>

.sidebar {
            width: 250px;
            background-color: #800000;
            padding: 20px;
            box-shadow: 5px 0 5px rgba(189, 189, 189, 0.5);
            display: flex;
            flex-direction: column;
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
        </style>

<link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
</head>
<body>


<div class="sidebar">
    <h1>Admin/ Faculty Panel</h1>
    <h2>  <?php echo htmlspecialchars($_SESSION['admin_username'] ?? $_SESSION['faculty_username'] ?? 'Guest'); ?></h2>


    <a href="profile.php">Edit Account</a>
    <a href="dashboard.php">Main Dashboard</a>
    <a href="offices.php">Offices</a>
    <a href="view_admins.php">Admins</a>
    <a href="view_faculty_staff.php">Faculty / Staff List</a>
    <a href="view_students.php">Student / Client List</a>
    <a href="history.php">History</a>
    <a href="track_alert.php">Failed Login Attempts</a>
 

    
    <a href="logout.php">Log Out</a>
</div>

</body>
</html>