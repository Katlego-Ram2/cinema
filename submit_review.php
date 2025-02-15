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

$user_id = $_SESSION['user_id'];
$movie_id = $_POST['movie_id'];
$rating = $_POST['rating'];
$review = $_POST['review'];

// Insert the review into the database
$sql = "INSERT INTO movie_reviews (user_id, movie_id, rating, review) 
        VALUES ('$user_id', '$movie_id', '$rating', '$review')";

if ($conn->query($sql) === TRUE) {
    $_SESSION['message'] = "Review submitted successfully.";
} else {
    $_SESSION['message'] = "Error submitting review: " . $conn->error;
}

header("Location: profile.php");
?>
