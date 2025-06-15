<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

// Determine user role (assuming it's set in session or your auth logic)
$role = $_SESSION['role'] ?? null;

if (!$role || !in_array($role, ['parent', 'student', 'teacher'])) {
    $_SESSION['error_message'] = "Unauthorized access.";
    header("Location: ../index.php");
    exit;
}

// Fetch newsletters for this role
$stmt = $pdo->prepare("SELECT * FROM Newsletters WHERE status = 'sent' AND (target = :role OR target = 'all') ORDER BY created_at DESC");
$stmt->execute(['role' => $role]);
$newsletters = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>

<head>
    <title>Newsletters</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7f6;
            margin: 0;
            padding: 20px;
        }

        h2 {
            text-align: center;
            color: #2d87f0;
        }

        .newsletter {
            background: #fff;
            margin: 20px auto;
            padding: 20px;
            width: 80%;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .newsletter h3 {
            margin: 0 0 10px;
            color: #333;
        }

        .newsletter p {
            color: #555;
        }

        .newsletter small {
            color: #888;
            display: block;
            margin-bottom: 10px;
        }

        .back-btn {
            display: block;
            width: fit-content;
            margin: 30px auto 0;
            padding: 10px 20px;
            background-color: #2d87f0;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .back-btn:hover {
            background-color: #1a5fc3;
        }
    </style>
</head>

<body>
    <h2>Newsletters from Admin</h2>

    <?php if (count($newsletters) === 0): ?>
        <p style="text-align: center;">No newsletters available.</p>
    <?php else: ?>
        <?php foreach ($newsletters as $n): ?>
            <div class="newsletter">
                <h3><?= htmlspecialchars($n['subject']) ?></h3>
                <small>Sent on <?= $n['created_at'] ?></small>
                <p><?= nl2br(htmlspecialchars($n['body'])) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    // Construct proper relative path to dashboard
    $dashboardPaths = [
        'parent' => '/Classroom Portal Dissertation/Parent_Dashboard/PHP/parent_dashboard.php',
        'student' => '/Classroom Portal Dissertation/Student_Dashboard/PHP/student_dashboard.php',
        'teacher' => '/Classroom Portal Dissertation/Teacher_Dashboard/PHP/teacher_dashboard.php'
    ];

    $dashboardLink = $dashboardPaths[$role] ?? '../index.php';
    ?>
    <a class="back-btn" href="<?= $dashboardLink ?>">← Back to Dashboard</a>

</body>

</html>