<?php
$qrLink = "";
if (file_exists("latest_qr_link.txt")) {
    $qrLink = trim(file_get_contents("latest_qr_link.txt"));
}
$qrImage = !empty($qrLink) ? "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrLink) : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generated QR Code</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { background-color: #ffffff; color: #333; display: flex; justify-content: center; align-items: center; height: 100vh; flex-direction: column; }
        .container { background: #fff; padding: 20px; box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1); border-radius: 8px; text-align: center; width: 400px; }
        h2 { color: maroon; margin-bottom: 15px; }
        .qr-container { margin-top: 20px; }
        img { width: 200px; height: 200px; border-radius: 8px; }
        button { width: 100%; padding: 10px; background-color: maroon; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-top: 15px; }
        button:hover { background-color: darkred; }
    </style>
</head>
<body>

    <div class="container">
        <h2>Generated QR Code</h2>
        <div class="qr-container">
            <?php if (!empty($qrLink)) { ?>
                <img id="qrImage" src="<?= $qrImage ?>" alt="QR Code">
                <p>QR Code for: <strong><?= htmlspecialchars($qrLink) ?></strong></p>
                <button onclick="downloadQRCode()">Download QR Code</button>
            <?php } else { ?>
                <p>No QR Code found. Generate one first.</p>
            <?php } ?>
        </div>
        <button onclick="goBack()">Back</button>
    </div>

    <script>
       function goBack() {
    window.history.back();
}

        function downloadQRCode() {
            let qrImage = document.getElementById("qrImage").src;
            let link = document.createElement("a");
            link.href = qrImage;
            link.download = "qr_code.png"; 
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>

</body>
</html>
