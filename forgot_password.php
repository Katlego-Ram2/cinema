<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cinema_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];

    // Check if the email exists
    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $reset_token = bin2hex(random_bytes(50)); // Generate a random token
        $reset_expires = date("Y-m-d H:i:s", strtotime("+3 hour")); // Token expires in 1 hour

        // Store the reset token and expiry in the database
        $update_sql = "UPDATE users SET reset_token='$reset_token', reset_expires='$reset_expires' WHERE email='$email'";
        $conn->query($update_sql);

        // Send email with the reset link
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your email';
        $mail->Password = 'idqe jstc txeq xbrj';  // Change this to your correct password or app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('@gmail', 'Online Cinema');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $reset_link = "http://localhost/CINEMA/reset_password.php?token=$reset_token";
        $mail->Body = "<p>Click <a href='$reset_link'>here</a> to reset your password. This link will expire in 1 hour.</p>";

        if ($mail->send()) {
            $success_message = "An email has been sent to $email with instructions to reset your password.";
        } else {
            $error_message = "Failed to send email. Please try again later.";
        }
    } else {
        $error_message = "No account found with that email address.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        body {
            background-image: url('images/3d.jpg');
            background-size: cover;
            background-attachment: fixed; /* Fix the background in place */
            color: #fff;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        /* Fixed Header Styles */
        header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: rgba(34, 34, 34, 0.95);  /* Slight transparency */
            padding: 15px 0;
            z-index: 1000;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
        }

        nav {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .login-container {
            background-color: rgba(34, 34, 34, 0.8);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            color: #9acd32;
            text-align: center;
            margin-bottom: 20px;
        }

        .login-container form {
            display: flex;
            flex-direction: column;
        }

        .login-container input[type="email"],
        .login-container input[type="password"] {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #444;
            background-color: #333;
            color: #fff;
        }

        .login-container button {
            padding: 12px;
            border: none;
            border-radius: 5px;
            background-color: #9acd32;
            color: #111;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .login-container button:hover {
            background-color: #86b019;
        }

        .error-message {
            color: #ff4c4c;
            text-align: center;
            margin-bottom: 15px;
        }

        .login-container p {
            text-align: center;
        }

        .login-container a {
            color: #9acd32;
            text-decoration: none;
        }

        .login-container a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Forgot Password</h2>

        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php elseif (!empty($success_message)): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <form action="forgot_password.php" method="POST">
            <input type="email" name="email" placeholder="Enter your email" required>
            <button type="submit">Send Reset Link</button>
        </form>

        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>
