<?php
session_start();
require_once '../../Global_PHP/db.php';

$assessment_id = $_POST['id'] ?? null;

if ($assessment_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM Assessments WHERE id = :id AND teacher_id = :teacher_id");
        $stmt->execute(['id' => $assessment_id, 'teacher_id' => $_SESSION['user_id']]);
        echo "Assessment deleted successfully!";
        header("Location: manage_assessments.php");
        exit();
    } catch (PDOException $e) {
        error_log("Error deleting assessment: " . $e->getMessage());
        echo "An error occurred while deleting the assessment.";
    }
} else {
    echo "Invalid assessment ID.";
}
?>