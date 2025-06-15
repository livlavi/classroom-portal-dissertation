<?php
session_start();
require_once 'db.php'; // Include the database connection
require_once 'auth.php'; // Include the authentication handler

requireRole(['teacher', 'student', 'parent']);

// Check if the user is logged in and determine role
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$assessmentId = $_GET['assessment_id'] ?? null;
$studentId = $_GET['student_id'] ?? null;

try {
    if ($userRole === 'teacher') {
        // Fetch all reviewed submitted assessments for this teacher
        $stmt = $pdo->prepare("
            SELECT sa.id, sa.submission_content, sa.submission_date, sa.submission_attachment, sa.status, sa.grade, sa.feedback,
                   a.title, a.subject, a.due_date, a.description,
                   s.first_name AS student_first_name, s.last_name AS student_last_name
            FROM Submitted_Assessments sa
            JOIN Assessments a ON sa.assessment_id = a.id
            JOIN Users s ON sa.student_id = s.id
            WHERE a.teacher_id = :teacher_id AND sa.status IN ('reviewed', 'rejected') " . ($assessmentId ? "AND sa.assessment_id = :assessment_id" : "") . "
            ORDER BY 
                CASE sa.status 
                    WHEN 'reviewed' THEN 1 
                    WHEN 'rejected' THEN 2 
                    ELSE 3 
                END, sa.submission_date DESC
        ");
        $params = ['teacher_id' => $userId];
        if ($assessmentId) {
            $params['assessment_id'] = $assessmentId;
        }
        $stmt->execute($params);
        $submittedAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($userRole === 'student') {
        // Fetch only reviewed submitted assessments for this student
        $stmt = $pdo->prepare("
            SELECT sa.id, sa.submission_content, sa.submission_date, sa.submission_attachment, sa.status, sa.grade, sa.feedback,
                   a.title, a.subject, a.due_date, a.description
            FROM Submitted_Assessments sa
            JOIN Assessments a ON sa.assessment_id = a.id
            WHERE sa.student_id = :user_id AND sa.status IN ('reviewed', 'rejected') " . ($assessmentId ? "AND sa.assessment_id = :assessment_id" : "") . "
            ORDER BY 
                CASE sa.status 
                    WHEN 'reviewed' THEN 1 
                    WHEN 'rejected' THEN 2 
                    ELSE 3 
                END, sa.submission_date DESC
        ");
        $params = ['user_id' => $userId];
        if ($assessmentId) {
            $params['assessment_id'] = $assessmentId;
        }
        $stmt->execute($params);
        $submittedAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($userRole === 'parent') {
        // Fetch the student's ID if provided via query parameter, otherwise from Parents table
        if ($studentId) {
            $childId = $studentId;
        } else {
            $stmt = $pdo->prepare("SELECT child_full_name FROM Parents WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            $childFullName = $parent['child_full_name'] ?? null;

            if ($childFullName) {
                $stmt = $pdo->prepare("SELECT id FROM Users WHERE CONCAT(first_name, ' ', last_name) = :child_full_name AND role = 'student'");
                $stmt->execute(['child_full_name' => trim($childFullName)]);
                $child = $stmt->fetch(PDO::FETCH_ASSOC);
                $childId = $child['id'] ?? null;
            } else {
                $childId = null;
            }
        }

        // Fetch the child's name from Users for display
        $childName = 'Unknown';
        if ($childId) {
            $stmt = $pdo->prepare("SELECT first_name, last_name FROM Users WHERE id = :child_id AND role = 'student'");
            $stmt->execute(['child_id' => $childId]);
            $childUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($childUser) {
                $childName = $childUser['first_name'] . ' ' . $childUser['last_name'];
            }
        }

        if ($childId) {
            // Fetch only reviewed submitted assessments for the child
            $stmt = $pdo->prepare("
                SELECT sa.id, sa.submission_content, sa.submission_date, sa.submission_attachment, sa.status, sa.grade, sa.feedback,
                       a.title, a.subject, a.due_date, a.description
                FROM Submitted_Assessments sa
                JOIN Assessments a ON sa.assessment_id = a.id
                WHERE sa.student_id = :child_id AND sa.status IN ('reviewed', 'rejected') " . ($assessmentId ? "AND sa.assessment_id = :assessment_id" : "") . "
                ORDER BY 
                    CASE sa.status 
                        WHEN 'reviewed' THEN 1 
                        WHEN 'rejected' THEN 2 
                        ELSE 3 
                    END, sa.submission_date DESC
            ");
            $params = ['child_id' => $childId];
            if ($assessmentId) {
                $params['assessment_id'] = $assessmentId;
            }
            $stmt->execute($params);
            $submittedAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $submittedAssessments = []; // No child found, no assessments
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching reviewed assessments: " . $e->getMessage());
    $submittedAssessments = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reviewed Assessments</title>
    <link rel="stylesheet" href="../CSS/view_assessments.css"> <!-- Link to CSS file for consistency -->
    <style>
        .section {
            margin-bottom: 20px;
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .section h1 {
            color: #007bff;
            margin-bottom: 20px;
        }

        .submitted-assessment-list {
            list-style-type: none;
            padding: 0;
            margin-top: 20px;
        }

        .submitted-assessment-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            background-color: #f9f9f9;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .submitted-assessment-item:last-child {
            border-bottom: none;
        }

        .submitted-assessment-item a {
            color: #007bff;
            text-decoration: none;
        }

        .submitted-assessment-item a:hover {
            text-decoration: underline;
        }

        .no-submissions {
            padding: 20px;
            color: #666;
        }

        .status-pending {
            color: #007bff;
        }

        .status-reviewed {
            color: #28a745;
        }

        .status-rejected {
            color: #dc3545;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            display: none;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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

        body.dark-mode .section {
            background-color: #444;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        body.dark-mode .section h1 {
            color: #007bff;
        }

        body.dark-mode .submitted-assessment-item {
            background-color: #555;
            border-color: #666;
        }

        body.dark-mode .no-submissions {
            color: #ccc;
        }

        body.dark-mode .status-pending {
            color: #007bff;
        }

        body.dark-mode .status-reviewed {
            color: #28a745;
        }

        body.dark-mode .status-rejected {
            color: #dc3545;
        }
    </style>
</head>

<body class="<?php echo isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark-mode' : ''; ?>">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
            <?php unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
            <?php unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <div class="section">
        <h1>View Reviewed Assessments</h1>
        <ul class="submitted-assessment-list">
            <?php if (!empty($submittedAssessments)): ?>
                <?php foreach ($submittedAssessments as $submission): ?>
                    <li class="submitted-assessment-item">
                        <strong>Assessment: <?= htmlspecialchars($submission['title']) ?></strong> (Subject:
                        <?= htmlspecialchars($submission['subject']) ?>)
                        <?php if ($userRole === 'teacher' || $userRole === 'parent'): ?>
                            <p>Submitted by:
                                <?= htmlspecialchars($userRole === 'parent' ? $childName : $submission['student_first_name'] . ' ' . $submission['student_last_name']) ?>
                            </p>
                        <?php endif; ?>
                        <p>Submission Date: <?= htmlspecialchars($submission['submission_date']) ?></p>
                        <p>Due Date: <?= htmlspecialchars($submission['due_date']) ?></p>
                        <p>Status: <span
                                class="status-<?= htmlspecialchars(strtolower($submission['status'])) ?>"><?= htmlspecialchars($submission['status']) ?></span>
                        </p>
                        <h3>Assessment Details</h3>
                        <p><strong>Description:</strong> <?= htmlspecialchars($submission['description'] ?? 'No description') ?>
                        </p>
                        <h3>Student Submission</h3>
                        <p><strong>Answer:</strong>
                            <?= htmlspecialchars($submission['submission_content'] ?? 'No submission') ?></p>
                        <?php if ($submission['submission_attachment']): ?>
                            <p><strong>Attachment:</strong> <a
                                    href="../../Documents/<?= htmlspecialchars(basename($submission['submission_attachment'])) ?>"
                                    target="_blank">View Attachment</a></p>
                        <?php endif; ?>
                        <?php if (!empty($submission['grade']) || !empty($submission['feedback'])): ?>
                            <p><strong>Grade:</strong> <?= htmlspecialchars($submission['grade'] ?? 'Not graded') ?></p>
                            <p><strong>Feedback:</strong> <?= htmlspecialchars($submission['feedback'] ?? 'No feedback') ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="no-submissions">No reviewed assessments available.</li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Go Back Button -->
    <div style="margin-top: 20px;">
        <a href="<?= ($userRole === 'parent') ? '../Parent_Dashboard/PHP/parent_dashboard.php' : (($userRole === 'teacher') ? '../Teacher_Dashboard/PHP/teacher_dashboard.php' : '../Student_Dashboard/PHP/student_dashboard.php') ?>"
            style="text-decoration: none;">
            <button
                style="background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                Go Back to Dashboard
            </button>
        </a>
    </div>

    <script>
        // Dark mode toggle
        document.getElementById("dark-mode-toggle")?.addEventListener("click", () => {
            document.body.classList.toggle("dark-mode");
            document.cookie = `dark_mode=${document.body.classList.contains("dark-mode")}; path=/`;
        });
    </script>
</body>

</html>