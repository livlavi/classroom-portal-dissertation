<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../Global_PHP/auth.php';
require_once '../../Global_PHP/db.php';

// Make sure only admins can access this
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitise & trim input
    $userId = trim($_POST['user_id'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $role = trim($_POST['role'] ?? '');

    $childFullName = trim($_POST['child_full_name'] ?? '');
    $teacherNumber = trim($_POST['teacher_number'] ?? '');
    $subjectTaught = trim($_POST['subject_taught'] ?? '');
    $studentNumber = trim($_POST['student_number'] ?? '');
    $yearOfStudy = trim($_POST['year_of_study'] ?? '');

    $errors = [];

    // Basic validations
    if (empty($userId)) $errors[] = "User ID is required.";
    if (empty($firstName)) $errors[] = "First name is required.";
    if (empty($lastName)) $errors[] = "Last name is required.";
    if (empty($role) || !in_array($role, ['admin', 'parent', 'teacher', 'student'])) {
        $errors[] = "Valid role is required.";
    }

    // Role-specific validations
    switch ($role) {
        case 'parent':
            if (empty($childFullName)) $errors[] = "Child full name is required.";
            break;
        case 'teacher':
            if (empty($teacherNumber)) $errors[] = "Teacher number is required.";
            if (empty($subjectTaught)) $errors[] = "Subject taught is required.";
            break;
        case 'student':
            if (empty($studentNumber)) $errors[] = "Student number is required.";
            if (empty($yearOfStudy)) $errors[] = "Year of study is required.";
            break;
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(" ", $errors);
        header("Location: admin_dashboard.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update Users table
        $stmt = $pdo->prepare("UPDATE Users SET first_name = :first_name, last_name = :last_name, role = :role WHERE id = :user_id");
        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':role' => $role,
            ':user_id' => $userId,
        ]);

        // Update role-specific tables
        switch ($role) {
            case 'student':
                $stmt = $pdo->prepare("UPDATE Students SET student_number = :student_number, year_of_study = :year_of_study WHERE user_id = :user_id");
                $stmt->execute([
                    ':student_number' => $studentNumber,
                    ':year_of_study' => $yearOfStudy,
                    ':user_id' => $userId,
                ]);
                break;

            case 'teacher':
                $stmt = $pdo->prepare("UPDATE Teachers SET teacher_number = :teacher_number, subject_taught = :subject_taught WHERE user_id = :user_id");
                $stmt->execute([
                    ':teacher_number' => $teacherNumber,
                    ':subject_taught' => $subjectTaught,
                    ':user_id' => $userId,
                ]);
                break;

            case 'parent':
                $stmt = $pdo->prepare("UPDATE Parents SET child_full_name = :child_full_name WHERE user_id = :user_id");
                $stmt->execute([
                    ':child_full_name' => $childFullName,
                    ':user_id' => $userId,
                ]);
                break;

            case 'admin':
                // No additional table updates for admin role
                break;
        }

        $pdo->commit();

        $_SESSION['success_message'] = "User updated successfully!";
        header("Location: admin_dashboard.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error updating user: " . $e->getMessage();
        header("Location: admin_dashboard.php");
        exit;
    }
} else {
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: admin_dashboard.php");
    exit;
}
