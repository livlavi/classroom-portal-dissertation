<?php
session_start();
require_once '../../Global_PHP/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo '[]';
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT 
      id, 
      title, 
      DATE_FORMAT(start_date, '%Y-%m-%dT%H:%i:%s') AS start,
      DATE_FORMAT(end_date,   '%Y-%m-%dT%H:%i:%s') AS end
    FROM StudentCalendarEvents
    WHERE user_id = :u
");
$stmt->execute(['u' => $userId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// FullCalendar expects `end` field only if not null
foreach ($events as &$e) {
    if (is_null($e['end'])) {
        unset($e['end']);
    }
}
echo json_encode($events);