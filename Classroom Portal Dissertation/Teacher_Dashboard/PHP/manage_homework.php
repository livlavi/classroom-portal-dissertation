<?php
session_start();
require_once '../../Global_PHP/db.php';

// Fetch all homework created by the logged-in teacher
try {
    $stmt = $pdo->prepare("SELECT * FROM Homework WHERE teacher_id = :teacher_id ORDER BY created_at DESC");
    $stmt->execute(['teacher_id' => $_SESSION['user_id']]);
    $homeworks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching homework: " . $e->getMessage());
    $homeworks = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Homework</title>
    <link rel="stylesheet" href="../CSS/manage_homework.css">
</head>

<body>
    <h2>Manage Homework</h2>
    <?php if (!empty($homeworks)): ?>
    <table border="1" cellpadding="10">
        <thead>
            <tr>
                <th>Subject</th>
                <th>Title</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($homeworks as $homework): ?>
            <tr>
                <td><?= htmlspecialchars($homework['subject']) ?></td>
                <td><?= htmlspecialchars($homework['title']) ?></td>
                <td><?= htmlspecialchars($homework['due_date']) ?></td>
                <td><?= htmlspecialchars($homework['created_at']) ?></td>
                <td>
                    <form action="edit_homework.php" method="GET" style="display: inline;">
                        <input type="hidden" name="id" value="<?= $homework['id'] ?>">
                        <button type="submit">Edit</button>
                    </form>
                    <form action="delete_homework.php" method="POST" style="display: inline;"
                        onsubmit="return confirm('Are you sure you want to delete this homework?');">
                        <input type="hidden" name="id" value="<?= $homework['id'] ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>No homework available.</p>
    <?php endif; ?>

    <br>
    <button onclick="location.href='teacher_dashboard.php'">Return to Dashboard</button>
</body>

</html>