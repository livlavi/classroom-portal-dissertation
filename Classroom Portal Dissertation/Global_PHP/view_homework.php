<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$studentUserId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get student ID from Students table (user_id column)
$stmt = $pdo->prepare("SELECT id FROM Students WHERE user_id = :user_id");
$stmt->execute(['user_id' => $studentUserId]);
$studentRow = $stmt->fetch();

if (!$studentRow) {
    echo "Student not found.";
    exit();
}

$studentId = $studentRow['id'];

// Fetch homework with submitted homework details
$query = "
    SELECT 
        h.id AS homework_id,
        h.title,
        h.subject,
        h.description,
        h.due_date,
        h.attachment_path,
        CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
        sh.submission_date,
        sh.status,
        sh.percentage,
        sh.feedback,
        sh.submission_content,
        sh.submission_attachment
    FROM Homework h
    JOIN Users u ON h.teacher_id = u.id
    JOIN Homework_Students hs ON hs.homework_id = h.id
    LEFT JOIN Submitted_Homework sh 
        ON sh.homework_id = h.id AND sh.student_id = :student_user_id
    WHERE hs.student_id = :student_user_id
    ORDER BY h.due_date DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute(['student_user_id' => $studentUserId]);
$homeworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dashboard URLs by role
$dashboards = [
    'student' => '../Student_Dashboard/PHP/student_dashboard.php',
    'teacher' => '../Teacher_Dashboard/PHP/teacher_dashboard.php',
    'parent' => '../Parent_Dashboard/PHP/parent_dashboard.php',
    'admin' => '../Admin_Dashboard/PHP/admin_dashboard.php',
];
$dashboardUrl = $dashboards[$role] ?? 'login.php';

// Current date for comparison
$today = date('Y-m-d');

?>

<!DOCTYPE html>
<html>

<head>
    <title>Your Homework</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f7fb;
        }

        .container {
            width: 85%;
            margin: 30px auto;
        }

        .back-btn {
            margin-bottom: 20px;
            padding: 8px 15px;
            font-size: 14px;
            background-color: #3399ff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .homework-card {
            background: #fff;
            border: 1px solid #cce0ff;
            border-left: 6px solid #3399ff;
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 15px 20px;
            box-shadow: 2px 2px 8px rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .homework-card.overdue-not-submitted {
            border-left-color: #dc3545 !important;
        }

        .homework-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .homework-title {
            font-size: 18px;
            font-weight: bold;
            color: #0056b3;
        }

        .due-date {
            color: black;
            font-size: 14px;
            margin-left: 10px;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: bold;
            display: inline-block;
            white-space: nowrap;
            margin-left: 10px;
        }

        .submitted {
            background-color: #d4edda;
            color: #155724;
        }

        .pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .not-submitted {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px solid #ced4da;
        }

        .overdue-not-submitted {
            background-color: #dc3545;
            color: white;
            font-weight: bold;
        }

        .buttons {
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .buttons button,
        .buttons a button {
            margin-right: 10px;
            margin-bottom: 5px;
            padding: 5px 12px;
            font-size: 13px;
            cursor: pointer;
            border-radius: 5px;
            border: 1px solid #ccc;
            background-color: #e6f0ff;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            color: black;
        }

        .buttons button:hover,
        .buttons a button:hover {
            background-color: #cce0ff;
        }

        .details-section {
            display: none;
            margin-top: 10px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .details-section p {
            margin: 6px 0;
        }
    </style>
    <script>
        function toggleSection(id, section) {
            const elem = document.getElementById(section + '-' + id);
            elem.style.display = (elem.style.display === 'none' || elem.style.display === '') ? 'block' : 'none';
        }
    </script>
</head>

<body>

    <div class="container">
        <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="back-btn">← Back to Dashboard</a>

        <h2>Your Homework</h2>

        <?php if (empty($homeworks)): ?>
            <p>No homework assigned yet.</p>
        <?php else: ?>
            <?php foreach ($homeworks as $hw): ?>
                <?php
                $dueDate = $hw['due_date'];
                $isPastDue = ($dueDate < $today);
                $status = $hw['status'];

                $statusClass = 'not-submitted';
                $statusLabel = 'Not Submitted';

                if ($isPastDue && (!$status || $status === 'rejected')) {
                    $statusClass = 'overdue-not-submitted';
                    $statusLabel = 'Overdue & Not Submitted';
                } elseif ($status === 'reviewed') {
                    $statusClass = 'submitted';
                    $statusLabel = 'Reviewed';
                } elseif ($status === 'pending') {
                    $statusClass = 'pending';
                    $statusLabel = 'Pending';
                } elseif ($status === 'rejected') {
                    $statusClass = 'not-submitted';
                    $statusLabel = 'Rejected';
                }
                ?>
                <div class="homework-card <?= $statusClass ?>">
                    <div class="homework-header">
                        <div style="display:flex; align-items:center; flex-wrap:wrap;">
                            <div class="homework-title"><?= htmlspecialchars($hw['title']) ?></div>
                            <div class="due-date">(Due: <?= htmlspecialchars($dueDate) ?>)</div>
                            <div class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></div>
                        </div>
                        <div style="margin-left:auto; min-width: 180px; text-align:right;">
                            <div><small>Subject: <?= htmlspecialchars($hw['subject']) ?></small></div>
                            <div><small>Teacher: <?= htmlspecialchars($hw['teacher_name']) ?></small></div>
                        </div>
                    </div>

                    <div class="buttons">
                        <button onclick="toggleSection(<?= $hw['homework_id'] ?>, 'desc')">View Description</button>
                        <?php if ($hw['attachment_path']): ?>
                            <button onclick="toggleSection(<?= $hw['homework_id'] ?>, 'attach')">View Attachment</button>
                        <?php endif; ?>
                        <?php if ($status): ?>
                            <button onclick="toggleSection(<?= $hw['homework_id'] ?>, 'feedback')">View Feedback</button>
                        <?php endif; ?>

                        <?php if ($hw['submission_content'] || $hw['submission_attachment']): ?>
                            <button onclick="toggleSection(<?= $hw['homework_id'] ?>, 'submission')">View Your Submission</button>
                        <?php endif; ?>

                        <?php
                        // Show submit button ONLY if NOT overdue and status null or rejected
                        if (!$isPastDue && ($status === null || $status === 'rejected')):
                        ?>
                            <a href="submit_homework.php?homework_id=<?= $hw['homework_id'] ?>"><button>Submit</button></a>
                        <?php endif; ?>
                    </div>

                    <div class="details-section" id="desc-<?= $hw['homework_id'] ?>">
                        <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($hw['description'])) ?></p>
                    </div>

                    <div class="details-section" id="attach-<?= $hw['homework_id'] ?>">
                        <p><strong>Attachment:</strong><br>
                            <a href="<?= htmlspecialchars($hw['attachment_path']) ?>" target="_blank">Download File</a>
                        </p>
                    </div>

                    <div class="details-section" id="feedback-<?= $hw['homework_id'] ?>">
                        <p><strong>Score:</strong>
                            <?= htmlspecialchars($hw['percentage']) ? htmlspecialchars($hw['percentage']) . '%' : 'N/A' ?></p>
                        <p><strong>Feedback:</strong><br><?= nl2br(htmlspecialchars($hw['feedback'])) ?></p>
                    </div>

                    <div class="details-section" id="submission-<?= $hw['homework_id'] ?>">
                        <?php if ($hw['submission_content']): ?>
                            <p><strong>Your Answer:</strong><br><?= nl2br(htmlspecialchars($hw['submission_content'])) ?></p>
                        <?php else: ?>
                            <p><em>No text submission.</em></p>
                        <?php endif; ?>

                        <?php if ($hw['submission_attachment']): ?>
                            <p><strong>Your Attachment:</strong> <a href="<?= htmlspecialchars($hw['submission_attachment']) ?>"
                                    target="_blank">Download</a></p>
                        <?php else: ?>
                            <p><em>No attachment submitted.</em></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</body>

</html>