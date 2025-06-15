<?php
session_start();
require_once '../../Global_PHP/auth.php';
require_once '../../Global_PHP/db.php';
requireRole(['admin']);

$newsletters = $pdo->query("SELECT * FROM Newsletters ORDER BY created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html>

<head>
    <title>Sent Newsletters</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f7f6;
        margin: 0;
        padding: 0;
    }

    h2 {
        text-align: center;
        margin-top: 30px;
        color: #333;
    }

    table {
        width: 80%;
        margin: 20px auto;
        border-collapse: collapse;
        background-color: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    th,
    td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    th {
        background-color: #2d87f0;
        color: #fff;
    }

    tr:hover {
        background-color: #f5f5f5;
    }

    a {
        color: #2d87f0;
        text-decoration: none;
        font-weight: bold;
    }

    a:hover {
        text-decoration: underline;
    }

    .actions button {
        padding: 6px 12px;
        background-color: #28a745;
        border: none;
        color: white;
        border-radius: 4px;
        cursor: pointer;
        margin-right: 10px;
    }

    .actions button.delete {
        background-color: #dc3545;
    }

    .actions button:hover {
        opacity: 0.8;
    }

    .actions a {
        color: white;
        text-decoration: none;
    }

    .actions {
        display: flex;
        gap: 10px;
    }

    .back-btn {
        display: block;
        width: 200px;
        margin: 30px auto;
        padding: 10px;
        text-align: center;
        background-color: #007bff;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        font-weight: bold;
    }

    .back-btn:hover {
        background-color: #0056b3;
    }
    </style>
</head>

<body>
    <h2>Sent Newsletters</h2>
    <?php if (isset($_SESSION['success_message'])): ?>
    <p style="color: green"><?= $_SESSION['success_message'] ?></p>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
    <p style="color: red"><?= $_SESSION['error_message'] ?></p>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Subject</th>
                <th>Target</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($newsletters as $n): ?>
            <tr>
                <td><?= $n['id'] ?></td>
                <td><?= htmlspecialchars($n['subject']) ?></td>
                <td><?= $n['target'] ?></td>
                <td><?= $n['status'] ?></td>
                <td><?= $n['created_at'] ?></td>
                <td class="actions">
                    <a href="edit_newsletter.php?id=<?= $n['id'] ?>"><button>Edit</button></a>
                    <a href="delete_newsletter.php?id=<?= $n['id'] ?>"
                        onclick="return confirm('Are you sure you want to delete this newsletter?')"><button
                            class="delete">Delete</button></a>
                </td>

            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
</body>

</html>