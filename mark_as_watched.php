<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized access");
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cinema_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Mark the movie as watched
$booking_id = $_POST['booking_id'];
$sql = "UPDATE bookings SET watched = 1 WHERE id = '$booking_id'";
$conn->query($sql);

$conn->close();
?>
