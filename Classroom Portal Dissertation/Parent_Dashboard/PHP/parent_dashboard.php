<?php
session_start();
require_once '../../Global_PHP/db.php'; // Include the database connection
require_once '../../Global_PHP/getting_informations.php'; // Include the utility functions

// Check if the user is logged in and is a parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
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


// Fetch parent and child details
try {
    // Fetch parent details using fetchUserDetails (for parent's first_name, last_name, and child_full_name)
    $userDetails = fetchUserDetails($pdo, $_SESSION['user_id'], 'parent');

    if (!$userDetails['success']) {
        error_log("Error fetching parent details: " . $userDetails['message']);
        $firstName = '';
        $lastName = '';
        $childName = 'No child associated';
        $childId = null;
    } else {
        $firstName = $userDetails['first_name'] ?? '';
        $lastName = $userDetails['last_name'] ?? '';
        $childFullName = $userDetails['child_full_name'] ?? 'No child associated';

        // Store the parent's name in the session for easy access
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;

        // Log initial user details
        error_log("Parent user_id: {$_SESSION['user_id']}, First Name: $firstName, Last Name: $lastName, Child Full Name:
$childFullName");

        // Attempt to fetch the child's user_id by matching child_full_name to Users
        if ($childFullName !== 'No child associated') {
            $stmt = $pdo->prepare("SELECT id FROM Users WHERE CONCAT(first_name, ' ', last_name) = :child_full_name AND role =
'student'");
            $stmt->execute(['child_full_name' => trim($childFullName)]);
            $childUser = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Query result for child from Users: " . print_r($childUser, true));

            $childId = $childUser['id'] ?? null;

            if ($childId) {
                error_log("Child ID from Users table: $childId");
                // Fetch the child's name from Users for consistency
                $stmt = $pdo->prepare("SELECT first_name, last_name FROM Users WHERE id = :child_id");
                $stmt->execute(['child_id' => $childId]);
                $childUserDetails = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($childUserDetails) {
                    $childName = $childUserDetails['first_name'] . ' ' . $childUserDetails['last_name'];
                    error_log("Child name from Users table: $childName");
                } else {
                    error_log("Child details not found in Users for child_id: $childId");
                    $childName = $childFullName; // Fallback to child_full_name
                }
            } else {
                error_log("No student found in Users table for child_full_name: $childFullName");
                $childName = 'Child not found in Users table';
            }
        } else {
            $childId = null;
            $childName = 'No child associated';
        }
    }

    // Fetch profile photo separately (since fetchUserDetails doesn't include it)
    $stmt = $pdo->prepare("SELECT photo_path FROM ProfilePhotos WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $profilePhoto = $stmt->fetchColumn() ?: 'default_profile.jpg';
} catch (PDOException $e) {
    error_log("Error fetching parent or child details: " . $e->getMessage());
    $firstName = '';
    $lastName = '';
    $childName = 'Error fetching child details';
    $childId = null;
    $profilePhoto = 'default_profile.jpg';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard</title>
    <link rel="stylesheet" href="../CSS/parent_dashboard.css">
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
            <p><?= htmlspecialchars($firstName . ' ' . $lastName) ?> (Parent of <?= htmlspecialchars($childName) ?>)</p>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar">
        <h2>Parent Dashboard</h2>
        <ul>
            <li><a href='../../Global_PHP/view_reviewed_assessments.php?student_id=<?= $childId ?>'>Reviewed
                    Assessments</a></li>
            <li><a href='../../Global_PHP/view_reviewed_homework.php?child_id=<?= $childId ?>'>View Homework</a></li>
            <li><a href="../../Teacher_Dashboard/PHP/grades.php">Grades & Feedback</a></li>
            <li><a href='../../Teacher_Dashboard/PHP/view_attendance.php'>Attendance</a></li>
            <li><a href=" ../../Teacher_Dashboard/PHP/view_announcements.php">
                    Announcements
                    <?php if ($unreadCount > 0): ?>
                    <span class="badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li><a href="../../Global_PHP/view_newsletters.php">Newsletters</a></li>
            <li><a href="parent_view_calendar.php">Calendar</a></li>
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

        <section id="attendance-section" class="section">
            <h2>Attendance</h2>
            <?php if ($childId): ?>
            <button
                onclick="location.href='../../Teacher_Dashboard/PHP/view_attendance.php?student_id=<?= $childId ?>'">View
                Attendance</button>
            <?php else: ?>
            <p>No child associated with this account or child not found in Users table. Please contact the
                administrator.</p>
            <?php endif; ?>
        </section>


        <!-- Assessments Section -->
        <section id="assessments" class="section">
            <h2>Reviewed Assessments</h2>
            <?php if ($childId): ?>
            <button
                onclick="location.href='../../Global_PHP/view_reviewed_assessments.php?student_id=<?= $childId ?>'">View
                Reviewed Assessments</button>
            <?php else: ?>
            <p>No child associated with this account or child not found in Users table. Please contact the
                administrator.</p>
            <?php endif; ?>
        </section>

        <!-- Homework Section -->
        <section id="homework" class="section">
            <h2>Homework</h2>
            <?php if ($childId): ?>
            <button onclick="location.href='../../Global_PHP/view_reviewed_homework.php?child_id=<?= $childId ?>'">View
                Reviewed Homework</button>
            <?php else: ?>
            <p>No child associated with this account or child not found in Users table. Please contact the
                administrator.</p>
            <?php endif; ?>
        </section>

        <!-- Grades Section -->
        <section id="grades" class="section">
            <h2>Grades & Feedback</h2>
            <?php if ($childId): ?>
            <button onclick="location.href='../../Teacher_Dashboard/PHP/grades.php'">View Grades</button>
            <?php else: ?>
            <p>No child associated with this account or child not found in Users table. Please contact the
                administrator.</p>
            <?php endif; ?>
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
            <button onclick="location.href='parent_view_calendar.php'">View Calendar</button>
        </section>
    </main>

    <script src="../JavaScript/parent_dashboard.js"></script>
    <script>
    // Dark mode toggle (if not already in parent_dashboard.js)
    document.getElementById("dark-mode-toggle")?.addEventListener("click", () => {
        document.body.classList.toggle("dark-mode");
        document.cookie = `dark_mode=${document.body.classList.contains('dark-mode')}; path=/`;
    });

    // Open chat modal (if not already in parent_dashboard.js)
    document.getElementById("open-chat-modal")?.addEventListener("click", () => {
        window.open("/Classroom Portal Dissertation/Messaging_Chat/chat.php", "_blank");
    });
    </script>
</body>

</html>