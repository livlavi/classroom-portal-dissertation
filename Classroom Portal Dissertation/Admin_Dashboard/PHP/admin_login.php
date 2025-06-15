<?php
session_start();
require_once 'db.php'; // Include the database connection

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Fetch admin from the database
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = :username AND role = 'admin'");
    $stmt->execute(['username' => $username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['role'] = $admin['role'];

        // Redirect to the admin dashboard
        header("Location: admin_dashboard.php");
        exit;
    } else {
        $error = "Invalid admin credentials.";
    }
}
?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Classroom Management Portal</title>
    <link rel="stylesheet" href="../CSS/admin_login.css"> <!-- Link to external CSS -->
</head>

<body>
    <!-- Header -->
    <header>
        <h1>Classroom Management Portal</h1>
        <nav>
            <a href="../../Global_PHP/login.php">Back to General Login</a>
        </nav>
    </header>

    <!-- Admin Login Container -->
    <div class="admin-login-container">
        <h2>Admin Login</h2>
        <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form action="" method="POST">
            <input type="text" name="username" placeholder="Enter your admin username" required>
            <input type="password" name="password" placeholder="Enter your admin password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>

</html>