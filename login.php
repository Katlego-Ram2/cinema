<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cinema_db";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// If form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Validate login credentials
    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Fetch the user's details
        $user = $result->fetch_assoc();
        
        // Verify the hashed password
        if (password_verify($password, $user['password'])) {
            // Set the session with user details
            $_SESSION['user_id'] = $user['id']; // Storing the user_id in the session
            $_SESSION['user_name'] = $user['name']; // Storing the user's name in the session
            $_SESSION['user_email'] = $user['email'];

            // Check if the user is an admin
            if ($user['role'] === 'admin') {
                $_SESSION['admin'] = true; // Set admin session variable
                header("Location: dashboard.php"); // Redirect to the admin dashboard
            } else {
                header("Location: watch.php"); // Regular user redirect
            }
            exit();
        } else {
            $error_message = "Invalid email or password!";
        }
    } else {
        $error_message = "Invalid email or password!";
    }
}
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Online Cinema Booking</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        body {
            background-image: url('images/3d.jpg');
            background-size: cover;
            background-attachment: fixed;
            color: #fff;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: rgba(34, 34, 34, 0.95);
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

<header>
    <nav>
        <a href="movies.php">Browse Movies</a>
        <a href="index.php">Home</a>
    </nav>
</header>

<div class="login-container">
    <h2>Login</h2>

    <?php
    if (!empty($error_message)) {
        echo "<div class='error-message'>$error_message</div>";
    }
    ?>

    <form action="login.php" method="POST">
        <input type="email" name="email" placeholder="Enter your email" required>
        <input type="password" name="password" placeholder="Enter your password" required>
        <button type="submit">Login</button>
    </form>

    <p>Don't have an account? <a href="register.php">Register Here</a></p>
    <p>Forgot your password? <a href="forgot_password.php">Reset Here</a></p>
</div>
</body>
</html>
