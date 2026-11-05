<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $link = trim($_POST["link"]);

    if (!empty($link)) {
        file_put_contents("latest_qr_link.txt", $link); // Save the link in a file
    }

    header("Location: qr_code.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Generator</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { background-color: #ffffff; color: #333; display: flex; justify-content: center; align-items: center; height: 100vh; flex-direction: column; }
        .container { background: #fff; padding: 20px; box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1); border-radius: 8px; text-align: center; width: 400px; }
        h2 { color: maroon; margin-bottom: 15px; }
        input, button { width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 5px; font-size: 16px; }
        input { border: 1px solid #ccc; }
        button { background-color: maroon; color: white; border: none; cursor: pointer; }
        button:hover { background-color: darkred; }
    </style>
</head>
<body>

    <div class="container">
        <h2>QR Code Generator</h2>
        <form method="POST">
            <input type="text" name="link" placeholder="Enter your link..." required>
            <button type="submit">Generate QR Code</button>
        </form>
        <button onclick="window.location.href='qr_code.php'">View QR Code</button>
        <button onclick="goBack()">Back</button>
    </div>
    <script>
       function goBack() {
    window.history.back();
}
</script>
</body>
</html>
