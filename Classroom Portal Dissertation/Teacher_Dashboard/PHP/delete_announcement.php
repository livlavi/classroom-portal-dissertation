<?php
session_start();
require_once '../../Global_PHP/db.php';
require_once '../../Global_PHP/auth.php';

// Check if the user is logged in and is a teacher or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    header("Location: ../../Global_PHP/login.php");
    exit();
}

// Check if the announcement ID is provided
if (isset($_POST['announcement_id'])) {
    $announcementId = $_POST['announcement_id'];
    $teacherId = $_SESSION['user_id'];

    try {
        // Start a transaction
        $pdo->beginTransaction();

        // Delete from AnnouncementReads first (due to foreign key constraint)
        $stmt = $pdo->prepare("DELETE FROM AnnouncementReads WHERE announcement_id = :announcement_id");
        $stmt->execute(['announcement_id' => $announcementId]);

        // Delete associated recipients
        $stmt = $pdo->prepare("DELETE FROM AnnouncementRecipients WHERE announcement_id = :announcement_id");
        $stmt->execute(['announcement_id' => $announcementId]);

        // Delete the announcement itself
        $stmt = $pdo->prepare("DELETE FROM Announcements WHERE id = :announcement_id AND teacher_id = :teacher_id");
        $stmt->execute([
            'announcement_id' => $announcementId,
            'teacher_id' => $teacherId
        ]);

        // Check if announcement was deleted
        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            // Redirect to announcements page
            header("Location: view_announcements.php?deleted=1");
            exit();
        } else {
            $pdo->rollBack();
            echo "Failed to delete the announcement. Either it doesn't exist or you're not authorized.";
        }
    } catch (PDOException $e) {
        // Rollback on error
        $pdo->rollBack();
        error_log("Error deleting announcement: " . $e->getMessage());
        echo "An error occurred while deleting the announcement: " . $e->getMessage();
    }
} else {
    header("Location: view_announcements.php"); // If no announcement ID
    exit();
}