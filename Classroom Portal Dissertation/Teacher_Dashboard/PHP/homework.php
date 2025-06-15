<?php
session_start();
require_once '../../Global_PHP/db.php';
require_once '../../Global_PHP/auth.php';
require_once '../../Global_PHP/getting_informations.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

$teacherId = $_SESSION['user_id'] ?? null;
$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';
$profilePhoto = 'default_profile.jpg';

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

// Fetch and group students by year
$studentsByYear = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS full_name, s.year_of_study
        FROM Users u
        JOIN Students s ON u.id = s.user_id
        WHERE u.role = 'student'
        ORDER BY s.year_of_study, u.last_name
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($students as $student) {
        $year = $student['year_of_study'];
        $studentsByYear[$year][] = $student;
    }
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
    $totalQuestions = isset($_POST['total_questions']) && is_numeric($_POST['total_questions']) ? (int)$_POST['total_questions'] : 0;
    $assignedStudents = $_POST['students'] ?? [];
    $attachment = null;

    if (empty($title) || empty($subject) || empty($dueDate) || empty($assignedStudents)) {
        $_SESSION['error_message'] = "Please fill in all required fields and assign at least one student.";
        header("Location: homework.php");
        exit();
    }

    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = '../../Documents/';
        $fileName = time() . '_' . basename($_FILES['attachment']['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
            $attachment = $targetPath;
        } else {
            $_SESSION['error_message'] = "Failed to upload attachment.";
            header("Location: homework.php");
            exit();
        }
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO Homework (teacher_id, title, subject, description, due_date, attachment_path, total_questions)
            VALUES (:teacher_id, :title, :subject, :description, :due_date, :attachment_path, :total_questions)
        ");
        $stmt->execute([
            'teacher_id' => $teacherId,
            'title' => $title,
            'subject' => $subject,
            'description' => $description,
            'due_date' => $dueDate,
            'attachment_path' => $attachment,
            'total_questions' => $totalQuestions
        ]);
        $homeworkId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO Homework_Students (homework_id, student_id) VALUES (:homework_id, :student_id)");
        foreach ($assignedStudents as $studentId) {
            $stmt->execute(['homework_id' => $homeworkId, 'student_id' => $studentId]);
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Homework created successfully.";
        header("Location: teacher_dashboard.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error creating homework: " . $e->getMessage());
        $_SESSION['error_message'] = "Error creating homework: " . $e->getMessage();
        header("Location: homework.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Homework</title>
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

    .year-group label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1px;
    }

    .student-list {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #ddd;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
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

<body class="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark-mode' : '' ?>">
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

    <main class="main-content">
        <section class="section">
            <h2>Add Homework</h2>

            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <form action="homework.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Title:</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="subject">Subject:</label>
                    <input type="text" id="subject" name="subject" required>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="due_date">Due Date:</label>
                    <input type="date" id="due_date" name="due_date" required>
                </div>
                <div class="form-group">
                    <label for="total_questions">Total Questions:</label>
                    <input type="number" id="total_questions" name="total_questions" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="attachment">Attachment (Optional):</label>
                    <input type="file" id="attachment" name="attachment">
                </div>

                <div class="form-group">
                    <label for="year_select">Select Year of Study:</label>
                    <select id="year_select">
                        <option value="">-- Choose a Year --</option>
                        <?php foreach (array_keys($studentsByYear) as $year): ?>
                        <option value="year_<?= $year ?>">Year <?= $year ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Assign to Students:</label>
                    <?php foreach ($studentsByYear as $year => $students): ?>
                    <div class="student-list year-group" id="year_<?= $year ?>" style="display: none;">
                        <strong>Year <?= $year ?></strong><br>
                        <?php foreach ($students as $student): ?>
                        <label>
                            <input type="checkbox" name="students[]" value="<?= $student['id'] ?>">
                            <?= htmlspecialchars($student['full_name']) ?>
                        </label><br>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit">Create Homework</button>
                <a href="teacher_dashboard.php"><button type="button"
                        style="background-color: #6c757d;">Cancel</button></a>
            </form>
        </section>
    </main>

    <script>
    document.getElementById("dark-mode-toggle")?.addEventListener("click", () => {
        document.body.classList.toggle("dark-mode");
        document.cookie = `dark_mode=${document.body.classList.contains("dark-mode")}; path=/`;
    });

    document.getElementById("year_select").addEventListener("change", function() {
        const selectedYear = this.value;
        document.querySelectorAll(".year-group").forEach(group => {
            group.style.display = (group.id === selectedYear) ? "block" : "none";
        });
    });
    </script>
</body>

</html>