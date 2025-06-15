<?php
session_start();
require_once 'db.php';
require_once 'auth.php'; //  auth.php handles initial login check and setting session role/user_id
require_once 'getting_informations.php'; // file contains fetchUserDetails

// Check if the user is logged in and is a student or parent
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'parent'])) {
    header("Location: login.php"); // Redirect to login if not logged in or wrong role
    exit();
}

$role = $_SESSION['role'];
$userId = $_SESSION['user_id']; // The user ID of the logged-in student or parent
$today = date('Y-m-d'); // Current date for due date comparison

$firstName = '';
$lastName = '';
$profilePhoto = null;
$childId = null;
$childName = null; // Variable to store child's full name for parent view
$studentId = null; // The user ID whose homework we are fetching (either the student or the child)

$assignedHomework = []; // Array to hold homework assigned to the studentId
$submittedHomework = []; // Array to hold submitted homework for the studentId
$submittedHomeworkMap = []; // Map for easy lookup of submitted homework status

// --- Logic to determine whose homework to fetch based on role ---
if ($role === 'student') {
    // If logged in as a student, fetch their own details and set studentId
    $userDetails = fetchUserDetails($pdo, $userId, 'student'); // Assuming fetchUserDetails works for student role
    $firstName = $userDetails['first_name'] ?? '';
    $lastName = $userDetails['last_name'] ?? '';
    $profilePhoto = $userDetails['photo_path'] ?? 'default_profile.jpg';
    $studentId = $userId; // Student is viewing their own homework

} elseif ($role === 'parent') {
    // If logged in as a parent, fetch parent details
    $userDetails = fetchUserDetails($pdo, $userId, 'parent'); // Assuming fetchUserDetails works for parent role
    $firstName = $userDetails['first_name'] ?? '';
    $lastName = $userDetails['last_name'] ?? '';
    $profilePhoto = $userDetails['photo_path'] ?? 'default_profile.jpg';

    // --- Fetch the child's ID using the method from view_assessments.php ---
    try {
        $stmt = $pdo->prepare("SELECT child_user_id FROM Parents WHERE user_id = :parent_user_id");
        $stmt->execute(['parent_user_id' => $userId]);
        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        $childId = $child['child_user_id'] ?? null; // Get the child's user_id

        if ($childId) {
            // Fetch the child's first and last name to display in the parent view if needed
            $childDetails = fetchUserDetails($pdo, $childId, 'student');
            $childFirstName = $childDetails['first_name'] ?? '';
            $childLastName = $childDetails['last_name'] ?? '';
            $childName = trim($childFirstName . ' ' . $childLastName); // Store child's full name

            $studentId = $childId; // Set the studentId variable to the child's ID for fetching homework
        } else {
            // Parent has no child linked in the Parents table
            $_SESSION['error_message'] = "No child linked to this parent account.";
            // studentId remains null, so homework fetching below will not run.
        }
    } catch (PDOException $e) {
        error_log("Error fetching child ID for parent: " . $e->getMessage());
        $_SESSION['error_message'] = "Error fetching child information.";
        // studentId remains null, so homework fetching below will not run.
    }
}

// --- Fetch homework for the determined studentId (either the student or the child) ---
if ($studentId) { // Only attempt to fetch homework if a valid studentId was found
    try {
        // Fetch assigned homework for the studentId where the due date is today or in the future
        $stmt = $pdo->prepare("
            SELECT h.id, h.title, h.subject, h.description, h.due_date, h.attachment_path, h.total_questions,
                   t.first_name AS teacher_first_name, t.last_name AS teacher_last_name
            FROM Homework h
            JOIN Homework_Students hs ON h.id = hs.homework_id
            JOIN Users t ON h.teacher_id = t.id
            WHERE hs.student_id = :student_id
            AND h.due_date >= :today
            ORDER BY h.due_date DESC
        ");
        $stmt->execute(['student_id' => $studentId, 'today' => $today]);
        $assignedHomework = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; // Use [] if no results

        // Fetch submitted homework status for the studentId
        $stmt = $pdo->prepare("
            SELECT sh.homework_id, sh.submission_content, sh.submission_attachment, sh.status
            FROM Submitted_Homework sh
            WHERE sh.student_id = :student_id
        ");
        $stmt->execute(['student_id' => $studentId]);
        $submittedHomework = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Create a map for quick lookup of submission status by homework ID
        foreach ($submittedHomework as $sh) {
            $submittedHomeworkMap[$sh['homework_id']] = $sh;
        }
    } catch (PDOException $e) {
        error_log("Error fetching homework: " . $e->getMessage());
        // An error message might already be set from fetching child ID, keep the most recent one or append.
        $_SESSION['error_message'] = "Error fetching homework list: " . $e->getMessage();
        $assignedHomework = []; // Ensure assignedHomework is empty on error
        $submittedHomeworkMap = [];
    }
} else {
    // studentId is null (e.g., parent with no child linked or error fetching child)
    $assignedHomework = []; // Ensure assignedHomework is empty
    $submittedHomeworkMap = [];
}

// Set session variables for display in the header bar (still uses the logged-in user's name/photo)
$_SESSION['first_name'] = $firstName;
$_SESSION['last_name'] = $lastName;
// $_SESSION['photo_path'] = $profilePhoto;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Homework</title>
    <link rel="stylesheet"
        href="../<?php echo $role === 'student' ? 'Student_Dashboard/CSS/student_dashboard.css' : 'Parent_Dashboard/CSS/parent_dashboard.css'; ?>">
    <style>
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

        /* 1. Make the table container scrollable */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            /* smooth scrolling on iOS */
            margin-bottom: 1rem;
        }

        /* 2. Adjust table styling for small screens */
        @media (max-width: 768px) {
            .table-responsive table {
                width: 100%;
                /* ensures the table takes full container width */
                border: 0;
            }

            .table-responsive th,
            .table-responsive td {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }

            /* Optionally hide less important columns on very small screens */
            @media (max-width: 576px) {
                .table-responsive th:nth-child(3),
                .table-responsive td:nth-child(3),
                /* Teacher column */
                .table-responsive th:nth-child(5),
                .table-responsive td:nth-child(5)

                /* Total Questions column */
                    {
                    display: none;
                }
            }
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        tr:hover {
            background-color: #ddd;
        }

        .no-homework {
            padding: 20px;
            color: #666;
        }

        .open-btn,
        .view-reviewed-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            margin-left: 5px;
        }

        .open-btn:hover,
        .view-reviewed-btn:hover {
            background-color: #218838;
        }

        .homework-details {
            margin-top: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            display: none;
        }

        .homework-details.active {
            display: block;
        }

        .homework-details textarea {
            width: 100%;
            height: 150px;
            margin-bottom: 10px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }

        .homework-details .disabled {
            background-color: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            opacity: 0.65;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
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

        body.dark-mode .no-homework {
            color: #ccc;
        }

        body.dark-mode .open-btn,
        body.dark-mode .view-reviewed-btn {
            background-color: #28a745;
            color: white;
        }

        body.dark-mode .open-btn:hover,
        body.dark-mode .view-reviewed-btn:hover {
            background-color: #218838;
        }

        body.dark-mode .homework-details {
            background-color: #444;
            border-color: #555;
        }

        body.dark-mode .homework-details textarea {
            background-color: #555;
            border-color: #666;
            color: #fff;
        }

        body.dark-mode .homework-details .disabled {
            background-color: #6c757d;
            color: #fff;
        }
    </style>
</head>

<body class="<?php echo isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark-mode' : ''; ?>">
    <header class="top-bar">
        <nav class="profile-actions">
            <a href="#" id="dark-mode-toggle">Dark Mode</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </nav>
        <div class="admin-profile">
            <img src="../Images/<?= htmlspecialchars($profilePhoto) ?>" alt="Profile Photo" class="profile-photo">
            <p><?= htmlspecialchars($firstName . ' ' . $lastName) ?></p>
        </div>
    </header>

    <aside class="sidebar">
        <h2><?php echo $role === 'student' ? 'Student Dashboard' : 'Parent Dashboard'; ?></h2>
        <ul>
            <?php if ($role === 'student'): ?>
                <li><a href="../Student_Dashboard/PHP/student_dashboard.php#homework">Manage Homework</a></li>
                <li><a href="../Student_Dashboard/PHP/view_grades.php">View Grades</a></li>
                <li><a href="../Student_Dashboard/PHP/view_calendar.php">Calendar</a></li>
                <li><a href="../Student_Dashboard/PHP/view_announcements.php">Announcements</a></li>
            <?php else: ?>
                <li><a href="../Parent_Dashboard/PHP/parent_dashboard.php">Dashboard</a></li>
                <li><a href="view_homework.php">View Child Homework</a></li>
                <li><a href="view_child_grades.php">View Child Grades</a></li>
                <li><a href="view_calendar.php">Calendar</a></li>
                <li><a href="view_announcements.php">Announcements</a></li>
            <?php endif; ?>
        </ul>
    </aside>

    <main class="main-content">
        <section class="section">
            <?php if ($role === 'parent' && $childName): // Display child's name if parent and child found 
            ?>
                <h2>Homework for <?= htmlspecialchars($childName) ?></h2>
            <?php elseif ($role === 'student'): // Display "Assigned Homework" for student 
            ?>
                <h2>Assigned Homework</h2>
            <?php else: // Parent with no child or error 
            ?>
                <h2>Assigned Homework</h2> <?php endif; ?>


            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($assignedHomework) && $studentId): // Show message if studentId is valid but no homework found 
            ?>
                <p class="no-homework">No homework assigned.</p>
            <?php elseif (empty($assignedHomework) && !$studentId && $role === 'parent'): // Show message if parent has no child or child not found 
            ?>
                <p class="no-homework">No child linked or unable to fetch homework for your child.</p>
            <?php else: // Display table if homework found 
            ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Subject</th>
                                <th>Teacher</th>
                                <th>Due Date</th>
                                <th>Total Questions</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignedHomework as $hw): ?>
                                <tr>
                                    <td><?= htmlspecialchars($hw['title']) ?></td>
                                    <td><?= htmlspecialchars($hw['subject']) ?></td>
                                    <td><?= htmlspecialchars($hw['teacher_first_name'] . ' ' . $hw['teacher_last_name']) ?></td>
                                    <td><?= htmlspecialchars($hw['due_date']) ?></td>
                                    <td><?= htmlspecialchars($hw['total_questions']) ?></td>
                                    <td>
                                        <?php
                                        $status = isset($submittedHomeworkMap[$hw['id']]) ? $submittedHomeworkMap[$hw['id']]['status'] : 'Not Submitted';
                                        echo htmlspecialchars($status);
                                        ?>
                                    </td>
                                    <td>
                                        <button class="open-btn" onclick="toggleDetails('details-<?= $hw['id'] ?>')">View
                                            Details</button>
                                        <?php if ($role === 'student'): // Only show submission/edit options for students 
                                        ?>
                                            <?php if (!isset($submittedHomeworkMap[$hw['id']]) || $submittedHomeworkMap[$hw['id']]['status'] === 'pending'): ?>
                                                <button class="open-btn"
                                                    onclick="location.href='submit_homework.php?homework_id=<?= $hw['id'] ?>'">
                                                    <?= isset($submittedHomeworkMap[$hw['id']]) ? 'Edit Submission' : 'Submit' ?>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (isset($submittedHomeworkMap[$hw['id']]) && in_array($submittedHomeworkMap[$hw['id']]['status'], ['reviewed', 'rejected'])): ?>
                                                <button class="view-reviewed-btn"
                                                    onclick="location.href='view_reviewed_homework.php?homework_id=<?= $hw['id'] ?>'">
                                                    View Review
                                                </button>
                                            <?php endif; ?>
                                        <?php elseif ($role === 'parent' && isset($submittedHomeworkMap[$hw['id']]) && in_array($submittedHomeworkMap[$hw['id']]['status'], ['reviewed', 'rejected'])): // Only show view review for parents if submitted and reviewed/rejected
                                        ?>
                                            <button class="view-reviewed-btn"
                                                onclick="location.href='view_reviewed_homework.php?homework_id=<?= $hw['id'] ?>'">
                                                View Review
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="7">
                                        <div id="details-<?= $hw['id'] ?>" class="homework-details">
                                            <p><strong>Description:</strong>
                                                <?= htmlspecialchars($hw['description'] ?? 'No description') ?></p>
                                            <?php if ($hw['attachment_path']): ?>
                                                <p><strong>Attachment:</strong> <a
                                                        href="<?= htmlspecialchars($hw['attachment_path']) ?>"
                                                        target="_blank">Download</a></p>
                                            <?php endif; ?>
                                            <?php if (isset($submittedHomeworkMap[$hw['id']])): // Show submission details if submitted 
                                            ?>
                                                <p><strong>Child's Submission:</strong></p>
                                                <p style="white-space: pre-wrap;">
                                                    <?= htmlspecialchars($submittedHomeworkMap[$hw['id']]['submission_content']) ?>
                                                </p>
                                                <?php if ($submittedHomeworkMap[$hw['id']]['submission_attachment']): ?>
                                                    <p><strong>Attachment:</strong> <a
                                                            href="<?= htmlspecialchars($submittedHomeworkMap[$hw['id']]['submission_attachment']) ?>"
                                                            target="_blank">Download</a></p>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <div style="margin-top: 20px;">
            <a href="../<?php echo $role === 'student' ? 'Student_Dashboard/PHP/student_dashboard.php' : 'Parent_Dashboard/PHP/parent_dashboard.php'; ?>"
                style="text-decoration: none;">
                <button
                    style="background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                    Go Back to Dashboard
                </button>
            </a>
        </div>
    </main>

    <script>
        // Dark mode toggle
        document.getElementById("dark-mode-toggle")?.addEventListener("click", () => {
            document.body.classList.toggle("dark-mode");
            document.cookie = `dark_mode=${document.body.classList.contains("dark-mode")}; path=/`;
        });

        // Toggle homework details
        function toggleDetails(id) {
            const element = document.getElementById(id);
            element.classList.toggle("active");
        }
    </script>
</body>

</html>