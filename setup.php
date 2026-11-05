<!-- setup.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup</title>
    <style>
        /* Body styles */
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: url('dashboard_background_image.jpg') no-repeat center center fixed;
            background-size: cover;
            color: white;
            overflow: hidden;
        }

        /* Modal Styles */
        .modal {
            display: block; /* Auto show modal on page load */
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.36); /* Dark overlay background */
            padding-top: 60px;
            transition: opacity 0.4s ease;
            top: -40px;
        }

        /* Modal Content */
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 40px;
            border-radius: 12px;
            width: 80%;
            max-width: 600px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.4);
            transition: transform 0.4s ease, opacity 0.4s ease;
            opacity: 1;
        }

        /* Modal Header */
        .modal-header {
            text-align: center;
            font-size: 30px;
            color: maroon;
            margin-bottom: 20px;
            font-weight: bold;
            position: relative;
        }

        /* Modal Body */
        .modal-body {
            text-align: center;
        }

        /* Modal Links */
        .modal-body ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 15px; /* Space between buttons */
        }

        .modal-body li {
            display: block;
            width: 100%;
        }

        .modal-body a {
            display: inline-block;
            font-size: 18px;
            color: white;
            background-color: maroon;
            text-decoration: none;
            padding: 10px 10px; /* Make buttons square */
            border: 2px solid maroon;
            border-radius: 8px;
            width: 70%;
            text-align: center;
            transition: background-color 0.3s, color 0.3s;
            box-sizing: border-box; /* Ensure buttons don't overflow */
        }

        .modal-body a:hover {
            background-color: #f0f0f0;
            color: maroon;
        }

        /* Close (X) Button */
        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            color: maroon;
            font-size: 30px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-btn:hover {
            color: #800000; /* Darker maroon on hover */
        }

    </style>
</head>
<body>

    <!-- Modal -->
    <div id="setupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Choose Setup Option</h2>
                <!-- X Button to close modal -->
                <button class="close-btn" id="closeModalBtn">&times;</button>
            </div>
            <div class="modal-body">
                <ul>

                    <li><a href="schedule_modal.php">Feedback Schedule</a></li>
                    <li><a href="offices.php">Office / Colleges</a></li>
                    <li><a href="view_admins.php">Admins</a></li>
                    <li><a href="view_faculty_staff.php">Faculty / Staff List</a></li>
                    <li><a href="view_students.php">Student / Client List</a></li>
                    <li><a href="qr_code_generate.php">Qr Code Link Generation</a></li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Get the modal and close button
        const modal = document.getElementById('setupModal');
        const closeModalBtn = document.getElementById('closeModalBtn');

        // Close the modal when the "X" button is clicked
        closeModalBtn.onclick = function() {
            modal.style.display = 'none';
            window.location.href = 'dashboard.php'; // Redirect to dashboard when modal closes
        }

        // Close the modal if clicked outside of the modal content
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
                window.location.href = 'dashboard.php'; // Redirect to dashboard when modal closes
            }
        }
    </script>

</body>
</html>
