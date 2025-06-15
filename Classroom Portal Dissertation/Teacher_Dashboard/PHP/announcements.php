<?php
session_start();
require_once '../../Global_PHP/db.php';
require_once '../../Global_PHP/auth.php';
require_once '../../Global_PHP/getting_informations.php';

// Check if the user is logged in and authorized (teacher or admin)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

// Fetch students grouped by year
$studentsByYear = [];
$parentsByYear = [];

try {
    $stmt = $pdo->query("SELECT S.id, CONCAT(S.first_name, ' ', S.last_name) AS full_name, S.year_of_study, S.user_id FROM Students S ORDER BY S.year_of_study, S.last_name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $studentsByYear[$row['year_of_study']][] = $row;
    }

    $stmt = $pdo->query("SELECT P.id AS parent_id, CONCAT(P.first_name, ' ', P.last_name) AS full_name, P.child_full_name, S.year_of_study
                         FROM Parents P
                         JOIN Parent_Student PS ON PS.parent_id = P.id
                         JOIN Students S ON S.user_id = PS.student_id
                         ORDER BY S.year_of_study, P.last_name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $parentsByYear[$row['year_of_study']][] = $row;
    }
} catch (PDOException $e) {
    error_log("Error fetching recipients: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Post Announcement</title>
    <link rel="stylesheet" href="../CSS/teacher_dashboard.css">
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f5f5f5;
        color: #333;
        line-height: 1.6;
        padding: 20px;
    }

    form {
        max-width: 700px;
        margin: 0 auto;
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    label {
        display: block;
        margin-top: 15px;
        font-weight: bold;
    }

    textarea,
    input[type="text"],
    select {
        width: 100%;
        padding: 10px;
        margin-top: 5px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .year-group {
        margin-top: 20px;
        padding-left: 10px;
        border-left: 3px solid #ccc;
    }

    .select-all {
        margin-bottom: 10px;
    }

    button {
        padding: 10px 20px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 1em;
    }

    button:hover {
        background-color: #0056b3;
    }
    </style>
</head>

<body>
    <h2>Post Announcement</h2>
    <form action="save_announcement.php" method="POST">
        <div class="form-group">
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" placeholder="Enter announcement title" required>
        </div>

        <div class="form-group">
            <label for="description">Description:</label>
            <textarea id="description" name="description" placeholder="Enter announcement details..."
                required></textarea>
        </div>

        <div class="form-group">
            <label for="year_select">Select Year of Study:</label>
            <select id="year_select">
                <option value="">-- Choose a Year --</option>
                <?php foreach (array_keys($studentsByYear + $parentsByYear) as $year): ?>
                <option value="year_<?= $year ?>">Year <?= $year ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php foreach ($studentsByYear as $year => $students): ?>
        <div class="year-group" id="year_<?= $year ?>" style="display: none;">
            <strong>Year <?= $year ?></strong><br>
            <label><input type="checkbox" class="select-all" data-target="student_<?= $year ?>"> Select All
                Students</label><br>
            <?php foreach ($students as $student): ?>
            <label>
                <input type="checkbox" class="student_<?= $year ?>" name="recipients[]"
                    value="student_<?= $student['id'] ?>">
                <?= htmlspecialchars($student['full_name']) ?>
            </label><br>
            <?php endforeach; ?>

            <?php if (!empty($parentsByYear[$year])): ?>
            <br>
            <label><input type="checkbox" class="select-all" data-target="parent_<?= $year ?>"> Select All
                Parents</label><br>
            <?php foreach ($parentsByYear[$year] as $parent): ?>
            <label>
                <input type="checkbox" class="parent_<?= $year ?>" name="recipients[]"
                    value="parent_<?= $parent['parent_id'] ?>">
                <?= htmlspecialchars($parent['full_name']) ?> (<?= htmlspecialchars($parent['child_full_name']) ?>)
            </label><br>
            <?php endforeach; ?>
            <?php else: ?>
            <p><em>No parents found for this year.</em></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <button type="submit">Post Announcement</button>
    </form>
    <br>
    <button onclick="location.href='teacher_dashboard.php'">Return to Dashboard</button>

    <script>
    document.getElementById('year_select').addEventListener('change', function() {
        const selectedId = this.value;
        document.querySelectorAll('.year-group').forEach(group => group.style.display = 'none');
        if (selectedId) {
            const selectedGroup = document.getElementById(selectedId);
            if (selectedGroup) selectedGroup.style.display = 'block';
        }
    });

    document.querySelectorAll('.select-all').forEach(masterCheckbox => {
        masterCheckbox.addEventListener('change', function() {
            const targetClass = this.getAttribute('data-target');
            const checkboxes = document.querySelectorAll('input.' + targetClass);
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    });
    </script>
</body>

</html>