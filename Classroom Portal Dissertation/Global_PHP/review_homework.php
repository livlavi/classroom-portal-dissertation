<?php
session_start();
require_once 'db.php';
require_once 'auth.php';
require_once 'getting_informations.php';

// Check if the user is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacherId = $_SESSION['user_id'] ?? null;
$submissionId = $_GET['submission_id'] ?? null;
$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';
$profilePhoto = 'default_profile.jpg';
$submission = null;

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
} catch (PDOException $e) {
    error_log("Error fetching teacher details: " . $e->getMessage());
}

// Fetch the submission details
if ($teacherId && $submissionId) {
    try {
        $stmt = $pdo->prepare("
            SELECT sh.id, sh.homework_id, sh.student_id, sh.submission_content, sh.submission_attachment, sh.submission_date, sh.status,
                   sh.percentage, sh.feedback, sh.corrected_submission,
                   h.title, h.subject, h.description, h.due_date, h.attachment_path,
                   CONCAT(s.first_name, ' ', s.last_name) AS student_name
            FROM Submitted_Homework sh
            JOIN Homework h ON sh.homework_id = h.id
            JOIN Users s ON sh.student_id = s.id
            WHERE sh.id = :submission_id AND h.teacher_id = :teacher_id
        ");
        $stmt->execute(['submission_id' => $submissionId, 'teacher_id' => $teacherId]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$submission) {
            $_SESSION['error_message'] = "Submission not found or you do not have permission to review it.";
            header("Location: ../Teacher_Dashboard/PHP/review_submitted_homework.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error fetching submission: " . $e->getMessage());
        $_SESSION['error_message'] = "Error fetching submission: " . $e->getMessage();
        header("Location: ../Teacher_Dashboard/PHP/review_submitted_homework.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = "Invalid teacher or submission ID.";
    header("Location: ../Teacher_Dashboard/PHP/review_submitted_homework.php");
    exit();
}

// Handle form submission for review
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $percentage = $_POST['percentage'] ?? null;
    $feedback = $_POST['feedback'] ?? '';
    $correctedSubmission = $_POST['corrected_submission'] ?? '';
    $status = $_POST['status'] ?? 'reviewed';

    if (!is_numeric($percentage) || $percentage < 0 || $percentage > 100) {
        $_SESSION['error_message'] = "Please enter a valid percentage between 0 and 100.";
        header("Location: review_homework.php?submission_id=$submissionId");
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE Submitted_Homework
            SET percentage = :percentage,
                feedback = :feedback,
                corrected_submission = :corrected_submission,
                status = :status
            WHERE id = :submission_id
        ");
        $stmt->execute([
            'percentage' => $percentage,
            'feedback' => $feedback,
            'corrected_submission' => $correctedSubmission,
            'status' => $status,
            'submission_id' => $submissionId
        ]);

        $_SESSION['success_message'] = "Homework reviewed successfully.";
        // Redirect back to review_submitted_homework.php instead of teacher_dashboard.php
        header("Location: ../Teacher_Dashboard/PHP/review_submitted_homework.php");
        exit();
    } catch (PDOException $e) {
        error_log("Error reviewing homework: " . $e->getMessage());
        $_SESSION['error_message'] = "Error reviewing homework: " . $e->getMessage();
        header("Location: review_homework.php?submission_id=$submissionId");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Homework</title>
    <link rel="stylesheet" href="../Teacher_Dashboard/CSS/teacher_dashboard.css">
    <style>
    .submission-details {
        margin-top: 20px;
        padding: 15px;
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 5px;
    }

    .submission-details textarea {
        width: 100%;
        height: 150px;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        resize: vertical;
    }

    .alert {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
    }

    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    body.dark-mode {
        background-color: #333;
        color: #fff;
    }

    body.dark-mode .submission-details {
        background-color: #444;
        border-color: #555;
    }

    body.dark-mode .submission-details textarea {
        background-color: #555;
        border-color: #666;
        color: #fff;
    }
    </style>
</head>

<body class="<?php echo isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark-mode' : ''; ?>">
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($_SESSION['error_message']) ?>
        <?php unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($_SESSION['success_message']) ?>
        <?php unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>

    <!-- Top Navigation Bar -->
    <header class="top-bar">
        <nav class="profile-actions">
            <a href="#" id="dark-mode-toggle">Dark Mode</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </nav>
        <div class="admin-profile">
            <img src="../Images/<?= htmlspecialchars($profilePhoto) ?>" alt="Profile Photo" class="profile-photo">
            <p><?= htmlspecialchars($firstName . ' ' . $lastName) ?></p>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar">
        <h2>Teacher Dashboard</h2>
        <ul>
            <li><a href="../Teacher_Dashboard/PHP/view_attendance.php">Attendance Reports</a></li>
            <li><a href="../Teacher_Dashboard/PHP/teacher_dashboard.php#homework">Manage Homework</a></li>
            <li><a href="../Teacher_Dashboard/PHP/teacher_dashboard.php#assessments">Manage Assessments</a></li>
            <li><a href="../Teacher_Dashboard/PHP/grades.php">Grades & Feedback</a></li>
            <li><a href="../Teacher_Dashboard/PHP/view_calendar.php">Calendar</a></li>
            <li><a href="../Teacher_Dashboard/PHP/announcements.php">Announcements</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <section class="section">
            <h2>Review Homework: <?= htmlspecialchars($submission['title']) ?></h2>
            <p><strong>Subject:</strong> <?= htmlspecialchars($submission['subject']) ?></p>
            <p><strong>Description:</strong> <?= htmlspecialchars($submission['description']) ?></p>
            <p><strong>Due Date:</strong> <?= htmlspecialchars($submission['due_date']) ?></p>
            <?php if ($submission['attachment_path']): ?>
            <p><strong>Attachment:</strong> <a href="<?= htmlspecialchars($submission['attachment_path']) ?>"
                    target="_blank">Download</a></p>
            <?php endif; ?>
            <p><strong>Student:</strong> <?= htmlspecialchars($submission['student_name']) ?></p>
            <p><strong>Submission Date:</strong> <?= htmlspecialchars($submission['submission_date']) ?></p>

            <div class="submission-details">
                <h3>Student's Submission</h3>
                <p><strong>Solution:</strong></p>
                <p style="white-space: pre-wrap;"><?= htmlspecialchars($submission['submission_content']) ?></p>
                <?php if ($submission['submission_attachment']): ?>
                <p><strong>Attachment:</strong> <a href="<?= htmlspecialchars($submission['submission_attachment']) ?>"
                        target="_blank">Download</a></p>
                <?php endif; ?>
            </div>

            <div class="submission-details">
                <h3>Review</h3>
                <form action="review_homework.php?submission_id=<?= $submissionId ?>" method="POST">
                    <label for="percentage">Percentage (0-100):</label>
                    <input type="number" id="percentage" name="percentage" min="0" max="100" step="0.1"
                        value="<?= htmlspecialchars($submission['percentage'] ?? '') ?>" required><br><br>

                    <label for="feedback">Feedback:</label>
                    <textarea id="feedback" name="feedback"
                        placeholder="Enter your feedback here..."><?php echo htmlspecialchars($submission['feedback'] ?? ''); ?></textarea><br><br>

                    <label for="corrected_submission">Corrections:</label>
                    <textarea id="corrected_submission" name="corrected_submission"
                        placeholder="Enter corrections here..."><?php echo htmlspecialchars($submission['corrected_submission'] ?? ''); ?></textarea><br><br>

                    <label for="status">Status:</label>
                    <select id="status" name="status">
                        <option value="reviewed" <?= $submission['status'] === 'reviewed' ? 'selected' : '' ?>>Reviewed
                        </option>
                        <option value="rejected" <?= $submission['status'] === 'rejected' ? 'selected' : '' ?>>Rejected
                        </option>
                    </select><br><br>

                    <button type="submit">Submit Review</button>
                    <a href="../Teacher_Dashboard/PHP/review_submitted_homework.php" style="text-decoration: none;">
                        <button type="button"
                            style="background-color: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                            Cancel
                        </button>
                    </a>
                </form>
            </div>
        </section>
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