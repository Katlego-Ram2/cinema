<?php
session_start();

// Redirect to login if the user is not logged in
if (!isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cinema_db";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize filter variables
$searchTerm = $_GET['searchTerm'] ?? '';
$quality = $_GET['quality'] ?? 'All';
$genre = $_GET['genre'] ?? 'All';
$rating = $_GET['rating'] ?? 'All';
$year = $_GET['year'] ?? 'All';
$orderBy = $_GET['orderBy'] ?? 'Latest';

// Create SQL query with filters
$sql = "SELECT * FROM movies WHERE 1=1";

if (!empty($searchTerm)) {
    $sql .= " AND (title LIKE '%$searchTerm%' OR synopsis LIKE '%$searchTerm%')";
}
if ($quality != 'All') {
    $sql .= " AND quality = '$quality'";
}
if ($genre != 'All') {
    $sql .= " AND genre = '$genre'";
}
if ($rating != 'All') {
    $sql .= " AND rating = '$rating'";
}
if ($year != 'All') {
    $sql .= " AND YEAR(release_date) = '$year'";
}

$sql .= " ORDER BY " . ($orderBy == 'Latest' ? 'release_date DESC' : 'title ASC');

$result = $conn->query($sql);

// Get the logged-in user's name from the session
$user_name = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watch Movies - Online Cinema Booking</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        body {
            background-image: url('images/man.jpg');
            background-size: cover;
            background-attachment: fixed;
            background-color: #111;
            color: #fff;
            font-family: Arial, sans-serif;
            margin: 0;
        }

        /* Header and NavBar Styles */
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

        /* Main Content Styles */
        main {
            padding: 100px 20px 20px;
            text-align: center;
        }

        h2 {
            color: #9acd32;
            margin-bottom: 20px;
        }

        .search-bar {
            background-color: rgba(34, 34, 34, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            margin-bottom: 20px;
        }

        .search-bar form {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 800px;
        }

        .search-bar input[type="text"] {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            border: 1px solid #444;
            background-color: #333;
            color: #fff;
            width: 97%;
        }

        .search-bar .options {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .search-bar select, .search-bar button {
            padding: 10px;
            margin: 5px;
            border-radius: 5px;
            border: 1px solid #444;
            background-color: #333;
            color: #fff;
        }

        .movie-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 20px;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .movie {
            background-color: #1c1c1c;
            border: 1px solid #333;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .movie:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        .movie img {
            width: 100%;
            height: auto;
            display: block;
        }

        .movie-details {
            padding: 15px;
        }

        .movie-details h3 {
            font-size: 18px;
            margin: 10px 0;
            color: #9acd32;
        }

        .movie-details p {
            font-size: 14px;
            margin: 5px 0;
            color: #bbb;
        }

        .movie-details a {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 12px;
            color: #111;
            background-color: #9acd32;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .movie-details a:hover {
            background-color: #86b019;
        }

        /* Trailer Modal */
        #trailer-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        #trailer-video {
            width: 80%;
            max-width: 900px;
            border-radius: 10px;
            background-color: #000;
        }
    </style>
</head>
<body>

    <!-- Hidden Video Player for Trailer -->
    <div id="trailer-modal">
        <video id="trailer-video" controls autoplay>
            <source id="trailer-source" src="" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const movieCards = document.querySelectorAll('.movie');
            const trailerModal = document.getElementById('trailer-modal');
            const trailerVideo = document.getElementById('trailer-video');
            const trailerSource = document.getElementById('trailer-source');

            movieCards.forEach(card => {
                card.addEventListener('click', function () {
                    const trailerUrl = this.getAttribute('data-trailer');
                    trailerSource.src = trailerUrl;

                    trailerVideo.load();
                    trailerModal.style.display = 'flex';
                    trailerVideo.play();
                });
            });

            // Close the modal when clicking outside the video
            trailerModal.addEventListener('click', function (event) {
                if (event.target === trailerModal) {
                    trailerModal.style.display = 'none';
                    trailerVideo.pause();
                    trailerVideo.currentTime = 0;
                }
            });
        });
    </script>

    <!-- Header Section with Fixed NavBar -->
    <header>
        <nav>
            <a href="profile.php">Profile</a>
            <a href="index.php">Logout</a>
        </nav>
    </header>

    <main>
        <h2>Welcome, <?php echo htmlspecialchars($user_name); ?>! Enjoy Watching Movies</h2>

        <!-- Search Bar -->
        <div class="search-bar">
            <form action="watch.php" method="GET">
                <input type="text" name="searchTerm" placeholder="Search Term" value="<?php echo htmlspecialchars($searchTerm); ?>">

                <div class="options">
                    <select name="genre">
                        <option value="All">Genre</option>
                        <option value="Action" <?php if($genre == 'Action') echo 'selected'; ?>>Action</option>
                        <option value="Comedy" <?php if($genre == 'Comedy') echo 'selected'; ?>>Comedy</option>
                    </select>
                    <select name="rating">
                        <option value="All">Rating</option>
                        <option value="G" <?php if($rating == 'G') echo 'selected'; ?>>G</option>
                        <option value="PG" <?php if($rating == 'PG') echo 'selected'; ?>>PG</option>
                        <option value="PG-13" <?php if($rating == 'PG-13') echo 'selected'; ?>>PG-13</option>
                        <option value="R" <?php if($rating == 'R') echo 'selected'; ?>>R</option>
                    </select>
                    <select name="year">
                        <option value="All">Year</option>
                        <?php 
                        for ($i = date("Y"); $i >= 1900; $i--) {
                            echo "<option value='$i' ".($year == $i ? 'selected' : '').">$i</option>";
                        }
                        ?>
                    </select>
                    <select name="orderBy">
                        <option value="Latest" <?php if($orderBy == 'Latest') echo 'selected'; ?>>Latest</option>
                        <option value="Alphabetical" <?php if($orderBy == 'Alphabetical') echo 'selected'; ?>>Alphabetical</option>
                    </select>
                </div>
                <button type="submit">Search</button>
            </form>
        </div>

        <!-- Movie List -->
        <div class="movie-list">
        <?php
        if ($result->num_rows > 0) {
            while ($movie = $result->fetch_assoc()) {
                $imgUrl = !empty($movie['image']) ? htmlspecialchars($movie['image']) : 'path/to/default/image.jpg';
                $trailerUrl = !empty($movie['trailer']) ? htmlspecialchars($movie['trailer']) : ''; // Get trailer URL

                echo "<div class='movie' data-trailer='$trailerUrl'>";
                echo "<img src='$imgUrl' alt='" . htmlspecialchars($movie['title']) . " Cover'>";
                echo "<div class='movie-details'>";
                echo "<h3>" . htmlspecialchars($movie['title']) . "</h3>";
                echo "<p>Genre: " . htmlspecialchars($movie['genre']) . "</p>";
                echo "<p>Rating: " . htmlspecialchars($movie['rating']) . "</p>";
                echo "<p>Duration: " . htmlspecialchars($movie['duration']) . " minutes</p>";
                echo "<p>Release Date: " . htmlspecialchars($movie['release_date']) . "</p>";
                echo "<a href='booking.php?movie_id=" . htmlspecialchars($movie['id']) . "'>Watch Now</a>";
                echo "</div>";
                echo "</div>";
            }
        } else {
            echo "<p>No movies available at the moment.</p>";
        }
        ?>
        </div>
    </main>

</body>
</html>
