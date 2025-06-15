<?php
session_start();
require_once '../../Global_PHP/db.php'; // Include the database connection

// Debug session data
error_log("Teacher Dashboard Session: " . print_r($_SESSION, true));

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

// Fetch teacher details
try {
    $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, p.photo_path 
                           FROM Users u
                           LEFT JOIN ProfilePhotos p ON u.id = p.user_id
                           WHERE u.id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $teacherDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    $firstName = $teacherDetails['first_name'] ?? '';
    $lastName = $teacherDetails['last_name'] ?? '';
    $profilePhoto = $teacherDetails['photo_path'] ?? 'default_profile.jpg'; // Default if null

    // Store the teacher's name in the session for easy access
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name'] = $lastName;
} catch (PDOException $e) {
    error_log("Error fetching teacher details: " . $e->getMessage());
    $firstName = '';
    $lastName = '';
    $profilePhoto = 'default_profile.jpg';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="../CSS/teacher_dashboard.css">
    <style>
    /* Button Styling */
    button {
        background-color: #007bff;
        color: white;
        border: none;
        padding: 10px 15px;
        margin: 5px;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    button:hover {
        background-color: #0056b3;
    }

    /* Section Styling */
    .section {
        margin-bottom: 20px;
    }

    .section h2 {
        margin-bottom: 10px;
    }

    /* Sidebar Styling */
    .sidebar ul {
        list-style: none;
        padding: 0;
    }

    .sidebar ul li {
        margin: 10px 0;
    }

    .sidebar ul li a {
        text-decoration: none;
        color: white;
        font-weight: bold;
    }

    .sidebar ul li a:hover {
        color: #ecf0f1;
    }

    /* Homework and Assessments Div Styling */
    .homework-actions,
    .assessment-actions {
        margin-bottom: 10px;
    }

    body.dark-mode .section {
        background-color: #444;
        color: #fff;
    }
    </style>
</head>

<body class="<?php echo isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark-mode' : ''; ?>">
    <!-- Top Navigation Bar -->
    <header class="top-bar">
        <nav class="profile-actions">
            <a href="#" id="dark-mode-toggle">Dark Mode</a>
            <a href="../../Global_PHP/profile.php">Profile</a>
            <a href="../../Global_PHP/logout.php">Logout</a>
        </nav>
        <div class="admin-profile">
            <img src="../../Images/<?= htmlspecialchars($profilePhoto) ?>" alt="Profile Photo" class="profile-photo">
            <p><?= htmlspecialchars($firstName . ' ' . $lastName) ?></p>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar">
        <h2>Teacher Dashboard</h2>
        <ul>
            <li><a href="view_attendance.php">Attendance Reports</a></li>
            <li><a href="#homework">Manage Homework</a></li>
            <li><a href="#assessments">Manage Assessments</a></li>
            <li><a href="grades.php">Grades & Feedback</a></li>
            <li><a href="view_calendar.php">Calendar</a></li>
            <li><a href="view_announcements.php">Announcements</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Live Chat -->
        <div class="section" id="live-chat">
            <h3>Messages</h3>
            <button id="open-chat-modal"
                onclick="window.open('/Classroom Portal Dissertation/Messaging_Chat/chat.php', '_blank')">Open
                Chat</button>
        </div>

        <section id="attendance" class="section">
            <h2>Attendance</h2>
            <button onclick="location.href='attendance.php'">Take Attendance</button>
        </section>

        <section id="homework" class="section">
            <h2>Homework</h2>
            <div class="homework-actions">
                <button onclick="location.href='homework.php'">Add Homework</button>
                <button onclick="location.href='view_assigned_homework.php'">View and Edit Homework</button>
                <button onclick="location.href='review_submitted_homework.php'">Review Submitted Homework</button>
            </div>
        </section>

        <section id="assessments" class="section">
            <h2>Assessments</h2>
            <div class="assessment-actions">
                <button onclick="location.href='add_assessment.php'">Add Assessment</button>
                <button onclick="location.href='../../Global_PHP/submitted_assessment.php'">Check Submitted
                    Assessments</button>
                <button onclick="location.href='../../Global_PHP/view_reviewed_assessments.php'">View Reviewed
                    Assessments</button>
            </div>
        </section>

        <section id="grades" class="section">
            <h2>Grades & Feedback</h2>
            <button onclick="location.href='grades.php'">Add Grades</button>
            <button onclick="location.href='reports.php'">Add Report</button>
        </section>

        <section id="calendar" class="section">
            <h2>Calendar</h2>
            <button onclick="location.href='calendar.php'">View Calendar</button>
        </section>

        <section id="announcements" class="section">
            <h2>Announcements</h2>
            <button onclick="location.href='announcements.php'">Post Announcement</button>
        </section>
    </main>

    <script src="../JavaScript/teacher_dashboard.js"></script>
    <script>
    // Sidebar toggle and dark mode
    document.getElementById("dark-mode-toggle")?.addEventListener("click", () => {
        document.body.classList.toggle("dark-mode");
        document.cookie = `dark_mode=${document.body.classList.contains("dark-mode")}; path=/`;
    });

    // Open chat modal
    document.getElementById("open-chat-modal")?.addEventListener("click", () => {
        window.open("/Classroom Portal Dissertation/Messaging_Chat/chat.php", "_blank");
    });
    </script>
</body>

</html>