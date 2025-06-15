<?php
session_start();
require_once '../../Global_PHP/db.php';
require_once '../../Global_PHP/auth.php';
require_once '../../Global_PHP/getting_informations.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

$teacherId = $_SESSION['user_id'] ?? null;
$firstName = '';
$lastName = '';
$profilePhoto = null;
$submittedHomeworks = [];

if ($teacherId) {
    // Fetch teacher details
    try {
        $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, p.photo_path 
                               FROM Users u
                               LEFT JOIN ProfilePhotos p ON u.id = p.user_id
                               WHERE u.id = :user_id");
        $stmt->execute(['user_id' => $teacherId]);
        $teacherDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        $firstName = $teacherDetails['first_name'] ?? '';
        $lastName = $teacherDetails['last_name'] ?? '';
        $profilePhoto = $teacherDetails['photo_path'] ?? 'default_profile.jpg';

        // Store the teacher's name in the session
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
    } catch (PDOException $e) {
        error_log("Error fetching teacher details: " . $e->getMessage());
        $firstName = '';
        $lastName = '';
        $profilePhoto = 'default_profile.jpg';
    }

    // Fetch submitted homework for the teacher's assigned homework
    try {
        $stmt = $pdo->prepare("
            SELECT sh.id, sh.homework_id, sh.student_id, sh.submission_content, sh.submission_attachment, sh.submission_date, sh.status,
                   h.title, h.subject, h.description, h.due_date,
                   CONCAT(s.first_name, ' ', s.last_name) AS student_name
            FROM Submitted_Homework sh
            JOIN Homework h ON sh.homework_id = h.id
            JOIN Users s ON sh.student_id = s.id
            WHERE h.teacher_id = :teacher_id
            ORDER BY sh.submission_date DESC
        ");
        $stmt->execute(['teacher_id' => $teacherId]);
        $submittedHomeworks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        error_log("Submitted Homeworks for teacher_id {$teacherId}: " . print_r($submittedHomeworks, true));
    } catch (PDOException $e) {
        error_log("Error fetching submitted homework: " . $e->getMessage());
        $_SESSION['error_message'] = "Error fetching submitted homework: " . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "Invalid teacher ID.";
    header("Location: teacher_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Submitted Homework</title>
    <link rel="stylesheet" href="../CSS/teacher_dashboard.css">
    <style>
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    th,
    td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    th {
        background-color: #007bff;
        color: white;
    }

    tr:nth-child(even) {
        background-color: #f2f2f2;
    }

    tr:hover {
        background-color: #ddd;
    }

    .no-submissions {
        padding: 20px;
        color: #666;
    }

    .review-btn {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 3px;
        cursor: pointer;
    }

    .review-btn:hover {
        background-color: #218838;
    }

    .alert {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
        display: none;
    }

    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    body.dark-mode {
        background-color: #333;
        color: #fff;
    }

    body.dark-mode table {
        background-color: #444;
        color: #fff;
    }

    body.dark-mode th {
        background-color: #0056b3;
    }

    body.dark-mode tr:nth-child(even) {
        background-color: #555;
    }

    body.dark-mode tr:hover {
        background-color: #666;
    }

    body.dark-mode .no-submissions {
        color: #ccc;
    }

    body.dark-mode .review-btn {
        background-color: #28a745;
        color: white;
    }

    body.dark-mode .review-btn:hover {
        background-color: #218838;
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
            <li><a href="teacher_dashboard.php#homework">Manage Homework</a></li>
            <li><a href="teacher_dashboard.php#assessments">Manage Assessments</a></li>
            <li><a href="grades.php">Grades & Feedback</a></li>
            <li><a href="view_calendar.php">Calendar</a></li>
            <li><a href="announcements.php">Announcements</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <section class="section" id="submitted-homework">
            <h2>Submitted Homework</h2>
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
            <?php endif; ?>

            <?php if (empty($submittedHomeworks)): ?>
            <p class="no-submissions">No homework submissions available.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Subject</th>
                        <th>Student</th>
                        <th>Submission Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submittedHomeworks as $sh): ?>
                    <tr>
                        <td><?= htmlspecialchars($sh['title']) ?></td>
                        <td><?= htmlspecialchars($sh['subject']) ?></td>
                        <td><?= htmlspecialchars($sh['student_name']) ?></td>
                        <td><?= htmlspecialchars($sh['submission_date']) ?></td>
                        <td><?= htmlspecialchars($sh['status']) ?></td>
                        <td>
                            <?php if ($sh['status'] === 'pending'): ?>
                            <button class="review-btn"
                                onclick="location.href='../../Global_PHP/review_homework.php?submission_id=<?= $sh['id'] ?>'">
                                Review
                            </button>
                            <?php else: ?>
                            <button class="review-btn"
                                onclick="location.href='../../Global_PHP/review_homework.php?submission_id=<?= $sh['id'] ?>'">
                                View
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>

        <!-- Go Back Button -->
        <div style="margin-top: 20px;">
            <a href="teacher_dashboard.php" style="text-decoration: none;">
                <button
                    style="background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                    Go Back to Dashboard
                </button>
            </a>
        </div>
    </main>

    <script>
    // Dark mode toggle
    document.getElementById("dark-mode-toggle")?.addEventListener("click", () => {
        document.body.classList.toggle("dark-mode");
        document.cookie = `dark_mode=${document.body.classList.contains("dark-mode")}; path=/`;
    });
    </script>
</body>

</html>