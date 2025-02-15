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

// Fetch user profile data
$user_sql = "SELECT name, surname, email FROM users WHERE id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();

$name = $user_data['name'] ?? '';
$surname = $user_data['surname'] ?? '';
$email = $user_data['email'] ?? '';

// Fetch booked movies with online and cinema viewing options, including the last played time and the booking time
$sql = "SELECT bookings.id AS booking_id, bookings.played, bookings.last_played_time, bookings.booking_time, 
               movies.title, movies.movie, movies.trailer, movies.image, movies.release_date, bookings.watch_option, bookings.ticket_type 
        FROM bookings 
        JOIN movies ON bookings.movie_id = movies.id 
        WHERE bookings.user_id = '$user_id' 
        ORDER BY bookings.booking_time DESC, bookings.played ASC";

$result = $conn->query($sql);

// Store results in an array to avoid re-fetching
$movies = [];
while ($movie = $result->fetch_assoc()) {
    $movies[] = $movie;
}

// Separate booked and played movies, and check for 24-hour expiry for refund
$booked_movies = [];
$played_movies = [];
$current_time = time();

foreach ($movies as $movie) {
    $booking_id = $movie['booking_id'];
    $booking_time = strtotime($movie['booking_time']);
    $played = $movie['played'];
    
    // If 24 hours have passed and movie hasn't been played, mark as played and refund the user
    if (!$played && ($current_time - $booking_time > 86400)) {
        $conn->query("UPDATE bookings SET played = 1 WHERE id = $booking_id");

        // Refund logic: add amount back to wallet (for simplicity, assuming a fixed amount)
        $refund_amount = 10.00; // This can be dynamic based on ticket type
        $conn->query("UPDATE wallets SET balance = balance + $refund_amount WHERE user_id = $user_id");
    }

    // Separate into booked and played movies
    if ($played) {
        $played_movies[] = $movie;
    } else {
        $booked_movies[] = $movie;
    }
}

// Fetch wallet balance
$wallet_sql = "SELECT balance FROM wallets WHERE user_id = '$user_id'";
$wallet_result = $conn->query($wallet_sql);
$wallet = $wallet_result->fetch_assoc();
$wallet_balance = $wallet ? $wallet['balance'] : 0;

?>





<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Watch Movies</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        @media (max-width: 768px) {
            .movie-list {
                flex-direction: column;
                align-items: center;
            }
            .movie {
                width: 100%;
                height: auto;
            }
        }

        body {
            background-color: #141414;
            color: #fff;
            font-family: Arial, sans-serif;
            margin: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
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

        .container {
            display: flex;
            flex: 1;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .wallet-balance {
            text-align: center;
            margin-bottom: 20px;
            font-size: 18px;
            color: #ffcc00;
        }

         /* Your existing styles */
        .trailer-container {
            width: 100%;
            height: 400px;
            margin-bottom: 20px;
            overflow: hidden;
            position: relative;
        }

        .trailer-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .movie-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }

        .movie {
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            margin: 15px;
            padding: 20px;
            width: 250px;
            height: 350px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            color: white;
            position: relative;
            text-align: center;
        }

        .movie h3 {
            margin: 0;
            background-color: rgba(0, 0, 0, 0.6);
            padding: 10px;
            border-radius: 5px;
            color: #ffcc00;
        }

        button.play-button {
            padding: 10px;
            background-color: rgba(255, 204, 0, 0.8);
            border: none;
            border-radius: 50px;
            color: #111;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 100px;
            height: 100px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto;
        }

        button.play-button:hover {
            background-color: #ffcc00;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            position: relative;
            width: 80%;
            max-width: 900px;
        }

        .modal video {
            width: 100%;
            border-radius: 10px;
        }

        .modal .close {
            position: absolute;
            top: 15px;
            right: 30px;
            font-size: 30px;
            color: white;
            cursor: pointer;
        }

        .resume-choice {
            margin-top: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
        }

        .no-movies {
            text-align: center;
            margin-top: 50px;
            font-size: 20px;
        }
    </style>

    <script>
        let lastPlayedTime = 0;

        // Open the modal and ask the user if they want to resume or start fresh
        function playMovie(movieUrl, bookingId, lastTime) {
            lastPlayedTime = lastTime;

            // Hide the cancel button for this movie card
    const cancelButton = document.getElementById(`cancelButton-${bookingId}`);
    if (cancelButton) {
        cancelButton.style.display = 'none';
    }
            
            if (lastPlayedTime > 0) {
                // If there's a last played time, ask whether to resume or start fresh
                const resumeChoice = confirm("Do you want to resume the movie from where you left off?");
                if (resumeChoice) {
                    openModal(movieUrl, lastPlayedTime, bookingId); // Resume from last played time
                } else {
                    openModal(movieUrl, 0, bookingId); // Start from beginning
                }
            } else {
                openModal(movieUrl, 0, bookingId); // No last played time, start fresh
            }
        }

        // Open the modal and play the video at the specified time
        function openModal(movieUrl, startTime, bookingId) {
            const modal = document.getElementById('videoModal');
            const videoElement = document.getElementById('modalVideo');
            modal.style.display = 'flex';
            videoElement.src = movieUrl;
            videoElement.currentTime = startTime;
            videoElement.play();

            // Save progress periodically
            videoElement.addEventListener('timeupdate', function() {
                saveProgress(bookingId, videoElement.currentTime);
            });
        }

        // Close the modal and save the current time
        function closeModal() {
            const modal = document.getElementById('videoModal');
            const videoElement = document.getElementById('modalVideo');
            saveProgress(null, videoElement.currentTime);
            videoElement.pause();
            modal.style.display = 'none';
        }

        // Save the last played time to the database
        function saveProgress(bookingId, currentTime) {
            if (bookingId) {
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "save_progress.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.send("booking_id=" + bookingId + "&last_played_time=" + currentTime);
            }
        }

        // Close the modal when clicking outside the video
        window.onclick = function(event) {
            const modal = document.getElementById('videoModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>

     <script>
 let trailers = <?php
    // Prepare the trailers array
    $trailers = [];
    $result->data_seek(0);  // Reset the pointer to re-fetch trailers
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['trailer'])) {
            $trailers[] = $row['trailer'];
        }
    }
    echo json_encode($trailers);
?>;

let currentTrailer = 0;
let videoElement;

// Function to load and play the next trailer
function playNextTrailer() {
    if (trailers.length > 0 && videoElement) {
        videoElement.src = trailers[currentTrailer]; // Set the current trailer's URL

        videoElement.onloadeddata = function() {
            videoElement.play(); // Start playing the trailer once it's loaded
        };
        
        videoElement.onerror = function() {
            console.error("Error loading the trailer: " + trailers[currentTrailer]);
        };

        currentTrailer = (currentTrailer + 1) % trailers.length;  // Loop to the next trailer
    } else {
        console.error('No trailers available or video element not found.');
    }
}

window.onload = function() {
    videoElement = document.getElementById('background-trailer'); // Reference the trailer video element
    
    if (videoElement) {
        playNextTrailer(); // Start playing the first trailer
        
        // When the current trailer ends, play the next one
        videoElement.addEventListener('ended', playNextTrailer);
    } else {
        console.error('Video element not found.');
    }
};


</script>

</head>
<body>

    <!-- Header Section -->
<header>
    <nav>
        <a href="#" id="editProfileLink">Edit Profile</a>
        <a href="watch.php">Browse Movies</a>
        <a href="index.php">Logout</a>
        <a href="#" class="wallet-balance">
            Wallet Balance: R<?php echo number_format($wallet_balance, 2); ?>
        </a>
        Hi <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
    </nav>
</header>



<!-- Profile Edit Modal -->
<div id="editProfileModal" class="modal">
    <div class="modal-content">
        <span class="close" id="closeEditProfileModal">&times;</span>
        <h2>Edit Profile</h2>
        <form id="editProfileForm" action="update_profile.php" method="POST">
            <label for="name">Name:</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="surname">Surname:</label>
            <input type="text" name="surname" value="<?php echo htmlspecialchars($surname, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="email">Email:</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="password">Password:</label>
            <input type="password" name="password" placeholder="Enter new password (optional)">

            <button type="submit">Update Profile</button>
        </form>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    justify-content: center;
    align-items: center;
}

.modal-content {
    background-color: #222;
    padding: 20px;
    border-radius: 10px;
    width: 400px;
    max-width: 90%;
    color: grey;
}

.modal-content label {
    display: block;
    margin: 10px 0 5px;
}

.modal-content input {
    width: 100%;
    padding: 8px;
    margin-bottom: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

.modal-content button {
    background-color: #ffcc00;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
}

.modal-content button:hover {
    background-color: grey;
}

.modal .close {
    position: absolute;
    top: 10px;
    right: 20px;
    font-size: 24px;
    cursor: pointer;
    color: white;
}
</style>

<script>
    // Show modal when edit profile link is clicked
    document.getElementById('editProfileLink').onclick = function() {
        document.getElementById('editProfileModal').style.display = 'flex';
    };

    // Close modal when the 'X' is clicked
    document.getElementById('closeEditProfileModal').onclick = function() {
        document.getElementById('editProfileModal').style.display = 'none';
    };

    // Close modal if clicking outside the modal content
    window.onclick = function(event) {
        const modal = document.getElementById('editProfileModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    };
</script>








    <div class="container">
    <div class="main-content">
        <!-- Trailer section -->
        <div class="trailer-container">
            <video id="background-trailer" autoplay muted loop></video>
        </div>

        <h2>Your Booked Movies (Online & Cinema)</h2>

        <div class="movie-list" id="booked-movies-list">
        <?php
        // Reset the pointer to fetch all movies again
        $result->data_seek(0);

        if ($result->num_rows > 0) {
            // Separate booked and played movies
            $booked_movies = [];
            $played_movies = [];

            while ($movie = $result->fetch_assoc()) {
                if ($movie['played']) {
                    $played_movies[] = $movie;
                } else {
                    $booked_movies[] = $movie;
                }
            }

            // Display booked movies
foreach ($booked_movies as $movie) {
    $title = !empty($movie['title']) ? htmlspecialchars($movie['title']) : 'Untitled Movie';
    $movie_url = !empty($movie['movie']) ? htmlspecialchars($movie['movie']) : null;
    $image_url = !empty($movie['image']) ? htmlspecialchars($movie['image']) : 'default-image.jpg';
    $booking_id = $movie['booking_id'];
    $last_played_time = $movie['last_played_time'];
    $is_played = isset($movie['is_played']) ? $movie['is_played'] : false; // Set a default value of false if 'is_played' is missing

    echo "<div class='movie' style='background-image: url($image_url); display: none;'>";
    echo "<h3>$title</h3>";

    if ($movie['watch_option'] == 'online') {
        if ($movie_url) {
            echo "<button class='play-button' onclick='playMovie(\"$movie_url\", $booking_id, $last_played_time)'>Play</button>";
        } else {
            echo "<p>No video available for this movie.</p>";
        }
    } else {
        echo "<p><strong>Watch Option:</strong> Cinema/Theater</p>";
    }

    // Only show the Cancel button if is_played is false
    if (!$is_played) {
        echo "<form action='cancel_booking.php' method='POST' id='cancelForm-$booking_id'>
                <input type='hidden' name='booking_id' value='$booking_id'>
                <button type='submit' id='cancelButton-$booking_id'>Cancel Booking</button>
              </form>";
    }

    echo "</div>";
          }

        } else {
            echo "<div class='no-movies'><p>No movies booked for online or cinema viewing.</p></div>";
        }
        ?>
        </div>

        <div class="pagination">
            <button id="prev-booked" onclick="changeBookedPage(-1)">&#9664;</button>
            <span id="page-info-booked"></span>
            <button id="next-booked" onclick="changeBookedPage(1)">&#9654;</button>
        </div>

        <h3>Movies Already Played</h3>
        <div class="movie-list" id="played-movies-list">
        <?php
        if (count($played_movies) > 0) {
            foreach ($played_movies as $movie) {
                $title = !empty($movie['title']) ? htmlspecialchars($movie['title']) : 'Untitled Movie';
                $image_url = !empty($movie['image']) ? htmlspecialchars($movie['image']) : 'default-image.jpg';
                $rating = !empty($movie['rating']) ? intval($movie['rating']) : 0; // Retrieve rating

                echo "<div class='movie' style='background-image: url($image_url); display: none;'>";
                echo "<h3>$title</h3>";
                echo "<p>Movie already played.</p>";

                /*

                // Star rating system for played movies
                echo "<div class='star-rating' data-booking-id='{$movie['booking_id']}' data-rating='$rating'>"; // Use rating from the database
                for ($i = 1; $i <= 5; $i++) {
                    $starColor = $i <= $rating ? 'gold' : 'gray'; // Set star color based on rating
                    echo "<span class='star' data-value='$i' style='color: $starColor;'>&#9733;</span>";
                }
                echo "</div>";  */

                echo "</div>";
            }
        } else {
            echo "<div class='no-movies'><p>No movies have been played.</p></div>";
        }
        ?>
        </div>

        <div class="pagination">
            <button id="prev-played" onclick="changePlayedPage(-1)">&#9664;</button>
            <span id="page-info-played"></span>
            <button id="next-played" onclick="changePlayedPage(1)">&#9654;</button>
        </div>
    </div>
</div>


<script>
let currentBookedPage = 0;
let currentPlayedPage = 0;
const moviesPerPage = 4;

// Function to show the booked movies for the current page
function showBookedPage(page) {
    const bookedMovies = document.querySelectorAll('#booked-movies-list .movie');
    const totalBookedPages = Math.ceil(bookedMovies.length / moviesPerPage);
    currentBookedPage = Math.max(0, Math.min(page, totalBookedPages - 1));

    bookedMovies.forEach((movie, index) => {
        movie.style.display = (index >= currentBookedPage * moviesPerPage && index < (currentBookedPage + 1) * moviesPerPage) ? 'block' : 'none';
    });

    document.getElementById('page-info-booked').innerText = `Page ${currentBookedPage + 1} of ${totalBookedPages}`;
    document.getElementById('prev-booked').disabled = currentBookedPage === 0;
    document.getElementById('next-booked').disabled = currentBookedPage === totalBookedPages - 1;
}

// Function to show the played movies for the current page
function showPlayedPage(page) {
    const playedMovies = document.querySelectorAll('#played-movies-list .movie');
    const totalPlayedPages = Math.ceil(playedMovies.length / moviesPerPage);
    currentPlayedPage = Math.max(0, Math.min(page, totalPlayedPages - 1));

    playedMovies.forEach((movie, index) => {
        movie.style.display = (index >= currentPlayedPage * moviesPerPage && index < (currentPlayedPage + 1) * moviesPerPage) ? 'block' : 'none';
    });

    document.getElementById('page-info-played').innerText = `Page ${currentPlayedPage + 1} of ${totalPlayedPages}`;
    document.getElementById('prev-played').disabled = currentPlayedPage === 0;
    document.getElementById('next-played').disabled = currentPlayedPage === totalPlayedPages - 1;
}

// Function to handle star rating click
document.addEventListener('DOMContentLoaded', () => {
    const stars = document.querySelectorAll('.star-rating .star');

    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-value');
            const bookingId = this.closest('.star-rating').getAttribute('data-booking-id');
            submitRating(bookingId, rating);

            // Update the star display for the specific movie
            const starRating = this.parentElement;
            const allStars = starRating.querySelectorAll('.star');
            allStars.forEach(s => {
                s.style.color = s.getAttribute('data-value') <= rating ? 'gold' : 'gray';
            });

            // Store the rating in the data attribute
            starRating.setAttribute('data-rating', rating);
        });
    });
});

// Function to submit the rating to the server
function submitRating(bookingId, rating) {
    const review = ""; // Add a review text if needed, or keep it empty

    // Create a form data object to submit the data
    const formData = new FormData();
    formData.append('movie_id', bookingId); // Change booking_id to movie_id
    formData.append('rating', rating);
    formData.append('review', review); // If you have a review text input, you can capture it here

    fetch('submit_review.php', {
        method: 'POST',
        body: formData,
    })
    .then(response => response.text())
    .then(data => {
        alert('Rating submitted successfully!'); // You can customize this message
        // Optionally, refresh the page or update the UI
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Initial display
showBookedPage(currentBookedPage);
showPlayedPage(currentPlayedPage);

// Functions to change pages for both sections
function changeBookedPage(direction) {
    showBookedPage(currentBookedPage + direction);
}

function changePlayedPage(direction) {
    showPlayedPage(currentPlayedPage + direction);
}

</script>

   <!-- Modal for playing the video -->
<div id="videoModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <video id="modalVideo" controls></video>
    </div>
</div>

<script>
    // Open the modal and play video from specified start time
    function openModal(movieUrl, startTime, bookingId) {
        const modal = document.getElementById('videoModal');
        const videoElement = document.getElementById('modalVideo');
        modal.style.display = 'flex';  // Display the modal
        videoElement.src = movieUrl;
        videoElement.currentTime = startTime; // Set start time
        videoElement.play();

        // Save progress periodically
        videoElement.addEventListener('timeupdate', saveProgressEvent);
        
        function saveProgressEvent() {
            saveProgress(bookingId, videoElement.currentTime);
        }
    }

    // Close modal and pause video
    function closeModal() {
        console.log("closeModal function triggered");  // Check if function is called
        const modal = document.getElementById('videoModal');
        const videoElement = document.getElementById('modalVideo');
        videoElement.pause(); // Stop the video
        videoElement.src = ''; // Reset source to stop playback fully
        modal.style.display = 'none';
    }

    // Add an event listener for the close button in case inline onclick fails
    document.querySelector('.close').addEventListener('click', closeModal);

    // Save the last played time to the database
    function saveProgress(bookingId, currentTime) {
        if (bookingId) {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "save_progress.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.send("booking_id=" + bookingId + "&last_played_time=" + currentTime);
        }
    }


function playMovie(movieUrl, bookingId, lastTime) {
    lastPlayedTime = lastTime;

    // Hide the cancel button for this movie card
    const cancelButton = document.getElementById(`cancelButton-${bookingId}`);
    if (cancelButton) {
        cancelButton.style.display = 'none';
    }

    // Update the database to set is_played to true for this booking
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_played_status.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.send("booking_id=" + bookingId);

    // Play the movie at the selected start time
    if (lastPlayedTime > 0) {
        const resumeChoice = confirm("Do you want to resume the movie from where you left off?");
        openModal(movieUrl, resumeChoice ? lastPlayedTime : 0, bookingId);
    } else {
        openModal(movieUrl, 0, bookingId);
    }
}


    

    // Close modal if clicking outside the modal content
    window.onclick = function(event) {
        const modal = document.getElementById('videoModal');
        if (event.target === modal) {
            closeModal();
        }
    };
</script>



</body>
</html>