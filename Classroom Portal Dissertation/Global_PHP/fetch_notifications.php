<?php
require_once 'db.php';

header('Content-Type: application/json'); // Ensure JSON response

try {
    $stmt = $pdo->query("SELECT id, message, created_at, is_read FROM Notifications ORDER BY created_at DESC");


    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching notifications: ' . $e->getMessage()
    ]);
}