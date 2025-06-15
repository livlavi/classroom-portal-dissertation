<?php
require_once 'db.php'; // adjust path if needed

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['id'], $_POST['read'])) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        exit;
    }

    $id = intval($_POST['id']);
    $read = $_POST['read'] === "1" ? 1 : 0;

    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = ? WHERE id = ?");
        $stmt->execute([$read, $id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);