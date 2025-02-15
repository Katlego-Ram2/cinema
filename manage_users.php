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
    <title>Manage Users</title>
    <link rel="stylesheet" href="styles/admin_styles.css">
</head>
<body>
    <h2>Manage Users</h2>
    <h3>Existing Users</h3>
    <table>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Age</th>
            <th>Role</th>
            <th>Actions</th>
        </tr>
        <?php
        // Fetch existing users
        $sql = "SELECT * FROM users";
        $result = $conn->query($sql);
        while ($user = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$user['name']} {$user['surname']}</td>
                    <td>{$user['email']}</td>
                    <td>{$user['age']}</td>
                    <td>{$user['role']}</td>
                    <td>
                        <a href='edit_user.php?id={$user['id']}'>Edit</a> |
                        <a href='delete_user.php?id={$user['id']}'>Delete</a>
                    </td>
                  </tr>";
        }
        ?>
    </table>
</body>
</html>
