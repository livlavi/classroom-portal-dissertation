<?php
// Start the session to access session variables like user_id and role
session_start();

// Include the database connection script
require_once 'db.php';

// Check if the user is logged in and has a role of student or teacher
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'teacher'])) {
    // If not logged in or invalid role, redirect to login page
    header("Location: login.php");
    exit();
}

// Store user ID and role from session for easier access
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get today's date in Y-m-d format to compare deadlines
$today = date('Y-m-d');

// Different SQL queries for students and teachers since they see different data
if ($role === 'student') {
    // STUDENT VIEW:
    // Select assessments assigned to the student along with teacher name and any submissions made
    $sql = "
        SELECT 
            a.id AS assessment_id,
            a.title,
            a.subject,
            a.description,
            a.due_date,
            a.attachment AS attachment_path,
            CONCAT(tu.first_name, ' ', tu.last_name) AS teacher_name,
            sa.submission_date,
            sa.status,
            sa.grade,
            sa.feedback,
            sa.submission_content,
            sa.submission_attachment
        FROM Assessments a
        JOIN Assessment_Students ast ON ast.assessment_id = a.id
        JOIN Users tu ON tu.id = a.teacher_id
        LEFT JOIN Submitted_Assessments sa ON sa.assessment_id = a.id AND sa.student_id = :student_id
        WHERE ast.student_id = :student_id
        ORDER BY a.due_date DESC
    ";

    // Prepare and execute the query safely with PDO to prevent SQL injection
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['student_id' => $userId]);

    // Fetch all the results as associative arrays
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Link to student dashboard for the back button
    $dashboardUrl = '../Student_Dashboard/PHP/student_dashboard.php';
} elseif ($role === 'teacher') {
    // TEACHER VIEW:
    // Select assessments created by the teacher along with student submissions (if any)
    $sql = "
        SELECT 
            a.id AS assessment_id,
            a.title,
            a.subject,
            a.description,
            a.due_date,
            a.attachment AS attachment_path,
            CONCAT(tu.first_name, ' ', tu.last_name) AS teacher_name,
            sa.submission_date,
            sa.status,
            sa.grade,
            sa.feedback,
            sa.submission_content,
            sa.submission_attachment,
            CONCAT(su.first_name, ' ', su.last_name) AS student_name
        FROM Assessments a
        LEFT JOIN Submitted_Assessments sa ON sa.assessment_id = a.id
        LEFT JOIN Users su ON su.id = sa.student_id
        JOIN Users tu ON tu.id = a.teacher_id
        WHERE a.teacher_id = :teacher_id
        ORDER BY a.due_date DESC, su.last_name ASC
    ";

    // Prepare and execute query with the teacher's user ID
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['teacher_id' => $userId]);

    // Fetch all results
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Link to teacher dashboard for the back button
    $dashboardUrl = '../Teacher_Dashboard/PHP/teacher_dashboard.php';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <!-- Dynamic page title depending on role -->
    <title><?= $role === 'student' ? 'Your Assessments' : 'Assessments You Created' ?></title>
    <style>
        /* CSS styles for the page */

        /* Body styles: font, background color, and spacing */
        body {
            font-family: Arial, sans-serif;
            background: #f9fafb;
            margin: 0;
            padding: 20px;
        }

        /* Container to center content and limit width */
        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Back button styling: blue background, white text, rounded corners */
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 15px;
            background: #007bff;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }

        /* Card container for each assessment */
        .assessment-card {
            background: white;
            border-left: 6px solid #007bff;
            /* blue left border */
            border-radius: 8px;
            box-shadow: 0 0 6px #ccc;
            /* subtle shadow */
            margin-bottom: 20px;
            padding: 15px 20px;
        }

        /* Special border color for overdue and not submitted assessments */
        .assessment-card.overdue-not-submitted {
            border-left-color: #dc3545 !important;
            /* red */
        }

        /* Header layout inside each card: flexbox for spacing */
        .header {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            align-items: center;
        }

        /* Title style for assessment titles */
        .title {
            font-weight: 700;
            font-size: 18px;
            color: #0056b3;
        }

        /* Due date styling */
        .due-date {
            font-size: 14px;
            color: #333;
        }

        /* Status badge style (like a label) */
        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            margin-left: 10px;
        }

        /* Different colors for submission status badges */
        .submitted {
            background-color: #d4edda;
            color: #155724;
        }

        .pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .not-submitted {
            background-color: #e2e3e5;
            color: #6c757d;
        }

        .overdue-not-submitted {
            background-color: #dc3545;
            color: white;
            font-weight: 700;
        }

        /* Container for buttons below the card header */
        .buttons {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            /* space between buttons */
        }

        /* Button and linked buttons styling */
        button,
        a button {
            background: #e9f0ff;
            border: 1px solid #b3d1ff;
            border-radius: 5px;
            padding: 6px 14px;
            cursor: pointer;
            font-size: 13px;
            color: #004085;
            transition: background 0.2s ease-in-out;
            text-decoration: none;
        }

        /* Hover effect on buttons */
        button:hover,
        a button:hover {
            background: #cce0ff;
        }

        /* Hidden detail sections by default */
        .details {
            display: none;
            margin-top: 10px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            font-size: 14px;
            color: #333;
        }

        /* iframe style for viewing attachments */
        iframe {
            width: 100%;
            height: 350px;
            border: none;
            margin-top: 5px;
        }
    </style>

    <script>
        // JavaScript function to toggle visibility of details sections
        function toggleDetails(id, type) {
            // Construct element ID from type and assessment ID
            const elem = document.getElementById(type + '-' + id);
            if (!elem) return; // safety check

            // Toggle between block and none display
            elem.style.display = elem.style.display === 'block' ? 'none' : 'block';
        }
    </script>
</head>

<body>
    <div class="container">
        <!-- Back button to the dashboard -->
        <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="back-btn">← Back to Dashboard</a>

        <!-- Page header title depending on user role -->
        <h2><?= $role === 'student' ? 'Your Assessments' : 'Assessments You Created' ?></h2>

        <?php if (empty($assessments)): ?>
            <!-- Show message if there are no assessments -->
            <p><?= $role === 'student' ? 'You have no assigned assessments at this time.' : 'You have not created any assessments yet.' ?>
            </p>
        <?php else: ?>

            <?php if ($role === 'student'): ?>

                <!-- STUDENT VIEW: Loop through each assessment assigned to student -->
                <?php foreach ($assessments as $a):
                    // Store some variables for easy access and clarity
                    $dueDate = $a['due_date'];
                    $status = $a['status'];
                    $isPastDue = ($dueDate < $today); // check if the due date has passed

                    // Default CSS class and label for submission status
                    $statusClass = 'not-submitted';
                    $statusLabel = 'Not Submitted';

                    // Change CSS class and label depending on status and due date
                    if ($isPastDue && (!$status || $status === 'rejected')) {
                        $statusClass = 'overdue-not-submitted';
                        $statusLabel = 'Overdue & Not Submitted';
                    } elseif ($status === 'reviewed') {
                        $statusClass = 'submitted';
                        $statusLabel = 'Reviewed';
                    } elseif ($status === 'pending') {
                        $statusClass = 'pending';
                        $statusLabel = 'Pending Review';
                    } elseif ($status === 'rejected') {
                        $statusClass = 'rejected';
                        $statusLabel = 'Rejected';
                    }
                ?>
                    <div class="assessment-card <?= $statusClass ?>">
                        <div class="header">
                            <div>
                                <!-- Display the assessment title, due date, and status badge -->
                                <span class="title"><?= htmlspecialchars($a['title']) ?></span>
                                <span class="due-date">(Due: <?= htmlspecialchars($dueDate) ?>)</span>
                                <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                            </div>
                            <div style="font-size: 14px; color:#555;">
                                <!-- Show subject and teacher name -->
                                Subject: <?= htmlspecialchars($a['subject']) ?><br>
                                Teacher: <?= htmlspecialchars($a['teacher_name']) ?>
                            </div>
                        </div>

                        <div class="buttons">
                            <!-- Button to toggle description visibility -->
                            <button onclick="toggleDetails(<?= $a['assessment_id'] ?>, 'desc')">View Description</button>

                            <!-- Button to view attachment if there is one -->
                            <?php if ($a['attachment_path']): ?>
                                <button onclick="toggleDetails(<?= $a['assessment_id'] ?>, 'attach')">View Attachment</button>
                            <?php endif; ?>

                            <!-- Button to view feedback if assessment has a status -->
                            <?php if ($status): ?>
                                <button onclick="toggleDetails(<?= $a['assessment_id'] ?>, 'feedback')">View Feedback</button>
                            <?php endif; ?>

                            <!-- Button to view student's own submission if exists -->
                            <?php if ($a['submission_content'] || $a['submission_attachment']): ?>
                                <button onclick="toggleDetails(<?= $a['assessment_id'] ?>, 'submission')">View Your Submission</button>
                            <?php endif; ?>

                            <!-- Show submit button only if due date not passed and not submitted or rejected -->
                            <?php if (!$isPastDue && (!$status || $status === 'rejected')): ?>
                                <a href="submit_assessment.php?assessment_id=<?= $a['assessment_id'] ?>"><button>Submit</button></a>
                            <?php endif; ?>
                        </div>

                        <!-- Hidden description details -->
                        <div class="details" id="desc-<?= $a['assessment_id'] ?>">
                            <strong>Description:</strong>
                            <p><?= nl2br(htmlspecialchars($a['description'])) ?></p>
                        </div>

                        <!-- Hidden attachment iframe -->
                        <?php if ($a['attachment_path']): ?>
                            <div class="details" id="attach-<?= $a['assessment_id'] ?>">
                                <strong>Attachment:</strong>
                                <iframe src="<?= htmlspecialchars($a['attachment_path']) ?>"></iframe>
                            </div>
                        <?php endif; ?>

                        <!-- Hidden feedback details -->
                        <?php if ($status): ?>
                            <div class="details" id="feedback-<?= $a['assessment_id'] ?>">
                                <p><strong>Submission Date:</strong> <?= htmlspecialchars($a['submission_date']) ?></p>
                                <p><strong>Grade:</strong> <?= htmlspecialchars($a['grade']) ?></p>
                                <p><strong>Feedback:</strong><br><?= nl2br(htmlspecialchars($a['feedback'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Hidden submission content and attachments -->
                        <?php if ($a['submission_content'] || $a['submission_attachment']): ?>
                            <div class="details" id="submission-<?= $a['assessment_id'] ?>">
                                <p><strong>Your Submission:</strong><br><?= nl2br(htmlspecialchars($a['submission_content'])) ?></p>
                                <?php if ($a['submission_attachment']): ?>
                                    <p><strong>Submitted Attachment:</strong></p>
                                    <iframe src="<?= htmlspecialchars($a['submission_attachment']) ?>"></iframe>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

            <?php elseif ($role === 'teacher'): ?>

                <!-- TEACHER VIEW -->

                <?php
                // Group submissions by assessment id for easier display of all submissions under each assessment
                $groupedAssessments = [];
                foreach ($assessments as $row) {
                    $id = $row['assessment_id'];
                    // If this assessment hasn't been added yet, create a new entry
                    if (!isset($groupedAssessments[$id])) {
                        $groupedAssessments[$id] = [
                            'assessment_id' => $id,
                            'title' => $row['title'],
                            'subject' => $row['subject'],
                            'description' => $row['description'],
                            'due_date' => $row['due_date'],
                            'attachment_path' => $row['attachment_path'],
                            'teacher_name' => $row['teacher_name'],
                            'submissions' => []
                        ];
                    }
                    // If there is a student submission, add it to the submissions array
                    if ($row['student_name']) {
                        $groupedAssessments[$id]['submissions'][] = [
                            'student_name' => $row['student_name'],
                            'submission_date' => $row['submission_date'],
                            'status' => $row['status'],
                            'grade' => $row['grade'],
                            'feedback' => $row['feedback'],
                            'submission_content' => $row['submission_content'],
                            'submission_attachment' => $row['submission_attachment']
                        ];
                    }
                }
                ?>

                <!-- Loop through grouped assessments -->
                <?php foreach ($groupedAssessments as $a): ?>
                    <div class="assessment-card">
                        <div class="header">
                            <div>
                                <!-- Assessment title and due date -->
                                <span class="title"><?= htmlspecialchars($a['title']) ?></span>
                                <span class="due-date">(Due: <?= htmlspecialchars($a['due_date']) ?>)</span>
                            </div>
                            <div style="font-size: 14px; color:#555;">
                                Subject: <?= htmlspecialchars($a['subject']) ?><br>
                                Created by: <?= htmlspecialchars($a['teacher_name']) ?>
                            </div>
                        </div>

                        <div class="buttons">
                            <!-- Toggle description -->
                            <button onclick="toggleDetails(<?= $a['assessment_id'] ?>, 'desc')">View Description</button>
                            <!-- Toggle attachment if exists -->
                            <?php if ($a['attachment_path']): ?>
                                <button onclick="toggleDetails(<?= $a['assessment_id'] ?>, 'attach')">View Attachment</button>
                            <?php endif; ?>
                        </div>

                        <!-- Description -->
                        <div class="details" id="desc-<?= $a['assessment_id'] ?>">
                            <p><?= nl2br(htmlspecialchars($a['description'])) ?></p>
                        </div>

                        <!-- Attachment -->
                        <?php if ($a['attachment_path']): ?>
                            <div class="details" id="attach-<?= $a['assessment_id'] ?>">
                                <iframe src="<?= htmlspecialchars($a['attachment_path']) ?>"></iframe>
                            </div>
                        <?php endif; ?>

                        <h4>Student Submissions</h4>

                        <!-- If no submissions, display a message -->
                        <?php if (empty($a['submissions'])): ?>
                            <p>No submissions yet for this assessment.</p>
                        <?php else: ?>
                            <!-- Loop through all submissions for this assessment -->
                            <?php foreach ($a['submissions'] as $sub):
                                // Determine CSS class and label for submission status
                                $statusClass = 'not-submitted';
                                $statusLabel = 'Not Submitted';

                                if ($sub['status'] === 'reviewed') {
                                    $statusClass = 'submitted';
                                    $statusLabel = 'Reviewed';
                                } elseif ($sub['status'] === 'pending') {
                                    $statusClass = 'pending';
                                    $statusLabel = 'Pending Review';
                                } elseif ($sub['status'] === 'rejected') {
                                    $statusClass = 'rejected';
                                    $statusLabel = 'Rejected';
                                }
                            ?>
                                <div class="assessment-card <?= $statusClass ?>" style="margin-bottom:10px;">
                                    <strong>Student:</strong> <?= htmlspecialchars($sub['student_name']) ?><br>
                                    <strong>Submission Date:</strong> <?= htmlspecialchars($sub['submission_date']) ?><br>
                                    <strong>Status:</strong> <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span><br>

                                    <button
                                        onclick="toggleDetails('subfeedback-<?= $a['assessment_id'] . '-' . md5($sub['student_name']) ?>', 'sub')">View
                                        Feedback</button>
                                    <button
                                        onclick="toggleDetails('subcontent-<?= $a['assessment_id'] . '-' . md5($sub['student_name']) ?>', 'sub')">View
                                        Submission</button>

                                    <!-- Feedback section hidden by default -->
                                    <div class="details" id="subfeedback-<?= $a['assessment_id'] . '-' . md5($sub['student_name']) ?>">
                                        <p><strong>Grade:</strong> <?= htmlspecialchars($sub['grade']) ?></p>
                                        <p><strong>Feedback:</strong><br><?= nl2br(htmlspecialchars($sub['feedback'])) ?></p>
                                    </div>

                                    <!-- Submission content hidden by default -->
                                    <div class="details" id="subcontent-<?= $a['assessment_id'] . '-' . md5($sub['student_name']) ?>">
                                        <p><strong>Submission
                                                Content:</strong><br><?= nl2br(htmlspecialchars($sub['submission_content'])) ?></p>
                                        <?php if ($sub['submission_attachment']): ?>
                                            <p><strong>Attachment:</strong></p>
                                            <iframe src="<?= htmlspecialchars($sub['submission_attachment']) ?>"></iframe>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>

            <?php endif; ?>

        <?php endif; ?>

    </div>
</body>

</html>