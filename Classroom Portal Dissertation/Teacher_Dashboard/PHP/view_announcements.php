<?php
session_start();
require_once __DIR__ . '/../../Global_PHP/db.php';
require_once __DIR__ . '/../../Global_PHP/auth.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

$stmt = $pdo->prepare("
    SELECT ar.announcement_id
    FROM AnnouncementRecipients ar
    LEFT JOIN AnnouncementReads r ON ar.announcement_id = r.announcement_id AND r.user_id = :user_id
    WHERE ar.recipient_id = :user_id AND r.id IS NULL
");
$stmt->execute(['user_id' => $userId]);
$unreadIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Mark as read (bulk insert)
if (!empty($unreadIds)) {
    $values = [];
    foreach ($unreadIds as $aid) {
        $values[] = "($aid, $userId)";
    }
    $valuesStr = implode(',', $values);

    $pdo->exec("INSERT IGNORE INTO AnnouncementReads (announcement_id, user_id) VALUES $valuesStr");
}

// Get announcements based on user role
try {
    if ($userRole === 'teacher') {
        // Teacher sees only their own announcements
        $stmt = $pdo->prepare("
            SELECT a.id, a.title, a.description, a.created_at, u.first_name, u.last_name
            FROM Announcements a
            JOIN Users u ON a.teacher_id = u.id
            WHERE a.teacher_id = :teacher_id
            ORDER BY a.created_at DESC
        ");
        $stmt->execute(['teacher_id' => $userId]);
    } elseif ($userRole === 'parent' || $userRole === 'student') {
        // Parents and students see announcements sent to them
        $stmt = $pdo->prepare("
            SELECT a.id, a.title, a.description, a.created_at, u.first_name, u.last_name
            FROM AnnouncementRecipients ar
            JOIN Announcements a ON ar.announcement_id = a.id
            JOIN Users u ON a.teacher_id = u.id
            WHERE ar.recipient_id = :user_id
            ORDER BY a.created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);
    } else {
        // If the role is unknown or invalid, set $stmt as null.
        $stmt = null;
    }

    // Fetch the results if the query was successful
    $announcements = ($stmt) ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    error_log("Error fetching announcements: " . $e->getMessage());
    $announcements = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View Announcements</title>
    <link rel="stylesheet" href="../CSS/teacher_dashboard.css">
    <style>
    body {
        font-family: Arial, sans-serif;
        padding: 20px;
    }

    .announcement {
        background: #f9f9f9;
        border-left: 4px solid #007bff;
        margin-bottom: 20px;
        padding: 15px;
        border-radius: 5px;
    }

    .announcement h3 {
        margin: 0;
        color: #333;
    }

    .announcement small {
        color: #666;
    }

    .announcement p {
        margin-top: 10px;
    }

    button {
        background-color: #007bff;
        color: #fff;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
    }

    button:hover {
        background-color: #0056b3;
    }

    .delete-btn {
        background-color: #dc3545;
        color: white;
        font-size: 0.8em;
        padding: 5px 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .delete-btn:hover {
        background-color: #c82333;
    }
    </style>
</head>

<body>
    <h2>Announcements</h2>

    <?php if (empty($announcements)): ?>
    <p>No announcements found.</p>
    <?php else: ?>
    <?php foreach ($announcements as $announcement): ?>
    <div class="announcement">
        <h3><?= htmlspecialchars($announcement['title']) ?></h3>
        <small>Posted by <?= htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']) ?>
            on <?= date('F j, Y, g:i a', strtotime($announcement['created_at'])) ?></small>
        <p><?= nl2br(htmlspecialchars($announcement['description'])) ?></p>

        <?php if ($userRole === 'teacher' || $userRole === 'admin'): ?>
        <form action="delete_announcement.php" method="POST" style="display:inline;">
            <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
            <button type="submit" class="delete-btn">Delete</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <button
        onclick="location.href='<?= ($userRole === 'teacher') ? 'teacher_dashboard.php' : (($userRole === 'student') ? '../../Student_Dashboard/PHP/student_dashboard.php' : '../../Parent_Dashboard/PHP/parent_dashboard.php') ?>'">
        Return to Dashboard
    </button>
</body>

</html>