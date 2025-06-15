<?php
session_start();
require_once '../../Global_PHP/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
$userId = $_SESSION['user_id'];

// Gather inputs
$id    = $_POST['id'] ?? '';
$title = trim($_POST['title'] ?? '');
$start = $_POST['start_date'] ?? '';
$end   = $_POST['end_date']   ?? null;

if (!$title || !$start) {
    echo json_encode(['success' => false, 'message' => 'Missing title or start date']);
    exit();
}

try {
    if ($id) {
        // Update existing
        $sql = "UPDATE StudentCalendarEvents
                  SET title = :t, start_date = :s, end_date = :e
                WHERE id = :i AND user_id = :u";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            't' => $title,
            's' => $start,
            'e' => $end,
            'i' => $id,
            'u' => $userId
        ]);
    } else {
        // Insert new
        $sql = "INSERT INTO StudentCalendarEvents
                  (user_id, title, start_date, end_date)
                VALUES
                  (:u, :t, :s, :e)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'u' => $userId,
            't' => $title,
            's' => $start,
            'e' => $end
        ]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}