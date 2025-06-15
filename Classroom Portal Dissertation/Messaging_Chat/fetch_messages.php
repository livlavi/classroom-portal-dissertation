<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../Global_PHP/db.php';

$user_id = $_SESSION['user_id'] ?? null;
$other_user_id = $_GET['other_user_id'] ?? '';

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

try {
    if ($other_user_id === 'all') {
        $stmt = $pdo->prepare("
            SELECT sender_id, receiver_id, message, created_at, read_status, sender_role, receiver_role,
                   (SELECT first_name FROM Users WHERE id = sender_id) AS first_name,
                   (SELECT last_name FROM Users WHERE id = sender_id) AS last_name
            FROM ChatMessages
            WHERE sender_id = :user_id OR receiver_id = :user_id
            ORDER BY created_at DESC
        ");
        $stmt->execute(['user_id' => $user_id]);
    } else {
        if (empty($other_user_id)) {
            echo json_encode(['success' => false, 'message' => 'No recipient specified']);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT sender_id, sender_role, message, created_at, read_status,
                   (SELECT first_name FROM Users WHERE id = sender_id) AS first_name,
                   (SELECT last_name FROM Users WHERE id = sender_id) AS last_name
            FROM ChatMessages
            WHERE (sender_id = :user_id AND receiver_id = :other_user_id) 
               OR (sender_id = :other_user_id AND receiver_id = :user_id)
            ORDER BY created_at ASC
        ");
        $stmt->execute(['user_id' => $user_id, 'other_user_id' => $other_user_id]);
    }
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Fetched messages for user $user_id with $other_user_id: " . json_encode($messages));
    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (PDOException $e) {
    error_log("Error fetching messages: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching messages: ' . $e->getMessage()]);
}
?>