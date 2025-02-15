<?php
session_start();

if (!isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit();
}

// Fetch the watch option and booking ID from the URL or session
$watch_option = isset($_GET['watch_option']) ? $_GET['watch_option'] : 'online';
$booking_id = isset($_GET['booking_id']) ? $_GET['booking_id'] : 0;

// Determine the redirect URL
if ($watch_option === 'cinema') {
    $redirect_url = "booking_confirmation.php?booking_id=" . $booking_id;
} else {
    $redirect_url = "profile.php";
}

// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #111;
            color: #fff;
            font-family: Arial, sans-serif;
            margin: 0;
        }

        .container {
            text-align: center;
            padding: 20px;
            background-color: rgba(34, 34, 34, 0.8);
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            width: 400px;
        }

        h2 {
            color: #9acd32;
            margin-bottom: 20px;
        }

        p {
            font-size: 16px;
            margin-bottom: 10px;
        }

        .redirect-message {
            color: #ffcc00;
            margin-top: 20px;
        }
    </style>
    <script>
        // Auto redirect after 5 seconds
        setTimeout(function() {
            window.location.href = "<?php echo $redirect_url; ?>";
        }, 5000);
    </script>
</head>
<body>

<div class="container">
    <h2>Payment Successful</h2>
    <p>Your payment has been successfully processed!</p>

    <?php if ($watch_option === 'online'): ?>
        <p>You will be redirected to your profile shortly.</p>
    <?php else: ?>
        <p>You will be redirected to the booking confirmation page shortly.</p>
    <?php endif; ?>

    <p class="redirect-message">Please wait...</p>
</div>

</body>
</html>

<?php
// Send the output buffer and flush
ob_end_flush();
?>
