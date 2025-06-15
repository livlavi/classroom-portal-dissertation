<?php
session_start();
require_once '../../Global_PHP/auth.php';
require_once '../../Global_PHP/db.php';
requireRole(['admin']); // Make sure the user is an admin

if (isset($_POST['sendNewsletter'])) {
    // Get form data
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $target = $_POST['target'];
    $sender_id = $_SESSION['user_id'];

    // Validate inputs 
    if (empty($subject) || empty($body) || empty($target)) {
        $_SESSION['error_message'] = 'All fields are required!';
        header('Location: send_newsletter.php');
        exit();
    }

    try {
        // Insert the newsletter into the database
        $stmt = $pdo->prepare("INSERT INTO Newsletters (subject, body, target, sender_id, status) VALUES (?, ?, ?, ?, 'sent')");
        $stmt->execute([$subject, $body, $target, $sender_id]);

        $_SESSION['success_message'] = 'Newsletter sent successfully!';
        header('Location: sent_newsletters.php');
        exit();
    } catch (PDOException $e) {
        // Handle errors (e.g., database errors)
        $_SESSION['error_message'] = 'Error sending newsletter: ' . $e->getMessage();
        header('Location: send_newsletter.php');
        exit();
    }
}
