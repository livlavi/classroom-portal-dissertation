<?php
// book_slot.php
session_start();
require_once '../../Global_PHP/db.php';
require_once '../../Global_PHP/getting_informations.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

$slotId = $_POST['slot_id'] ?? null;
if (!is_numeric($slotId)) {
    die("Invalid slot.");
}

$parentId = $_SESSION['user_id'];

$userDetails = fetchUserDetails($pdo, $parentId, 'parent');
$childFullName = $userDetails['child_full_name'] ?? '';

$stmt = $pdo->prepare("SELECT id FROM Users WHERE CONCAT(first_name, ' ', last_name) = :child_full_name AND role = 'student'");
$stmt->execute(['child_full_name' => trim($childFullName)]);
$childUser = $stmt->fetch(PDO::FETCH_ASSOC);
$childId = $childUser['id'] ?? null;

if (!$childId) {
    die("Child not found.");
}

try {
    $stmt = $pdo->prepare("INSERT INTO BookedSlots (slot_id, parent_id, student_id) VALUES (:slot_id, :parent_id, :student_id)");
    $stmt->execute([
        'slot_id' => $slotId,
        'parent_id' => $parentId,
        'student_id' => $childId
    ]);
    header("Location: parent_view_calendar.php");
    exit();
} catch (PDOException $e) {
    error_log("Error booking slot: " . $e->getMessage());
    die("Error booking slot.");
}
?>

<?php
// parent_view_calendar.php
session_start();
require_once '../../Global_PHP/db.php';
require_once '../../Global_PHP/getting_informations.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

$userDetails = fetchUserDetails($pdo, $_SESSION['user_id'], 'parent');
$childFullName = $userDetails['child_full_name'] ?? 'No child associated';
$childId = null;

if ($childFullName !== 'No child associated') {
    $stmt = $pdo->prepare("SELECT id FROM Users WHERE CONCAT(first_name, ' ', last_name) = :full_name AND role = 'student'");
    $stmt->execute(['full_name' => trim($childFullName)]);
    $child = $stmt->fetch(PDO::FETCH_ASSOC);
    $childId = $child['id'] ?? null;
}

$availableSlots = [];
if ($childId) {
    $stmt = $pdo->prepare("SELECT s.id, s.date, s.time, s.type, u.first_name AS teacher_first, u.last_name AS teacher_last FROM AppointmentSlots s JOIN Users u ON s.teacher_id = u.id LEFT JOIN BookedSlots b ON s.id = b.slot_id WHERE s.date >= CURDATE() AND b.slot_id IS NULL ORDER BY s.date, s.time");
    $stmt->execute();
    $availableSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Calendar</title>
</head>

<body>
    <h2>Available Appointment Slots</h2>
    <?php if ($availableSlots): ?>
    <ul>
        <?php foreach ($availableSlots as $slot): ?>
        <li>
            <?= htmlspecialchars($slot['date']) ?> at <?= htmlspecialchars($slot['time']) ?> with
            <?= htmlspecialchars($slot['teacher_first'] . ' ' . $slot['teacher_last']) ?>
            (<?= htmlspecialchars($slot['type']) ?>)
            <form action="book_slot.php" method="POST" style="display:inline">
                <input type="hidden" name="slot_id" value="<?= $slot['id'] ?>">
                <button type="submit">Book</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p>No available slots.</p>
    <?php endif; ?>
</body>

</html>

<?php
// teacher_view_bookings.php
session_start();
require_once '../../Global_PHP/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

$teacherId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT s.date, s.time, s.type, p.first_name AS parent_first, p.last_name AS parent_last, c.first_name AS student_first, c.last_name AS student_last FROM BookedSlots b JOIN AppointmentSlots s ON b.slot_id = s.id JOIN Users p ON b.parent_id = p.id JOIN Users c ON b.student_id = c.id WHERE s.teacher_id = :teacher_id ORDER BY s.date, s.time");
$stmt->execute(['teacher_id' => $teacherId]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Bookings</title>
</head>

<body>
    <h2>Your Booked Slots</h2>
    <?php if ($bookings): ?>
    <table border="1">
        <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Type</th>
            <th>Parent</th>
            <th>Student</th>
        </tr>
        <?php foreach ($bookings as $booking): ?>
        <tr>
            <td><?= htmlspecialchars($booking['date']) ?></td>
            <td><?= htmlspecialchars($booking['time']) ?></td>
            <td><?= htmlspecialchars($booking['type']) ?></td>
            <td><?= htmlspecialchars($booking['parent_first'] . ' ' . $booking['parent_last']) ?></td>
            <td><?= htmlspecialchars($booking['student_first'] . ' ' . $booking['student_last']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>No bookings found.</p>
    <?php endif; ?>
</body>

</html>