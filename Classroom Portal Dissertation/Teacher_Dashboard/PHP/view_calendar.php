<?php
session_start();
require_once '../../Global_PHP/db.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

// Handle deletion of a past booked slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking_id'])) {
    $bookingId = $_POST['delete_booking_id'];

    try {
        $stmt = $pdo->prepare("
            DELETE b FROM BookedSlots b
            JOIN AppointmentSlots s ON b.slot_id = s.id
            WHERE b.id = :booking_id AND s.teacher_id = :teacher_id
        ");
        $stmt->execute([
            'booking_id' => $bookingId,
            'teacher_id' => $_SESSION['user_id']
        ]);
    } catch (PDOException $e) {
        error_log("Error deleting booked slot: " . $e->getMessage());
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Delete past, unbooked slots
try {
    $stmt = $pdo->prepare("
        DELETE FROM AppointmentSlots
        WHERE teacher_id = :teacher_id
        AND id NOT IN (SELECT slot_id FROM BookedSlots)
        AND CONCAT(date, ' ', time) < NOW()
    ");
    $stmt->execute(['teacher_id' => $_SESSION['user_id']]);
} catch (PDOException $e) {
    error_log("Error deleting old slots: " . $e->getMessage());
}

// Fetch available slots for the logged-in teacher
try {
    $stmt = $pdo->prepare("
        SELECT id, date, time, type
        FROM AppointmentSlots
        WHERE teacher_id = :teacher_id AND id NOT IN (
            SELECT slot_id FROM BookedSlots
        )
        ORDER BY date ASC, time ASC
    ");
    $stmt->execute(['teacher_id' => $_SESSION['user_id']]);
    $availableSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching available slots: " . $e->getMessage());
    $availableSlots = [];
}

// Fetch booked slots for the logged-in teacher
try {
    $stmt = $pdo->prepare("
        SELECT b.id AS booking_id, s.date, s.time, s.type, 
               p.first_name AS parent_first_name, p.last_name AS parent_last_name,
               st.first_name AS student_first_name, st.last_name AS student_last_name
        FROM BookedSlots b
        JOIN AppointmentSlots s ON b.slot_id = s.id
        JOIN Users p ON b.parent_id = p.id
        JOIN Users st ON b.student_id = st.id
        WHERE s.teacher_id = :teacher_id
        ORDER BY s.date ASC, s.time ASC
    ");
    $stmt->execute(['teacher_id' => $_SESSION['user_id']]);
    $bookedSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching booked slots: " . $e->getMessage());
    $bookedSlots = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Calendar</title>
    <link rel="stylesheet" href="../CSS/teacher_dashboard.css">
    <style>
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

    .slots-container {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 10px;
        margin-top: 20px;
        background: #fff;
    }

    .slot-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        border-bottom: 1px solid #ddd;
    }

    .slot-item:last-child {
        border-bottom: none;
    }

    .slot-item button {
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.9em;
    }

    .slot-item button:hover {
        background-color: #b02a37;
    }

    @media (max-width: 768px) {
        .slots-container {
            padding: 10px;
        }
    }
    </style>
</head>

<body>
    <h2>Appointment Slots</h2>

    <!-- Available Slots -->
    <h3>Available Slots</h3>
    <?php if (!empty($availableSlots)): ?>
    <div class="slots-container">
        <?php foreach ($availableSlots as $slot): ?>
        <div class="slot-item">
            <span>
                <?= htmlspecialchars($slot['date']) ?> at <?= htmlspecialchars($slot['time']) ?>
                (<?= ucfirst(htmlspecialchars($slot['type'])) ?>)
            </span>
            <form action="delete_slot.php" method="POST" style="display: inline;">
                <input type="hidden" name="id" value="<?= $slot['id'] ?>">
                <button type="submit"
                    onclick="return confirm('Are you sure you want to delete this slot?');">Delete</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p>No available slots.</p>
    <?php endif; ?>

    <!-- Booked Slots -->
    <h3>Booked Slots</h3>
    <?php if (!empty($bookedSlots)): ?>
    <div class="slots-container">
        <?php foreach ($bookedSlots as $slot): ?>
        <?php
                $slotDateTime = strtotime($slot['date'] . ' ' . $slot['time']);
                $isPast = $slotDateTime < time();
                ?>
        <div class="slot-item">
            <span>
                <?= htmlspecialchars($slot['date']) ?> at <?= htmlspecialchars($slot['time']) ?>
                (<?= ucfirst(htmlspecialchars($slot['type'])) ?>)<br>
                Booked by: <?= htmlspecialchars($slot['parent_first_name'] . ' ' . $slot['parent_last_name']) ?><br>
                Child: <?= htmlspecialchars($slot['student_first_name'] . ' ' . $slot['student_last_name']) ?>
            </span>
            <?php if ($isPast): ?>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="delete_booking_id" value="<?= $slot['booking_id'] ?>">
                <button type="submit"
                    onclick="return confirm('Are you sure you want to delete this past booking?');">Delete</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p>No booked slots.</p>
    <?php endif; ?>

    <br>
    <button onclick="location.href='teacher_dashboard.php'">Return to Dashboard</button>
</body>

</html>