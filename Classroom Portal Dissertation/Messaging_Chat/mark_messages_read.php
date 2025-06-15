<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../Global_PHP/db.php';

// Safely retrieve and cast inputs
$user_id = $_SESSION['user_id'] ?? null;
$other_user_id = isset($_POST['other_user_id']) ? (int)$_POST['other_user_id'] : null;

// Validate input
if (!$user_id || !$other_user_id || !is_numeric($user_id) || !is_numeric($other_user_id)) {
    error_log("Invalid input in mark_messages_read.php: user_id=$user_id, other_user_id=$other_user_id");
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user or recipient ID'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE ChatMessages 
        SET read_status = 1 
        WHERE receiver_id = :user_id 
          AND sender_id = :other_user_id 
          AND read_status = 0
    ");

    $stmt->execute([
        'user_id' => $user_id,
        'other_user_id' => $other_user_id
    ]);

    $affectedRows = $stmt->rowCount();

    error_log("Marked messages as read: $affectedRows messages updated (user_id=$user_id, other_user_id=$other_user_id)");

    echo json_encode([
        'success' => true,
        'affected_rows' => $affectedRows,
        'message' => "$affectedRows messages marked as read."
    ]);
} catch (PDOException $e) {
    error_log("Error in mark_messages_read.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error marking messages as read: ' . $e->getMessage()
    ]);
    exit;
}