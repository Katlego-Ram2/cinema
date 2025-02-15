<?php

ini_set('upload_max_filesize', '500M');
ini_set('post_max_size', '500M');
ini_set('max_file_uploads', '200');


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


session_start();

if (!isset($_SESSION['admin'])) { 
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

// Fetch Key Metrics
$total_users = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$total_bookings = $conn->query("SELECT COUNT(*) AS total FROM bookings")->fetch_assoc()['total'];
$total_movies = $conn->query("SELECT COUNT(*) AS total FROM movies")->fetch_assoc()['total'];
$active_vouchers = $conn->query("SELECT COUNT(*) AS total FROM vouchers WHERE used = 0 AND expiration_date > NOW()")->fetch_assoc()['total'];

// Calculate total revenue based on ticket types
$total_revenue_query = $conn->query("
    SELECT SUM(
        CASE
            WHEN ticket_type = 'VIP' THEN 150.00
            WHEN ticket_type = 'Standard' THEN 100.00
            WHEN ticket_type = 'Children' THEN 50.00
            WHEN ticket_type = 'Disability' THEN 70.00
            ELSE 0
        END
    ) AS revenue
    FROM bookings
    JOIN payments ON bookings.id = payments.booking_id
");
$total_revenue = $total_revenue_query ? $total_revenue_query->fetch_assoc()['revenue'] : 0;
$total_revenue = $total_revenue ? $total_revenue : 0; // Handle NULL case



// Recent Activity: last 10 bookings and new movies

// Get current page and items per page
if (isset($_POST['action']) && $_POST['action'] === 'fetch_recent_bookings') {
    // Get page and date filter from POST data
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $items_per_page = 10;
    $offset = ($page - 1) * $items_per_page;
    $date_filter = isset($_POST['date_filter']) ? $_POST['date_filter'] : '';

    // Construct the query to join users and movies, with pagination and date filter
    $query = "
        SELECT b.*, u.name AS user_name, m.title AS movie_title
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN movies m ON b.movie_id = m.id
    ";

    if (!empty($date_filter)) {
        $query .= " WHERE DATE(b.booking_time) = '$date_filter'";
    }

    $query .= " ORDER BY b.booking_time DESC LIMIT $items_per_page OFFSET $offset";
    
    $recent_bookings = $conn->query($query);

    // Fetch total records for pagination
    $total_bookings_query = "SELECT COUNT(*) AS total FROM bookings";
    if (!empty($date_filter)) {
        $total_bookings_query .= " WHERE DATE(booking_time) = '$date_filter'";
    }
    $total_bookings = $conn->query($total_bookings_query)->fetch_assoc()['total'];
    $total_pages = ceil($total_bookings / $items_per_page);

    // Prepare the results to be sent back as JSON
    $bookings_data = [];
    while ($row = $recent_bookings->fetch_assoc()) {
        $bookings_data[] = $row;
    }

    // Return the results as a JSON object
    echo json_encode([
        'bookings' => $bookings_data,
        'total_pages' => $total_pages
    ]);
    exit();
}



// Recent Movies
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'fetch_recent_movies') {
        // Get page number and items per page for pagination
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $items_per_page = 10;
        $offset = ($page - 1) * $items_per_page;

        // Get the date filter if provided
        $date_filter = isset($_POST['date_filter']) && !empty($_POST['date_filter']) ? $_POST['date_filter'] : null;

        // Query for fetching recent movies with pagination and optional date filter
        $query = "SELECT * FROM movies WHERE 1";

        if ($date_filter) {
            $query .= " AND release_date >= '$date_filter'";
        }

        $query .= " ORDER BY release_date DESC LIMIT $items_per_page OFFSET $offset";

        $result = $conn->query($query);

        // Get the total number of movies for pagination
        $total_movies_query = "SELECT COUNT(*) as total FROM movies WHERE 1";
        if ($date_filter) {
            $total_movies_query .= " AND release_date >= '$date_filter'";
        }
        $total_movies_result = $conn->query($total_movies_query);
        $total_movies = $total_movies_result->fetch_assoc()['total'];
        $total_pages = ceil($total_movies / $items_per_page);

        $movies = [];
        while ($row = $result->fetch_assoc()) {
            $movies[] = $row;
        }

        echo json_encode([
            'movies' => $movies,
            'total_pages' => $total_pages
        ]);
        exit();
    }
}





// Data for Graphs & Statistics
// Bookings per Day (last 7 days)
$bookings_per_day_query = $conn->query("
    SELECT DATE(booking_time) as date, COUNT(*) as total
    FROM bookings
    GROUP BY DATE(booking_time)
    ORDER BY date DESC
    LIMIT 7
");

$bookings_per_day = [];
$dates = [];
while ($row = $bookings_per_day_query->fetch_assoc()) {
    $dates[] = $row['date'];
    $bookings_per_day[] = $row['total'];
}

// Revenue trends (last 7 days)
$revenue_trends_query = $conn->query("
    SELECT DATE(booking_time) as date, 
    SUM(
        CASE
            WHEN ticket_type = 'VIP' THEN 150.00
            WHEN ticket_type = 'Standard' THEN 100.00
            WHEN ticket_type = 'Children' THEN 50.00
            WHEN ticket_type = 'Disability' THEN 70.00
            ELSE 0
        END
    ) as revenue
    FROM bookings
    JOIN payments ON bookings.id = payments.booking_id
    WHERE payments.status = 'paid'
    GROUP BY DATE(booking_time)
    ORDER BY date DESC
    LIMIT 7
");

$revenue_trends = [];
while ($row = $revenue_trends_query->fetch_assoc()) {
    $revenue_trends[] = $row['revenue'];
}


// Most popular movies (last 7 days)
$popular_movies_query = $conn->query("
    SELECT movies.title, COUNT(*) as total
    FROM bookings
    JOIN movies ON bookings.movie_id = movies.id
    GROUP BY movie_id
    ORDER BY total DESC
    LIMIT 5
");

$popular_movies = [];
$movie_titles = [];
while ($row = $popular_movies_query->fetch_assoc()) {
    $movie_titles[] = $row['title'];
    $popular_movies[] = $row['total'];
}






$search_result = null;
$edit_user = null; 
$user_bookings = null;
$user_vouchers = null; // Variables to hold user bookings and vouchers

// Handle user-related AJAX requests
if (isset($_POST['action'])) {

    // Search user
    if ($_POST['action'] === 'search_user') {
        $search_query = $conn->real_escape_string($_POST['search_query']);
        $search_result = $conn->query("SELECT * FROM users WHERE name LIKE '%$search_query%' OR email LIKE '%$search_query%'");

        $users = [];
        while ($user = $search_result->fetch_assoc()) {
            $users[] = $user;
        }
        echo json_encode($users);
        exit();
    }

    // Fetch a single user's details for editing
    if ($_POST['action'] === 'fetch_user') {
        $user_id = $conn->real_escape_string($_POST['user_id']);
        $user_result = $conn->query("SELECT * FROM users WHERE id='$user_id'");
        $user = $user_result->fetch_assoc();

        echo json_encode($user);
        exit();
    }

    // Update user
    if ($_POST['action'] === 'update_user') {
        $user_id = $conn->real_escape_string($_POST['id']);
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $role = $conn->real_escape_string($_POST['role']);

        $conn->query("UPDATE users SET name='$name', email='$email', role='$role' WHERE id='$user_id'");
        echo json_encode(["status" => "success", "message" => "User updated successfully!"]);
        exit();
    }

    // Fetch user bookings and vouchers
    if ($_POST['action'] === 'view_user_bookings') {
        $user_id = $conn->real_escape_string($_POST['user_id']);
        
        // Fetch booking history for the user
        $bookings_result = $conn->query("
            SELECT bookings.id, bookings.booking_time, bookings.ticket_type, bookings.watch_option, payments.status as payment_status, movies.title as movie_title
            FROM bookings
            LEFT JOIN payments ON bookings.id = payments.booking_id
            LEFT JOIN movies ON bookings.movie_id = movies.id
            WHERE bookings.user_id = '$user_id'
            ORDER BY bookings.booking_time DESC
        ");
        
        // Fetch vouchers claimed by the user
        $vouchers_result = $conn->query("SELECT * FROM vouchers WHERE user_id = '$user_id' AND used = 1");

        $user_data = [
            "bookings" => $bookings_result->fetch_all(MYSQLI_ASSOC),
            "vouchers" => $vouchers_result->fetch_all(MYSQLI_ASSOC)
        ];

        echo json_encode($user_data);
        exit();
    }
}




// Movie Management Functionality

// Handle movie-related AJAX requests
if (isset($_POST['action'])) {

    // Add New Movie
   if ($_POST['action'] === 'add_movie') {
    $title = $conn->real_escape_string($_POST['title']);
    $genre = $conn->real_escape_string($_POST['genre']);
    $rating = $conn->real_escape_string($_POST['rating']);
    $duration = $conn->real_escape_string($_POST['duration']);
    $release_date = $conn->real_escape_string($_POST['release_date']);

    // Define the directories where the files will be stored
    $image_directory = "C:/wamp64/www/CINEMA/images/";
    $trailer_directory = "C:/wamp64/www/CINEMA/Trailer/";
    $movie_directory = "C:/wamp64/www/CINEMA/movie/";

    // URLs to save in the database
    $image_url_base = "http://localhost/cinema/images/";
    $trailer_url_base = "http://localhost/cinema/trailer/";
    $movie_url_base = "http://localhost/cinema/movie/";

    // Ensure files are uploaded
    if (!empty($_FILES['image']['name']) && !empty($_FILES['trailer']['name']) && !empty($_FILES['movie_file']['name'])) {
        $image = $_FILES['image']['name'];
        $trailer = $_FILES['trailer']['name'];
        $movie_file = $_FILES['movie_file']['name'];

        // Create the full file paths for saving the files
        $image_path = $image_directory . basename($image);
        $trailer_path = $trailer_directory . basename($trailer);
        $movie_path = $movie_directory . basename($movie_file);

        // Links to be stored in the database
        $image_url = $image_url_base . basename($image);
        $trailer_url = $trailer_url_base . basename($trailer);
        $movie_url = $movie_url_base . basename($movie_file);

        // Move the files to the correct directories
        if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path) &&
            move_uploaded_file($_FILES['trailer']['tmp_name'], $trailer_path) &&
            move_uploaded_file($_FILES['movie_file']['tmp_name'], $movie_path)) {

            // Insert the movie data into the database with the URLs
            $insert_movie_sql = "INSERT INTO movies (title, genre, rating, duration, release_date, image, trailer, movie)
                                 VALUES ('$title', '$genre', '$rating', '$duration', '$release_date', '$image_url', '$trailer_url', '$movie_url')";

            if ($conn->query($insert_movie_sql) === TRUE) {
                echo json_encode(["status" => "success", "message" => "Movie added successfully!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error: " . $conn->error]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "File upload failed. Check folder permissions."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "All files (image, trailer, and movie file) are required."]);
    }
    exit();
}


    // Delete movie
    if ($_POST['action'] === 'delete_movie') {
        $movie_id = $conn->real_escape_string($_POST['movie_id']);
        $delete_movie_sql = "DELETE FROM movies WHERE id='$movie_id'";

        if ($conn->query($delete_movie_sql) === TRUE) {
            echo json_encode(["status" => "success", "message" => "Movie deleted successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error deleting movie: " . $conn->error]);
        }
        exit();
    }

    // Fetch all movies
    if ($_POST['action'] === 'fetch_movies') {
        $movies_result = $conn->query("SELECT * FROM movies");
        $movies = [];

        while ($row = $movies_result->fetch_assoc()) {
            $movies[] = $row;
        }

        echo json_encode($movies);
        exit();
    }

    // Fetch single movie details for editing
    if ($_POST['action'] === 'fetch_movie') {
        $movie_id = $conn->real_escape_string($_POST['movie_id']);
        $movie_result = $conn->query("SELECT * FROM movies WHERE id='$movie_id'");
        $movie = $movie_result->fetch_assoc();

        echo json_encode($movie);
        exit();
    }

    // Update movie
    if ($_POST['action'] === 'edit_movie') {
        $movie_id = $conn->real_escape_string($_POST['movie_id']);
        $title = $conn->real_escape_string($_POST['title']);
        $genre = $conn->real_escape_string($_POST['genre']);
        $rating = $conn->real_escape_string($_POST['rating']);
        $duration = $conn->real_escape_string($_POST['duration']);
        $release_date = $conn->real_escape_string($_POST['release_date']);

        $update_movie_sql = "UPDATE movies SET title='$title', genre='$genre', rating='$rating', duration='$duration', release_date='$release_date' WHERE id='$movie_id'";

        if ($conn->query($update_movie_sql) === TRUE) {
            echo json_encode(["status" => "success", "message" => "Movie updated successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database Error: " . $conn->error]);
        }
        exit();
    }
}











// Bookings Management Functionality
$bookings_result = $conn->query("SELECT * FROM bookings");
$booking_statuses = ["paid", "unpaid", "canceled"];

// Bookings Management AJAX functionality
if (isset($_POST['action'])) {

    // Filter bookings
    if ($_POST['action'] === 'filter_bookings') {
        $filter_date = $conn->real_escape_string($_POST['filter_date']);
        $filter_user = $conn->real_escape_string($_POST['filter_user']);
        $filter_movie = $conn->real_escape_string($_POST['filter_movie']);
        $filter_status = $conn->real_escape_string($_POST['filter_status']);

        $query = "SELECT * FROM bookings WHERE 1=1";

        if ($filter_date) {
            $query .= " AND DATE(booking_time) = '$filter_date'";
        }
        if ($filter_user) {
            $query .= " AND user_id = '$filter_user'";
        }
        if ($filter_movie) {
            $query .= " AND movie_id = '$filter_movie'";
        }
        if ($filter_status) {
            $query .= " AND status = '$filter_status'";
        }

        $bookings_result = $conn->query($query);

        // Return bookings as JSON
        $bookings = [];
        while ($row = $bookings_result->fetch_assoc()) {
            $bookings[] = $row;
        }
        echo json_encode($bookings);
        exit();
    }

    // Update booking status
    if ($_POST['action'] === 'update_booking') {
        $booking_id = $conn->real_escape_string($_POST['booking_id']);
        $new_status = $conn->real_escape_string($_POST['new_status']);

        $update_booking_sql = "UPDATE bookings SET status='$new_status' WHERE id='$booking_id'";
        if ($conn->query($update_booking_sql) === TRUE) {
            echo json_encode(["status" => "success", "message" => "Booking status updated successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error updating booking status: " . $conn->error]);
        }
        exit();
    }
}





// Voucher Management Functionality

// Handle voucher-related AJAX requests
if (isset($_POST['action'])) {
    // Create voucher
    if ($_POST['action'] === 'create_voucher') {
        $voucher_code = $conn->real_escape_string($_POST['voucher_code']);
        $expiration_date = $conn->real_escape_string($_POST['expiration_date']);
        $user_id = $conn->real_escape_string($_POST['user_id']); // Added user assignment

        // Check if the voucher code already exists
        $existing_voucher_query = "SELECT COUNT(*) AS total FROM vouchers WHERE voucher_code = '$voucher_code'";
        $existing_voucher_result = $conn->query($existing_voucher_query);
        $existing_voucher_count = $existing_voucher_result->fetch_assoc()['total'];

        if (strlen($voucher_code) != 10) {
            echo json_encode(["status" => "error", "message" => "Voucher code must be exactly 10 characters long."]);
        } elseif ($existing_voucher_count > 0) {
            echo json_encode(["status" => "error", "message" => "Voucher code already exists. Please use a unique code."]);
        } else {
            // Insert the voucher into the database with assigned user
            $insert_voucher_sql = "INSERT INTO vouchers (voucher_code, expiration_date, used, issued_at, user_id) 
                                   VALUES ('$voucher_code', '$expiration_date', 0, NOW(), '$user_id')";

            if ($conn->query($insert_voucher_sql) === TRUE) {
                echo json_encode(["status" => "success", "message" => "Voucher created and assigned to user successfully!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error: " . $conn->error]);
            }
        }
        exit();
    }

    // Delete voucher
    if ($_POST['action'] === 'delete_voucher') {
        $voucher_code = $conn->real_escape_string($_POST['voucher_code']);

        $delete_voucher_sql = "DELETE FROM vouchers WHERE voucher_code = '$voucher_code'";

        if ($conn->query($delete_voucher_sql) === TRUE) {
            echo json_encode(["status" => "success", "message" => "Voucher deleted successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error deleting voucher: " . $conn->error]);
        }
        exit();
    }




    
// Fetch vouchers with pagination
if ($_POST['action'] === 'fetch_vouchers') {
    $items_per_page = 5; // Limit of 5 vouchers per page
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $offset = ($page - 1) * $items_per_page;

    // Query to fetch paginated vouchers
    $vouchers_result = $conn->query("
        SELECT SQL_CALC_FOUND_ROWS v.*, u.name AS user_name 
        FROM vouchers v 
        LEFT JOIN users u ON v.user_id = u.id
        ORDER BY v.issued_at DESC
        LIMIT $items_per_page OFFSET $offset
    ");

    // Get total number of vouchers for pagination
    $total_result = $conn->query("SELECT FOUND_ROWS() as total");
    $total_vouchers = $total_result->fetch_assoc()['total'];
    $total_pages = ceil($total_vouchers / $items_per_page);

    $vouchers = [];
    while ($row = $vouchers_result->fetch_assoc()) {
        $vouchers[] = $row;
    }

    // Send both vouchers and pagination data back to the front-end
    echo json_encode([
        "vouchers" => $vouchers,
        "total_pages" => $total_pages
    ]);
    exit();
}






    // Handle voucher-related AJAX requests
if (isset($_POST['action'])) {
    // Other actions...

    // Search users
    if ($_POST['action'] === 'search_users') {
        $search_value = $conn->real_escape_string($_POST['search_value']);
        $search_query = "SELECT id, name FROM users WHERE name LIKE '%$search_value%'";
        $search_result = $conn->query($search_query);
        
        $users = [];
        while ($row = $search_result->fetch_assoc()) {
            $users[] = $row;
        }
        
        echo json_encode($users);
        exit();
    }
}
 
}










// Declare variables for report results
$report_result = null;
$activity_report_result = null;
$statistics_result = null;

// Revenue Reports, User Activity Reports, and Booking Statistics with AJAX handling

if (isset($_POST['action'])) {
    // Handle Revenue Reports
    if ($_POST['action'] === 'generate_report') {
        $report_period = $_POST['report_period'];

        if ($report_period == 'weekly') {
            $report_query = "
                SELECT DATE(p.date) AS date, SUM(p.amount) AS total_revenue 
                FROM bookings b
                JOIN payments p ON b.id = p.booking_id
                WHERE p.date >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                GROUP BY DATE(p.date)";
        } elseif ($report_period == 'monthly') {
            $report_query = "
                SELECT MONTH(p.date) AS month, SUM(p.amount) AS total_revenue 
                FROM bookings b
                JOIN payments p ON b.id = p.booking_id
                WHERE p.date >= DATE_SUB(NOW(), INTERVAL 1 MONTH) 
                GROUP BY MONTH(p.date)";
        } elseif ($report_period == 'yearly') {
            $report_query = "
                SELECT YEAR(p.date) AS year, SUM(p.amount) AS total_revenue 
                FROM bookings b
                JOIN payments p ON b.id = p.booking_id
                WHERE p.date >= DATE_SUB(NOW(), INTERVAL 1 YEAR) 
                GROUP BY YEAR(p.date)";
        }

        $report_result = $conn->query($report_query);

        // Fetch results and return them as JSON
        $data = [];
        while ($row = $report_result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }

    // Handle User Activity Reports
    if ($_POST['action'] === 'user_activity_report') {
        $activity_report_query = "
            SELECT u.id, u.name, COUNT(b.id) AS booking_count 
            FROM users u
            LEFT JOIN bookings b ON u.id = b.user_id 
            GROUP BY u.id";

        $activity_report_result = $conn->query($activity_report_query);

        // Fetch results and return them as JSON
        $data = [];
        while ($row = $activity_report_result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }

    // Handle Booking Statistics
    if ($_POST['action'] === 'booking_statistics') {
        $statistics_query = "
            SELECT b.ticket_type, COUNT(*) AS total_bookings, SUM(p.amount) AS total_revenue 
            FROM bookings b
            LEFT JOIN payments p ON b.id = p.booking_id 
            GROUP BY b.ticket_type";

        $statistics_result = $conn->query($statistics_query);

        // Fetch results and return them as JSON
        $data = [];
        while ($row = $statistics_result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }
}




// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cinema</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        body {
            background-color: #f8f9fa;
        }
        .admin-dashboard {
            display: flex;
            height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #343a40;
            color: #ffffff;
            padding: 15px;
        }
        .sidebar h2 {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .sidebar a {
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
             margin-bottom: 10px;
        }

        .sidebar a i {
        margin-right: 10px;
        font-size: 18px; /* Adjust icon size */
        }

        .sidebar a:hover {
            text-decoration: underline;
        }
        .content {
            flex-grow: 1;
            padding: 20px;
        }
        .metric {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .metric h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .metric p {
            font-size: 18px;
        }
        .recent-activity table {
            width: 100%;
            margin-top: 20px;
        }
        .recent-activity th, .recent-activity td {
            text-align: center;
        }
        .hidden {
            display: none;
        }
        .chart-container {
            position: relative;
            height: 40vh;
            width: 80vw;
        }
    </style>
</head>
<body>
    <div class="container-fluid admin-dashboard">
        <div class="sidebar">
            <h2>Dashboard</h2>
            <a href="#" onclick="showSection('metrics')"><i class="fas fa-tachometer-alt"></i>Key Metrics</a>
            <a href="#" onclick="showSection('recent-bookings')"><i class="fas fa-book"></i>Recent Bookings</a>
            <a href="#" onclick="showSection('recent-movies')"><i class="fas fa-film"></i>Recent Movies</a>
            <a href="#" onclick="showSection('charts')"><i class="fas fa-chart-line"></i>Graphs & Statistics</a>
            <a href="#" onclick="showSection('user-management')"><i class="fas fa-users"></i>User Management</a>
            <a href="#" onclick="showSection('movie-management')"><i class="fas fa-video"></i> Movie Management</a>
            <a href="#" onclick="showSection('bookings-management')"><i class="fas fa-ticket-alt"></i>Bookings Management</a>
            <a href="#" onclick="showSection('voucher-management')"><i class="fas fa-gift"></i>Voucher & Promotions Management</a>
            <a href="#" onclick="showSection('reports')"><i class="fas fa-chart-pie"></i>Reports & Analytics</a>
            <a href="index.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
        </div>
        <div class="content">
            <h1 class="text-center">Admin Dashboard</h1>

            <!-- Metrics Section -->
            <div id="metrics" class="section">
                <div class="row">
                    <div class="col-md-3">
                        <div class="metric">
                            <h2>Total Users</h2>
                            <p><?php echo $total_users; ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric">
                            <h2>Total Bookings</h2>
                            <p><?php echo $total_bookings; ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric">
                            <h2>Total Revenue</h2>
                            <p>R<?php echo number_format($total_revenue, 2); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric">
                            <h2>Movies Available</h2>
                            <p><?php echo $total_movies; ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric">
                            <h2>Active Vouchers</h2>
                            <p><?php echo $active_vouchers; ?></p>
                        </div>
                    </div>
                </div>
            </div>


<!-- Recent Bookings Section -->
<div id="recent-bookings" class="section">
    <h2>Recent Bookings</h2>

    <!-- Date Filter -->
    <div class="form-group">
        <label for="date_filter">Filter by Date:</label>
        <input type="date" id="date_filter" class="form-control" onchange="fetchRecentBookings(event, 1)">
    </div>

    <!-- Recent Activity Table -->
    <div class="recent-activity">
        <table class="table">
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>User Name</th>
                    <th>Movie Title</th>
                    <th>Booking Time</th>
                </tr>
            </thead>
            <tbody id="recentBookingsTableBody">
                <!-- Recent bookings will be loaded here via AJAX -->
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <div id="pagination" class="pagination">
        <!-- Pagination buttons will be dynamically loaded here -->
    </div>
</div>

<!-- AJAX and JavaScript code -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Function to fetch recent bookings using AJAX
    function fetchRecentBookings(event, page = 1) {
        // Prevent default behavior
        if (event) {
            event.preventDefault();
        }

        // Get the date filter value
        const dateFilter = document.getElementById('date_filter').value;

        // Log the fetching process
        console.log("Fetching recent bookings:", { page, dateFilter });

        // AJAX request to load recent bookings
        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: {
                action: 'fetch_recent_bookings',
                page: page,
                date_filter: dateFilter
            },
            success: function(response) {
                console.log("Response received:", response);
                const data = JSON.parse(response);
                const bookings = data.bookings;
                const totalPages = data.total_pages;

                let tableBody = '';
                bookings.forEach(booking => {
                    tableBody += `
                        <tr>
                            <td>${booking.id}</td>
                            <td>${booking.user_name}</td>
                            <td>${booking.movie_title}</td>
                            <td>${booking.booking_time}</td>
                        </tr>
                    `;
                });

                // Update the table body with recent bookings
                document.getElementById('recentBookingsTableBody').innerHTML = tableBody;

                // Update pagination controls
                let paginationControls = '';

                if (page > 1) {
                    paginationControls += `<button class="btn btn-primary" onclick="fetchRecentBookings(event, ${page - 1})">Previous</button>`;
                }

                if (page < totalPages) {
                    paginationControls += `<button class="btn btn-primary" onclick="fetchRecentBookings(event, ${page + 1})">Next</button>`;
                }

                document.getElementById('pagination').innerHTML = paginationControls;
            },
            error: function(xhr, status, error) {
                console.error("Error occurred while fetching recent bookings:", status, error);
            }
        });
    }

    // Load the recent bookings when the page loads
    $(document).ready(function() {
        console.log("Document ready: loading recent bookings");
        fetchRecentBookings(null); // Pass null to avoid event blocking
    });
</script>





<!-- Recent Movies Section -->
<div id="recent-movies" class="section hidden">
    <h2>Recent Movies</h2>

    <!-- Date Filter -->
    <div class="form-group">
        <label for="movie_date_filter">Filter by Date:</label>
        <input type="date" id="movie_date_filter" class="form-control" onchange="fetchRecentMovies(1)">
    </div>

    <!-- Recent Movies Table -->
    <div class="recent-activity">
        <table class="table">
            <thead>
                <tr>
                    <th>Movie ID</th>
                    <th>Title</th>
                    <th>Release Date</th>
                </tr>
            </thead>
            <tbody id="recentMoviesTableBody">
                <!-- Movies will be dynamically loaded here -->
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <nav>
        <ul class="pagination" id="recentMoviesPagination">
            <!-- Pagination links will be generated here -->
        </ul>
    </nav>
</div>

<!-- Include jQuery for AJAX -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function fetchRecentMovies(page = 1) {
    const dateFilter = document.getElementById('movie_date_filter').value;

    $.ajax({
        url: 'dashboard.php',
        type: 'POST',
        data: {
            action: 'fetch_recent_movies',
            page: page,
            date_filter: dateFilter
        },
        success: function(response) {
            const result = JSON.parse(response);
            const movies = result.movies;
            const totalPages = result.total_pages;

            // Populate the table body with recent movies
            let tableRows = '';
            movies.forEach(movie => {
                tableRows += `
                    <tr>
                        <td>${movie.id}</td>
                        <td>${movie.title}</td>
                        <td>${movie.release_date}</td>
                    </tr>
                `;
            });
            $('#recentMoviesTableBody').html(tableRows);

            // Create pagination links
            let paginationLinks = '';
            for (let i = 1; i <= totalPages; i++) {
                paginationLinks += `<li class="page-item ${i === page ? 'active' : ''}">
                    <a class="page-link" href="javascript:void(0);" onclick="fetchRecentMovies(${i})">${i}</a>
                </li>`;
            }
            $('#recentMoviesPagination').html(paginationLinks);
        }
    });
}

// Load recent movies on page load
$(document).ready(function() {
    fetchRecentMovies(1);
});
</script>






            <!-- Charts Section -->
            <div id="charts" class="section hidden">
                <h2>Graphs & Statistics</h2>
                <div class="chart-container">
                    <canvas id="bookingsPerDayChart"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="revenueTrendsChart"></canvas>
                </div>
            </div>



            <!-- User Management Section -->
<div id="user-management" class="section hidden">
    <h2>User Management</h2>

    <!-- Search User Form -->
    <form id="searchUserForm" class="mb-3">
        <input type="text" name="search_query" id="search_query" class="form-control" placeholder="Search by Name or Email" required>
        <button type="submit" class="btn btn-primary mt-2">Search</button>
    </form>

    <!-- Display Search Results -->
    <table class="table mt-3" id="userTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="userTableBody">
            <!-- Users will be dynamically loaded here -->
        </tbody>
    </table>

    <!-- Edit User Form (dynamically populated) -->
    <div id="editUserFormContainer" class="hidden">
        <h3>Edit User</h3>
        <form id="editUserForm">
            <input type="hidden" name="id" id="edit_user_id">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" name="name" id="edit_user_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="edit_user_email" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select name="role" id="edit_user_role" class="form-control" required>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success">Update User</button>
        </form>
    </div>

    <!-- User Bookings and Vouchers Section -->
    <div id="userBookingsContainer" class="hidden">
        <h2>Booking History</h2>
        <table class="table" id="userBookingsTable">
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>Movie Title</th>
                    <th>Booking Time</th>
                    <th>Ticket Type</th>
                    <th>Watch Option</th>
                    <th>Payment Status</th>
                </tr>
            </thead>
            <tbody id="userBookingsBody">
                <!-- Bookings will be dynamically loaded here -->
            </tbody>
        </table>

        <h2>Vouchers Claimed</h2>
        <table class="table" id="userVouchersTable">
            <thead>
                <tr>
                    <th>Voucher Code</th>
                    <th>Expiration Date</th>
                    <th>Used</th>
                </tr>
            </thead>
            <tbody id="userVouchersBody">
                <!-- Vouchers will be dynamically loaded here -->
            </tbody>
        </table>
    </div>
</div>

<!-- Include jQuery for AJAX -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Prevent the form from submitting and triggering a page reload
    $('#searchUserForm').on('submit', function(e) {
        e.preventDefault();  // Stop the default form submission

        const searchQuery = $('#search_query').val();

        // Perform an AJAX request to search for users
        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: { action: 'search_user', search_query: searchQuery },
            success: function(response) {
                const users = JSON.parse(response);
                let userTable = '';

                if (users.length > 0) {
                    users.forEach(user => {
                        userTable += `
                            <tr>
                                <td>${user.id}</td>
                                <td>${user.name}</td>
                                <td>${user.email}</td>
                                <td>${user.role}</td>
                                <td>
                                    <button class="btn btn-warning" onclick="editUser(${user.id})">Edit</button>
                                    <button class="btn btn-info" onclick="viewUserBookings(${user.id})">View Bookings</button>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    userTable = `<tr><td colspan="5">No users found</td></tr>`;
                }

                $('#userTableBody').html(userTable);  // Update the table with results
            },
            error: function() {
                alert('Error occurred while searching for users.');
            }
        });
    });

    // Function to edit user
    function editUser(userId) {
        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: { action: 'fetch_user', user_id: userId },
            success: function(response) {
                const user = JSON.parse(response);

                // Populate the form with user details
                $('#edit_user_id').val(user.id);
                $('#edit_user_name').val(user.name);
                $('#edit_user_email').val(user.email);
                $('#edit_user_role').val(user.role);

                // Show the edit form
                $('#editUserFormContainer').removeClass('hidden');
            },
            error: function() {
                alert('Error occurred while fetching user details.');
            }
        });
    }

    // Update user details using AJAX
    $('#editUserForm').on('submit', function(e) {
        e.preventDefault();  // Prevent form submission
        const formData = $('#editUserForm').serialize();  // Get form data

        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: formData + '&action=update_user',
            success: function(response) {
                const result = JSON.parse(response);
                alert(result.message);

                // Hide the edit form and refresh the user list if update was successful
                if (result.status === 'success') {
                    $('#editUserFormContainer').addClass('hidden');
                    $('#searchUserForm').trigger('submit');  // Re-trigger search to refresh the user list
                }
            },
            error: function() {
                alert('Error occurred while updating user.');
            }
        });
    });

    // View user bookings and vouchers
    function viewUserBookings(userId) {
        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: { action: 'view_user_bookings', user_id: userId },
            success: function(response) {
                const data = JSON.parse(response);
                let bookingsTable = '';
                let vouchersTable = '';

                // Populate the bookings table
                if (data.bookings.length > 0) {
                    data.bookings.forEach(booking => {
                        bookingsTable += `
                            <tr>
                                <td>${booking.id}</td>
                                <td>${booking.movie_title}</td>
                                <td>${booking.booking_time}</td>
                                <td>${booking.ticket_type}</td>
                                <td>${booking.watch_option}</td>
                                <td>${booking.payment_status}</td>
                            </tr>
                        `;
                    });
                } else {
                    bookingsTable = `<tr><td colspan="6">No bookings found</td></tr>`;
                }

                // Populate the vouchers table
                if (data.vouchers.length > 0) {
                    data.vouchers.forEach(voucher => {
                        vouchersTable += `
                            <tr>
                                <td>${voucher.voucher_code}</td>
                                <td>${voucher.expiration_date}</td>
                                <td>Yes</td>
                            </tr>
                        `;
                    });
                } else {
                    vouchersTable = `<tr><td colspan="3">No vouchers found</td></tr>`;
                }

                // Update the tables and display
                $('#userBookingsBody').html(bookingsTable);
                $('#userVouchersBody').html(vouchersTable);
                $('#userBookingsContainer').removeClass('hidden');  // Show the section
            },
            error: function() {
                alert('Error occurred while fetching bookings and vouchers.');
            }
        });
    }
</script>





            <!-- Movie Management Section -->
<div id="movie-management" class="section hidden">
    <h2>Movie Management</h2>

    <!-- Add/Edit Movie Form -->
    <form id="addEditMovieForm" enctype="multipart/form-data" class="mb-3">
        <h4 id="form-title">Add New Movie</h4> <!-- Dynamic title for add/edit -->
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" name="title" id="title" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="genre">Genre</label>
            <input type="text" name="genre" id="genre" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="rating">Rating</label>
            <input type="number" name="rating" id="rating" class="form-control" min="1" max="10" required>
        </div>
        <div class="form-group">
            <label for="duration">Duration (minutes)</label>
            <input type="number" name="duration" id="duration" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="release_date">Release Date</label>
            <input type="date" name="release_date" id="release_date" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="image">Image</label>
            <input type="file" name="image" id="image" class="form-control">
        </div>
        <div class="form-group">
            <label for="trailer">Trailer</label>
            <input type="file" name="trailer" id="trailer" class="form-control">
        </div>
        <div class="form-group">
            <label for="movie_file">Movie File</label>
            <input type="file" name="movie_file" id="movie_file" class="form-control">
        </div>
        <input type="hidden" id="movie_id" name="movie_id"> <!-- Hidden field to store movie ID for editing -->
        <button type="submit" class="btn btn-primary" id="submit-btn">Add Movie</button>
    </form>

    <h4>Existing Movies</h4>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Genre</th>
                <th>Rating</th>
                <th>Duration</th>
                <th>Release Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="moviesTableBody">
            <!-- Movies will be dynamically loaded here -->
        </tbody>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Fetch movies dynamically and populate the table
    function fetchMovies() {
        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: { action: 'fetch_movies' },
            success: function(response) {
                const movies = JSON.parse(response);
                let moviesTable = '';

                movies.forEach(movie => {
                    moviesTable += `
                        <tr>
                            <td>${movie.id}</td>
                            <td>${movie.title}</td>
                            <td>${movie.genre}</td>
                            <td>${movie.rating}</td>
                            <td>${movie.duration}</td>
                            <td>${movie.release_date}</td>
                            <td>
                                <button class="btn btn-warning" onclick="editMovie(${movie.id})">Edit</button>
                                <button class="btn btn-danger" onclick="deleteMovie(${movie.id})">Delete</button>
                            </td>
                        </tr>
                    `;
                });

                $('#moviesTableBody').html(moviesTable);  // Update the movie list
            }
        });
    }

    // AJAX to add or edit a movie
// AJAX to add or edit a movie
$('#addEditMovieForm').on('submit', function(e) {
    e.preventDefault();  // Prevent page reload

    const formData = new FormData(this);  // Create FormData object to handle file uploads
    const movieId = $('#movie_id').val(); // Check if we are editing

    formData.append('action', movieId ? 'edit_movie' : 'add_movie');  // Append action

    $.ajax({
        url: 'dashboard.php',
        type: 'POST',
        data: formData,
        processData: false,  // Required for file uploads
        contentType: false,  // Required for file uploads
        success: function(response) {
            const result = JSON.parse(response);

            if (result.status === 'success') {
                alert(result.message);
                fetchMovies();  // Refresh movie list after adding/editing
                resetForm();  // Reset form after success
            } else {
                alert(result.message);  // Show error message if any
            }
        },
        error: function(xhr, status, error) {
            console.error("Error occurred: " + status + " " + error);
            console.log(xhr.responseText);  // Log the response for debugging
        }
    });
});





    // AJAX to delete a movie
    function deleteMovie(movieId) {
        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: { action: 'delete_movie', movie_id: movieId },
            success: function(response) {
                const result = JSON.parse(response);

                if (result.status === 'success') {
                    alert(result.message);
                    fetchMovies();  // Refresh movie list after deleting
                } else {
                    alert(result.message);
                }
            }
        });
    }

    // Function to fetch a movie's details for editing
    function editMovie(movieId) {
        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: { action: 'fetch_movie', movie_id: movieId },
            success: function(response) {
                const movie = JSON.parse(response);

                // Populate form with movie details
                $('#movie_id').val(movie.id);
                $('#title').val(movie.title);
                $('#genre').val(movie.genre);
                $('#rating').val(movie.rating);
                $('#duration').val(movie.duration);
                $('#release_date').val(movie.release_date);

                // Update form title and button
                $('#form-title').text('Edit Movie');
                $('#submit-btn').text('Update Movie');
            }
        });
    }

    // Reset the form after adding/editing a movie
    function resetForm() {
        $('#addEditMovieForm')[0].reset();
        $('#form-title').text('Add New Movie');
        $('#submit-btn').text('Add Movie');
        $('#movie_id').val(''); // Reset movie ID
    }

    // Fetch movies when the page loads
    $(document).ready(function() {
        fetchMovies();
    });
</script>





           <!-- Bookings Management Section -->
<div id="bookings-management" class="section hidden">
    <h2>Bookings Management</h2>

    <!-- Filter Bookings Form -->
    <form id="filterBookingsForm" class="mb-3">
        <h4>Filter Bookings</h4>
        <div class="form-group">
            <label for="filter_date">Date</label>
            <input type="date" name="filter_date" id="filter_date" class="form-control">
        </div>
        <div class="form-group">
            <label for="filter_user">User ID</label>
            <input type="text" name="filter_user" id="filter_user" class="form-control">
        </div>
        <div class="form-group">
            <label for="filter_movie">Movie ID</label>
            <input type="text" name="filter_movie" id="filter_movie" class="form-control">
        </div>
        <div class="form-group">
            <label for="filter_status">Status</label>
            <select name="filter_status" id="filter_status" class="form-control">
                <option value="">All</option>
                <option value="paid">Paid</option>
                <option value="unpaid">Unpaid</option>
                <option value="canceled">Canceled</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>

    <!-- Existing Bookings Table -->
    <h4>Existing Bookings</h4>
    <table class="table">
        <thead>
            <tr>
                <th>Booking ID</th>
                <th>User ID</th>
                <th>Movie ID</th>
                <th>Booking Time</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="bookingsTableBody">
            <!-- Bookings will be dynamically loaded here -->
        </tbody>
    </table>
</div>

<!-- Include jQuery for AJAX -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Ensure all DOM manipulations happen after the document is ready
    $(document).ready(function() {
        // Attach the event handler before triggering the submit event
        $('#filterBookingsForm').on('submit', function(e) {
            e.preventDefault();  // Prevent page reload

            const filterDate = $('#filter_date').val();
            const filterUser = $('#filter_user').val();
            const filterMovie = $('#filter_movie').val();
            const filterStatus = $('#filter_status').val();

            $.ajax({
                url: 'dashboard.php',
                type: 'POST',
                data: {
                    action: 'filter_bookings',
                    filter_date: filterDate,
                    filter_user: filterUser,
                    filter_movie: filterMovie,
                    filter_status: filterStatus
                },
                success: function(response) {
                    const bookings = JSON.parse(response);
                    let bookingsTable = '';

                    bookings.forEach(booking => {
                        bookingsTable += `
                            <tr>
                                <td>${booking.id}</td>
                                <td>${booking.user_id}</td>
                                <td>${booking.movie_id}</td>
                                <td>${booking.booking_time}</td>
                                <td>${booking.status}</td>
                                <td>
                                    <select class="form-control" onchange="updateBookingStatus(${booking.id}, this.value)">
                                        <option value="">Update Status</option>
                                        <option value="paid">Paid</option>
                                        <option value="unpaid">Unpaid</option>
                                        <option value="canceled">Canceled</option>
                                    </select>
                                </td>
                            </tr>
                        `;
                    });

                    $('#bookingsTableBody').html(bookingsTable);  // Update the bookings table
                }
            });
        });

        // Update booking status using AJAX
        window.updateBookingStatus = function(bookingId, newStatus) {
            if (newStatus) {
                $.ajax({
                    url: 'dashboard.php',
                    type: 'POST',
                    data: {
                        action: 'update_booking',
                        booking_id: bookingId,
                        new_status: newStatus
                    },
                    success: function(response) {
                        const result = JSON.parse(response);
                        if (result.status === 'success') {
                            alert(result.message);
                        } else {
                            alert(result.message);
                        }
                    }
                });
            }
        };

        // Fetch initial bookings when the page loads
        $('#filterBookingsForm').trigger('submit');  // Now safe to trigger submit
    });
</script>





<!-- Voucher Management Section -->
<div id="voucher-management" class="section hidden">
    <h2>Voucher & Promotions Management</h2>

    <!-- Create Voucher Form -->
    <form id="createVoucherForm" class="mb-3">
        <h4>Create Voucher</h4>
        <div class="form-group">
            <label for="voucher_code">Voucher Code (10 Characters Max)</label>
            <input type="text" name="voucher_code" id="voucher_code" class="form-control" maxlength="10" required>
        </div>
        <div class="form-group">
            <label for="expiration_date">Expiration Date</label>
            <input type="date" name="expiration_date" id="expiration_date" class="form-control" required>
        </div>
<div class="form-group">
    <label for="assign_user">Assign to User</label>
    <input type="text" id="user_search" class="form-control" placeholder="Search for a user..." onkeyup="searchUsers()">
    <select name="user_id" id="assign_user" class="form-control mt-2" required>
        <option value="">Select User</option>
        <!-- User options will be populated here -->
    </select>
</div>

<script>
function searchUsers() {
    const searchValue = document.getElementById('user_search').value;

    // AJAX request to fetch users based on the search input
    $.ajax({
        url: 'dashboard.php',
        type: 'POST',
        data: {
            action: 'search_users',
            search_value: searchValue
        },
        success: function(response) {
            const users = JSON.parse(response);
            const userSelect = document.getElementById('assign_user');
            userSelect.innerHTML = '<option value="">Select User</option>'; // Reset options

            // Populate user options
            users.forEach(user => {
                userSelect.innerHTML += `<option value="${user.id}">${user.name}</option>`;
            });
        }
    });
}
</script>


        <button type="submit" class="btn btn-primary">Create Voucher</button>
    </form>

    <h4>Existing Vouchers</h4>
    <table class="table">
        <thead>
            <tr>
                <th>Voucher Code</th>
                <th>Issued At</th>
                <th>Expiration Date</th>
                <th>Used</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="voucherTableBody">
            <!-- Vouchers will be dynamically loaded here -->
        </tbody>
    </table>

<!-- Pagination Controls -->
<div class="pagination">
    <button class="btn btn-primary" onclick="changePage('prev')">Previous</button>
    <span id="currentPage">1</span> / <span id="totalPages"></span>
    <button class="btn btn-primary" onclick="changePage('next')">Next</button>
</div>



</div>

<!-- Include jQuery for AJAX -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>

    // Fetch vouchers dynamically and populate the table

let currentPage = 1;
let totalPages = 1;

function fetchVouchers(page = 1) {
    $.ajax({
        url: 'dashboard.php',
        type: 'POST',
        data: { action: 'fetch_vouchers', page: page },
        success: function(response) {
            const data = JSON.parse(response);
            const vouchers = data.vouchers;
            totalPages = data.total_pages; // Total pages from server

            let voucherTable = '';
            vouchers.forEach(voucher => {
                voucherTable += `
                    <tr>
                        <td>${voucher.voucher_code}</td>
                        <td>${voucher.issued_at}</td>
                        <td>${voucher.expiration_date}</td>
                        <td>${voucher.used == 1 ? 'Yes' : 'No'}</td>
                        <td>
                            <button class="btn btn-danger" onclick="deleteVoucher('${voucher.voucher_code}')">Delete</button>
                        </td>
                    </tr>
                `;
            });

            $('#voucherTableBody').html(voucherTable); // Update the voucher list
            $('#currentPage').text(currentPage);        // Update current page display
            $('#totalPages').text(totalPages);          // Update total pages display
        }
    });
}

// Pagination Controls
function changePage(direction) {
    if (direction === 'prev' && currentPage > 1) {
        currentPage--;
    } else if (direction === 'next' && currentPage < totalPages) {
        currentPage++;
    }

    fetchVouchers(currentPage); // Fetch vouchers for the updated page
}

// Initial load
$(document).ready(function() {
    fetchVouchers(); // Fetch the first page of vouchers when the page loads
});


    // AJAX to create a new voucher
$('#createVoucherForm').on('submit', function(e) {
    e.preventDefault(); // Prevent page reload

    const voucherCode = $('#voucher_code').val();
    const userId = $('#assign_user').val();  // Get the selected user ID

    if (voucherCode.length !== 10) {
        alert('Voucher code must be exactly 10 characters!');
        return;
    }

    if (!userId) {
        alert('Please select a user to assign the voucher to.');
        return;
    }

    $.ajax({
        url: 'dashboard.php',
        type: 'POST',
        data: {
            action: 'create_voucher',
            voucher_code: voucherCode,
            expiration_date: $('#expiration_date').val(),
            user_id: userId  // Include user_id in the data
        },
        success: function(response) {
            const result = JSON.parse(response);

            if (result.status === 'success') {
                alert(result.message);
                fetchVouchers(); // Refresh voucher list after creating
            } else {
                alert(result.message);
            }
        }
    });
});


    // AJAX to delete a voucher
    function deleteVoucher(voucherCode) {
        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: { action: 'delete_voucher', voucher_code: voucherCode },
            success: function(response) {
                const result = JSON.parse(response);

                if (result.status === 'success') {
                    alert(result.message);
                    fetchVouchers(); // Refresh voucher list after deleting
                } else {
                    alert(result.message);
                }
            }
        });
    }

    // Fetch vouchers when the page loads
    $(document).ready(function() {
        fetchVouchers();
    });
</script>





           <!-- Reports Section -->
<div id="reports" class="section hidden">
    <h2>Reports and Analytics</h2>

    <!-- Revenue Reports Form -->
    <form id="revenueReportForm" class="mb-3">
        <h4>Revenue Reports</h4>
        <div class="form-group">
            <label for="report_period">Period</label>
            <select name="report_period" id="report_period" class="form-control">
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Generate Report</button>
    </form>

    <!-- User Activity Reports Form -->
    <form id="userActivityReportForm" class="mb-3">
        <button type="submit" class="btn btn-primary">Generate User Activity Report</button>
    </form>

    <!-- Booking Statistics Form -->
    <form id="bookingStatisticsForm" class="mb-3">
        <button type="submit" class="btn btn-primary">Generate Booking Statistics</button>
    </form>

    <!-- Report Results Display -->
    <div id="reportResults"></div>
    <div id="userActivityResults"></div>
    <div id="bookingStatisticsResults"></div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // AJAX for Revenue Reports
    $('#revenueReportForm').on('submit', function(e) {
        e.preventDefault();
        const reportPeriod = $('#report_period').val();

        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: { action: 'generate_report', report_period: reportPeriod },
            success: function(response) {
                const data = JSON.parse(response);
                let html = '<h4>Revenue Report Results</h4><table class="table"><thead><tr><th>Date/Month/Year</th><th>Total Revenue</th></tr></thead><tbody>';

                data.forEach(row => {
                    html += `<tr><td>${row.date || row.month || row.year}</td><td>${parseFloat(row.total_revenue).toFixed(2)}</td></tr>`;
                });

                html += '</tbody></table>';
                $('#reportResults').html(html);  // Display results in the reportResults div
            }
        });
    });

    // AJAX for User Activity Reports
    $('#userActivityReportForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: { action: 'user_activity_report' },
            success: function(response) {
                const data = JSON.parse(response);
                let html = '<h4>User Activity Report Results</h4><table class="table"><thead><tr><th>User ID</th><th>Name</th><th>Booking Count</th></tr></thead><tbody>';

                data.forEach(row => {
                    html += `<tr><td>${row.id}</td><td>${row.name}</td><td>${row.booking_count}</td></tr>`;
                });

                html += '</tbody></table>';
                $('#userActivityResults').html(html);  // Display results in the userActivityResults div
            }
        });
    });

    // AJAX for Booking Statistics
    $('#bookingStatisticsForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: { action: 'booking_statistics' },
            success: function(response) {
                const data = JSON.parse(response);
                let html = '<h4>Booking Statistics Results</h4><table class="table"><thead><tr><th>Ticket Type</th><th>Total Bookings</th><th>Total Revenue</th></tr></thead><tbody>';

                data.forEach(row => {
                    html += `<tr><td>${row.ticket_type}</td><td>${row.total_bookings}</td><td>${parseFloat(row.total_revenue).toFixed(2)}</td></tr>`;
                });

                html += '</tbody></table>';
                $('#bookingStatisticsResults').html(html);  // Display results in the bookingStatisticsResults div
            }
        });
    });
</script>





        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.classList.add('hidden');
            });
            document.getElementById(sectionId).classList.remove('hidden');
        }

        const bookingsPerDayChartCtx = document.getElementById('bookingsPerDayChart').getContext('2d');
        const revenueTrendsChartCtx = document.getElementById('revenueTrendsChart').getContext('2d');

        new Chart(bookingsPerDayChartCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Bookings per Day',
                    data: <?php echo json_encode($bookings_per_day); ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderWidth: 2
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        new Chart(revenueTrendsChartCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Revenue Trends',
                    data: <?php echo json_encode($revenue_trends); ?>,
                    backgroundColor: 'rgba(153, 102, 255, 0.2)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
    <script>
$(document).ready(function() {
    // Handle Revenue Report Form Submission
    $('#revenue-report-form').submit(function(e) {
        e.preventDefault();  // Prevent the default form submission
        var report_period = $('#report_period').val();

        $.ajax({
            url: 'Dashboard.php',  // Assuming the report generation is handled in the same file
            type: 'POST',
            data: {
                report_period: report_period,
                generate_report: true
            },
            success: function(response) {
                $('#revenue-report-results').html(response);
            }
        });
    });

    // Handle User Activity Report Form Submission
    $('#user-activity-report-form').submit(function(e) {
        e.preventDefault();  // Prevent the default form submission

        $.ajax({
            url: 'Dashboard.php',
            type: 'POST',
            data: {
                user_activity_report: true
            },
            success: function(response) {
                $('#user-activity-report-results').html(response);
            }
        });
    });

    // Handle Booking Statistics Form Submission
    $('#booking-statistics-form').submit(function(e) {
        e.preventDefault();  // Prevent the default form submission

        $.ajax({
            url: 'Dashboard.php',
            type: 'POST',
            data: {
                booking_statistics: true
            },
            success: function(response) {
                $('#booking-statistics-results').html(response);
            }
        });
    });
});
</script>



</body>
</html>
