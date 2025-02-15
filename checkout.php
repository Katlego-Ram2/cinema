<?php
session_start();

if (!isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit();
}

$movie_id = $_GET['movie_id'];
$price = $_GET['price'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Booking Slip</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        body {
            background-color: #111;
            color: #fff;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
        }

        .booking-slip {
            background-color: #222;
            border-radius: 10px;
            padding: 20px;
            margin: 0 auto;
            width: 50%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body>

<div class="booking-slip">
    <h2>Booking Confirmation</h2>
    <p>Thank you for booking to watch this movie at the cinema/theater.</p>
    <p><strong>Movie ID:</strong> <?php echo htmlspecialchars($movie_id); ?></p>
    <p><strong>Total Amount:</strong> $<?php echo htmlspecialchars($price); ?></p>
    <p><strong>Date:</strong> <?php echo date("Y-m-d H:i:s"); ?></p>
</div>

</body>
</html>
