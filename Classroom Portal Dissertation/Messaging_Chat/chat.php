<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../Global_PHP/db.php';
require_once '../Global_PHP/auth.php';
if (!isset($_SESSION['user_id'])) {
    die("Please log in to use the chat. <a href='/Classroom Portal Dissertation/Global_PHP/login.php'>Login</a>");
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Fetch users for recipient list based on the user's role
try {
    if ($user_role === 'student') {
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, role 
            FROM Users 
            WHERE id != :user_id AND role IN ('teacher', 'student', 'admin')
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, role 
            FROM Users 
            WHERE id != :user_id
        ");
    }
    $stmt->execute(['user_id' => $user_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group users by role for dropdowns
    $groupedUsers = ['teacher' => [], 'student' => [], 'parent' => [], 'admin' => []];
    foreach ($users as $user) {
        $groupedUsers[$user['role']][] = $user;
    }
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $groupedUsers = ['teacher' => [], 'student' => [], 'parent' => [], 'admin' => []];
}

// Fetch chat history with unread counts, excluding self as a contact, and fixing ambiguous created_at
try {
    $stmt = $pdo->query("
        SELECT DISTINCT 
            CASE 
                WHEN cm.sender_id = $user_id THEN cm.receiver_id 
                ELSE cm.sender_id 
            END AS id,
            CASE 
                WHEN cm.sender_id = $user_id THEN cm.receiver_role 
                ELSE cm.sender_role 
            END AS role,
            u.first_name, u.last_name,
            MAX(cm.created_at) AS latest_message, -- Explicitly specify ChatMessages.created_at
            SUM(CASE WHEN cm.receiver_id = $user_id AND cm.read_status = 0 THEN 1 ELSE 0 END) AS unread_count
        FROM ChatMessages cm
        JOIN Users u ON u.id = 
            CASE 
                WHEN cm.sender_id = $user_id THEN cm.receiver_id 
                ELSE cm.sender_id 
            END
        WHERE (cm.sender_id = $user_id OR cm.receiver_id = $user_id)
            AND (cm.sender_id != $user_id OR cm.receiver_id != $user_id) -- Explicitly exclude self
        GROUP BY id, role, u.first_name, u.last_name
        ORDER BY latest_message DESC
    ");
    $chatHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching chat history: " . $e->getMessage());
    $chatHistory = [];
}

// Fetch current user's details
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, role FROM Users WHERE id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching current user: " . $e->getMessage());
    $currentUser = ['first_name' => 'Unknown', 'last_name' => '', 'role' => ''];
}

// Determine dashboard based on role
$dashboardPath = '';
switch ($_SESSION['role']) {
    case 'admin':
        $dashboardPath = '/Classroom Portal Dissertation/Admin_Dashboard/PHP/admin_dashboard.php';
        break;
    case 'teacher':
        $dashboardPath = '/Classroom Portal Dissertation/Teacher_Dashboard/PHP/teacher_dashboard.php';
        break;
    case 'student':
        $dashboardPath = '/Classroom Portal Dissertation/Student_Dashboard/PHP/student_dashboard.php';
        break;
    case 'parent':
        $dashboardPath = '/Classroom Portal Dissertation/Parent_Dashboard/PHP/parent_dashboard.php';
        break;
    default:
        $dashboardPath = '/Classroom Portal Dissertation/index.php';
}

// Check if a recipient is pre-selected
$preSelectedRecipient = $_GET['recipient_id'] ?? '';
?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chat</title>
    <link rel="stylesheet" href="chat.css">
</head>

<body>
    <div class="chat-container">
        <div class="chat-header">
            <h3>Live Chat</h3>
            <button id="close-chat" onclick="if (confirm('Close the chat window?')) window.close();">Close</button>
            <a href="<?php echo $dashboardPath; ?>" class="close-link">Back to Dashboard</a>
        </div>
        <div class="chat-content">
            <div class="user-list">
                <h4>Contacts</h4>
                <?php foreach (['teacher', 'student', 'parent', 'admin'] as $role): ?>
                <?php if (!empty($groupedUsers[$role]) && ($user_role !== 'student' || in_array($role, ['teacher', 'student', 'admin']))): ?>
                <h5><?php echo ucfirst($role) . "s"; ?></h5>
                <select class="user-dropdown" data-role="<?php echo $role; ?>">
                    <option value="">Select <?php echo ucfirst($role); ?></option>
                    <?php foreach ($groupedUsers[$role] as $user): ?>
                    <option value="<?php echo $user['id']; ?>">
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <?php endforeach; ?>

                <h4>Recent Chats</h4>
                <ul id="chat-history">
                    <?php foreach ($chatHistory as $contact): ?>
                    <li class="user-item" data-user-id="<?php echo $contact['id']; ?>"
                        data-role="<?php echo $contact['role']; ?>">
                        <?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?>
                        (<?php echo $contact['role']; ?>)
                        <?php if ($contact['unread_count'] > 0): ?>
                        <span class="unread-count"><?php echo $contact['unread_count']; ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="chat-area">
                <div id="profile-info" style="display: none; margin: 10px 0;">
                    Chatting with: <span id="recipient-name"></span>
                </div>
                <ul id="chat-messages"></ul>
                <textarea id="chat-input" placeholder="Type your message..."></textarea>
                <button id="send-message">Send</button>
            </div>
        </div>
    </div>
    <script>
    const currentUserId = <?php echo json_encode($user_id); ?>;
    const preSelectedRecipientId = <?php echo json_encode($preSelectedRecipient); ?>;
    </script>
    <script src="chat.js"></script>
</body>

</html>