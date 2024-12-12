<?php
require 'config.php';  // Include the config file for database connection

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');  // Redirect to dashboard if already logged in
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: dashboard.php');
        exit;
    } else {
        echo "<p class='error'>Invalid login credentials!</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank App - Login</title>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h1>Welcome to Our Bank</h1>

            <!-- Login Form -->
            <h2>Login</h2>
            <form method="POST" class="form">
                <input type="text" name="username" placeholder="Username" required><br>
                <input type="password" name="password" placeholder="Password" required><br>
                <button type="submit" name="login">Login</button>
            </form>
            <p class="register-text">Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</body>
</html>
