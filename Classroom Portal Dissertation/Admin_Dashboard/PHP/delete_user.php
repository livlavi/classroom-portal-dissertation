<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../Global_PHP/auth.php';
require_once '../../Global_PHP/db.php';

// Make sure only admins can access this
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $data['user_id'] ?? '';

    if (empty($userId)) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // First, get the user's email and role
        $userStmt = $pdo->prepare("SELECT email, role FROM Users WHERE id = :user_id");
        $userStmt->execute([':user_id' => $userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception('User not found');
        }

        // Delete from UniqueCodes table if there's a matching email
        $deleteCodesStmt = $pdo->prepare("DELETE FROM UniqueCodes WHERE email = :email");
        $deleteCodesStmt->execute([':email' => $user['email']]);

        // Clean up junction tables (adjust table names as needed)
        $cleanupQueries = [
            "DELETE FROM Homework_Students WHERE student_id = :user_id",
            "DELETE FROM Assessment_Students WHERE student_id = :user_id",
            "DELETE FROM AnnouncementRecipients WHERE recipient_id = :user_id",
            "DELETE FROM AnnouncementReads WHERE user_id = :user_id"
        ];

        foreach ($cleanupQueries as $query) {
            try {
                $cleanupStmt = $pdo->prepare($query);
                $cleanupStmt->execute([':user_id' => $userId]);
            } catch (PDOException $e) {
                // Log but continue - table might not exist
                error_log("Cleanup query failed: " . $query . " - " . $e->getMessage());
            }
        }

        // Delete from role-specific tables first
        switch ($user['role']) {
            case 'student':
                $stmt = $pdo->prepare("DELETE FROM Students WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                break;
            case 'teacher':
                $stmt = $pdo->prepare("DELETE FROM Teachers WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                break;
            case 'parent':
                $stmt = $pdo->prepare("DELETE FROM Parents WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                break;
        }

        // Finally, delete from Users table
        $deleteUserStmt = $pdo->prepare("DELETE FROM Users WHERE id = :user_id");
        $result = $deleteUserStmt->execute([':user_id' => $userId]);

        if (!$result || $deleteUserStmt->rowCount() === 0) {
            throw new Exception('Failed to delete user - user may not exist');
        }

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully!'
        ]);
    } catch (Exception $e) {
        // Rollback on any error
        $pdo->rollBack();

        error_log("Error deleting user: " . $e->getMessage());

        echo json_encode([
            'success' => false,
            'message' => 'Error deleting user: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit;