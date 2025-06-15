<?php
// Include the DB connection
require_once '../../Global_PHP/db.php';

echo "Reached script!";  // For debugging

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Get the POST data
$title = isset($_POST['title']) ? $_POST['title'] : null;
$start = isset($_POST['start']) ? $_POST['start'] : null;

// Log the data being received
file_put_contents('php://stderr', "Title: " . print_r($title, true) . "\n");
file_put_contents('php://stderr', "Start: " . print_r($start, true) . "\n");

if ($title && $start) {
    // Validate the date format
    $startDate = DateTime::createFromFormat('Y-m-d', $start);

    if ($startDate && $startDate->format('Y-m-d') === $start) {
        try {
            // Prepare and execute the SQL query using PDO
            $stmt = $pdo->prepare("INSERT INTO calendar_events (title, start_date) VALUES (:title, :start_date)");

            // Bind parameters
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':start_date', $start);

            // Execute the query
            $stmt->execute();

            // Return a success response
            $response = [
                'success' => true,
                'message' => 'Event saved successfully',
                'title' => $title,
                'start' => $start
            ];
        } catch (PDOException $e) {
            // Return an error if something goes wrong
            $response = [
                'success' => false,
                'message' => 'Error saving event: ' . $e->getMessage()
            ];
        }
    } else {
        // If the date format is incorrect, return an error
        $response = [
            'success' => false,
            'message' => 'Invalid date format. Please use YYYY-MM-DD.'
        ];
    }
} else {
    // If the required data is missing, return an error
    $response = [
        'success' => false,
        'message' => 'Missing event data'
    ];
}

echo json_encode($response);
exit;
