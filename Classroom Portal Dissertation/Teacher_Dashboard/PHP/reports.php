<?php
session_start();
require_once '../../Global_PHP/db.php';
require_once '../../Global_PHP/auth.php';

// Only teachers may access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}
$teacherId = $_SESSION['user_id'];

// Terms dropdown
$terms = ['HT1', 'HT2', 'HT3', 'FT', 'ST1', 'ST2'];
$filterTerm = $_GET['term'] ?? '';

// --- FETCH ALL STUDENTS FOR “Add New Report” DROPDOWN ---
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name
    FROM Users
    WHERE role = 'student'
    ORDER BY last_name, first_name
");
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- FETCH EXISTING REPORTS ---
if ($filterTerm) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.student_id, r.term, r.overall_grade, r.comments,
               u.first_name, u.last_name
        FROM Reports r
        JOIN Users u ON r.student_id = u.id
        WHERE r.teacher_id = :tid
          AND r.term = :term
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute(['tid' => $teacherId, 'term' => $filterTerm]);
} else {
    $stmt = $pdo->prepare("
        SELECT r.id, r.student_id, r.term, r.overall_grade, r.comments,
               u.first_name, u.last_name
        FROM Reports r
        JOIN Users u ON r.student_id = u.id
        WHERE r.teacher_id = :tid
        ORDER BY r.term, u.last_name, u.first_name
    ");
    $stmt->execute(['tid' => $teacherId]);
}
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Term Reports</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
    }

    h1 {
        color: #007bff;
    }

    .button {
        padding: 8px 12px;
        background: #007bff;
        color: #fff;
        text-decoration: none;
        border-radius: 4px;
    }

    .button:hover {
        background: #0056b3;
    }

    .filter {
        margin: 15px 0;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 8px;
        border: 1px solid #ddd;
    }

    th {
        background: #007bff;
        color: #fff;
    }

    tr:nth-child(even) {
        background: #f9f9f9;
    }
    </style>
</head>

<body>
    <h1>Term Reports</h1>

    <!-- Add & Back Buttons -->
    <a href="add_report.php" class="button">Add New Report</a>
    <a href="teacher_dashboard.php" class="button" style="background:#6c757d;">Back to Dashboard</a>

    <!-- Term Filter -->
    <div class="filter">
        <label>Filter by Term:
            <select id="termFilter" onchange="applyFilter()">
                <option value="">All</option>
                <?php foreach ($terms as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= $t === $filterTerm ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <!-- Reports Table -->
    <?php if (empty($reports)): ?>
    <p>No reports found.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Term</th>
                <th>Grade</th>
                <th>Comments</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reports as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                <td><?= htmlspecialchars($r['term']) ?></td>
                <td><?= htmlspecialchars($r['overall_grade']) ?></td>
                <td><?= nl2br(htmlspecialchars($r['comments'])) ?></td>
                <td>
                    <a href="add_report.php?id=<?= $r['id'] ?>" class="button" style="background:#28a745;">
                        Edit
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <script>
    function applyFilter() {
        const term = document.getElementById('termFilter').value;
        window.location.search = term ? '?term=' + term : '';
    }
    </script>
</body>

</html>