<?php
session_start();
require_once '../../Global_PHP/db.php';
require_once '../../Global_PHP/auth.php';
requireRole(['admin']);

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Delete the newsletter from the database
    $stmt = $pdo->prepare("DELETE FROM Newsletters WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['success_message'] = "Newsletter deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting the newsletter.";
    }

    // Redirect back to the Sent Newsletters page
    header('Location: sent_newsletters.php');
    exit();
} else {
    $_SESSION['error_message'] = "Invalid newsletter ID.";
    header('Location: sent_newsletters.php');
    exit();
}