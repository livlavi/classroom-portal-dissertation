<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../Global_PHP/db.php';
require_once '../Global_PHP/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senderId = $_SESSION['user_id'];
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if (!$receiverId || empty($message)) {
        error_log("Invalid input in send_message.php: receiver_id=$receiverId, message=$message");
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Fetch sender and receiver roles from Users table for accuracy
        $stmt = $pdo->prepare("SELECT role FROM Users WHERE id = :id");
        
        $stmt->execute(['id' => $senderId]);
        $senderRole = $stmt->fetchColumn() ?: 'unknown';

        $stmt->execute(['id' => $receiverId]);
        $receiverRole = $stmt->fetchColumn() ?: 'unknown';

        $stmt = $pdo->prepare("INSERT INTO ChatMessages (sender_id, receiver_id, message, created_at, read_status, sender_role, receiver_role) 
                               VALUES (:sender_id, :receiver_id, :message, NOW(), 0, :sender_role, :receiver_role)");
        $stmt->execute([
            ':sender_id' => $senderId,
            ':receiver_id' => $receiverId,
            ':message' => $message,
            ':sender_role' => $senderRole,
            ':receiver_role' => $receiverRole
        ]);

        $pdo->commit();
        error_log("Message sent successfully: sender=$senderId ($senderRole), receiver=$receiverId ($receiverRole), message=$message");
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error sending message: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
exit;
?>