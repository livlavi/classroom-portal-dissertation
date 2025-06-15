<?php
session_start();

// Redirect logged-in users to their respective dashboards
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: Admin_Dashboard/PHP/admin_dashboard.php");
            break;
        case 'teacher':
            header("Location: Teacher_Dashboard/PHP/teacher_dashboard.php");
            break;
        case 'student':
            header("Location: PHP/student_dashboard.php");
            break;
        case 'parent':
            header("Location: PHP/parent_dashboard.php");
            break;
        default:
            header("Location: PHP/unauthorised.php");
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classroom Management Portal</title>
    <link rel="stylesheet" href="Global_CSS/index.css"> <!-- Link to external CSS -->
    <script src="JavaScript/index.js" defer></script> <!-- Link to external JS -->
</head>

<body>

    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo-link">
                <img src="Images/logo.png" alt="Portal Logo" class="navbar-logo">

            </a>
            <div class="nav-links">
                <a href="#about" class="btn-secondary">Learn More</a>
            </div>
        </div>
    </nav>
    <!-- Hero Section -->
    <header class="hero">
        <div class="hero-content">
            <!-- <img src="Images/logo.png" alt="Portal Logo" class="logo"> -->
            <h1>Welcome to the AllConnectEdu</h1>
            <p>
                Streamline classroom activities, manage attendance, assignments, and communication
                between teachers, students, and parents—all in one place.
            </p>
            <div class=" cta-buttons">
                <a href="Global_PHP/login.php" class="btn-primary">Login</a>
                <a href="#about" class="btn-secondary">Learn More</a>
            </div>
        </div>
    </header>

    <!-- About Section -->
    <section id="about" class="about">
        <h2>About the Portal</h2>
        <p>
            The Classroom Management Portal is designed to simplify the educational experience for
            teachers, students, and parents. With features like attendance tracking, assignment management,
            and real-time communication, this portal ensures seamless collaboration and organization.
        </p>
        <ul class="features-list">
            <li><i class="fas fa-check-circle"></i> Manage classes and assignments effortlessly.</li>
            <li><i class="fas fa-check-circle"></i> Track attendance and performance.</li>
            <li><i class="fas fa-check-circle"></i> Communicate with teachers, students, and parents.</li>
            <li><i class="fas fa-check-circle"></i> Access analytics and reports.</li>
        </ul>
    </section>

    <!-- Footer -->
    <footer>
        <p>&copy; 2025 Classroom Management Portal. All rights reserved.</p>
    </footer>
</body>

</html>