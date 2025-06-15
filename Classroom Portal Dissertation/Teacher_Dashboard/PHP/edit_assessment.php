<?php
session_start();
require_once '../../Global_PHP/db.php';

$assessment_id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $due_date = $_POST['due_date'];
    $subject = $_POST['subject'];

    try {
        $stmt = $pdo->prepare("UPDATE Assessments SET title = :title, description = :description, due_date = :due_date, subject = :subject WHERE id = :id AND teacher_id = :teacher_id");
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'due_date' => $due_date,
            'subject' => $subject,
            'id' => $assessment_id,
            'teacher_id' => $_SESSION['user_id']
        ]);
        echo "Assessment updated successfully!";
        header("Location: manage_assessments.php");
        exit();
    } catch (PDOException $e) {
        error_log("Error updating assessment: " . $e->getMessage());
        echo "An error occurred while updating the assessment.";
    }
}

// Fetch assessment details for editing
if ($assessment_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM Assessments WHERE id = :id AND teacher_id = :teacher_id");
        $stmt->execute(['id' => $assessment_id, 'teacher_id' => $_SESSION['user_id']]);
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
    <title>Edit Assessment</title>
    <link rel="stylesheet" href="../CSS/edit_assessment.css">
</head>

<body>
    <h2>Edit Assessment</h2>
    <?php if ($assessment): ?>
    <form action="edit_assessment.php?id=<?= $assessment['id'] ?>" method="POST">
        <label for="title">Title:</label>
        <input type="text" id="title" name="title" value="<?= htmlspecialchars($assessment['title']) ?>"
            required><br><br>

        <label for="subject">Subject:</label>
        <select id="subject" name="subject" required>
            <option value="Maths" <?= $assessment['subject'] === 'Maths' ? 'selected' : '' ?>>Maths</option>
            <option value="English" <?= $assessment['subject'] === 'English' ? 'selected' : '' ?>>English</option>
            <option value="Geography" <?= $assessment['subject'] === 'Geography' ? 'selected' : '' ?>>Geography</option>
            <option value="Science" <?= $assessment['subject'] === 'Science' ? 'selected' : '' ?>>Science</option>
            <option value="History" <?= $assessment['subject'] === 'History' ? 'selected' : '' ?>>History</option>
        </select><br><br>

        <label for="description">Description:</label>
        <textarea id="description" name="description" rows="4"
            cols="50"><?= htmlspecialchars($assessment['description']) ?></textarea><br><br>

        <label for="due_date">Due Date:</label>
        <input type="date" id="due_date" name="due_date" value="<?= htmlspecialchars($assessment['due_date']) ?>"
            required><br><br>

        <button type="submit">Save Changes</button>
    </form>
    <?php else: ?>
    <p>Assessment not found or you do not have permission to edit it.</p>
    <?php endif; ?>
</body>

</html>