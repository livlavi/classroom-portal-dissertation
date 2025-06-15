<?php
// Check if a session has not already been started.
// This prevents errors if session_start() is called multiple times.
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Start the session to access session variables.
}

// Include necessary global PHP files for authentication, database connection, and fetching common information.
require_once '../../Global_PHP/auth.php';           // Handles user authentication and role-based access.
require_once '../../Global_PHP/db.php';             // Provides the database connection ($pdo object).
require_once '../../Global_PHP/getting_informations.php'; // Contains functions to retrieve various user and system data.

// Restrict access to this page to users with the 'admin' role.
// If the user does not have the 'admin' role, they will be redirected or denied access.
requireRole(['admin']);

// Fetch data required for the admin dashboard.
$users = fetchUsers($pdo);                           // Retrieves a list of all users, typically categorized by role.
$notifications = fetchNotifications($pdo);           // Fetches system notifications (e.g., for display count).
$adminDetails = fetchAdminDetails($pdo, $_SESSION['user_id']); // Gets specific details for the currently logged-in admin.
$analyticsData = fetchAnalyticsData($pdo);           // Gathers data for analytical charts and reports.
$studentsByYear = fetchStudentsByYear($pdo);         // Organizes student data by their year of study.
$parentsByYear = fetchParentsByChildYear($pdo);     // Organizes parent data based on their child's year of study.

// Extract first name, last name, and profile photo path from admin details.
// Uses the null coalescing operator (??) to provide a default empty string or null if the key doesn't exist.
$firstName = $adminDetails['first_name'] ?? '';
$lastName = $adminDetails['last_name'] ?? '';
$profilePhoto = $adminDetails['photo_path'] ?? null;

// Store the admin's first and last name in session variables.
// This makes them easily accessible across different pages without re-fetching.
$_SESSION['first_name'] = $firstName;
$_SESSION['last_name'] = $lastName;

// Define a range for academic years, typically 1 to 6 for primary/secondary education.
$yearRange = range(1, 6);
// Encode the analytics data into a JSON string for easy consumption by JavaScript on the client-side.
$analyticsJson = json_encode($analyticsData);

?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../CSS/admin_dashboard.css">
    <link rel="stylesheet" href="../../Messaging_Chat/chat.css">
    <script src="https://kit.fontawesome.com/YOUR-KIT-ID.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
</head>

<body>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
            <?php unset($_SESSION['success_message']); // Clear the message after displaying 
            ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
            <?php unset($_SESSION['error_message']); // Clear the message after displaying 
            ?>
        </div>
    <?php endif; ?>

    <div class="sidebar">
        <h2>Admin Dashboard</h2>
        <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
        <div class="admin-profile">
            <?php if ($profilePhoto): ?>
                <img src="../../Images/<?= htmlspecialchars($profilePhoto) ?>" alt="Profile Photo" class="profile-photo">
            <?php else: ?>
                <div class="placeholder-photo">
                    <?= substr($firstName, 0, 1) . substr($lastName, 0, 1) ?>
                </div>
            <?php endif; ?>
            <p><?= htmlspecialchars($firstName . ' ' . $lastName) ?></p>
        </div>
        <a href="#user-management"><i class="fas fa-users"></i> Manage Users</a>
        <a href="#analytics-section"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="sent_newsletters.php"><i class="fas fa-envelope"></i> Sent Newsletters</a>
        <a href="notifications.php" id="notifications-link">
            <i class="fas fa-bell"></i> Notifications
            <?php if (!empty($notifications)): ?>
                <span id="notif-count"><?= count($notifications) ?></span>
            <?php endif; ?>
        </a>
        <a href="#calendar-section"><i class="fas fa-calendar-alt"></i> Calendar</a>
        <button id="dark-mode-toggle">Dark Mode</button>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <input type="text" id="search-bar" placeholder="Search..." />
            <ul id="search-results"></ul>
            <div class="profile-actions">
                <a href="../../Global_PHP/profile.php"><button><i class="fas fa-user"></i> Profile</button></a>
                <a href="../../Global_PHP/logout.php"><button><i class="fas fa-sign-out-alt"></i> Logout</button></a>
            </div>
        </div>
        <div class="dashboard-grid">
            <div class="section" id="user-management">
                <h3>User Management</h3>
                <button id="add-user" onclick="window.location.href='add_user.php'">Add User</button>
                <button id="view-users">View Users</button>
                <button id="manage-codes" onclick="window.location.href='manage_unique_codes.php'">Manage Unique
                    Codes</button>
            </div>

            <div id="view-users-modal" class="modal">
                <div class="modal-content">
                    <span class="close">×</span>
                    <h2>View Users</h2>
                    <h3>Students</h3>
                    <?php foreach ($yearRange as $year): ?>
                        <div class="year-group">
                            <h4>Year <?= htmlspecialchars($year) ?></h4>
                            <table class="year-table">
                                <thead>
                                    <tr>
                                        <th>First Name</th>
                                        <th>Last Name</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get students for the current year, default to empty array if none.
                                    $yearStudents = $studentsByYear[$year] ?? [];
                                    if (empty($yearStudents)): ?>
                                        <tr>
                                            <td colspan="3">No students in Year <?= htmlspecialchars($year) ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($yearStudents as $student): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($student['first_name']) ?></td>
                                                <td><?= htmlspecialchars($student['last_name']) ?></td>
                                                <td>
                                                    <button class="edit-user-btn" data-user-id="<?= $student['id'] ?>"
                                                        data-first-name="<?= $student['first_name'] ?>"
                                                        data-last-name="<?= $student['last_name'] ?>"
                                                        data-role="student">Edit</button>
                                                    <button class="delete-user-btn"
                                                        data-user-id="<?= $student['id'] ?>">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>

                    <h3>Parents</h3>
                    <?php foreach (array_merge($yearRange, ['Unknown']) as $year): ?>
                        <div class="year-group">
                            <h4><?= $year === 'Unknown' ? 'Unknown Year' : 'Year ' . htmlspecialchars($year) ?></h4>
                            <table class="year-table">
                                <thead>
                                    <tr>
                                        <th>First Name</th>
                                        <th>Last Name</th>
                                        <th>Child Full Name</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get parents for the current year/category, default to empty array if none.
                                    $yearParents = $parentsByYear[$year] ?? [];
                                    if (empty($yearParents)): ?>
                                        <tr>
                                            <td colspan="4">No parents for
                                                <?= $year === 'Unknown' ? 'unknown year' : 'Year ' . htmlspecialchars($year) ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($yearParents as $parent): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($parent['first_name']) ?></td>
                                                <td><?= htmlspecialchars($parent['last_name']) ?></td>
                                                <td><?= htmlspecialchars($parent['child_full_name']) ?></td>
                                                <td>
                                                    <button class="edit-user-btn" data-user-id="<?= $parent['id'] ?>"
                                                        data-first-name="<?= $parent['first_name'] ?>"
                                                        data-last-name="<?= $parent['last_name'] ?>"
                                                        data-role="parent">Edit</button>
                                                    <button class="delete-user-btn"
                                                        data-user-id="<?= $parent['id'] ?>">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>

                    <h3>Teachers</h3>
                    <table class="year-table">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Get teachers, default to empty array if none.
                            $teachers = $users['teacher'] ?? [];
                            if (empty($teachers)): ?>
                                <tr>
                                    <td colspan="3">No teachers found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($teachers as $teacher): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($teacher['first_name']) ?></td>
                                        <td><?= htmlspecialchars($teacher['last_name']) ?></td>
                                        <td>
                                            <button class="edit-user-btn" data-user-id="<?= $teacher['id'] ?>"
                                                data-first-name="<?= $teacher['first_name'] ?>"
                                                data-last-name="<?= $teacher['last_name'] ?>" data-role="teacher">Edit</button>
                                            <button class="delete-user-btn" data-user-id="<?= $teacher['id'] ?>">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <h3>Admins</h3>
                    <table class="year-table">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Get admins, default to empty array if none.
                            $admins = $users['admin'] ?? [];
                            if (empty($admins)): ?>
                                <tr>
                                    <td colspan="3">No admins found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($admin['first_name']) ?></td>
                                        <td><?= htmlspecialchars($admin['last_name']) ?></td>
                                        <td>
                                            <button class="edit-user-btn" data-user-id="<?= $admin['id'] ?>"
                                                data-first-name="<?= $admin['first_name'] ?>"
                                                data-last-name="<?= $admin['last_name'] ?>" data-role="admin">Edit</button>
                                            <button class="delete-user-btn" data-user-id="<?= $admin['id'] ?>">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="edit-user-modal" class="modal">
                <div class="modal-content">
                    <span class="close">×</span>
                    <h2>Edit User</h2>
                    <form id="edit-user-form" method="POST" action="edit_user.php">
                        <input type="hidden" id="edit-user-id" name="user_id">
                        <label for="edit-first-name">First Name:</label>
                        <input type="text" id="edit-first-name" name="first_name" required>
                        <label for="edit-last-name">Last Name:</label>
                        <input type="text" id="edit-last-name" name="last_name" required>
                        <label for="edit-role">Role:</label>
                        <select id="edit-role" name="role" required>
                            <option value="teacher">Teacher</option>
                            <option value="student">Student</option>
                            <option value="parent">Parent</option>
                            <option value="admin">Admin</option>
                        </select>
                        <div id="edit-parent-fields" style="display: none;">
                            <label for="edit-child-full-name">Child Full Name:</label>
                            <input type="text" id="edit-child-full-name" name="child_full_name">
                        </div>
                        <div id="edit-teacher-fields" style="display: none;">
                            <label for="edit-teacher-number">Teacher Number:</label>
                            <input type="text" id="edit-teacher-number" name="teacher_number">
                            <label for="edit-subject-taught">Subject Taught:</label>
                            <input type="text" id="edit-subject-taught" name="subject_taught">
                        </div>
                        <div id="edit-student-fields" style="display: none;">
                            <label for="edit-student-number">Student Number:</label>
                            <input type="text" id="edit-student-number" name="student_number">
                            <label for="edit-year-of-study">Year of Study:</label>
                            <input type="number" id="edit-year-of-study" name="year_of_study">
                        </div>
                        <button type="submit">Save Changes</button>
                    </form>
                </div>
            </div>

            <div class="section" id="analytics-section">
                <h3>Analytics Overview</h3>
                <canvas id="analyticsChart"></canvas>
            </div>

            <div class="section" id="notifications-section">
                <h3>System Notifications</h3>
                <ul id="notifications-list">
                    <a href="notifications.php" class="sidebar-link">Notifications</a>
                </ul>
            </div>

            <div class="section" id="live-chat">
                <h3>Messages</h3>
                <button id="open-chat-modal"
                    onclick="window.open('/Classroom Portal Dissertation/Messaging_Chat/chat.php', '_blank')">
                    Open Chat
                </button>
            </div>

            <div class="section">
                <h3>Send Newsletter</h3>
                <form method="POST" action="send_newsletter.php">
                    <input type="text" id="newsletterTitle" name="subject" placeholder="Enter title..." required />
                    <textarea id="newsletterContent" name="body" placeholder="Write your announcement here..."
                        required></textarea>

                    <label for="newsletterTarget">Send to:</label>
                    <select id="newsletterTarget" name="target" required>
                        <option value="all">All Users</option>
                        <option value="teacher">Teachers Only</option>
                        <option value="parent">Parents Only</option>
                        <option value="student">Students Only</option>
                    </select>

                    <button type="submit" name="sendNewsletter">Send</button>
                </form>
            </div>

            <div class="section" id="calendar-section">
                <h3>School Calendar</h3>
                <div id="calendar"></div>
            </div>
        </div>
    </div>

    <script>
        window.analyticsData = <?php echo $analyticsJson; ?>;
    </script>
    <script src="../JavaScript/admin_dashboard.js"></script>
</body>

</html>