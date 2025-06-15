<?php
session_start();
require_once '../../Global_PHP/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $homework_id = $_POST['homework_id'];
    $feedback = $_POST['feedback'];

    try {
        $stmt = $pdo->prepare("UPDATE SubmittedHomework SET status = 'reviewed', feedback = :feedback WHERE id = :id");
        $stmt->execute([
            'feedback' => $feedback,
            'id' => $homework_id
        ]);
        echo "Feedback submitted successfully!";
        header("Location: submitted_homework.php");
        exit();
    } catch (PDOException $e) {
        error_log("Error submitting feedback: " . $e->getMessage());
        echo "An error occurred while submitting feedback.";
    }
}

// Fetch homework details for feedback
$homework_id = $_GET['id'] ?? null;
if ($homework_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM SubmittedHomework WHERE id = :id");
        $stmt->execute(['id' => $homework_id]);
        $homework = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching homework details: " . $e->getMessage());
        $homework = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Provide Feedback</title>
    <link rel="stylesheet" href="../CSS/teacher_dashboard.css">
</head>

<body>
    <h2>Provide Feedback</h2>
    <?php if ($homework): ?>
    <form action="feedback.php" method="POST">
        <input type="hidden" name="homework_id" value="<?= $homework['id'] ?>">
        <label for="feedback">Feedback:</label>
        <textarea id="feedback" name="feedback" rows="4" cols="50" required></textarea><br><br>
        <button type="submit">Submit Feedback</button>
    </form>
    <?php else: ?>
    <p>Homework not found.</p>
    <?php endif; ?>
</body>

</html>