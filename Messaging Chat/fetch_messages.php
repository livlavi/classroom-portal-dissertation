<?php
session_start();
require_once '../PHP/db.php'; // Adjust the path to your database connection file

$user_id = $_SESSION['user_id'];
$other_user_id = $_GET['other_user_id'];

try {
    $stmt = $pdo->prepare("SELECT sender_id, message, created_at 
                           FROM ChatMessages 
                           WHERE (sender_id = :user_id AND receiver_id = :other_user_id) 
                              OR (sender_id = :other_user_id AND receiver_id = :user_id) 
                           ORDER BY created_at ASC");
    $stmt->execute(['user_id' => $user_id, 'other_user_id' => $other_user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching messages: ' . $e->getMessage()]);
}
?>