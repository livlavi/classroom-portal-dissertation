<?php
session_start();
require_once '../../Global_PHP/db.php'; // Include the database connection

// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM AnnouncementRecipients ar
    LEFT JOIN AnnouncementReads r ON ar.announcement_id = r.announcement_id AND r.user_id = :user_id
    WHERE ar.recipient_id = :user_id AND r.id IS NULL
");
$stmt->execute(['user_id' => $userId]);
$unreadCount = $stmt->fetchColumn();

// Fetch student details
try {
    $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, p.photo_path
        FROM Users u
        LEFT JOIN ProfilePhotos p ON u.id = p.user_id
        WHERE u.id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $studentDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    $firstName = $studentDetails['first_name'] ?? '';
    $lastName = $studentDetails['last_name'] ?? '';
    $profilePhoto = $studentDetails['photo_path'] ?? null;

    // Store the student's name in the session for easy access
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name'] = $lastName;
} catch (PDOException $e) {
    error_log("Error fetching student details: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../CSS/student_dashboard.css" />

    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />

    <style>
    /* Modal Overlay */
    #modalOverlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.5);
        z-index: 900;
    }

    /* Calendar Modal */
    #calendarModal {
        display: none;
        position: fixed;
        top: 10%;
        left: 50%;
        transform: translateX(-50%);
        width: 90%;
        max-width: 900px;
        height: 80vh;
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.7);
        z-index: 1000;
        overflow-y: auto;
    }

    #calendarModal button.closeBtn {
        float: right;
        font-size: 24px;
        border: none;
        background: transparent;
        cursor: pointer;
        line-height: 1;
    }


    /* Add Event Modal */
    #eventModal {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        z-index: 1100;
        width: 300px;
    }

    #eventModal label {
        font-weight: bold;
    }

    body.modal-open {
        overflow: hidden;
    }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</head>

<body class="<?php echo isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark-mode' : ''; ?>">
    <!-- Modal Overlay -->
    <div id="modalOverlay"></div>

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
        <h2>Student Dashboard</h2>
        <ul>
            <li><a href="../../Global_PHP/view_assessments.php">View Assessments</a></li>
            <li><a href="../../Global_PHP/view_homework.php">View Homework</a></li>
            <li><a href="../../Teacher_Dashboard/PHP/grades.php">Grades & Feedback</a></li>
            <li><a href="../../Teacher_Dashboard/PHP/view_announcements.php">
                    Announcements
                    <?php if ($unreadCount > 0): ?>
                    <span class="badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a></li>
            <li><a href="#newsletters">Newsletters</a></li>
            <li><a href="#calendar">Calendar</a></li>
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

        <!-- Assessments Section -->
        <section id="assessments" class="section">
            <h2>Assessments</h2>
            <button onclick="location.href='../../Global_PHP/view_assessments.php'">View Assessments</button>
        </section>

        <!-- Homework Section -->
        <section id="homework" class="section">
            <h2>Homework</h2>
            <button onclick="location.href='../../Global_PHP/view_homework.php'">View Homework</button>
        </section>

        <!-- Grades Section -->
        <section id="grades" class="section">
            <h2>Grades & Feedback</h2>
            <button onclick="location.href='../../Teacher_Dashboard/PHP/grades.php'">View Grades</button>

        </section>

        <!-- Announcements Section -->
        <section id="announcements" class="section">
            <h2>Announcements</h2>
            <a href="../../Teacher_Dashboard/PHP/view_announcements.php" class="announcement-link">
                <button>
                    View Announcements
                    <?php if ($unreadCount > 0): ?>
                    <span class="badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </button>
            </a>
        </section>

        <!-- Newsletters Section -->
        <section id="newsletters" class="section">
            <h2>Newsletters</h2>
            <button onclick="location.href='../../Global_PHP/view_newsletters.php'">View Newsletters</button>
        </section>

        <!-- Calendar Section -->
        <section id="calendar" class="section">
            <h2>Calendar</h2>
            <!-- Button to open calendar modal -->
            <button onclick="openCalendarPopup()">View Calendar</button>

        </section>

    </main>

    <script src="../JavaScript/student_dashboard.js"></script>
</body>

</html>