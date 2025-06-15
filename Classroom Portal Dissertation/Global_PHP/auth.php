<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login page if the user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Function to check if the user has a specific role
function requireRole($allowedRoles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        header("Location: unauthorised.php");
        exit;
    }
}
?>