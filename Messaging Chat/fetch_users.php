<?php
session_start();
require_once '../PHP/db.php'; // Adjust the path to your database connection file

try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, role FROM Users");
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

    echo json_encode(['success' => true, 'users' => $groupedUsers]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching users: ' . $e->getMessage()]);
}
?>