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

// Check if token is valid
$token = $_GET['token'] ?? null;

if (!$token) {
    die("Invalid reset token.");
}

$sql = "SELECT * FROM users WHERE reset_token='$token' AND reset_expires > NOW()";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Invalid or expired reset token.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['password'];
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    // Update the user's password and invalidate the reset token
    $sql_update = "UPDATE users SET password='$hashed_password', reset_token=NULL, reset_expires=NULL WHERE reset_token='$token'";
    if ($conn->query($sql_update) === TRUE) {
        header("Location: login.php?message=Password reset successfully");
    } else {
        $error_message = "Error updating password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
        <h2>Reset Password</h2>

        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
            <input type="password" name="password" placeholder="Enter your new password" required>
            <button type="submit">Reset Password</button>
        </form>

        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>
