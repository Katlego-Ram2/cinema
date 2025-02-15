<?php
session_start();

if (!isset($_SESSION['user_name'])) {
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

$booking_id = $_POST['booking_id'];
$user_id = $_SESSION['user_id'];

// Fetch booking details
$sql = "SELECT * FROM bookings WHERE id = '$booking_id' AND user_id = '$user_id'";
$result = $conn->query($sql);
$booking = $result->fetch_assoc();

if (!$booking) {
    die("Booking not found.");
}

// Fetch movie details
$movie_sql = "SELECT release_date FROM movies WHERE id = '" . $booking['movie_id'] . "'";
$movie_result = $conn->query($movie_sql);
$movie = $movie_result->fetch_assoc();

if (!$movie) {
    die("Movie not found.");
}

// Check if the movie has been played (if played, it can't be canceled)
if ($booking['played'] == 1) {
    $_SESSION['message'] = "This movie has already been played and cannot be canceled.";
    header("Location: profile.php");
    exit();
}

// Calculate the refund amount based on the ticket type
$refund_amount = 0;
switch ($booking['ticket_type']) {
    case 'VIP':
        $refund_amount = 150.00; // VIP is R150
        break;
    case 'Standard':
        $refund_amount = 100.00; // Standard is R100
        break;
    case 'Children':
        $refund_amount = 50.00; // Children is R50
        break;
    case 'Disability':
        $refund_amount = 70.00; // Disability is R70
        break;
}

// Update wallet balance
$wallet_sql = "SELECT balance FROM wallets WHERE user_id = '$user_id'";
$wallet_result = $conn->query($wallet_sql);
$wallet = $wallet_result->fetch_assoc();

if ($wallet) {
    $new_balance = $wallet['balance'] + $refund_amount;
    $update_wallet_sql = "UPDATE wallets SET balance = '$new_balance', last_updated = NOW() WHERE user_id = '$user_id'";
    $conn->query($update_wallet_sql);
} else {
    // If wallet doesn't exist, create a new one
    $insert_wallet_sql = "INSERT INTO wallets (user_id, balance) VALUES ('$user_id', '$refund_amount')";
    $conn->query($insert_wallet_sql);
}

// Delete the booking
$delete_booking_sql = "DELETE FROM bookings WHERE id = '$booking_id'";
$conn->query($delete_booking_sql);

// Redirect back to profile with a success message
$_SESSION['message'] = "Booking canceled successfully. A refund of R" . number_format($refund_amount, 2) . " has been credited to your wallet.";
header("Location: profile.php");

$conn->close();
?>
