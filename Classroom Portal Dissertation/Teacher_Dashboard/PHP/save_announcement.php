<?php
session_start();
require_once __DIR__ . '/../../Global_PHP/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$recipients = $_POST['recipients'] ?? [];

if (empty($title) || empty($description) || empty($recipients)) {
    echo "Error: Title, description, and recipients are required.";
    exit();
}

try {
    // Save announcement
    $stmt = $pdo->prepare("INSERT INTO Announcements (teacher_id, title, description) VALUES (:teacher_id, :title, :description)");
    $stmt->execute([
        'teacher_id' => $_SESSION['user_id'],
        'title' => $title,
        'description' => $description
    ]);

    $announcement_id = $pdo->lastInsertId();

    foreach ($recipients as $rawId) {
        // Example values: "student_5", "parent_12"
        if (preg_match('/^(student|parent)_(\d+)$/', $rawId, $matches)) {
            $role = $matches[1];
            $recordId = $matches[2];

            // Lookup user_id in Students or Parents table
            if ($role === 'student') {
                $stmt = $pdo->prepare("SELECT user_id FROM Students WHERE id = :id");
            } else {
                $stmt = $pdo->prepare("SELECT user_id FROM Parents WHERE id = :id");
            }

            $stmt->execute(['id' => $recordId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && isset($user['user_id'])) {
                $recipient_user_id = $user['user_id'];

                // Insert into AnnouncementRecipients using user_id
                $insertStmt = $pdo->prepare("INSERT INTO AnnouncementRecipients (announcement_id, recipient_id) VALUES (:announcement_id, :recipient_id)");
                $insertStmt->execute([
                    'announcement_id' => $announcement_id,
                    'recipient_id' => $recipient_user_id
                ]);
            }
        }
    }

    header("Location: announcements.php");
    exit();
} catch (PDOException $e) {
    error_log("Error posting announcement: " . $e->getMessage());
    echo "An error occurred while posting the announcement.";
}