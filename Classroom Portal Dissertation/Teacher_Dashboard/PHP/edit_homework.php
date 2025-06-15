<?php
session_start();
require_once '../../Global_PHP/db.php';
require_once '../../Global_PHP/auth.php';
require_once '../../Global_PHP/getting_informations.php';

// Check if the user is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

$teacherId = $_SESSION['user_id'] ?? null;
$homeworkId = $_GET['id'] ?? null;
$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';
$profilePhoto = 'default_profile.jpg';

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

// Fetch homework details
$homework = null;
if ($homeworkId) {
    try {
        $stmt = $pdo->prepare("
            SELECT h.id, h.title, h.subject, h.description, h.due_date, h.attachment_path, h.total_questions
            FROM Homework h
            WHERE h.id = :id AND h.teacher_id = :teacher_id
        ");
        $stmt->execute(['id' => $homeworkId, 'teacher_id' => $teacherId]);
        $homework = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$homework) {
            $_SESSION['error_message'] = "Homework not found or you do not have permission to edit it.";
            header("Location: view_assigned_homework.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error fetching homework: " . $e->getMessage());
        $_SESSION['error_message'] = "Error fetching homework: " . $e->getMessage();
        header("Location: view_assigned_homework.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = "Invalid homework ID.";
    header("Location: view_assigned_homework.php");
    exit();
}

// Fetch all students and currently assigned students
$students = [];
$assignedStudents = [];
try {
    $stmt = $pdo->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS full_name FROM Users WHERE role = 'student'");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT student_id FROM Homework_Students WHERE homework_id = :homework_id");
    $stmt->execute(['homework_id' => $homeworkId]);
    $assignedStudents = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'student_id');
} catch (PDOException $e) {
    error_log("Error fetching students: " . $e->getMessage());
    $_SESSION['error_message'] = "Error fetching students: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $description = $_POST['description'] ?? '';
    $dueDate = $_POST['due_date'] ?? '';
    $totalQuestions = $_POST['total_questions'] ?? 0;
    $type = $_POST['type'] ?? 'text';
    $assignedStudentsNew = $_POST['students'] ?? [];
    $removeAttachment = isset($_POST['remove_attachment']) && $_POST['remove_attachment'] == '1';

    // Validate inputs
    if (empty($title) || empty($subject) || empty($dueDate) || empty($assignedStudentsNew)) {
        $_SESSION['error_message'] = "Please fill in all required fields and assign at least one student.";
        header("Location: edit_homework.php?id=$homeworkId");
        exit();
    }

    // Handle file upload
    $attachment = $homework['attachment_path'];
    if ($removeAttachment && $attachment) {
        if (file_exists($attachment)) {
            unlink($attachment);
        }
        $attachment = null;
    }
    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = '../../Documents/';
        $fileName = time() . '_' . basename($_FILES['attachment']['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
            if ($attachment && file_exists($attachment)) {
                unlink($attachment);
            }
            $attachment = $targetPath;
        } else {
            $_SESSION['error_message'] = "Failed to upload attachment.";
            header("Location: edit_homework.php?id=$homeworkId");
            exit();
        }
    }

    // Update homework in the database
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            UPDATE Homework
            SET title = :title, subject = :subject, description = :description, due_date = :due_date,
                attachment_path = :attachment_path, total_questions = :total_questions
            WHERE id = :id AND teacher_id = :teacher_id
        ");
        $stmt->execute([
            'title' => $title,
            'subject' => $subject,
            'description' => $description,
            'due_date' => $dueDate,
            'attachment_path' => $attachment,
            'total_questions' => $totalQuestions,
            'id' => $homeworkId,
            'teacher_id' => $teacherId
        ]);

        // Update assigned students
        $stmt = $pdo->prepare("DELETE FROM Homework_Students WHERE homework_id = :homework_id");
        $stmt->execute(['homework_id' => $homeworkId]);
        $stmt = $pdo->prepare("INSERT INTO Homework_Students (homework_id, student_id) VALUES (:homework_id, :student_id)");
        foreach ($assignedStudentsNew as $studentId) {
            $stmt->execute(['homework_id' => $homeworkId, 'student_id' => $studentId]);
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Homework updated successfully.";
        header("Location: view_assigned_homework.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating homework: " . $e->getMessage());
        $_SESSION['error_message'] = "Error updating homework: " . $e->getMessage();
        header("Location: edit_homework.php?id=$homeworkId");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Homework</title>
    <link rel="stylesheet" href="../CSS/teacher_dashboard.css">
    <style>
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .student-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
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

        body.dark-mode .form-group input,
        body.dark-mode .form-group textarea,
        body.dark-mode .form-group select,
        body.dark-mode .student-list {
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
        <section class="section">
            <h2>Edit Homework</h2>
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

            <form action="edit_homework.php?id=<?= $homeworkId ?>" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Title:</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($homework['title']) ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="subject">Subject:</label>
                    <input type="text" id="subject" name="subject" value="<?= htmlspecialchars($homework['subject']) ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description"
                        name="description"><?= htmlspecialchars($homework['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="due_date">Due Date:</label>
                    <input type="date" id="due_date" name="due_date"
                        value="<?= htmlspecialchars($homework['due_date']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="total_questions">Total Questions:</label>
                    <input type="number" id="total_questions" name="total_questions" min="0"
                        value="<?= htmlspecialchars($homework['total_questions']) ?>">
                </div>
                <div class="form-group">
                    <label for="attachment">Attachment (Optional):</label>
                    <?php if ($homework['attachment_path']): ?>
                        <p>Current Attachment: <a href="<?= htmlspecialchars($homework['attachment_path']) ?>"
                                target="_blank">Download</a></p>
                        <p><input type="checkbox" name="remove_attachment" value="1"> Remove current attachment</p>
                    <?php endif; ?>
                    <input type="file" id="attachment" name="attachment">
                </div>
                <div class="form-group">
                    <label>Assign to Students:</label>
                    <div class="student-list">
                        <?php foreach ($students as $student): ?>
                            <label>
                                <input type="checkbox" name="students[]" value="<?= $student['id'] ?>"
                                    <?= in_array($student['id'], $assignedStudents) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($student['full_name']) ?>
                            </label><br>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit">Update Homework</button>
                <a href="view_assigned_homework.php" style="text-decoration: none;">
                    <button type="button" style="background-color: #6c757d;">Cancel</button>
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