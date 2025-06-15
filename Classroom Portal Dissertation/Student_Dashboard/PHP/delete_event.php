<?php
session_start();
require_once '../../Global_PHP/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
$userId = $_SESSION['user_id'];
$id = $_POST['id'] ?? '';

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing event ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM StudentCalendarEvents WHERE id = :i AND user_id = :u");
    $stmt->execute(['i' => $id, 'u' => $userId]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}