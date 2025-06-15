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

// Fetch all homework created by the teacher
$homeworks = [];
try {
    $stmt = $pdo->prepare("
        SELECT h.id, h.title, h.subject, h.description, h.due_date, h.attachment_path, h.total_questions
        FROM Homework h
        WHERE h.teacher_id = :teacher_id
        ORDER BY h.due_date DESC
    ");
    $stmt->execute(['teacher_id' => $teacherId]);
    $homeworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch assigned students for each homework
    foreach ($homeworks as &$hw) {
        $stmt = $pdo->prepare("
            SELECT CONCAT(u.first_name, ' ', u.last_name) AS student_name
            FROM Homework_Students hs
            JOIN Users u ON hs.student_id = u.id
            WHERE hs.homework_id = :homework_id
        ");
        $stmt->execute(['homework_id' => $hw['id']]);
        $hw['students'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    error_log("Error fetching homework: " . $e->getMessage());
    $_SESSION['error_message'] = "Error fetching homework: " . $e->getMessage();
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $deleteId = $_GET['delete_id'];
    try {
        $pdo->beginTransaction();
        // Delete from Homework_Students
        $stmt = $pdo->prepare("DELETE FROM Homework_Students WHERE homework_id = :homework_id");
        $stmt->execute(['homework_id' => $deleteId]);
        // Delete from Submitted_Homework
        $stmt = $pdo->prepare("DELETE FROM Submitted_Homework WHERE homework_id = :homework_id");
        $stmt->execute(['homework_id' => $deleteId]);
        // Delete the homework
        $stmt = $pdo->prepare("DELETE FROM Homework WHERE id = :id AND teacher_id = :teacher_id");
        $stmt->execute(['id' => $deleteId, 'teacher_id' => $teacherId]);
        $pdo->commit();
        $_SESSION['success_message'] = "Homework deleted successfully.";
        header("Location: view_assigned_homework.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error deleting homework: " . $e->getMessage());
        $_SESSION['error_message'] = "Error deleting homework: " . $e->getMessage();
        header("Location: view_assigned_homework.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Assigned Homework</title>
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

        .no-homework {
            padding: 20px;
            color: #666;
        }

        .action-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            margin-right: 5px;
        }

        .action-btn.delete {
            background-color: #dc3545;
        }

        .action-btn:hover {
            background-color: #218838;
        }

        .action-btn.delete:hover {
            background-color: #c82333;
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

        body.dark-mode .no-homework {
            color: #ccc;
        }

        body.dark-mode .action-btn {
            background-color: #28a745;
        }

        body.dark-mode .action-btn.delete {
            background-color: #dc3545;
        }

        body.dark-mode .action-btn:hover {
            background-color: #218838;
        }

        body.dark-mode .action-btn.delete:hover {
            background-color: #c82333;
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
            <h2>Assigned Homework</h2>
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

            <?php if (empty($homeworks)): ?>
                <p class="no-homework">No homework assigned.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Subject</th>
                            <th>Description</th>
                            <th>Due Date</th>
                            <th>Attachment</th>
                            <th>Total Questions</th>
                            <th>Assigned Students</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($homeworks as $hw): ?>
                            <tr>
                                <td><?= htmlspecialchars($hw['title']) ?></td>
                                <td><?= htmlspecialchars($hw['subject']) ?></td>
                                <td><?= htmlspecialchars($hw['description'] ?? 'No description') ?></td>
                                <td><?= htmlspecialchars($hw['due_date']) ?></td>
                                <td>
                                    <?php if ($hw['attachment_path']): ?>
                                        <a href="<?= htmlspecialchars($hw['attachment_path']) ?>" target="_blank">Download</a>
                                    <?php else: ?>
                                        No attachment
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($hw['total_questions']) ?></td>
                                <td><?= htmlspecialchars(implode(', ', $hw['students'])) ?></td>
                                <td>
                                    <button class="action-btn"
                                        onclick="location.href='edit_homework.php?id=<?= $hw['id'] ?>'">Edit</button>
                                    <button class="action-btn delete"
                                        onclick="if(confirm('Are you sure you want to delete this homework?')) location.href='view_assigned_homework.php?delete_id=<?= $hw['id'] ?>'">Delete</button>
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