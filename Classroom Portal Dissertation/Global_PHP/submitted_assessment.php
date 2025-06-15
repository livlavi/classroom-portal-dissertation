<?php
session_start();
require_once 'db.php'; // Include the database connection
require_once 'auth.php';

error_log("Submitted Assessment Session: " . print_r($_SESSION, true));

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

// Fetch teacher details and submitted assessments (only pending)
try {
    $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, p.photo_path 
                        FROM Users u
                        LEFT JOIN ProfilePhotos p ON u.id = p.user_id
                        WHERE u.id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $teacherDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    $firstName = $teacherDetails['first_name'] ?? '';
    $lastName = $teacherDetails['last_name'] ?? '';
    $profilePhoto = $teacherDetails['photo_path'] ?? null;

    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name'] = $lastName;

    // Fetch only pending submitted assessments for this teacher, including full assessment details
    $stmt = $pdo->prepare("
        SELECT sa.id, sa.submission_content, sa.submission_date, sa.submission_attachment, sa.status, sa.grade, sa.feedback,
               a.id AS assessment_id, a.title, a.subject, a.description, a.due_date, a.attachment AS assessment_attachment,
               s.first_name AS student_first_name, s.last_name AS student_last_name
        FROM Submitted_Assessments sa
        JOIN Assessments a ON sa.assessment_id = a.id
        JOIN Users s ON sa.student_id = s.id
        WHERE a.teacher_id = :teacher_id AND sa.status = 'pending'
        ORDER BY sa.submission_date DESC
    ");
    $stmt->execute(['teacher_id' => $_SESSION['user_id']]);
    $submittedAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submissions for grading, feedback, status, and submission corrections
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submission_id'])) {
        $submissionId = $_POST['submission_id'];
        $correctedSubmission = $_POST['corrected_submission'] ?? null;
        $grade = $_POST['grade'] ?? null;
        $feedback = $_POST['feedback'] ?? null;
        $status = $_POST['status'] ?? 'reviewed';

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE Submitted_Assessments 
                SET submission_content = COALESCE(:corrected_submission, submission_content), 
                    grade = :grade, 
                    feedback = :feedback, 
                    status = :status 
                WHERE id = :submission_id AND assessment_id IN (SELECT id FROM Assessments WHERE teacher_id = :teacher_id)
            ");
            $stmt->execute([
                'submission_id' => $submissionId,
                'corrected_submission' => $correctedSubmission,
                'grade' => $grade,
                'feedback' => $feedback,
                'status' => $status,
                'teacher_id' => $_SESSION['user_id']
            ]);

            $pdo->commit();
            $_SESSION['success_message'] = "Assessment updated successfully!";
            header("Location: submitted_assessment.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error updating assessment: " . $e->getMessage());
            $_SESSION['error_message'] = "An error occurred while updating the assessment: " . $e->getMessage();
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching teacher data or submitted assessments: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submitted Assessments</title>
    <link rel="stylesheet" href="../../Teacher_Dashboard/CSS/teacher_dashboard.css">
    <!-- Use teacher dashboard CSS for consistency -->
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

    .submitted-list {
        list-style-type: none;
        padding: 0;
    }

    .submitted-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
        background-color: #f9f9f9;
        margin-bottom: 10px;
        border-radius: 5px;
    }

    .submitted-item:last-child {
        border-bottom: none;
    }

    .submitted-item a {
        color: #007bff;
        text-decoration: none;
    }

    .submitted-item a:hover {
        text-decoration: underline;
    }

    .submission-form {
        margin-top: 10px;
    }

    .submission-form input[type="text"],
    .submission-form select,
    .submission-form textarea {
        width: 100%;
        padding: 8px;
        margin-bottom: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .submission-form button {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
    }

    .submission-form button:hover {
        background-color: #218838;
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

    body.dark-mode .submitted-item {
        background-color: #555;
        border-color: #666;
    }

    body.dark-mode .submitted-item a {
        color: #007bff;
    }

    body.dark-mode .submission-form input[type="text"],
    body.dark-mode .submission-form select,
    body.dark-mode .submission-form textarea {
        background-color: #555;
        border-color: #666;
        color: #fff;
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
        <h1>Submitted Assessments</h1>
        <ul class="submitted-list">
            <?php if (!empty($submittedAssessments)): ?>
            <?php foreach ($submittedAssessments as $submission): ?>
            <li class="submitted-item">
                <strong>Assessment: <?= htmlspecialchars($submission['title']) ?></strong> (Subject:
                <?= htmlspecialchars($submission['subject']) ?>)
                <p>Submitted by:
                    <?= htmlspecialchars($submission['student_first_name'] . ' ' . $submission['student_last_name']) ?>
                </p>
                <p>Submission Date: <?= htmlspecialchars($submission['submission_date']) ?></p>
                <p>Status: <?= htmlspecialchars($submission['status']) ?></p>
                <h3>Assessment Details</h3>
                <p><strong>Description:</strong> <?= htmlspecialchars($submission['description']) ?></p>
                <p><strong>Due Date:</strong> <?= htmlspecialchars($submission['due_date']) ?></p>
                <?php if ($submission['assessment_attachment']): ?>
                <p><strong>Teacher’s Attachment:</strong> <a
                        href="../../Documents/<?= htmlspecialchars(basename($submission['assessment_attachment'])) ?>"
                        target="_blank">Download</a></p>
                <?php endif; ?>
                <h3>Student Submission</h3>
                <form class="submission-form" method="POST" action="submitted_assessment.php">
                    <input type="hidden" name="submission_id" value="<?= $submission['id'] ?>">
                    <label for="corrected_submission_<?= $submission['id'] ?>">Submission (Editable for
                        Corrections):</label>
                    <textarea name="corrected_submission" id="corrected_submission_<?= $submission['id'] ?>"
                        required><?= htmlspecialchars($submission['submission_content']) ?></textarea><br>
                    <label for="status_<?= $submission['id'] ?>">Status:</label>
                    <select name="status" id="status_<?= $submission['id'] ?>">
                        <option value="pending" <?= $submission['status'] === 'pending' ? 'selected' : '' ?>>Pending
                        </option>
                        <option value="reviewed" <?= $submission['status'] === 'reviewed' ? 'selected' : '' ?>>Reviewed
                        </option>
                        <option value="rejected" <?= $submission['status'] === 'rejected' ? 'selected' : '' ?>>Rejected
                        </option>
                    </select><br>
                    <label for="grade_<?= $submission['id'] ?>">Grade:</label>
                    <input type="text" name="grade" id="grade_<?= $submission['id'] ?>"
                        value="<?= htmlspecialchars($submission['grade'] ?? '') ?>" placeholder="e.g., A, 85"><br>
                    <label for="feedback_<?= $submission['id'] ?>">Feedback:</label>
                    <textarea name="feedback" id="feedback_<?= $submission['id'] ?>"
                        placeholder="Enter feedback here..."><?= htmlspecialchars($submission['feedback'] ?? '') ?></textarea><br>
                    <button type="submit">Update</button>
                </form>
                <?php if (!empty($submission['grade']) || !empty($submission['feedback']) || (!empty($submission['corrected_submission']) && $submission['corrected_submission'] !== $submission['submission_content'])): ?>
                <p><strong>Current Submission (Corrected):</strong>
                    <?= htmlspecialchars($submission['corrected_submission'] ?? $submission['submission_content']) ?>
                </p>
                <p><strong>Current Grade:</strong> <?= htmlspecialchars($submission['grade'] ?? 'Not graded') ?></p>
                <p><strong>Current Feedback:</strong> <?= htmlspecialchars($submission['feedback'] ?? 'No feedback') ?>
                </p>
                <?php endif; ?>
                <?php if ($submission['submission_attachment']): ?>
                <p><strong>Student’s Attachment:</strong> <a
                        href="../../Documents/<?= htmlspecialchars(basename($submission['submission_attachment'])) ?>"
                        target="_blank">View Attachment</a></p>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
            <?php else: ?>
            <li>No submitted assessments.</li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Go Back Button -->
    <div style="margin-top: 20px;">
        <a href="../Teacher_Dashboard/PHP/teacher_dashboard.php" style="text-decoration: none;">
            <button
                style="background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                Go Back to Dashboard
            </button>
        </a>
    </div>

    <script src="../../Teacher_Dashboard/JavaScript/teacher_dashboard.js"></script>
    <script>
    // Dark mode toggle
    document.getElementById("dark-mode-toggle")?.addEventListener("click", () => {
        document.body.classList.toggle("dark-mode");
        document.cookie = `dark_mode=${document.body.classList.contains("dark-mode")}; path=/`;
    });
    </script>
</body>

</html>