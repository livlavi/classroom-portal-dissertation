<?php
session_start();
require_once '../../Global_PHP/db.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

// Validate and sanitize input data
$date = $_POST['date'] ?? null;
$time = $_POST['time'] ?? null;
$type = $_POST['type'] ?? null;

if (empty($date) || empty($time) || empty($type)) {
    echo "Error: All fields are required.";
    exit();
}

try {
    // Insert the new appointment slot into the database
    $stmt = $pdo->prepare("
        INSERT INTO AppointmentSlots (teacher_id, date, time, type)
        VALUES (:teacher_id, :date, :time, :type)
    ");
    $stmt->execute([
        'teacher_id' => $_SESSION['user_id'],
        'date' => $date,
        'time' => $time,
        'type' => $type
    ]);

    echo "Slot added successfully!";
} catch (PDOException $e) {
    error_log("Error adding appointment slot: " . $e->getMessage());
    echo "An error occurred while adding the slot.";
}

// Redirect back to the calendar page
header("Location: calendar.php");
exit();
?>