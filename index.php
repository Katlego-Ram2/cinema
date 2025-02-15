<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cinema_db";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Cinema Booking</title>
    <style>
        /* General Styles */
        body, html {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* Background Video Styling */
        .video-background {
            position: fixed;
            top: 0;
            left: 0;
            min-width: 100%;
            min-height: 100%;
            z-index: -1;
            object-fit: cover;
        }

        header {
            background-color: rgba(37, 150, 190, 0.7);
            color: white;
            padding: 1rem;
            text-align: center;
            position: fixed;
            top: 0;
            width: 100%;
            height: 100px;
            z-index: 2;
            margin-top: -25px;
        }

        nav a {
            color: white;
            margin: 0 10px;
            text-decoration: none;
            font-weight: bold;
        }

        nav a:hover {
            text-decoration: underline;
        }

        /* Main Content Styles */
        main {
            position: relative;
            z-index: 1;
            color: white;
            padding: 20px;
            width: 80%;
            margin: 100px auto 60px;
            text-align: center;
            background-color: rgba(0, 0, 0, 0.6);
            border-radius: 5px;
            margin-bottom: 600px;
            margin-top: 112px;
        }

        .movie-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
        }

        .movie-card {
            background-color: rgba(255, 255, 255, 0.8);
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 22%;
            margin: 10px;
            padding: 10px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 430px;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s; /* Smooth transition for hover effects */
        }

        /* Hover Effect for Movie Card */
        .movie-card:hover {
            transform: scale(1.05); /* Slightly enlarge the card */
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3); /* Increase shadow on hover */
            background-color: rgba(184,191,197,255); /* Make background fully opaque */
        }

        .movie-card img {
            width: 100%;
            height: 70%;
            border-radius: 5px;
        }

        h2 {
            margin: 1px 0;
            font-size: 18px;
        }

        .movie-card p {
            margin: 2px 0;
            font-size: 14px;
        }

        .movie-card .button {
            display: inline-block;
            margin-top: 5px;
            padding: 6px 12px;
            background-color: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .movie-card .button:hover {
            background-color: #555;
        }

        footer {
            background-color: rgba(37, 150, 190, 0.7);
            color: white;
            text-align: center;
            padding: 5px 15px;
            position: fixed;
            width: 100%;
            height: 30px;
            bottom: 0;
            z-index: 2;
            line-height: 5px;
        }

        /* Video Player Styles */
        #trailer-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 3;
            align-items: center;
            justify-content: center;
        }

        #trailer-video {
            width: 80%;
            max-width: 800px;
            height: auto;
            border: 5px solid #fff;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <!-- Background Videos -->
    <video id="video-background" class="video-background" autoplay muted loop>
        <source src="http://localhost/cinema/action.mp4" type="video/mp4">
        Your browser does not support the video tag.
    </video>

    <script>
        // JavaScript for sequential background video playback
        const videoBackground = document.getElementById('video-background');
        const videoSources = [
            "http://localhost/cinema/action.mp4", 
            "http://localhost/cinema/drama.mp4", 
            "http://localhost/cinema/comedy.mp4"
        ];
        let currentVideoIndex = 0;

        videoBackground.addEventListener('ended', function() {
            currentVideoIndex = (currentVideoIndex + 1) % videoSources.length;
            videoBackground.src = videoSources[currentVideoIndex];
            videoBackground.play();
        });
    </script>

    <!-- Hidden Video Player for Trailer -->
    <div id="trailer-modal">
        <video id="trailer-video" controls autoplay>
            <source id="trailer-source" src="" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>

    <script>
        // JavaScript to handle movie card clicks and play trailer
        document.addEventListener('DOMContentLoaded', function() {
            const movieCards = document.querySelectorAll('.movie-card');
            const trailerModal = document.getElementById('trailer-modal');
            const trailerVideo = document.getElementById('trailer-video');
            const trailerSource = document.getElementById('trailer-source');

            movieCards.forEach(card => {
                card.addEventListener('click', function() {
                    const trailerUrl = this.getAttribute('data-trailer');
                    trailerSource.src = trailerUrl;

                    // Debugging: Log the trailer URL to ensure it is correct
                    console.log('Playing trailer:', trailerUrl);

                    trailerVideo.load();
                    trailerModal.style.display = 'flex';
                    trailerVideo.play(); // Start playing the trailer
                });
            });

            // Close the modal when clicking outside the video or when the video ends
            trailerModal.addEventListener('click', function(event) {
                if (event.target === trailerModal) {
                    trailerModal.style.display = 'none';
                    trailerVideo.pause();
                    trailerVideo.currentTime = 0;
                }
            });
        });
    </script>

    <!-- Header Section -->
    <header>
        <h1>Welcome to Online Cinema Booking</h1>
        <nav>
            <a href="register.php">Create a profile</a> |
            <a href="login.php">Login</a> |
            <a href="movies.php">Browse Movies</a>
        </nav>
    </header>

    <!-- Main Content Section -->
    <main>
        <section class="featured-movies">
        <h2>Featured Movies</h2>
        <div class="movie-list">
            <?php
            // Fetch featured movies from the database
            $sql = "SELECT * FROM movies ORDER BY release_date DESC LIMIT 3"; // Limit to 4 movies
            $result = $conn->query($sql);

            if ($result && $result->num_rows > 0) {
                while ($movie = $result->fetch_assoc()) {
                    $trailerUrl = htmlspecialchars($movie['trailer']); // Use 'trailer' column for trailer URL
                    echo "<div class='movie-card' data-trailer='$trailerUrl'>";
                    $imgUrl = htmlspecialchars($movie['image']);
                    echo "<img src='$imgUrl' alt='" . htmlspecialchars($movie['title']) . " Cover'>";
                    echo "<h3>" . htmlspecialchars($movie['title']) . "</h3>";
                    echo "<p><strong>Genre:</strong> " . htmlspecialchars($movie['genre']) . "</p>";
                    echo "<p><strong>Rating:</strong> " . htmlspecialchars($movie['rating']) . "</p>";
                    echo "<a href='booking.php?movie_id=" . htmlspecialchars($movie['id']) . "' class='button'>Book Now</a>";
                    echo "</div>";
                }
            } else {
                echo "<p>No featured movies available at the moment.</p>";
            }
            ?>
        </div>
    </section>

        <section class="about-us">
            <h2>About Us</h2>
            <p>Welcome to our online cinema booking platform, where you can easily browse movies, select showtimes, and book your tickets with just a few clicks. Enjoy a seamless experience from the comfort of your home.</p>
        </section>
    </main>

    <!-- Footer Section -->
    <footer>
        <p>&copy; 2024 Online Cinema Booking. All rights reserved.</p>
    </footer>
</body>
</html>
