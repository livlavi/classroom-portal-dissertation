<?php

require_once '../../Global_PHP/db.php';

header('Content-Type: application/json');

try {
    // Query to fetch events from the database
    $stmt = $pdo->query("SELECT * FROM calendar_events");

    // Fetch all events from the database
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert the events into the format expected by FullCalendar
    $formattedEvents = [];
    foreach ($events as $event) {
        $formattedEvents[] = [
            'title' => $event['title'],
            'start' => $event['start_date'], // Ensure the format is compatible with FullCalendar
        ];
    }

    // Return the events as JSON
    echo json_encode($formattedEvents);
} catch (PDOException $e) {
    // If there's an error, return a failure response
    echo json_encode(['success' => false, 'message' => 'Error fetching events: ' . $e->getMessage()]);
}