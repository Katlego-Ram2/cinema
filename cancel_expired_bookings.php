<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cinema_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all online bookings that are older than 24 hours and haven't been watched
$sql = "SELECT bookings.id, bookings.user_id, bookings.movie_id, bookings.ticket_type, bookings.booking_time, wallets.balance 
        FROM bookings 
        JOIN wallets ON bookings.user_id = wallets.user_id
        WHERE bookings.watch_option = 'online' AND bookings.watched = 0 AND TIMESTAMPDIFF(HOUR, bookings.booking_time, NOW()) > 24";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($booking = $result->fetch_assoc()) {
        $booking_id = $booking['id'];
        $user_id = $booking['user_id'];
        $ticket_type = $booking['ticket_type'];
        $wallet_balance = $booking['balance'];

        // Calculate the refund based on the ticket type
        $refund_amount = 10.00; // Base price for online viewing
        switch ($ticket_type) {
            case 'VIP': $refund_amount += 10.00; break;
            case 'Children': $refund_amount -= 5.00; break;
            case 'Disability': $refund_amount -= 3.00; break;
        }

        // Update the user's wallet balance
        $new_balance = $wallet_balance + $refund_amount;
        $update_wallet_sql = "UPDATE wallets SET balance = '$new_balance', last_updated = NOW() WHERE user_id = '$user_id'";
        $conn->query($update_wallet_sql);

        // Mark the booking as cancelled
        $delete_booking_sql = "DELETE FROM bookings WHERE id = '$booking_id'";
        $conn->query($delete_booking_sql);

        // Optionally, log the refund action
        echo "Booking ID $booking_id has been cancelled and $$refund_amount refunded to user $user_id.\n";
    }
} else {
    echo "No bookings found for cancellation.";
}

$conn->close();
?>
