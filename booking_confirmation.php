<?php
session_start();

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

set_include_path(get_include_path() . PATH_SEPARATOR . 'C:/wamp64/www/CINEMA/tcpdf/');
require 'tcpdf.php';
require 'C:/wamp64/www/CINEMA/phpqrcode/qrlib.php';  // PHP QR Code path

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

// Get booking ID
$booking_id = $_GET['booking_id'];

// Fetch booking, movie, and user details along with mall, seating, date, and time information
$sql = "SELECT bookings.*, movies.title, users.name, users.surname, users.email, bookings.mall, bookings.seating, bookings.date, bookings.time_slot 
        FROM bookings 
        JOIN movies ON bookings.movie_id = movies.id 
        JOIN users ON bookings.user_id = users.id
        WHERE bookings.id = '$booking_id'";

$result = $conn->query($sql);
$booking = $result->fetch_assoc();

if (!$booking) {
    die("Booking not found.");
}

// Set price based on the ticket type
$ticket_type = $booking['ticket_type'];
$price = 0;

switch ($ticket_type) {
    case 'VIP':
        $price = 150.00; // VIP is R150
        break;
    case 'Standard':
        $price = 100.00; // Standard is R100
        break;
    case 'Children':
        $price = 50.00; // Children is R50
        break;
    case 'Disability':
        $price = 70.00; // Disability is R70
        break;
}

$user_name = $booking['name'];
$user_surname = $booking['surname'];
$user_email = $booking['email'];
$movie_title = $booking['title'];
$mall = $booking['mall'];
$seating = $booking['seating'];
$watch_date = $booking['date'];
$watch_time = $booking['time_slot'];  // Either morning, afternoon, evening

// Initialize the voucher code as null
$voucher_code = null;

// Check if the user is eligible for a voucher (only for VIP ticket type)
if ($ticket_type === 'VIP') {
    $voucher_sql = "SELECT * FROM vouchers WHERE user_id = '{$booking['user_id']}' AND used = 0 ORDER BY expiration_date DESC LIMIT 1";
    $voucher_result = $conn->query($voucher_sql);
    $voucher = $voucher_result->fetch_assoc();
    $voucher_code = $voucher ? $voucher['voucher_code'] : null;
}

// Generate QR Code
$qr_path = "qr_codes/booking_" . $booking_id . ".png";
$qr_data = "Booking ID: $booking_id, Name: $user_name $user_surname, Movie: $movie_title, Mall: $mall, Seating: $seating, Date: $watch_date, Time: $watch_time";
QRcode::png($qr_data, $qr_path);

// Store booking slip info in the database
$sql_insert = "INSERT INTO booking_slips (booking_id, qr_code_path) VALUES ('$booking_id', '$qr_path')";
$conn->query($sql_insert);

// Send email with booking info and QR code
$mail = new PHPMailer();
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'your email';
$mail->Password = 'idqe jstc txeq xbrj';  // Change this to your correct password or app password
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587;

$mail->setFrom('your email', 'Online Cinema');
$mail->addAddress($user_email);
$mail->isHTML(true);
$mail->Subject = 'Your Cinema Booking Confirmation';

$mailContent = "<h2>Booking Confirmation</h2>
                <p>Thank you, $user_name $user_surname, for booking to watch <strong>$movie_title</strong> at <strong>$mall</strong> in the <strong>$seating</strong> section.</p>
                <p><strong>Booking ID:</strong> $booking_id</p>
                <p><strong>Ticket Type:</strong> $ticket_type</p>
                <p><strong>Total Amount:</strong> R" . number_format($price, 2) . "</p>
                <p><strong>Date:</strong> $watch_date</p>
                <p><strong>Time:</strong> $watch_time</p>";

if ($voucher_code) {
    $mailContent .= "<p><strong>Your Voucher Code for Online Booking:</strong> $voucher_code (Valid for 10 days)</p>";
}

$mailContent .= "<p>Please find your QR code for theater entry attached.</p>";

$mail->Body = $mailContent;
$mail->addAttachment($qr_path);  // Attach QR code
$mail->send();

// Generate PDF with TCPDF
$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, "Booking Confirmation", 0, 1, 'C');
$pdf->Ln(10);
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, "Name: $user_name $user_surname", 0, 1);
$pdf->Cell(0, 10, "Movie: $movie_title", 0, 1);
$pdf->Cell(0, 10, "Mall: $mall", 0, 1);
$pdf->Cell(0, 10, "Seating Arrangement: $seating", 0, 1);
$pdf->Cell(0, 10, "Date: $watch_date", 0, 1);
$pdf->Cell(0, 10, "Time: $watch_time", 0, 1);
$pdf->Cell(0, 10, "Booking ID: $booking_id", 0, 1);
$pdf->Cell(0, 10, "Ticket Type: $ticket_type", 0, 1);
$pdf->Cell(0, 10, "Total Amount: R" . number_format($price, 2), 0, 1);

if ($voucher_code) {
    $pdf->Cell(0, 10, "Voucher Code: $voucher_code (Valid for 10 days)", 0, 1);
}

$pdf->Ln(10);
$pdf->Image($qr_path, 15, 140, 50, 50);  // Add QR code

$pdf_directory = "C:/wamp64/www/CINEMA/pdfs/";
if (!file_exists($pdf_directory)) {
    mkdir($pdf_directory, 0755, true);  // Create the directory if it doesn't exist
}

$pdf_path = $pdf_directory . "booking_" . $booking_id . ".pdf";
$pdf->Output($pdf_path, 'F');  // Save the PDF

// Update booking slip with PDF path
$conn->query("UPDATE booking_slips SET pdf_path = '$pdf_path' WHERE booking_id = '$booking_id'");

// Display booking slip
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - Online Cinema Booking</title>
    <link rel="stylesheet" href="styles/style.css">
   <style>
        body {
            background-image: url('images/sl.jpg');
            background-size: cover;
            color: #fff;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            margin: 0;
        }

        .booking-slip {
            background-color: rgba(34, 34, 34, 0.9);
            border-radius: 10px;
            padding: 20px;
            margin: 100px auto 0;  
            width: 60%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        h2, h3 {
            color: #9acd32;
        }

        .price {
            font-size: 20px;
            color: #ffcc00;
        }

        .qr-code {
            margin: 20px 0;
        }

        .qr-code img {
            width: 150px;
            height: 150px;
        }

        button {
            padding: 10px 20px;
            background-color: #9acd32;
            border: none;
            border-radius: 5px;
            color: #111;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #86b019;
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

        nav a {
            color: #9acd32;
            text-decoration: none;
            font-size: 18px;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        nav a:hover {
            color: #ffcc00;
        }

        body {
            padding-top: 30px;  
        }
    </style>

</head>
<body>

<header>
    <nav>
        <a href="profile.php">Profile</a>
        <a href="watch.php">Browse Movies</a>
        <a href="index.php">Logout</a>
    </nav>
</header>
<?php
$pdf_url = "/CINEMA/pdfs/booking_" . $booking_id . ".pdf";
?>
<div class="booking-slip">
    <h2>Booking Confirmation</h2>
    <p>Thank you, <?php echo htmlspecialchars($user_name . ' ' . $user_surname); ?>, for booking <strong><?php echo htmlspecialchars($movie_title); ?></strong><br>To watch at <strong><?php echo htmlspecialchars($mall); ?></strong> in the <strong><?php echo htmlspecialchars($seating); ?></strong> section.</p>
    <p><strong>Booking ID:</strong> <?php echo htmlspecialchars($booking_id); ?></p>
    <p><strong>Ticket Type:</strong> <?php echo htmlspecialchars($ticket_type); ?></p>
    <p><strong>Date:</strong> <?php echo htmlspecialchars($watch_date); ?></p>
    <p><strong>Time:</strong> <?php echo htmlspecialchars($watch_time); ?></p>  <!-- Time slot -->
    <p class="price"><strong>Total Amount:</strong> R<?php echo number_format($price, 2); ?></p>

    <?php if ($voucher_code): ?>
        <p><strong>Your Voucher Code for Online Booking:</strong> <?php echo htmlspecialchars($voucher_code); ?> (Valid for 10 days)</p>
    <?php endif; ?>

    <div class="qr-code">
        <h3>Your QR Code for Theater Entry</h3>
        <img src="<?php echo htmlspecialchars($qr_path); ?>" alt="QR Code">
    </div>

    <a href="<?php echo htmlspecialchars($pdf_url); ?>" download="booking_slip_<?php echo htmlspecialchars($booking_id); ?>.pdf">
        <button>Download Slip</button>
    </a>
</div>

</body>
</html>
