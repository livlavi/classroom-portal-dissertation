<?php
session_start();
require_once 'db.php';
require_once 'auth.php'; // Assuming auth.php handles initial login check and setting session role/user_id
require_once 'getting_informations.php'; // Assuming this file contains fetchUserDetails

// Check if the user is logged in and is a student or parent
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'parent'])) {
    header("Location: login.php"); // Redirect to login if not logged in or wrong role
    exit();
}

$role = $_SESSION['role'];
$userId = $_SESSION['user_id']; // The user ID of the logged-in student or parent
$homeworkId = $_GET['homework_id'] ?? null; // Homework ID is only used when viewing a single submission

$firstName = '';
$lastName = '';
$profilePhoto = null;
$childId = null; // Variable to store the child's user ID
$childName = null; // Variable to store child's full name for parent view
$studentId = null; // The user ID whose homework we are fetching (either the student or the child)
$submission = null; // For fetching a single reviewed submission by homeworkId

$submittedHomework = []; // Array to hold all reviewed homework submissions for the studentId (for the list view)


// --- Logic to determine whose reviewed homework to fetch based on role ---
if ($role === 'student') {
    // If logged in as a student, fetch their own details and set studentId
    $userDetails = fetchUserDetails($pdo, $userId, 'student'); // Assuming fetchUserDetails works for student role
    $firstName = $userDetails['first_name'] ?? '';
    $lastName = $userDetails['last_name'] ?? '';
    $profilePhoto = $userDetails['photo_path'] ?? 'default_profile.jpg';
    $studentId = $userId; // Student is viewing their own reviewed homework

} elseif ($role === 'parent') {
    // If logged in as a parent, fetch parent details
    $userDetails = fetchUserDetails($pdo, $userId, 'parent'); // Assuming fetchUserDetails works for parent role
    $firstName = $userDetails['first_name'] ?? '';
    $lastName = $userDetails['last_name'] ?? '';
    $profilePhoto = $userDetails['photo_path'] ?? 'default_profile.jpg';

    // --- MODIFIED PARENT LOGIC: Prioritize child_id from GET, then fall back to Parents table ---
    $childId = $_GET['child_id'] ?? null; // <<< Read the child_id from the URL

    if (!$childId) {
        // If child_id is NOT in the URL (e.g., accessing directly), try to find it from the Parents table
        try {
            $stmt = $pdo->prepare("SELECT child_user_id FROM Parents WHERE user_id = :parent_user_id");
            $stmt->execute(['parent_user_id' => $userId]); // $userId is the logged-in parent's user_id
            $parentChildLink = $stmt->fetch(PDO::FETCH_ASSOC);
            $childId = $parentChildLink['child_user_id'] ?? null; // Get the child's user_id
        } catch (PDOException $e) {
            error_log("Error fetching child ID from Parents table for parent {$userId}: " . $e->getMessage());
            $_SESSION['error_message'] = "Error fetching child information from database.";
            $childId = null; // Ensure childId is null on error
        }
    }

    // If childId is found (either from GET or fallback), fetch child's name and set studentId
    if ($childId) {
        try {
            $childDetails = fetchUserDetails($pdo, $childId, 'student'); // Assuming fetchUserDetails works for student role
            $childFirstName = $childDetails['first_name'] ?? '';
            $childLastName = $childDetails['last_name'] ?? '';
            // Note: childName is primarily for display in the list view header
            $childName = trim($childFirstName . ' ' . $childLastName);
            $studentId = $childId; // Set the studentId variable to the child's ID for fetching submissions
        } catch (PDOException $e) {
            error_log("Error fetching child details for child ID {$childId}: " . $e->getMessage());
            $_SESSION['error_message'] = "Error fetching child details.";
            $studentId = null; // Ensure studentId is null if child details fail
        }
    } else {
        // childId was null from GET and Parents table lookup failed
        $_SESSION['error_message'] = $_SESSION['error_message'] ?? "No child ID provided or linked to this parent account."; // Keep existing error or set new one
        $studentId = null; // Ensure studentId is null
    }
    // --- END MODIFIED PARENT LOGIC ---
}

// Set session variables for display in the header bar (still uses the logged-in user's name/photo)
$_SESSION['first_name'] = $firstName;
$_SESSION['last_name'] = $lastName;
// $_SESSION['photo_path'] = $profilePhoto; // assuming this is handled in auth.php or similar


// --- Fetch the reviewed submission(s) based on studentId and potentially homeworkId ---

// If a specific homeworkId is provided in the URL, fetch that single reviewed submission
if ($studentId && $homeworkId) {
    try {
        $stmt = $pdo->prepare("
            SELECT sh.id, sh.homework_id, sh.student_id, sh.submission_content, sh.submission_attachment, sh.submission_date, sh.status,
                sh.percentage, sh.feedback, sh.corrected_submission,
                h.title, h.subject, h.description, h.due_date, h.attachment_path,
                CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
            FROM Submitted_Homework sh
            JOIN Homework h ON sh.homework_id = h.id
            JOIN Users t ON h.teacher_id = t.id
            WHERE sh.student_id = :student_id AND sh.homework_id = :homework_id
            AND sh.status IN ('reviewed', 'rejected') -- Only fetch reviewed or rejected
        ");
        $stmt->execute(['student_id' => $studentId, 'homework_id' => $homeworkId]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$submission) {
            // If a homeworkId was requested but not found/not reviewed for this student
            $_SESSION['error_message'] = $_SESSION['error_message'] ?? "Reviewed homework submission not found or you do not have permission to view it.";
            // Redirect back to the list view if a specific homework wasn't found
            header("Location: view_reviewed_homework.php" . ($role === 'parent' && $childId ? "?child_id={$childId}" : ""));
            exit();
        }
        // If single submission found, $submittedHomework remains empty, $submission is used in HTML

    } catch (PDOException $e) {
        error_log("Error fetching single reviewed homework ID {$homeworkId} for student {$studentId}: " . $e->getMessage());
        $_SESSION['error_message'] = $_SESSION['error_message'] ?? "Error fetching specific reviewed homework details: " . $e->getMessage();
        header("Location: view_reviewed_homework.php" . ($role === 'parent' && $childId ? "?child_id={$childId}" : ""));
        exit();
    }
} elseif ($studentId) {
    // If no specific homeworkId is requested but studentId is found, fetch ALL reviewed submissions for the studentId
    try {
        $stmt = $pdo->prepare("
            SELECT sh.id, sh.homework_id, sh.submission_date, sh.status, sh.percentage, sh.feedback, -- Removed sh.grade
                   h.title, h.subject, h.due_date
            FROM Submitted_Homework sh
            JOIN Homework h ON sh.homework_id = h.id
            WHERE sh.student_id = :student_id AND sh.status IN ('reviewed', 'rejected')
            ORDER BY sh.submission_date DESC
        ");
        $stmt->execute(['student_id' => $studentId]);
        $submittedHomework = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; // Use [] if no results

        // If no submissions found for the student
        if (empty($submittedHomework)) {
            $_SESSION['error_message'] = $_SESSION['error_message'] ?? "No reviewed homework submissions found for this account.";
        } else {
            // Clear the error message if submissions were found after a previous error
            if (isset($_SESSION['error_message']) && strpos($_SESSION['error_message'], 'No reviewed homework submissions found') !== false) {
                unset($_SESSION['error_message']);
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching list of reviewed homework for student {$studentId}: " . $e->getMessage());
        $_SESSION['error_message'] = $_SESSION['error_message'] ?? "Error fetching list of reviewed homework submissions: " . $e->getMessage();
        $submittedHomework = []; // Ensure empty on error
    }
} else {
    // If studentId is null (child not found for parent, or student not logged in),
    // the error message "No child ID provided or linked..." is already set above.
    $submittedHomework = []; // Ensure empty
}

// --- End Fetch Submission Logic ---


// Set session variables for display in the header bar (still uses the logged-in user's name/photo)
$_SESSION['first_name'] = $firstName;
$_SESSION['last_name'] = $lastName;
// $_SESSION['photo_path'] = $profilePhoto; // assuming this is handled in auth.php or similar


// Note: The HTML part of this file determines whether to show the list or a single submission.
// The current HTML shows a list IF $submission is null (no homeworkId or homeworkId not found)
// AND THEN it shows a single submission IF $submission is NOT null. This logic is a bit mixed.
// It should probably show a list if homeworkId is null, and show the single submission if homeworkId is NOT null and $submission is found.
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reviewed Homework</title>
    <link rel="stylesheet"
        href="../<?php echo $role === 'student' ? 'Student_Dashboard/CSS/student_dashboard.css' : 'Parent_Dashboard/CSS/parent_dashboard.css'; ?>">
    <style>
        /* Your existing styles */
        .section {
            margin-bottom: 20px;
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .section h1,
        .section h2 {
            color: #007bff;
            margin-bottom: 10px;
            /* Adjusted margin */
        }

        .submitted-homework-list {
            /* Changed class name from submitted-assessment-list */
            list-style-type: none;
            padding: 0;
            margin-top: 20px;
        }

        .submitted-homework-item {
            /* Changed class name */
            padding: 10px;
            border-bottom: 1px solid #eee;
            background-color: #f9f9f9;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .submitted-homework-item:last-child {
            border-bottom: none;
        }

        .submitted-homework-item a {
            color: #007bff;
            text-decoration: none;
        }

        .submitted-homework-item a:hover {
            text-decoration: underline;
        }

        .no-submissions {
            padding: 20px;
            color: #666;
        }

        .status-pending {
            color: #007bff;
            /* Assuming pending status is shown for completeness, though query filters for reviewed/rejected */
        }

        .status-reviewed {
            color: #28a745;
        }

        .status-rejected {
            color: #dc3545;
        }

        .submission-details {
            /* Added this block from your old code */
            margin-top: 10px;
            /* Adjusted margin */
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }


        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            /* display: none; /* Remove display: none here so messages show */
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

        body.dark-mode .section h1,
        body.dark-mode .section h2 {
            color: #007bff;
        }

        body.dark-mode .submitted-homework-item {
            /* Changed class name */
            background-color: #555;
            border-color: #666;
        }

        body.dark-mode .no-submissions {
            color: #ccc;
        }

        body.dark-mode .status-pending {
            color: #007bff;
        }

        body.dark-mode .status-reviewed {
            color: #28a745;
        }

        body.dark-mode .status-rejected {
            color: #dc3545;
        }

        body.dark-mode .submission-details {
            /* Added dark mode for submission-details */
            background-color: #555;
            border-color: #666;
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

    <div style="margin-top: 20px;">
        <a href="<?= ($role === 'parent') ? '../Parent_Dashboard/PHP/parent_dashboard.php' : '../Student_Dashboard/PHP/student_dashboard.php' ?>"
            style="text-decoration: none;">
            <button
                style="background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                Go Back to Dashboard
            </button>
        </a>
    </div>
    <br>

    <div class="section">

        <?php if ($studentId): // Only show this title if a student/child was found 
        ?>
            <h1>Reviewed Homework for
                <?= htmlspecialchars($role === 'student' ? ($firstName . ' ' . $lastName) : $childName) ?></h1>
        <?php else: // Show generic title if no student/child found 
        ?>
            <h1>View Reviewed Homework</h1>
        <?php endif; ?>


        <?php if ($studentId && $homeworkId && $submission): // Display single submission details if homeworkId is set and submission is found 
        ?>

            <h2>Homework: <?= htmlspecialchars($submission['title']) ?></h2>
            <p><strong>Subject:</strong> <?= htmlspecialchars($submission['subject']) ?></p>
            <p><strong>Description:</strong>
                <?= htmlspecialchars($submission['description'] ?? 'No description') ?></p>
            <p><strong>Due Date:</strong> <?= htmlspecialchars($submission['due_date']) ?></p>
            <?php if ($submission['attachment_path']): ?>
                <p><strong>Assignment Attachment:</strong> <a href="<?= htmlspecialchars($submission['attachment_path']) ?>"    
                        target="_blank">Download</a></p>
            <?php endif; ?>
            <p><strong>Teacher:</strong> <?= htmlspecialchars($submission['teacher_name']) ?></p>
            <p><strong>Submission Date:</strong> <?= htmlspecialchars($submission['submission_date']) ?></p>

            <div class="submission-details">
                <h3><?= htmlspecialchars($role === 'student' ? 'Your Submission' : 'Child\'s Submission') ?>
                </h3>
                <p><strong>Solution:</strong></p>
                <p style="white-space: pre-wrap;"><?= htmlspecialchars($submission['submission_content']) ?>
                </p>
                <?php if ($submission['submission_attachment']): ?>
                    <p><strong>Attachment:</strong> <a
                            href="../../Documents/<?= htmlspecialchars(basename($submission['submission_attachment'])) ?>"      
                            target="_blank">View Attachment</a></p>
                <?php endif; ?>

            </div>

            <div class="submission-details">
                <h3>Teacher's Review</h3>
                <p><strong>Percentage:</strong> <?= htmlspecialchars($submission['percentage']) ?>%</p>
                <p><strong>Status:</strong> <span
                        class="status-<?= htmlspecialchars(strtolower($submission['status'])) ?>"><?= htmlspecialchars($submission['status']) ?></span>
                </p>
                <p><strong>Feedback:</strong>
                    <?= htmlspecialchars($submission['feedback'] ?? 'No feedback provided.') ?></p>
                <p><strong>Corrections:</strong>
                    <?= htmlspecialchars($submission['corrected_submission'] ?? 'No corrections provided.') ?></p>
            </div>


        <?php elseif ($studentId && !empty($submittedHomework)): // Display the list of reviewed homework if studentId is valid and submissions found 
        ?>
            <h2>List of Reviewed Homework</h2>
            <ul class="submitted-homework-list">
                <?php foreach ($submittedHomework as $submissionItem): // Renamed loop variable to avoid conflict with single $submission 
                ?>
                    <li class="submitted-homework-item">
                        <strong>Homework: <?= htmlspecialchars($submissionItem['title']) ?></strong> (Subject:
                        <?= htmlspecialchars($submissionItem['subject']) ?>)
                        <p>Submission Date: <?= htmlspecialchars($submissionItem['submission_date']) ?></p>
                        <p>Due Date: <?= htmlspecialchars($submissionItem['due_date']) ?></p>
                        <p>Status: <span
                                class="status-<?= htmlspecialchars(strtolower($submissionItem['status'])) ?>"><?= htmlspecialchars($submissionItem['status']) ?></span>
                        </p>
                        <?php if (!empty($submissionItem['percentage']) || !empty($submissionItem['feedback'])): // Show percentage/feedback summary if available 
                        ?>
                            <p><strong>Percentage:</strong> <?= htmlspecialchars($submissionItem['percentage'] ?? 'Not graded') ?>%
                            </p>
                            <p><strong>Feedback Summary:</strong>
                                <?= htmlspecialchars(substr($submissionItem['feedback'] ?? 'No feedback', 0, 100)) ?>...</p>
                        <?php endif; ?>

                        <a
                            href="view_reviewed_homework.php?homework_id=<?= $submissionItem['homework_id'] ?>&<?= $role === 'parent' && $childId ? 'child_id=' . $childId : 'student_id=' . $studentId ?>">
                            <button class="open-btn" style="margin-top: 5px;">View Full Review</button>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

        <?php elseif ($studentId && empty($submittedHomework)): // Show no submissions message if studentId valid but no reviewed homework 
        ?>
            <p class="no-submissions">No reviewed homework submissions found for this account.</p>

        <?php else: // studentId is null - show error about child not found or invalid access 
        ?>
            <p class="no-submissions">Unable to fetch reviewed homework. Invalid access or child information not found.</p>
        <?php endif; ?>

    </div>


    <script>
        // Dark mode toggle
        document.getElementById("dark-mode-toggle")?.addEventListener("click", () => {
            document.body.classList.toggle("dark-mode");
            document.cookie = `dark_mode=${document.body.classList.contains("dark-mode")}; path=/`;
        });
    </script>
</body>

</html>