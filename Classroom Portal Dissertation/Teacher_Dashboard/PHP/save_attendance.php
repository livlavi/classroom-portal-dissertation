<?php
session_start();
require_once '../../Global_PHP/db.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

// Validate and process the form data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $attendance = $_POST['attendance']; // Array of student IDs and statuses
    $teacherId = $_SESSION['user_id'];

    try {
        // Check if attendance already recorded for this teacher on this date
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Attendance WHERE recorded_by = :teacher AND date = :date");
        $stmt->execute(['teacher' => $teacherId, 'date' => $date]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            echo "<p style='color: red;'>Attendance has already been recorded for this date. You cannot record it again.</p>";
            echo '<a href="attendance.php"><button>Back to Attendance</button></a>';
            exit();
        }

        // Start a transaction to ensure atomicity
        $pdo->beginTransaction();

        foreach ($attendance as $student_id => $status) {
            // Insert attendance record for each student
            $stmt = $pdo->prepare("INSERT INTO Attendance (student_id, date, status, recorded_by) VALUES (:student_id, :date, :status, :recorded_by)");
            $stmt->execute([
                'student_id' => $student_id,
                'date' => $date,
                'status' => $status,
                'recorded_by' => $teacherId
            ]);
        }

        // Commit the transaction
        $pdo->commit();

        // Display success message and a button to go back to the dashboard
        echo "<p style='color: green;'>Attendance recorded successfully!</p>";
        echo '<a href="teacher_dashboard.php"><button>Back to Dashboard</button></a>';
        exit(); // Stop further execution
    } catch (PDOException $e) {
        // Rollback the transaction in case of an error
        $pdo->rollBack();
        error_log("Error saving attendance: " . $e->getMessage());
        echo "<p style='color: red;'>An error occurred while saving attendance.</p>";
    }
}