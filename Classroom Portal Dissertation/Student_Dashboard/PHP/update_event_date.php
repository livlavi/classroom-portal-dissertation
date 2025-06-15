<?php
session_start();
require_once '../../Global_PHP/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}
$userId = $_SESSION['user_id'];
$id    = $_POST['id'] ?? '';
$start = $_POST['start_date'] ?? '';
$end   = $_POST['end_date']   ?? $start; // if end not provided, keep same day

if (!$id || !$start) {
    echo json_encode(['success' => false]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE StudentCalendarEvents
           SET start_date = :s, end_date = :e
         WHERE id = :i AND user_id = :u
    ");
    $stmt->execute([
        's' => $start,
        'e' => $end,
        'i' => $id,
        'u' => $userId
    ]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}