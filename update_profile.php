<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cinema_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$name = $_POST['name'];
$surname = $_POST['surname'];
$email = $_POST['email'];
$password = $_POST['password'];

// Update query (we'll only update the password if it's set)
if (!empty($password)) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "UPDATE users SET name='$name', surname='$surname', email='$email', password='$hashed_password' WHERE id='$user_id'";
} else {
    $sql = "UPDATE users SET name='$name', surname='$surname', email='$email' WHERE id='$user_id'";
}

if ($conn->query($sql) === TRUE) {
    $_SESSION['name'] = $name;
    $_SESSION['surname'] = $surname;
    $_SESSION['email'] = $email;
    header("Location: profile.php");
} else {
    echo "Error updating profile: " . $conn->error;
}

$conn->close();
?>
