<?php
session_start();
require_once '../../Global_PHP/db.php'; // Include the database connection

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Fetch distinct years of study (for dropdown) - only needed for teachers
$years = [];
$selectedYear = $_GET['year'] ?? null;

if ($userRole === 'teacher') {
    try {
        $stmt = $pdo->query("SELECT DISTINCT year_of_study FROM Students ORDER BY year_of_study");
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error fetching years: " . $e->getMessage());
        $years = [];
    }
}

// Initialize grades array
$grades = [];

// Fetch grades based on role
try {
    if ($userRole === 'teacher') {
        if ($selectedYear) {
            // Filter grades by selected year
            $stmt = $pdo->prepare("
                SELECT sa.student_id, u.first_name, u.last_name, a.subject, a.title, sa.grade, sa.feedback
                FROM Submitted_Assessments sa
                LEFT JOIN Assessments a ON sa.assessment_id = a.id
                LEFT JOIN Users u ON sa.student_id = u.id
                LEFT JOIN Students s ON sa.student_id = s.user_id
                WHERE (a.teacher_id = :teacher_id OR sa.assessment_id IN (SELECT id FROM Assessments WHERE teacher_id = :teacher_id))
                  AND s.year_of_study = :year
                  AND sa.grade IS NOT NULL AND TRIM(COALESCE(sa.grade, '')) != ''
                ORDER BY u.last_name, u.first_name, a.subject
            ");
            $stmt->execute([
                'teacher_id' => $userId,
                'year' => $selectedYear,
            ]);
        } else {
            // No year filter - fetch all grades for this teacher
            $stmt = $pdo->prepare("
                SELECT sa.student_id, u.first_name, u.last_name, a.subject, a.title, sa.grade, sa.feedback
                FROM Submitted_Assessments sa
                LEFT JOIN Assessments a ON sa.assessment_id = a.id
                LEFT JOIN Users u ON sa.student_id = u.id
                WHERE (a.teacher_id = :teacher_id OR sa.assessment_id IN (SELECT id FROM Assessments WHERE teacher_id = :teacher_id))
                  AND sa.grade IS NOT NULL AND TRIM(COALESCE(sa.grade, '')) != ''
                ORDER BY u.last_name, u.first_name, a.subject
            ");
            $stmt->execute(['teacher_id' => $userId]);
        }
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif ($userRole === 'student') {
        $stmt = $pdo->prepare("
            SELECT a.subject, a.title, sa.grade, sa.feedback
            FROM Submitted_Assessments sa
            LEFT JOIN Assessments a ON sa.assessment_id = a.id
            WHERE sa.student_id = :student_id AND sa.grade IS NOT NULL AND TRIM(COALESCE(sa.grade, '')) != ''
            ORDER BY a.subject, a.title
        ");
        $stmt->execute(['student_id' => $userId]);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } elseif ($userRole === 'parent') {
        // Get parent ID
        $stmt = $pdo->prepare("SELECT id FROM Parents WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($parent) {
            $parentId = $parent['id'];

            // Get associated student IDs
            $stmt = $pdo->prepare("SELECT student_id FROM Parent_Student WHERE parent_id = :parent_id");
            $stmt->execute(['parent_id' => $parentId]);
            $studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($studentIds)) {
                $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
                $stmt = $pdo->prepare("
                    SELECT u.first_name AS child_first_name, u.last_name AS child_last_name,
                        a.subject, a.title, sa.grade, sa.feedback
                    FROM Submitted_Assessments sa
                    LEFT JOIN Assessments a ON sa.assessment_id = a.id
                    LEFT JOIN Users u ON sa.student_id = u.id
                    WHERE sa.student_id IN ($placeholders)
                      AND sa.grade IS NOT NULL AND TRIM(COALESCE(sa.grade, '')) != ''
                    ORDER BY u.last_name, u.first_name, a.subject
                ");
                $stmt->execute($studentIds);
                $grades = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $grades = [];
                $_SESSION['error_message'] = "No child associated with this parent.";
            }
        } else {
            $grades = [];
            $_SESSION['error_message'] = "Parent record not found.";
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching grades: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    $grades = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Grades</title>
    <link rel="stylesheet" href="../CSS/teacher_dashboard.css" />
    <style>
    /* Your existing CSS styles */
    .section {
        margin-bottom: 20px;
        background-color: white;
        padding: 15px;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .section h1 {
        color: #007bff;
        margin-bottom: 20px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    th,
    td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    th {
        background-color: #007bff;
        color: white;
    }

    tr:nth-child(even) {
        background-color: #f2f2f2;
    }

    tr:hover {
        background-color: #ddd;
    }

    .alert {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    body.dark-mode {
        background-color: #333;
        color: #fff;
    }

    body.dark-mode .section {
        background-color: #444;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
    }

    body.dark-mode .section h1 {
        color: #007bff;
    }

    body.dark-mode table {
        background-color: #444;
        color: #fff;
    }

    body.dark-mode th {
        background-color: #0056b3;
    }

    body.dark-mode tr:nth-child(even) {
        background-color: #555;
    }

    body.dark-mode tr:hover {
        background-color: #666;
    }
    </style>
</head>

<body class="<?php echo isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark-mode' : ''; ?>">
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($_SESSION['success_message']) ?>
        <?php unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($_SESSION['error_message']) ?>
        <?php unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>

    <div class="section">
        <h1>Grades</h1>
        <?php
        switch ($_SESSION['role']) {
            case 'student':
                $dashboardUrl = '../../Student_Dashboard/PHP/student_dashboard.php';
                break;
            case 'teacher':
                $dashboardUrl = 'teacher_dashboard.php';
                break;
            case 'parent':
                $dashboardUrl = '../../Parent_Dashboard/PHP/parent_dashboard.php';
                break;
            default:
                $dashboardUrl = '../../Global_PHP/login.php';
        }
        ?>


        <br>
        <button onclick="location.href='<?= htmlspecialchars($dashboardUrl) ?>'">
            Return to Dashboard
        </button>
        <br><br>


        <?php if ($userRole === 'teacher'): ?>
        <form method="GET" action="">
            <label for="year">Filter by Year of Study:</label>
            <select name="year" id="year" onchange="this.form.submit()">
                <option value="">-- All Years --</option>
                <?php foreach ($years as $year): ?>
                <option value="<?= htmlspecialchars($year) ?>" <?= ($year == $selectedYear) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($year) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit">Filter</button></noscript>
        </form>
        <?php endif; ?>

        <?php if (empty($grades)): ?>
        <p>No existing grades from submitted assessments.</p>
        <?php else: ?>
        <table>
            <thead>
                <?php if ($userRole === 'teacher'): ?>
                <tr>
                    <th>Student Name</th>
                    <th>Subject</th>
                    <th>Assessment Title</th>
                    <th>Grade</th>
                    <th>Feedback</th>
                </tr>
                <?php elseif ($userRole === 'student'): ?>
                <tr>
                    <th>Subject</th>
                    <th>Assessment Title</th>
                    <th>Grade</th>
                    <th>Feedback</th>
                </tr>
                <?php elseif ($userRole === 'parent'): ?>
                <tr>
                    <th>Student Name</th>
                    <th>Subject</th>
                    <th>Assessment Title</th>
                    <th>Grade</th>
                    <th>Feedback</th>
                </tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php if ($userRole === 'teacher'): ?>
                <?php foreach ($grades as $grade): ?>
                <tr>
                    <td><?= htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']) ?></td>
                    <td><?= htmlspecialchars($grade['subject'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($grade['title'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($grade['grade'] ?? 'Not graded') ?></td>
                    <td><?= htmlspecialchars($grade['feedback'] ?? 'No feedback') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php elseif ($userRole === 'student'): ?>
                <?php foreach ($grades as $grade): ?>
                <tr>
                    <td><?= htmlspecialchars($grade['subject'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($grade['title'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($grade['grade'] ?? 'Not graded') ?></td>
                    <td><?= htmlspecialchars($grade['feedback'] ?? 'No feedback') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php elseif ($userRole === 'parent'): ?>
                <?php foreach ($grades as $grade): ?>
                <tr>
                    <td><?= htmlspecialchars($grade['child_first_name'] . ' ' . $grade['child_last_name']) ?></td>
                    <td><?= htmlspecialchars($grade['subject'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($grade['title'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($grade['grade'] ?? 'Not graded') ?></td>
                    <td><?= htmlspecialchars($grade['feedback'] ?? 'No feedback') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</body>

</html>