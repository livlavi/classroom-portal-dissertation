<?php
session_start();
require_once '../Global_PHP/db.php';

$currentUserId = $_SESSION['user_id'] ?? null;
if (!$currentUserId) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

try {
    // Fetch all users
    $stmt = $pdo->query("SELECT id, first_name, last_name, role FROM Users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare a statement to count unread messages from each user to current user
    $unreadStmt = $pdo->prepare("
        SELECT COUNT(*) FROM ChatMessages
        WHERE sender_id = :sender_id AND receiver_id = :receiver_id AND read_status = 0
    ");

    $groupedUsers = [
        'teacher' => [],
        'student' => [],
        'parent' => [],
        'admin' => [],
    ];

    foreach ($users as $user) {
        // Get unread count from this user to current user
        $unreadStmt->execute([
            'sender_id' => $user['id'],
            'receiver_id' => $currentUserId,
        ]);
        $unreadCount = (int)$unreadStmt->fetchColumn();

        // Add unread_count to user info
        $user['unread_count'] = $unreadCount;

        $groupedUsers[$user['role']][] = $user;
    }

    echo json_encode(['success' => true, 'users' => $groupedUsers]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching users: ' . $e->getMessage()]);
}