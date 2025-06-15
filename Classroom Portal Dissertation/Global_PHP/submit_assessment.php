<?php

session_start();
require_once 'db.php'; // Include the database connection
require_once 'auth.php';

requireRole(['student']);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Debugging: Log incoming data
error_log("POST Data: " . print_r($_POST, true));
error_log("FILES Data: " . print_r($_FILES, true));

// Check if the assessment ID and submission content are provided
if (!isset($_POST['assessment_id']) || !isset($_POST['submission_content'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid submission data']);
    exit();
}

$assessmentId = $_POST['assessment_id'];
$studentId = $_SESSION['user_id'];
$submissionContent = $_POST['submission_content'];

// Handle file upload for submission attachment
$submissionAttachment = null;
if (isset($_FILES['submission_attachment']) && $_FILES['submission_attachment']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../../Documents/'; // Directory to store uploaded files
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); // Create directory if it doesn't exist
    }
    $file_name = basename($_FILES['submission_attachment']['name']);
    $submissionAttachment = $upload_dir . uniqid() . '_' . $file_name; // Unique file name
    move_uploaded_file($_FILES['submission_attachment']['tmp_name'], $submissionAttachment);
}

try {
    // Start a transaction
    $pdo->beginTransaction();

    // Insert the submission into Submitted_Assessments
    $stmt = $pdo->prepare("
        INSERT INTO Submitted_Assessments (assessment_id, student_id, submission_content, submission_date, submission_attachment, status)
        VALUES (:assessment_id, :student_id, :submission_content, NOW(), :submission_attachment, 'pending')
    ");
    $stmt->execute([
        'assessment_id' => $assessmentId,
        'student_id' => $studentId,
        'submission_content' => $submissionContent,
        'submission_attachment' => $submissionAttachment
    ]);

    // Notify the teacher with the student's name
    $stmt = $pdo->prepare("SELECT teacher_id, title FROM Assessments WHERE id = :assessment_id");
    $stmt->execute(['assessment_id' => $assessmentId]);
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($assessment) {
        $teacherId = $assessment['teacher_id'];
        $title = $assessment['title'];
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM Users WHERE id = :student_id");
        $stmt->execute(['student_id' => $studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        $studentName = $student ? $student['first_name'] . ' ' . $student['last_name'] : 'Unknown Student';

        $stmt = $pdo->prepare("INSERT INTO Notifications (message, created_at, target, sender_id, type) 
                               VALUES (:message, NOW(), :target, :sender_id, 'submission')");
        $stmt->execute([
            ':message' => "Student {$studentName} submitted assessment: '{$title}'",
            ':target' => "user:{$teacherId}",
            ':sender_id' => $studentId
        ]);
    }

    // Commit the transaction
    $pdo->commit();
    header("Location: view_assessments.php");
} catch (PDOException $e) {
    // Rollback the transaction
    $pdo->rollBack();
    error_log("Error submitting assessment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while submitting the assessment: ' . $e->getMessage()]);
}
exit();