<?php
// C:\Users\livla\OneDrive\Desktop\Dissertation\Classroom Portal\src\CalendarManager.php

namespace App;

// In a real application, you'd pass a database connection here.
class CalendarManager
{
    private $dbConnection;

    public function __construct($dbConnection = null)
    {
        $this->dbConnection = $dbConnection;
    }

    /**
     * Adds a new event to the calendar.
     * @param int $userId The ID of the user creating the event.
     * @param string $title The title of the event.
     * @param string $description The description of the event.
     * @param string $startTime The start time in 'YYYY-MM-DD HH:MM:SS' format.
     * @param string $endTime The end time in 'YYYY-MM-DD HH:MM:SS' format.
     * @param string $eventType The type of event (e.g., 'assignment', 'appointment', 'holiday').
     * @return int|false The ID of the new event, or false on failure.
     */
    public function addEvent(int $userId, string $title, string $description, string $startTime, string $endTime, string $eventType)
    {
        // --- REPLACE WITH YOUR ACTUAL DATABASE INSERT LOGIC ---
        if ($userId <= 0 || empty(trim($title)) || empty(trim($startTime)) || empty(trim($endTime)) || empty(trim($eventType))) {
            return false;
        }
        // Basic date format validation
        if (
            !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $startTime) ||
            !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $endTime)
        ) {
            return false;
        }
        // Basic time logic: start time must be before end time
        if (strtotime($startTime) >= strtotime($endTime)) {
            return false;
        }

        // Simulate success
        return rand(1000, 9999); // Dummy event ID
        // --- END OF DUMMY LOGIC ---
    }

    /**
     * Retrieves events for a specific user within a date range.
     * @param int $userId The ID of the user.
     * @param string $startDate The start date of the range ('YYYY-MM-DD').
     * @param string $endDate The end date of the range ('YYYY-MM-DD').
     * @return array An array of event data.
     */
    public function getEventsForUser(int $userId, string $startDate, string $endDate): array
    {
        // --- REPLACE WITH YOUR ACTUAL DATABASE SELECT LOGIC ---
        // Dummy data for testing
        $allDummyEvents = [
            1 => [ // User ID 1 events
                ['id' => 2001, 'user_id' => 1, 'title' => 'Math Class', 'start_time' => '2025-06-05 09:00:00', 'end_time' => '2025-06-05 10:00:00', 'type' => 'class'],
                ['id' => 2002, 'user_id' => 1, 'title' => 'Study Session', 'start_time' => '2025-06-06 14:00:00', 'end_time' => '2025-06-06 16:00:00', 'type' => 'appointment'],
                ['id' => 2003, 'user_id' => 1, 'title' => 'Project Due', 'start_time' => '2025-06-10 23:59:59', 'end_time' => '2025-06-10 23:59:59', 'type' => 'assignment'],
            ],
            2 => [ // User ID 2 events
                ['id' => 2004, 'user_id' => 2, 'title' => 'Meeting', 'start_time' => '2025-06-07 10:00:00', 'end_time' => '2025-06-07 11:00:00', 'type' => 'appointment'],
            ]
        ];

        $events = $allDummyEvents[$userId] ?? [];
        $filteredEvents = [];

        // Basic date range filtering for dummy data
        foreach ($events as $event) {
            if ($event['start_time'] >= $startDate . ' 00:00:00' && $event['start_time'] <= $endDate . ' 23:59:59') {
                $filteredEvents[] = $event;
            }
        }
        return $filteredEvents;
        // --- END OF DUMMY LOGIC ---
    }

    // You might add methods here for:
    // - Updating events
    // - Deleting events
    // - Getting events by type
    // - Handling recurring events
}
