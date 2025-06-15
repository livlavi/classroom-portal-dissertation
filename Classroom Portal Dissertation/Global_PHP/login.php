<?php
session_start();
require_once 'db.php'; // Include the database connection
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $consent = isset($_POST['consent']) ? true : false; // Check if consent checkbox was ticked


    // Fetch user from the database
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Redirect based on role
        if ($user['role'] === 'admin') {
            header("Location: ../Admin_Dashboard/PHP/admin_dashboard.php");
            exit;
        } elseif ($user['role'] === 'teacher') {
            header("Location: ../Teacher_Dashboard/PHP/teacher_dashboard.php");
            exit;
        } elseif ($user['role'] === 'student') {
            header("Location: ../Student_Dashboard/PHP/student_dashboard.php");
            exit;
        } elseif ($user['role'] === 'parent') {
            header("Location: ../Parent_Dashboard/PHP/parent_dashboard.php");
            exit;
        } else {
            header("Location: ../index.php");
            exit;
        }
    } else {
        $error = "Invalid username or password.";
    }
}

// Handle success message from registration
if (isset($_GET['success']) && $_GET['success'] == 1) :
?>
<p class="success">You have successfully register! Please log in.</p>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Classroom Management Portal</title>
    <link rel="stylesheet" href="../Global_CSS/login.css"> <!-- Link to external CSS -->
</head>

<body>
    <!-- Header -->
    <header>
        <img src="../Images/logo.png" alt="Portal Logo" class="navbar-logo">
        <h1>AllConnectEdu</h1>
        <nav>
            <a href="../index.php">About Us</a>
            <a href="#">Contact Support</a>
        </nav>
    </header>
    <!-- Login Container -->
    <div class="login-container">
        <h2>Login</h2>
        <?php if ($success): ?>
        <p class="success">You have successfully registered! Please log in.</p>
        <?php endif; ?>
        <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="text" name="username" placeholder="Enter your username" required>
            <input type="password" name="password" placeholder="Enter your password" required>

            <div class="gdpr-consent">
                <input type="checkbox" id="consent" name="consent" required>
                <label for="consent">I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a
                        href="privacy_policy.php" target="_blank">Privacy Policy</a>.</label>
            </div>
            <button type="submit">Login</button>
        </form>
        <div>
            <a href="forgot_password.php">Forgot Password?</a>
            <a href="register.php">Register</a>
        </div>
    </div>


    <footer>
        &copy; 2025 AllConnectEdu. All rights reserved.
        <a href="privacy_policy.php" target="_blank">Privacy Policy</a> |
        <a href="terms.php" target="_blank">Terms of Service</a>
    </footer>
</body>

</html>