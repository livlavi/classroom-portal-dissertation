<?php
session_start();
require_once '../../Global_PHP/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assessment_id = $_POST['assessment_id'];
    $grade = $_POST['grade'];
    $feedback = $_POST['feedback'];

    try {
        $stmt = $pdo->prepare("UPDATE SubmittedAssessments SET status = 'graded', grade = :grade, feedback = :feedback WHERE id = :id");
        $stmt->execute([
            'grade' => $grade,
            'feedback' => $feedback,
            'id' => $assessment_id
        ]);
        echo "Assessment graded successfully!";
        header("Location: submitted_assessment.php");
        exit();
    } catch (PDOException $e) {
        error_log("Error grading assessment: " . $e->getMessage());
        echo "An error occurred while grading the assessment.";
    }
}

// Fetch assessment details for grading
$assessment_id = $_GET['id'] ?? null;
if ($assessment_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM SubmittedAssessments WHERE id = :id");
        $stmt->execute(['id' => $assessment_id]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching assessment details: " . $e->getMessage());
        $assessment = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Grade Assessment</title>
    <link rel="stylesheet" href="../CSS/teacher_dashboard.css">
</head>

<body>
    <h2>Grade Assessment</h2>
    <?php if ($assessment): ?>
    <form action="grade_assessment.php" method="POST">
        <input type="hidden" name="assessment_id" value="<?= $assessment['id'] ?>">
        <label for="grade">Grade:</label>
        <input type="text" id="grade" name="grade" required><br><br>
        <label for="feedback">Feedback:</label>
        <textarea id="feedback" name="feedback" rows="4" cols="50" required></textarea><br><br>
        <button type="submit">Submit Grade</button>
    </form>
    <?php else: ?>
    <p>Assessment not found.</p>
    <?php endif; ?>
</body>

</html>