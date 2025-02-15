<?php
session_start();

if (!isset($_SESSION['user_name'])) {
    exit('Unauthorized');
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cinema_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$booking_id = $_POST['booking_id'];
$user_id = $_SESSION['user_id'];

// Update the 'played' status for the booking
$sql = "UPDATE bookings SET played = 1 WHERE id = '$booking_id' AND user_id = '$user_id'";
$conn->query($sql);

$conn->close();
?>
