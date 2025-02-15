<?php
include('db/db_connect.php');
if (isset($_GET['movie_id'])) {
    $movie_id = $_GET['movie_id'];
    
    // Fetch movie details
    $sql = "SELECT * FROM movies WHERE id = $movie_id";
    $result = $conn->query($sql);
    $movie = $result->fetch_assoc();
} else {
    echo "No movie selected.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Movies</title>
    <link rel="stylesheet" href="styles/admin_styles.css">
</head>
<body>
    <h2>Manage Movies</h2>
    <form action="manage_movies.php" method="post">
        <input type="text" name="title" placeholder="Title" required>
        <input type="text" name="genre" placeholder="Genre" required>
        <input type="text" name="rating" placeholder="Rating" required>
        <input type="number" name="duration" placeholder="Duration (minutes)" required>
        <input type="date" name="release_date" required>
        <textarea name="synopsis" placeholder="Synopsis" required></textarea>
        <button type="submit" name="add_movie">Add Movie</button>
    </form>
    
    <h3>Existing Movies</h3>
    <table>
        <tr>
            <th>Title</th>
            <th>Genre</th>
            <th>Rating</th>
            <th>Duration</th>
            <th>Release Date</th>
            <th>Actions</th>
        </tr>
        <?php
        // Fetch existing movies
        $sql = "SELECT * FROM movies";
        $result = $conn->query($sql);
        while ($movie = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$movie['title']}</td>
                    <td>{$movie['genre']}</td>
                    <td>{$movie['rating']}</td>
                    <td>{$movie['duration']}</td>
                    <td>{$movie['release_date']}</td>
                    <td>
                        <a href='edit_movie.php?id={$movie['id']}'>Edit</a> |
                        <a href='delete_movie.php?id={$movie['id']}'>Delete</a>
                    </td>
                  </tr>";
        }
        ?>
    </table>
</body>
</html>

<?php
// Add a new movie
if (isset($_POST['add_movie'])) {
    $title = $_POST['title'];
    $genre = $_POST['genre'];
    $rating = $_POST['rating'];
    $duration = $_POST['duration'];
    $release_date = $_POST['release_date'];
    $synopsis = $_POST['synopsis'];

    $sql = "INSERT INTO movies (title, genre, rating, duration, release_date, synopsis) 
            VALUES ('$title', '$genre', '$rating', $duration, '$release_date', '$synopsis')";

    if ($conn->query($sql) === TRUE) {
        echo "Movie added successfully!";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
?>
