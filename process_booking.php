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

// Include the PHP QR code library
require 'phpqrcode/qrlib.php';

// Capture booking details from the form
$user_id = $_SESSION['user_id'];
$movie_id = $_POST['movie_id'];
$ticket_type = $_POST['ticket_type'];
$watch_option = $_POST['watch_option'];
$use_wallet = isset($_POST['use_wallet']) ? true : false;
$use_voucher = isset($_POST['use_voucher']) ? true : false;  // Capture if user is using a voucher
$voucher_code = $_POST['voucher_code'] ?? null;  // Capture voucher code if provided
$mall = $_POST['mall'] ?? null;
$seating = $_POST['seating'] ?? null;
$date = $_POST['date'] ?? null;
$time_slot = $_POST['time_slot'] ?? null;
$card_number = $_POST['card_number'] ?? null;
$expiry_date = $_POST['expiry_date'] ?? null;
$cvv = $_POST['cvv'] ?? null;

// Check for duplicate online bookings for the same movie
$duplicate_check_sql = "SELECT * FROM bookings WHERE user_id='$user_id' AND movie_id='$movie_id' AND watch_option='online' AND played=0";
$duplicate_result = $conn->query($duplicate_check_sql);

// If the booking is found and not played, block the booking
if ($duplicate_result->num_rows > 0 && $watch_option == 'online') {
    // Redirect back to booking.php with an error message
    header("Location: booking.php?movie_id=$movie_id&error=duplicate_booking");
    exit();
}

// Set base price depending on watch option
$base_price = ($watch_option == 'online') ? 0.00 : 0.00; // No base charge for online or cinema yet

// Set ticket price based on the ticket type (removing any extra charges)
switch ($ticket_type) {
    case 'VIP': 
        $ticket_price = 150.00; 
        break;      
    case 'Standard': 
        $ticket_price = 100.00; 
        break; 
    case 'Children': 
        $ticket_price = 50.00;  
        break;  
    case 'Disability': 
        $ticket_price = 70.00;  
        break; 
    default: 
        $ticket_price = 0.00; // Default to 0 if no valid ticket type is selected
}

// Add ticket price to the base price
$final_price = $base_price + $ticket_price;

// Voucher payment logic
if ($use_voucher && $voucher_code) {
    // Debugging step: Check if voucher_code and use_voucher are correctly received
    error_log("Voucher Code: $voucher_code, Use Voucher: " . ($use_voucher ? 'Yes' : 'No'));

    // Check if the voucher is valid and not used
    $voucher_check_sql = "SELECT * FROM vouchers WHERE voucher_code='$voucher_code' AND user_id='$user_id' AND used=0 AND expiration_date > NOW()";
    $voucher_result = $conn->query($voucher_check_sql);

    if ($voucher_result->num_rows > 0) {
        // Voucher is valid, mark it as used
        $sql_update_voucher = "UPDATE vouchers SET used=1 WHERE voucher_code='$voucher_code' AND user_id='$user_id'";
        if ($conn->query($sql_update_voucher) === TRUE) {
            $final_price = 0.00; // Set final price to 0 as voucher covers full amount
        } else {
            header("Location: booking.php?movie_id=$movie_id&error=voucher_not_applied");
            exit();
        }
    } else {
        header("Location: booking.php?movie_id=$movie_id&error=invalid_voucher");
        exit();
    }
}


// Fetch wallet balance
$sql_wallet = "SELECT balance FROM wallets WHERE user_id='$user_id'";
$result_wallet = $conn->query($sql_wallet);
$wallet = $result_wallet->fetch_assoc();
$wallet_balance = $wallet ? $wallet['balance'] : 0.00;

// Check if wallet balance is enough for payment
if ($use_wallet && $wallet_balance >= $final_price) {
    // Deduct from wallet balance
    $new_balance = $wallet_balance - $final_price;
    $sql_update_wallet = "UPDATE wallets SET balance='$new_balance', last_updated=NOW() WHERE user_id='$user_id'";
    $conn->query($sql_update_wallet);

    // No need for card payment, process booking
    $card_number = null; // Since wallet is used, we don't store card details
    $payment_status = 'Completed'; // Wallet payment is considered completed
} else if ($use_wallet && $wallet_balance > 0) {
    // Partially deduct wallet balance and use card for the remaining amount
    $remaining_amount = $final_price - $wallet_balance;
    $new_balance = 0;  // Deduct the full wallet balance
    $sql_update_wallet = "UPDATE wallets SET balance='$new_balance', last_updated=NOW() WHERE user_id='$user_id'";
    $conn->query($sql_update_wallet);

    // Process remaining amount with the card
    $payment_status = processCardPayment($card_number, $expiry_date, $cvv, $remaining_amount);
} else {
    // No wallet used or insufficient wallet balance, process full card payment
    $payment_status = processCardPayment($card_number, $expiry_date, $cvv, $final_price);
}

// Function to process card payment (simulate success for now)
function processCardPayment($card_number, $expiry_date, $cvv, $amount) {
    // Here you would integrate with a payment gateway.
    // For now, we'll simulate a successful payment:
    return ($card_number && $expiry_date && $cvv) ? 'Completed' : 'Failed';
}

// Insert booking into the database
$sql = "INSERT INTO bookings (user_id, movie_id, ticket_type, watch_option, card_number, mall, seating, date, time_slot, played, status) 
        VALUES ('$user_id', '$movie_id', '$ticket_type', '$watch_option', '$card_number', '$mall', '$seating', '$date', '$time_slot', 0, 'Paid')"; // Set status as 'Paid'

if ($conn->query($sql) === TRUE) {
    $booking_id = $conn->insert_id; // Get the last inserted booking ID

    // Record payment details in the payments table
    $amount_paid = ($payment_status == 'Completed') ? $final_price : 0.00;
    $payment_method = $use_wallet ? 'Wallet' : 'Card';

    // Insert payment record
    $sql_payment = "INSERT INTO payments (user_id, booking_id, amount, payment_method, date, status) 
                    VALUES ('$user_id', '$booking_id', '$amount_paid', '$payment_method', NOW(), 'Paid')";

    if ($conn->query($sql_payment) === FALSE) {
        echo "Error: Could not record payment. " . $conn->error;
        exit();
    }

    // Generate voucher only for cinema bookings and VIP tickets
    if ($watch_option == 'cinema' && $ticket_type === 'VIP') {
        // Generate a random 10-digit voucher code
        $voucher_code = str_pad(rand(0, 9999999999), 10, '0', STR_PAD_LEFT);

        // Set expiration date for the voucher (valid for 10 days)
        $expiration_date = date('Y-m-d H:i:s', strtotime('+10 days'));

        // Insert the voucher into the vouchers table
        $insert_voucher_sql = "INSERT INTO vouchers (user_id, voucher_code, expiration_date, used)
                               VALUES ('$user_id', '$voucher_code', '$expiration_date', 0)";

        if ($conn->query($insert_voucher_sql) === FALSE) {
            echo "Error: Could not generate voucher. " . $conn->error;
            exit();
        }
    }

    // Fetch movie details for QR code generation
    $movie_sql = "SELECT title FROM movies WHERE id='$movie_id'";
    $movie_result = $conn->query($movie_sql);
    $movie = $movie_result->fetch_assoc();
    $movie_title = $movie['title'];

    // Generate QR code data
    $qr_data = "Booking ID: $booking_id, Movie: $movie_title, User ID: $user_id, Ticket Type: $ticket_type, Mall: $mall, Seating: $seating, Date: $date, Time: $time_slot";

    // Define the path to save the QR code
    $qr_path = "qr_codes/booking_$booking_id.png";

    // Generate the QR code
    QRcode::png($qr_data, $qr_path);

    // Redirect to profile or booking confirmation
    if ($watch_option == 'online') {
        header("Location: profile.php"); // Redirect to profile for online bookings
    } else {
        header("Location: booking_confirmation.php?booking_id=$booking_id"); // Redirect to booking confirmation for cinema bookings
    }
    exit(); // Ensure script termination after redirection
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>
