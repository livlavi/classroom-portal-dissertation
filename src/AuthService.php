<?php
// C:\Users\livla\OneDrive\Desktop\Dissertation\Classroom Portal\src\AuthService.php

namespace App; // This is the namespace we'll tell Composer to use

class AuthService
{
    private $dbConnection; // This could be a PDO object or your custom DB wrapper

    public function __construct($dbConnection = null)
    {
        //  pass a database connection here
        // or your database access layer.
        $this->dbConnection = $dbConnection;
    }

    public function login(string $email, string $password): bool
    {
     
        // Example: Query your database to find a user by email
        // Verify the password (using password_verify() with hashed passwords from DB)
        // If valid, start a session and store user role/info
        // If invalid, return false

        //  let's use some dummy logic for unit test basic examples:
        $users = [
            'admin@example.com' => ['password' => 'adminpass', 'role' => 'admin'],
            'teacher@example.com' => ['password' => 'teacherpass', 'role' => 'teacher'],
            'student@example.com' => ['password' => 'studentpass', 'role' => 'student'],
        ];

        if (isset($users[$email]) && $users[$email]['password'] === $password) {
            // Simulate session start if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = $users[$email]['role'];
            return true;
        }
        return false;
        // --- END OF DUMMY LOGIC ---
    }

    public function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_email']);
    }

    public function getUserRole(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user_role'] ?? null;
    }

    public function switchRole(string $newRole): bool
    {
        // Check if user is logged in
        // Check if the current user has permission to switch to $newRole
        // Update session and/or database with new role
        if ($this->isLoggedIn() && in_array($newRole, ['admin', 'teacher', 'student', 'parent'])) {
            $_SESSION['user_role'] = $newRole;
            return true;
        }
        return false;
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
    }
}
