<?php
session_start();
require_once '../../Global_PHP/auth.php';
require_once '../../Global_PHP/db.php';
requireRole(['admin']);

$newsletterId = $_GET['id'] ?? null;
if (!$newsletterId) {
    header("Location: sent_newsletters.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM Newsletters WHERE id = ?");
$stmt->execute([$newsletterId]);
$newsletter = $stmt->fetch();

if (!$newsletter) {
    $_SESSION['error_message'] = "Newsletter not found.";
    header("Location: sent_newsletters.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $target = $_POST['target'];

    $updateStmt = $pdo->prepare("UPDATE Newsletters SET subject = ?, body = ?, target = ? WHERE id = ?");
    $updateStmt->execute([$subject, $body, $target, $newsletterId]);

    $_SESSION['success_message'] = "Newsletter updated successfully.";
    header("Location: sent_newsletters.php");
    exit;
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Edit Newsletter</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #eef2f5;
        margin: 0;
        padding: 0;
    }

    h2 {
        text-align: center;
        color: #333;
        margin-top: 40px;
    }

    form {
        width: 60%;
        max-width: 700px;
        margin: 30px auto;
        padding: 25px;
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    label {
        font-weight: bold;
        display: block;
        margin-top: 15px;
        color: #333;
    }

    input[type="text"],
    select,
    textarea {
        width: 100%;
        padding: 10px;
        margin-top: 5px;
        border: 1px solid #ccc;
        border-radius: 6px;
        box-sizing: border-box;
        font-size: 16px;
    }

    textarea {
        resize: vertical;
    }

    button[type="submit"] {
        background-color: #28a745;
        color: white;
        padding: 12px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 16px;
        margin-top: 20px;
    }

    button[type="submit"]:hover {
        background-color: #218838;
    }

    a {
        display: block;
        text-align: center;
        margin-top: 25px;
        text-decoration: none;
        color: #007bff;
        font-weight: bold;
    }

    a:hover {
        text-decoration: underline;
        color: #0056b3;
    }
    </style>
</head>

<body>
    <h2>Edit Newsletter</h2>
    <form method="POST">
        <label>Subject:</label><br>
        <input type="text" name="subject" value="<?= htmlspecialchars($newsletter['subject']) ?>" required><br><br>

        <label>Body:</label><br>
        <textarea name="body" rows="10" cols="50"
            required><?= htmlspecialchars($newsletter['body']) ?></textarea><br><br>

        <label>Target:</label><br>
        <select name="target" required>
            <option value="all" <?= $newsletter['target'] == 'all' ? 'selected' : '' ?>>All Users</option>
            <option value="teacher" <?= $newsletter['target'] == 'teacher' ? 'selected' : '' ?>>Teachers</option>
            <option value="student" <?= $newsletter['target'] == 'student' ? 'selected' : '' ?>>Students</option>
            <option value="parent" <?= $newsletter['target'] == 'parent' ? 'selected' : '' ?>>Parents</option>
        </select><br><br>

        <button type="submit">Save Changes</button>
    </form>
    <a href="sent_newsletters.php">← Back to Sent Newsletters</a>
</body>

</html>