<?php
session_start();
require_once '../../Global_PHP/db.php';

$userId = $_SESSION['user_id'];

// Get last seen timestamp (you could store this in session or browser localStorage)
$lastCheck = isset($_SESSION['last_announcement_check']) ? $_SESSION['last_announcement_check'] : '2000-01-01 00:00:00';

// Get new announcements
$stmt = $pdo->prepare("
    SELECT a.id, a.title, a.description, a.created_at
    FROM AnnouncementRecipients ar
    JOIN Announcements a ON ar.announcement_id = a.id
    WHERE ar.recipient_id = :user_id AND a.created_at > :last_check
    ORDER BY a.created_at DESC
    LIMIT 1
");
$stmt->execute([
    'user_id' => $userId,
    'last_check' => $lastCheck
]);
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

// Update last check time
$_SESSION['last_announcement_check'] = date('Y-m-d H:i:s');

header('Content-Type: application/json');
echo json_encode($announcement ?: []);