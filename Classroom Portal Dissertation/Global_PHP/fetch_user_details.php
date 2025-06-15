<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'auth.php';
require_once 'db.php';
require_once 'getting_informations.php';

requireRole(['admin']);
header('Content-Type: application/json');

$userId = $_GET['user_id'] ?? '';
$role = $_GET['role'] ?? '';

if (empty($userId) || empty($role)) {
    echo json_encode(['success' => false, 'message' => 'User ID and role are required']);
    exit;
}

$data = fetchUserDetails($pdo, $userId, $role);
echo json_encode($data);
exit;