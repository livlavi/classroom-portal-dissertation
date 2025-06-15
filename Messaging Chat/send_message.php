<?php
session_start();
require_once '../PHP/db.php'; // Adjust the path to your database connection file

$sender_id = $_SESSION['user_id'];
$receiver_id = $_POST['receiver_id'];
$message = trim($_POST['message']);

if (!empty($message)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO ChatMessages (sender_id, receiver_id, message) 
                               VALUES (:sender_id, :receiver_id, :message)");
        $stmt->execute([
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'message' => $message,
        ]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error sending message: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty.']);
}
?>