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

$movie_id = $_GET['movie_id'];

// Fetch movie details
$sql = "SELECT * FROM movies WHERE id='$movie_id'";
$result = $conn->query($sql);
$movie = $result->fetch_assoc();

// Fetch user's wallet balance
$sql_wallet = "SELECT balance FROM wallets WHERE user_id='" . $_SESSION['user_id'] . "'";
$result_wallet = $conn->query($sql_wallet);
$wallet = $result_wallet->fetch_assoc();
$wallet_balance = $wallet ? $wallet['balance'] : 0.00;

// Check if the movie is already booked but not played
$sql_check = "SELECT * FROM bookings WHERE user_id='" . $_SESSION['user_id'] . "' AND movie_id='$movie_id' AND watch_option='online' AND played != 1";
$result_check = $conn->query($sql_check);
$already_booked = $result_check->num_rows > 0;

// Check if user has a valid unused voucher
$sql_voucher = "SELECT * FROM vouchers WHERE user_id='" . $_SESSION['user_id'] . "' AND used=0 AND expiration_date > NOW()";
$result_voucher = $conn->query($sql_voucher);
$has_voucher = $result_voucher->num_rows > 0;

// Check if there's an error message
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Movie - Online Cinema Booking</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        body {
            background-image: url('images/hand.jpg');
            background-size: cover;
            background-attachment: fixed;
            color: #fff;
            font-family: Arial, sans-serif;
        }

        header {
            background-color: rgba(34, 34, 34, 0.8);
            padding: 15px;
            text-align: center;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        header nav a {
            color: #fff;
            margin: 0 15px;
            text-decoration: none;
            font-weight: bold;
        }

        header nav a:hover {
            text-decoration: underline;
        }

        .booking-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: rgba(34, 34, 34, 0.8);
            border-radius: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            margin-top: 80px;
        }

        h2 {
            color: #9acd32;
            text-align: center;
        }

        .booking-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .error-message {
            color: #ff4c4c;
            font-size: 16px;
            text-align: center;
            margin-bottom: 15px;
        }

        label {
            font-size: 16px;
            color: #9acd32;
        }

        input, select {
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #444;
            background-color: #333;
            color: #fff;
        }

        .price {
            font-size: 18px;
            color: #ffcc00;
        }

        button {
            padding: 10px;
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

        .card-details {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .hidden {
            display: none;
        }
    </style>
    <script>
        // Update the price based on ticket type selection
        function updatePrice() {
            const ticketType = document.getElementById('ticket_type').value;
            const priceDisplay = document.getElementById('price');
            let basePrice = 0;

            // Set base price according to ticket type
            switch (ticketType) {
                case 'VIP': basePrice = 150.00; break;      // VIP price is R150
                case 'Standard': basePrice = 100.00; break; // Standard price is R100
                case 'Children': basePrice = 50.00; break;  // Children price is R50
                case 'Disability': basePrice = 70.00; break; // Disability price is R70
            }

            priceDisplay.innerText = 'Price: R' + basePrice.toFixed(2);
        }

        // Toggle mall and seating fields for cinema or online options
        function toggleMallAndSeating() {
            const watchOption = document.getElementById('watch_option').value;
            const mallField = document.getElementById('mall_field');
            const seatingField = document.getElementById('seating_field');
            const dateTimeSection = document.getElementById('date_time_section');
            const voucherSection = document.getElementById('voucher_section');
            const ticketTypeSelect = document.getElementById('ticket_type');

            if (watchOption === 'online') {
                mallField.classList.add('hidden');
                seatingField.classList.add('hidden');
                dateTimeSection.classList.add('hidden');
                voucherSection.classList.remove('hidden');
                // Remove VIP from ticket type for online
                ticketTypeSelect.innerHTML = `
                    <option value="Standard">Standard - R100</option>
                    <option value="Children">Children - R50</option>
                    <option value="Disability">Disability - R70</option>
                `;
            } else {
                mallField.classList.remove('hidden');
                seatingField.classList.remove('hidden');
                dateTimeSection.classList.remove('hidden');
                voucherSection.classList.add('hidden');
                // Add VIP back to ticket type for cinema
                ticketTypeSelect.innerHTML = `
                    <option value="Standard">Standard - R100</option>
                    <option value="VIP">VIP - R150</option>
                    <option value="Children">Children - R50</option>
                    <option value="Disability">Disability - R70</option>
                `;
            }

            updatePrice();  // Update price based on watch option
        }

        // Toggle voucher input field
        function toggleVoucherInput() {
            const useVoucher = document.getElementById('use_voucher').checked;
            const voucherField = document.getElementById('voucher_code_field');
            if (useVoucher) {
                voucherField.classList.remove('hidden');
                toggleCardDetails(true);  // Hide card details when voucher is used
            } else {
                voucherField.classList.add('hidden');
                toggleCardDetails(false);  // Show card details when voucher is not used
            }
        }

        // Toggle card details for payment
        function toggleCardDetails(hideCardDetails) {
            const useWallet = document.getElementById('use_wallet').checked;
            const cardNumber = document.getElementById('card_number');
            const expiryDate = document.getElementById('expiry_date');
            const cvv = document.getElementById('cvv');

            if (useWallet || hideCardDetails) {
                cardNumber.disabled = true;
                expiryDate.disabled = true;
                cvv.disabled = true;
                cardNumber.required = false;
                expiryDate.required = false;
                cvv.required = false;
            } else {
                cardNumber.disabled = false;
                expiryDate.disabled = false;
                cvv.disabled = false;
                cardNumber.required = true;
                expiryDate.required = true;
                cvv.required = true;
            }
        }

        window.onload = function() {
            toggleMallAndSeating(); // Ensure fields are correctly shown/hidden on page load
        };
    </script>
</head>
<body>
    <header>
        <nav>
            <a href="watch.php">Browse Movies</a>
            <a href="index.php">Logout</a>
            <a href="#" class="wallet-balance">
                | &nbsp; Wallet Balance: R<?php echo number_format($wallet_balance, 2); ?>
            </a>
        </nav>
    </header>

<div class="booking-container">
    <h2>Book: <?php echo htmlspecialchars($movie['title']); ?></h2>

    <?php if ($error === 'duplicate_booking'): ?>
        <div class="error-message">You have already booked this movie for online viewing.</div>
    <?php elseif ($already_booked): ?>
        <div class="error-message">You have already booked this movie but it is not yet marked as played.</div>
    <?php endif; ?>

    <?php if (!$already_booked): ?>
        <form action="process_booking.php" method="POST" class="booking-form">
            <input type="hidden" name="movie_id" value="<?php echo htmlspecialchars($movie['id']); ?>">

            <label for="watch_option">Watch Option:</label>
            <select name="watch_option" id="watch_option" onchange="toggleMallAndSeating()" required>
                <option value="online">Watch Online</option>
                <option value="cinema">Watch at Cinema/Theater</option>
            </select>

            <label for="ticket_type">Ticket Type:</label>
            <select name="ticket_type" id="ticket_type" onchange="updatePrice()" required>
                <option value="Standard">Standard - R100</option>
                <option value="VIP">VIP - R150</option>
                <option value="Children">Children - R50</option>
                <option value="Disability">Disability - R70</option>
            </select>

            <!-- Voucher Option (Hidden for Cinema/Theater) -->
            <div id="voucher_section" class="<?php echo $has_voucher ? '' : 'hidden'; ?>">
                <label for="use_voucher">Use Voucher:</label>
                <input type="checkbox" name="use_voucher" id="use_voucher" onchange="toggleVoucherInput()">
                
                <div id="voucher_code_field" class="hidden">
                    <label for="voucher_code">Enter Voucher Code:</label>
                    <input type="text" name="voucher_code" id="voucher_code" placeholder="Enter 10-digit voucher code">
                </div>
            </div>

            <!-- Mall Selection (Hidden for Online) -->
            <div id="mall_field" class="hidden">
                <label for="mall">Select Mall (for Cinema/Theater):</label>
                <select name="mall" id="mall">
                    <option value="">None Selected</option>
                    <option value="Mall of Africa (Midrand)">Mall of Africa (Midrand) - Gauteng</option>
                    <option value="Canal Walk (Cape Town)">Canal Walk (Cape Town) - Western Cape</option>
                    <option value="Gateway Theatre of Shopping (Umhlanga, Durban)">Gateway Theatre of Shopping (Umhlanga, Durban) - KwaZulu-Natal</option>
                </select>
            </div>

            <!-- Seating Arrangement (Hidden for Online) -->
            <div id="seating_field" class="hidden">
                <label for="seating">Seating Arrangement:</label>
                <select name="seating" id="seating">
                    <option value="None Selected">None Selected</option>
                    <option value="Front">Front</option>
                    <option value="Middle">Middle</option>
                    <option value="Back">Back</option>
                </select>
            </div>

            <!-- Date and Time Selection for Cinema/Theater -->
            <div id="date_time_section" class="hidden">
                <label for="date">Select Date:</label>
                <input type="date" name="date" id="date">
                <br><br>
                <label for="time_slot">Select Time:</label>
                <select name="time_slot" id="time_slot">
                    <option value="Morning 08:00 AM">Morning (08:00 AM)</option>
                    <option value="Afternoon 14:00 PM">Afternoon (14:00 PM)</option>
                    <option value="Evening ">Evening (18:00 PM)</option>
                </select>
            </div>

            <div class="price" id="price">Price: R100.00</div> <!-- Default price -->

            <label for="use_wallet">Use Wallet Balance (Available: R<?php echo number_format($wallet_balance, 2); ?>)</label>
            <input type="checkbox" name="use_wallet" id="use_wallet" onchange="toggleCardDetails(false)">

            <div class="card-details">
                <label for="card_number">Debit/Credit Card Number:</label>
                <input type="text" name="card_number" id="card_number" placeholder="Enter your card number" required>

                <label for="expiry_date">Expiry Date:</label>
                <input type="text" name="expiry_date" id="expiry_date" placeholder="MM/YY" required>

                <label for="cvv">CVV:</label>
                <input type="text" name="cvv" id="cvv" placeholder="CVV" required>
            </div>

            <button type="submit">Confirm Booking</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
