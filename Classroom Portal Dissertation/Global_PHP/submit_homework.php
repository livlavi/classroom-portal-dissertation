<?php
session_start();
require_once 'db.php';
require_once 'auth.php';
require_once 'getting_informations.php';

// Check if the user is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$studentId = $_SESSION['user_id'] ?? null;
$homeworkId = $_GET['homework_id'] ?? null;
$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';
$profilePhoto = 'default_profile.jpg';
$homework = null;
$existingSubmission = null;

// Fetch student details
try {
    $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, p.photo_path 
                           FROM Users u
                           LEFT JOIN ProfilePhotos p ON u.id = p.user_id
                           WHERE u.id = :user_id");
    $stmt->execute(['user_id' => $studentId]);
    $studentDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    $firstName = $studentDetails['first_name'] ?? '';
    $lastName = $studentDetails['last_name'] ?? '';
    $profilePhoto = $studentDetails['photo_path'] ?? 'default_profile.jpg';
} catch (PDOException $e) {
    error_log("Error fetching student details: " . $e->getMessage());
}

// Fetch homework details
if ($studentId && $homeworkId) {
    try {
        $stmt = $pdo->prepare("
            SELECT h.id, h.title, h.subject, h.description, h.due_date, h.attachment_path, h.total_questions,
                   t.first_name AS teacher_first_name, t.last_name AS teacher_last_name
            FROM Homework h
            JOIN Homework_Students hs ON h.id = hs.homework_id
            JOIN Users t ON h.teacher_id = t.id
            WHERE hs.student_id = :student_id AND h.id = :homework_id
        ");
        $stmt->execute(['student_id' => $studentId, 'homework_id' => $homeworkId]);
        $homework = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$homework) {
            $_SESSION['error_message'] = "Homework not found or you do not have permission to access it.";
            header("Location: view_homework.php");
            exit();
        }

        // Check if the homework is past due
        $dueDate = new DateTime($homework['due_date']);
        $today = new DateTime();
        if ($today > $dueDate) {
            $_SESSION['error_message'] = "This homework is past due and can no longer be submitted.";
            header("Location: view_homework.php");
            exit();
        }

        // Fetch existing submission, if any
        $stmt = $pdo->prepare("
            SELECT sh.id, sh.submission_content, sh.submission_attachment, sh.status
            FROM Submitted_Homework sh
            WHERE sh.student_id = :student_id AND sh.homework_id = :homework_id
        ");
        $stmt->execute(['student_id' => $studentId, 'homework_id' => $homeworkId]);
        $existingSubmission = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existingSubmission && $existingSubmission['status'] !== 'pending') {
            $_SESSION['error_message'] = "This homework has already been reviewed and cannot be edited.";
            header("Location: view_homework.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error fetching homework: " . $e->getMessage());
        $_SESSION['error_message'] = "Error fetching homework: " . $e->getMessage();
        header("Location: view_homework.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = "Invalid student or homework ID.";
    header("Location: view_homework.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submissionContent = $_POST['submission_content'] ?? '';
    $submissionAttachment = $existingSubmission['submission_attachment'] ?? null;
    $removeAttachment = isset($_POST['remove_attachment']) && $_POST['remove_attachment'] == '1';

    // Validate submission content
    if (empty($submissionContent)) {
        $_SESSION['error_message'] = "Please provide your solution.";
        header("Location: submit_homework.php?homework_id=$homeworkId");
        exit();
    }

    // Handle file upload
    if ($removeAttachment && $submissionAttachment) {
        if (file_exists($submissionAttachment)) {
            unlink($submissionAttachment);
        }
        $submissionAttachment = null;
    }
    if (!empty($_FILES['submission_attachment']['name'])) {
        $uploadDir = '../Documents/';
        $fileName = time() . '_' . basename($_FILES['submission_attachment']['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['submission_attachment']['tmp_name'], $targetPath)) {
            if ($submissionAttachment && file_exists($submissionAttachment)) {
                unlink($submissionAttachment);
            }
            $submissionAttachment = $targetPath;
        } else {
            $_SESSION['error_message'] = "Failed to upload attachment.";
            header("Location: submit_homework.php?homework_id=$homeworkId");
            exit();
        }
    }

    try {
        if ($existingSubmission) {
            // Update existing submission
            $stmt = $pdo->prepare("
                UPDATE Submitted_Homework
                SET submission_content = :submission_content,
                    submission_attachment = :submission_attachment,
                    submission_date = NOW(),
                    status = 'pending'
                WHERE id = :submission_id
            ");
            $stmt->execute([
                'submission_content' => $submissionContent,
                'submission_attachment' => $submissionAttachment,
                'submission_id' => $existingSubmission['id']
            ]);
            $_SESSION['success_message'] = "Submission updated successfully.";
        } else {
            // Insert new submission
            $stmt = $pdo->prepare("
                INSERT INTO Submitted_Homework (homework_id, student_id, submission_content, submission_attachment, submission_date, status)
                VALUES (:homework_id, :student_id, :submission_content, :submission_attachment, NOW(), 'pending')
            ");
            $stmt->execute([
                'homework_id' => $homeworkId,
                'student_id' => $studentId,
                'submission_content' => $submissionContent,
                'submission_attachment' => $submissionAttachment
            ]);
            $_SESSION['success_message'] = "Homework submitted successfully.";
        }
        header("Location: view_homework.php");
        exit();
    } catch (PDOException $e) {
        error_log("Error submitting homework: " . $e->getMessage());
        $_SESSION['error_message'] = "Error submitting homework: " . $e->getMessage();
        header("Location: submit_homework.php?homework_id=$homeworkId");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Homework</title>
    <link rel="stylesheet" href="../Student_Dashboard/CSS/student_dashboard.css">
    <style>
    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
    }

    .form-group textarea {
        width: 100%;
        height: 200px;
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

    body.dark-mode .form-group textarea {
        background-color: #555;
        border-color: #666;
        color: #fff;
    }
    </style>
</head>

<body class="<?php echo isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark-mode' : ''; ?>">
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
        <h2>Student Dashboard</h2>
        <ul>
            <li><a href="../Student_Dashboard/PHP/student_dashboard.php#homework">Manage Homework</a></li>
            <li><a href="../Student_Dashboard/PHP/view_grades.php">View Grades</a></li>
            <li><a href="../Student_Dashboard/PHP/view_calendar.php">Calendar</a></li>
            <li><a href="../Student_Dashboard/PHP/view_announcements.php">Announcements</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <section class="section">
            <h2>Submit Homework: <?= htmlspecialchars($homework['title']) ?></h2>
            <p><strong>Subject:</strong> <?= htmlspecialchars($homework['subject']) ?></p>
            <p><strong>Description:</strong> <?= htmlspecialchars($homework['description'] ?? 'No description') ?></p>
            <p><strong>Due Date:</strong> <?= htmlspecialchars($homework['due_date']) ?></p>
            <?php if ($homework['attachment_path']): ?>
            <p><strong>Attachment:</strong> <a href="<?= htmlspecialchars($homework['attachment_path']) ?>"
                    target="_blank">Download</a></p>
            <?php endif; ?>
            <p><strong>Total Questions:</strong> <?= htmlspecialchars($homework['total_questions']) ?></p>
            <p><strong>Teacher:</strong>
                <?= htmlspecialchars($homework['teacher_first_name'] . ' ' . $homework['teacher_last_name']) ?></p>

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

            <form action="submit_homework.php?homework_id=<?= $homeworkId ?>" method="POST"
                enctype="multipart/form-data">
                <div class="form-group">
                    <label for="submission_content">Your Solution:</label>
                    <textarea id="submission_content" name="submission_content"
                        required><?php echo htmlspecialchars($existingSubmission['submission_content'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="submission_attachment">Attachment (Optional):</label>
                    <?php if ($existingSubmission && $existingSubmission['submission_attachment']): ?>
                    <p>Current Attachment: <a
                            href="<?= htmlspecialchars($existingSubmission['submission_attachment']) ?>"
                            target="_blank">Download</a></p>
                    <p><input type="checkbox" name="remove_attachment" value="1"> Remove current attachment</p>
                    <?php endif; ?>
                    <input type="file" id="submission_attachment" name="submission_attachment">
                </div>
                <button type="submit"><?= $existingSubmission ? 'Update Submission' : 'Submit Homework' ?></button>
                <a href="view_homework.php" style="text-decoration: none;">
                    <button type="button"
                        style="background-color: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                        Cancel
                    </button>
                </a>
            </form>
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