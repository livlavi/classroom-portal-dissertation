<?php
session_start();
require_once '../../Global_PHP/db.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

// Get selected year from GET or default to current year or null
$selectedYear = $_GET['year'] ?? null;

// Fetch distinct years for the dropdown
try {
    $stmt = $pdo->query("SELECT DISTINCT year_of_study FROM Students ORDER BY year_of_study");
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching years: " . $e->getMessage());
    $years = [];
}

// Fetch students by selected year
$students = [];
if ($selectedYear) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name
            FROM Students s
            JOIN Users u ON s.user_id = u.id
            WHERE s.year_of_study = :year AND u.role = 'student'
            ORDER BY u.last_name, u.first_name
        ");
        $stmt->execute(['year' => $selectedYear]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching students: " . $e->getMessage());
        $students = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Attendance</title>
    <link rel="stylesheet" href="../CSS/attendance.css">
</head>

<body>
    <h2>Record Attendance</h2>

    <br>
    <button onclick="location.href='teacher_dashboard.php'">Return to Dashboard</button>
    <br>
    <br>

    <!-- Year selection form -->
    <form method="GET" action="attendance.php">
        <label for="year">Select Year of Study:</label>
        <select name="year" id="year" onchange="this.form.submit()" required>
            <option value="">-- Select Year --</option>
            <?php foreach ($years as $year): ?>
            <option value="<?= htmlspecialchars($year) ?>" <?= ($year == $selectedYear) ? 'selected' : '' ?>>
                <?= htmlspecialchars($year) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit">Go</button></noscript>
    </form>

    <?php if ($selectedYear): ?>
    <form action="save_attendance.php" method="POST">
        <!-- Pass selected year and date -->
        <input type="hidden" name="year" value="<?= htmlspecialchars($selectedYear) ?>">

        <label for="date">Date:</label>
        <input type="date" id="date" name="date" required value="<?= date('Y-m-d') ?>">
        <br><br>

        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Present</th>
                    <th>Absent</th>
                    <th>Excused</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($students)): ?>
                <?php foreach ($students as $student): ?>
                <tr>
                    <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                    <td><input type="radio" name="attendance[<?= $student['id'] ?>]" value="present" required></td>
                    <td><input type="radio" name="attendance[<?= $student['id'] ?>]" value="absent"></td>
                    <td><input type="radio" name="attendance[<?= $student['id'] ?>]" value="excused"></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="4">No students found for this year.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <br>
        <button type="submit">Submit Attendance</button>
    </form>
    <?php else: ?>
    <p>Please select a year to record attendance.</p>
    <?php endif; ?>

</body>

</html>