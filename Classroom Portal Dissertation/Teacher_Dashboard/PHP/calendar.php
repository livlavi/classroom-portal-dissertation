<?php
session_start();
require_once '../../Global_PHP/db.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Calendar</title>
    <link rel="stylesheet" href="../CSS/teacher_dashboard.css">
    <style>
        /* General Styling */
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }

        h2 {
            margin-bottom: 20px;
            color: #2c3e50;
        }

        form {
            max-width: 400px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }

        input[type="date"],
        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9em;
        }

        button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #0056b3;
        }

        /* Scrollable Time Picker */
        .scrollable-time {
            max-height: 150px;
            /* Adjust height as needed */
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            form {
                padding: 15px;
            }

            input,
            select,
            button {
                font-size: 0.9em;
            }
        }
    </style>
</head>

<body>
    <h2>Set Appointment Slots</h2>
    <form action="save_slots.php" method="POST">
        <label>Date: <input type="date" name="date" required></label><br>

        <!-- Scrollable Time Picker -->
        <label>Time:</label>
        <select name="time" class="scrollable-time" required>
            <?php
            // Generate time options from 8:00 AM to 8:00 PM in 30-minute intervals
            $start = strtotime('08:00');
            $end = strtotime('20:00');
            for ($time = $start; $time <= $end; $time += 1800) { // 1800 seconds = 30 minutes
                $timeFormatted = date('H:i', $time);
                echo "<option value='$timeFormatted'>$timeFormatted</option>";
            }
            ?>
        </select><br>

        <label>Type:
            <select name="type" required>
                <option value="online">Online</option>
                <option value="in-person">In-Person</option>
            </select>
        </label><br>
        <button type="submit">Add Slot</button>
    </form>

    <br>
    <button onclick="location.href='teacher_dashboard.php'">Return to Dashboard</button>
</body>

</html>