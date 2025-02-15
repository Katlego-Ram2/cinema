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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['booking_id'];
    $last_played_time = $_POST['last_played_time'];

    // Update last played time for the specific booking
    $sql = "UPDATE bookings SET last_played_time = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $last_played_time, $booking_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "Progress saved successfully.";
    } else {
        echo "Failed to save progress.";
    }

    $stmt->close();
}

$conn->close();
?>
