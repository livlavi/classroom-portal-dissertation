<?php
session_start();
require_once '../../Global_PHP/db.php';
require_once '../../Global_PHP/auth.php';

// Only teachers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}
$teacherId = $_SESSION['user_id'];

// Fetch all students (any user with role = 'student')
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name
      FROM Users
     WHERE role = 'student'
     ORDER BY last_name, first_name
");
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Terms
$terms = ['HT1', 'HT2', 'HT3', 'FT', 'ST1', 'ST2'];

// Are we editing an existing report?
$id = $_GET['id'] ?? null;
$report = ['student_id' => '', 'term' => '', 'overall_grade' => '', 'comments' => ''];
if ($id) {
    $stmt = $pdo->prepare("
        SELECT student_id, term, overall_grade, comments
          FROM Reports
         WHERE id = :i
           AND teacher_id = :tid
    ");
    $stmt->execute(['i' => $id, 'tid' => $teacherId]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($f) {
        $report = $f;
    }
}

// Handle POST (create or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rid      = $_POST['report_id'] ?: null;
    $sid      = $_POST['student_id'];
    $term     = $_POST['term'];
    $grade    = trim($_POST['overall_grade']);
    $comments = trim($_POST['comments']);

    if ($sid && $term) {
        if ($rid) {
            // Update existing
            $u = $pdo->prepare("
                UPDATE Reports
                   SET student_id    = :sid,
                       term          = :term,
                       overall_grade = :g,
                       comments      = :c
                 WHERE id = :i
                   AND teacher_id = :tid
            ");
            $u->execute([
                'sid' => $sid,
                'term' => $term,
                'g'   => $grade,
                'c'   => $comments,
                'i'   => $rid,
                'tid' => $teacherId
            ]);
        } else {
            // Insert new
            $i = $pdo->prepare("
                INSERT INTO Reports
                    (teacher_id, student_id, term, overall_grade, comments)
                VALUES
                    (:tid, :sid, :term, :g, :c)
            ");
            $i->execute([
                'tid' => $teacherId,
                'sid' => $sid,
                'term' => $term,
                'g'   => $grade,
                'c'   => $comments
            ]);
        }
    }

    header('Location: reports.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $id ? 'Edit' : 'Add' ?> Report</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
    }

    h1 {
        color: #007bff;
    }

    form {
        max-width: 400px;
    }

    label {
        display: block;
        margin-top: 12px;
        font-weight: bold;
    }

    select,
    input[type="text"],
    textarea {
        width: 100%;
        padding: 6px;
        margin-top: 4px;
    }

    textarea {
        height: 100px;
        resize: vertical;
    }

    button {
        margin-top: 12px;
        padding: 8px 12px;
        background: #007bff;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    button:hover {
        background: #0056b3;
    }

    .cancel-btn {
        background: #6c757d;
    }
    </style>
</head>

<body>
    <h1><?= $id ? 'Edit' : 'Add' ?> Report</h1>
    <form method="POST" action="add_report.php<?= $id ? '?id=' . urlencode($id) : '' ?>">
        <input type="hidden" name="report_id" value="<?= htmlspecialchars($id) ?>">

        <label for="student_id">Student</label>
        <select id="student_id" name="student_id" required>
            <option value="">-- select --</option>
            <?php foreach ($students as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s['id'] == $report['student_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <label for="term">Term</label>
        <select id="term" name="term" required>
            <option value="">-- select --</option>
            <?php foreach ($terms as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= $t === $report['term'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($t) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <label for="overall_grade">Overall Grade</label>
        <input type="text" id="overall_grade" name="overall_grade"
            value="<?= htmlspecialchars($report['overall_grade']) ?>">

        <label for="comments">Comments</label>
        <textarea id="comments" name="comments"><?= htmlspecialchars($report['comments']) ?></textarea>

        <button type="submit">Save</button>
        <button type="button" class="cancel-btn" onclick="location.href='reports.php'">
            Cancel
        </button>
    </form>
</body>

</html>