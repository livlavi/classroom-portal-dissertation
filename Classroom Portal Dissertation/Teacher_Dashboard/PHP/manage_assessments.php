<?php
session_start();
require_once '../../Global_PHP/db.php';

// Fetch all assessments created by the logged-in teacher
try {
    $stmt = $pdo->prepare("SELECT * FROM Assessments WHERE teacher_id = :teacher_id ORDER BY created_at DESC");
    $stmt->execute(['teacher_id' => $_SESSION['user_id']]);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching assessments: " . $e->getMessage());
    $assessments = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Assessments</title>
    <link rel="stylesheet" href="../CSS/manage_assessments.css">
</head>

<body>
    <h2>Manage Assessments</h2>
    <?php if (!empty($assessments)): ?>
    <table border="1" cellpadding="10">
        <thead>
            <tr>
                <th>Title</th>
                <th>Subject</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($assessments as $assessment): ?>
            <tr>
                <td><?= htmlspecialchars($assessment['title']) ?></td>
                <td><?= htmlspecialchars($assessment['subject']) ?></td>
                <td><?= htmlspecialchars($assessment['due_date']) ?></td>
                <td><?= htmlspecialchars($assessment['created_at']) ?></td>
                <td>
                    <form action="edit_assessment.php" method="GET" style="display: inline;">
                        <input type="hidden" name="id" value="<?= $assessment['id'] ?>">
                        <button type="submit">Edit</button>
                    </form>
                    <form action="delete_assessment.php" method="POST" style="display: inline;"
                        onsubmit="return confirm('Are you sure you want to delete this assessment?');">
                        <input type="hidden" name="id" value="<?= $assessment['id'] ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>No assessments available.</p>
    <?php endif; ?>

    <br>
    <button onclick="location.href='teacher_dashboard.php'">Return to Dashboard</button>
</body>

</html>