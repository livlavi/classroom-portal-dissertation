<?php
session_start();
require_once '../../Global_PHP/db.php';

// Check if user is logged in and role is set
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Get optional GET params
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : null;
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;

$years = [];
$attendanceRecords = [];
$parentChildAttendancePercentage = null;
$showAttendanceAlert = false;

try {
    // Fetch distinct years for dropdown (only if teacher)
    if ($userRole === 'teacher') {
        $stmtYears = $pdo->prepare("SELECT DISTINCT year_of_study FROM Students ORDER BY year_of_study");
        $stmtYears->execute();
        $years = $stmtYears->fetchAll(PDO::FETCH_COLUMN);
    }

    if ($userRole === 'teacher') {
        // Teacher: fetch all attendance they recorded, optionally filtered by year
        if ($selectedYear) {
            $stmt = $pdo->prepare("
                SELECT a.id, a.student_id, u.first_name, u.last_name, a.date, a.status, a.created_at
                FROM Attendance a
                JOIN Users u ON a.student_id = u.id
                JOIN Students s ON s.user_id = u.id
                WHERE a.recorded_by = :teacher_id AND s.year_of_study = :year
                ORDER BY a.date DESC
            ");
            $stmt->execute(['teacher_id' => $userId, 'year' => $selectedYear]);
        } else {
            $stmt = $pdo->prepare("
                SELECT a.id, a.student_id, u.first_name, u.last_name, a.date, a.status, a.created_at
                FROM Attendance a
                JOIN Users u ON a.student_id = u.id
                WHERE a.recorded_by = :teacher_id
                ORDER BY a.date DESC
            ");
            $stmt->execute(['teacher_id' => $userId]);
        }
        $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($userRole === 'parent') {
        // Parent: fetch attendance only for their child

        // Determine child ID
        if ($studentId) {
            $childId = $studentId;
        } else {
            $stmt = $pdo->prepare("SELECT child_full_name FROM Parents WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            $childFullName = $parent['child_full_name'] ?? null;

            if ($childFullName) {
                $stmt = $pdo->prepare("SELECT id FROM Users WHERE CONCAT(first_name, ' ', last_name) = :child_full_name AND role = 'student'");
                $stmt->execute(['child_full_name' => trim($childFullName)]);
                $child = $stmt->fetch(PDO::FETCH_ASSOC);
                $childId = $child['id'] ?? null;
            } else {
                $childId = null;
            }
        }

        if (!$childId) {
            die("No associated child found or invalid student ID.");
        }

        // Fetch attendance for this child
        $stmt = $pdo->prepare("
            SELECT a.id, a.student_id, u.first_name, u.last_name, a.date, a.status, a.created_at
            FROM Attendance a
            JOIN Users u ON a.student_id = u.id
            WHERE a.student_id = :child_id
            ORDER BY a.date DESC
        ");
        $stmt->execute(['child_id' => $childId]);
        $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate attendance percentage for this child
        if (!empty($attendanceRecords)) {
            $total = count($attendanceRecords);
            $present = 0;
            foreach ($attendanceRecords as $record) {
                if ($record['status'] === 'present') {
                    $present++;
                }
            }
            $parentChildAttendancePercentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

            // If attendance below 80%, set alert flag and send email
            if ($parentChildAttendancePercentage < 80) {
                $showAttendanceAlert = true;

                // Get parent's email
                $stmtEmail = $pdo->prepare("SELECT email FROM Users WHERE id = :parent_id");
                $stmtEmail->execute(['parent_id' => $userId]);
                $parentEmail = $stmtEmail->fetchColumn();

                if ($parentEmail) {
                    $subject = "Attendance Alert for Your Child";
                    $message = "Dear Parent,\n\nYour child's attendance is currently at " . $parentChildAttendancePercentage . "%, which is below the acceptable threshold of 80%. Please take necessary action.\n\nBest regards,\nSchool Administration";
                    $headers = "From: no-reply@school.edu";

                    @mail($parentEmail, $subject, $message, $headers);
                }
            }
        }
    } else {
        // Other roles not allowed
        header("Location: ../../Global_PHP/login.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching attendance data: " . $e->getMessage());
    $attendanceRecords = [];
}

// Calculate attendance percentages (only if teacher, since parents see one student)
$studentAttendancePercentages = [];
if ($userRole === 'teacher' && !empty($attendanceRecords)) {
    $groupedRecords = [];
    foreach ($attendanceRecords as $record) {
        $studentId = $record['student_id'];
        if (!isset($groupedRecords[$studentId])) {
            $groupedRecords[$studentId] = [
                'name' => $record['first_name'] . ' ' . $record['last_name'],
                'total' => 0,
                'present' => 0,
            ];
        }
        $groupedRecords[$studentId]['total']++;
        if ($record['status'] === 'present') {
            $groupedRecords[$studentId]['present']++;
        }
    }

    foreach ($groupedRecords as $studentId => $data) {
        $percentage = ($data['total'] > 0) ? round(($data['present'] / $data['total']) * 100, 2) : 0;
        $studentAttendancePercentages[] = [
            'name' => $data['name'],
            'percentage' => $percentage,
        ];
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
    <h2>Attendance History</h2>

    <?php if ($userRole === 'teacher'): ?>
    <button onclick="location.href='teacher_dashboard.php'">Return to Dashboard</button>

    <br><br>

    <!-- Year Filter Dropdown -->
    <form method="get" action="">
        <label for="year">Filter by Year:</label>
        <select name="year" id="year" onchange="this.form.submit()">
            <option value="">-- All Years --</option>
            <?php foreach ($years as $year): ?>
            <option value="<?= htmlspecialchars($year) ?>" <?= ($selectedYear == $year) ? 'selected' : '' ?>>
                Year <?= htmlspecialchars($year) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit">Filter</button></noscript>
    </form>

    <br>

    <!-- Attendance Percentage Report -->
    <h3>Attendance Percentage Report</h3>
    <table border="1" cellpadding="10">
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Attendance Percentage (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($studentAttendancePercentages)): ?>
            <?php foreach ($studentAttendancePercentages as $student): ?>
            <tr>
                <td><?= htmlspecialchars($student['name']) ?></td>
                <td><?= htmlspecialchars($student['percentage']) ?>%</td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
                <td colspan="2">No attendance data available for percentage calculation.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php elseif ($userRole === 'parent'): ?>
    <button onclick="location.href='../../Parent_Dashboard/PHP/parent_dashboard.php'">Return to Dashboard</button>

    <!-- Show attendance percentage for parent's child -->
    <?php if ($parentChildAttendancePercentage !== null): ?>
    <h3>Attendance Percentage for Your Child</h3>
    <p>
        <?= htmlspecialchars($attendanceRecords[0]['first_name'] . ' ' . $attendanceRecords[0]['last_name']) ?>:
        <?= $parentChildAttendancePercentage ?>%
    </p>
    <?php else: ?>
    <p>No attendance data available for your child.</p>
    <?php endif; ?>

    <?php endif; ?>

    <br>

    <!-- Attendance Table -->
    <table border="1" cellpadding="10">
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Date</th>
                <th>Status</th>
                <th>Recorded At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($attendanceRecords)): ?>
            <?php foreach ($attendanceRecords as $record): ?>
            <tr>
                <td><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></td>
                <td><?= htmlspecialchars($record['date']) ?></td>
                <td><?= htmlspecialchars(ucfirst($record['status'])) ?></td>
                <td><?= htmlspecialchars($record['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
                <td colspan="4">No attendance records available.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <br>

    <?php if ($showAttendanceAlert): ?>
    <script>
    alert(
        "Warning: Your child's attendance is below 80% (<?= $parentChildAttendancePercentage ?>%). Please take necessary action.");
    </script>
    <?php endif; ?>

</body>

</html>