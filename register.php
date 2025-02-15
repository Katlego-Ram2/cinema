<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cinema_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success_message = ""; // Initialize success message

// If form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $surname = $_POST['surname'];
    $email = $_POST['email'];
    $age = $_POST['age'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate password and confirm password
    if ($password !== $confirm_password) {
        $error_message = "Passwords do not match!";
    } else {
        // Check if email already exists
        $check_email = "SELECT * FROM users WHERE email='$email'";
        $result = $conn->query($check_email);

        if ($result->num_rows > 0) {
            $error_message = "Email already registered!";
        } else {
            // Hash the password before storing it in the database
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // Insert new user into the database
            $sql = "INSERT INTO users (name, surname, email, age, password) VALUES ('$name', '$surname', '$email', '$age', '$hashed_password')";
            if ($conn->query($sql) === TRUE) {
                $success_message = "Registration successful! Redirecting to login...";
            } else {
                $error_message = "Error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Online Cinema Booking</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        body {
            background-image: url('images/view.jpg');
            background-size: cover;
            background-attachment: fixed;
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

        .register-container {
            background-color: rgba(34, 34, 34, 0.8);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
            margin-top: 55px;
        }

        h2 {
            color: #9acd32;
            text-align: center;
            margin-bottom: 20px;
        }

        .register-container form {
            display: flex;
            flex-direction: column;
        }

        .register-container input[type="text"],
        .register-container input[type="email"],
        .register-container input[type="number"],
        .register-container input[type="password"] {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #444;
            background-color: #333;
            color: #fff;
        }

        .register-container button {
            padding: 12px;
            border: none;
            border-radius: 5px;
            background-color: #9acd32;
            color: #111;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .register-container button:hover {
            background-color: #86b019;
        }

        .error-message, .success-message {
            text-align: center;
            margin-bottom: 15px;
        }

        .error-message {
            color: #ff4c4c;
        }

        .success-message {
            color: #9acd32;
        }

        .register-container p {
            text-align: center;
        }

        .register-container a {
            color: #9acd32;
            text-decoration: none;
        }

        .register-container a:hover {
            text-decoration: underline;
        }
    </style>
    <script>
        // Redirect to login page after 3 seconds if registration is successful
        function redirectToLogin() {
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 3000);
        }
    </script>
</head>
<body>
    <!-- Header Section -->
    <header>
        <nav>
            <a href="movies.php">Browse Movies</a>
            <a href="index.php">Home</a>
        </nav>
    </header>

    <div class="register-container">
        <h2>Create an Account</h2>

        <?php
        if (!empty($error_message)) {
            echo "<div class='error-message'>$error_message</div>";
        }

        if (!empty($success_message)) {
            echo "<div class='success-message'>$success_message</div>";
            echo "<script>redirectToLogin();</script>"; // Trigger the redirect after 3 seconds
        }
        ?>

        <form action="register.php" method="POST">
            <input type="text" name="name" placeholder="Enter your name" required>
            <input type="text" name="surname" placeholder="Enter your surname" required>
            <input type="email" name="email" placeholder="Enter your email" required>
            <input type="number" name="age" placeholder="Enter your age" required>
            <input type="password" name="password" placeholder="Enter your password" required>
            <input type="password" name="confirm_password" placeholder="Confirm your password" required>
            <button type="submit">Register</button>
        </form>

        <p>Already have an account? <a href="login.php">Login Here</a></p>
    </div>
</body>
</html>
