<?php
session_start(); // Start the session

// Check if user_id is set in the session
if (!isset($_SESSION['user_id'])) {
    die("Session user ID is not set. Please log in.");
}

require_once '../PHP/db.php'; // Adjust path as needed

try {
    // Use a prepared statement to prevent SQL injection
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, role FROM Users WHERE id != :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group users by role
    $groupedUsers = [
        'teacher' => [],
        'student' => [],
        'parent' => [],
    ];
    foreach ($users as $user) {
        $groupedUsers[$user['role']][] = $user;
    }
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $groupedUsers = [];
}
?>

<div class="chat-container">
    <h3>Live Chat</h3>
    <!-- Dropdown for selecting recipient -->
    <label for="recipient-dropdown">Send to:</label>
    <select id="recipient-dropdown">
        <option value="">Select a recipient...</option>
        <?php
        foreach ($groupedUsers as $role => $usersInRole) {
            echo "<optgroup label='" . ucfirst($role) . "s'>";
            foreach ($usersInRole as $user) {
                echo "<option value='{$user['id']}'>{$user['first_name']} {$user['last_name']} ($role)</option>";
            }
            echo "</optgroup>";
        }
        ?>
    </select>
    <!-- Chat history -->
    <ul id="chat-messages"></ul>
    <!-- Textarea for typing messages -->
    <textarea id="chat-input" placeholder="Type your message..."></textarea>
    <!-- Send button -->
    <button id="send-message">Send</button>
</div>