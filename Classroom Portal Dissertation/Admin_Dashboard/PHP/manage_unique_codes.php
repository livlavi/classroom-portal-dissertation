<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and authentication helper
require_once '../../Global_PHP/db.php';
require_once '../../Global_PHP/auth.php';

// Ensure only admins can access this page
requireRole(['admin']);

// Function to generate a random unique code (re-using the one from add_user.php)
function generateUniqueCode($length = 16)
{
    return bin2hex(random_bytes($length / 2));
}

$successMessage = '';
$errorMessage = '';

// Handle adding a new unique code (if the form is submitted)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_new_code') {
    $email = trim($_POST['email'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $usernameSuggestion = trim($_POST['username_suggestion'] ?? '');

    // Role-specific fields
    $address = trim($_POST['address'] ?? null);
    $telephone = trim($_POST['telephone'] ?? null);
    $childFullName = trim($_POST['child_full_name'] ?? null);
    $parentType = trim($_POST['parent_type'] ?? null);
    $teacherNumber = trim($_POST['teacher_number'] ?? null);
    $subjectTaught = trim($_POST['subject_taught'] ?? null);
    $studentNumber = trim($_POST['student_number'] ?? null);
    $yearOfStudy = trim($_POST['year_of_study'] ?? null);

    $errors = [];

    // Basic validation
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
    if (empty($firstName)) $errors[] = "First name is required.";
    if (empty($lastName)) $errors[] = "Last name is required.";
    if (empty($role) || !in_array($role, ['parent', 'teacher', 'student'])) $errors[] = "A valid role is required.";
    if (empty($address)) $errors[] = "Address is required."; // Based on your schema design

    // Role-specific validation
    switch ($role) {
        case 'parent':
            if (empty($childFullName)) $errors[] = "Child's full name is required for parents.";
            if (empty($parentType) || !in_array($parentType, ['mother', 'father', 'guardian'])) $errors[] = "Parent type is required.";
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
        $errorMessage = implode(' ', $errors);
    } else {
        try {
            $pdo->beginTransaction();

            // Check if an entry with this email already exists in UniqueCodes (pending registration)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM UniqueCodes WHERE email = :email AND used = 0");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('An active registration for this email already exists in the unique code list. Please use that code or mark it as used/delete it before re-issuing.');
            }

            // If admin suggested a username, check if it's already taken by an active user
            if (!empty($usernameSuggestion)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE username = :usernameSuggestion");
                $stmt->execute([':usernameSuggestion' => $usernameSuggestion]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('The suggested username is already taken by an existing user. Please choose another.');
                }
            }

            // Generate a unique code
            $uniqueCode = generateUniqueCode();
            // Check for uniqueness of the generated code (unlikely to collide but good practice)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM UniqueCodes WHERE code = :code");
            $stmt->execute([':code' => $uniqueCode]);
            while ($stmt->fetchColumn() > 0) {
                $uniqueCode = generateUniqueCode(); // Regenerate if collision
                $stmt->execute([':code' => $uniqueCode]);
            }

            // Insert all details into the UniqueCodes table
            $stmt = $pdo->prepare("INSERT INTO UniqueCodes (
                code, role, email, first_name, last_name, username_suggestion,
                teacher_number, subject_taught, parent_type, child_full_name,
                student_number, year_of_study, address, telephone
            ) VALUES (
                :code, :role, :email, :first_name, :last_name, :username_suggestion,
                :teacher_number, :subject_taught, :parent_type, :child_full_name,
                :student_number, :year_of_study, :address, :telephone
            )");

            $stmt->execute([
                ':code' => $uniqueCode,
                ':role' => $role,
                ':email' => $email,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':username_suggestion' => !empty($usernameSuggestion) ? $usernameSuggestion : null,
                ':teacher_number' => $teacherNumber,
                ':subject_taught' => $subjectTaught,
                ':parent_type' => $parentType,
                ':child_full_name' => $childFullName,
                ':student_number' => $studentNumber,
                ':year_of_study' => $yearOfStudy,
                ':address' => $address,
                ':telephone' => $telephone
            ]);

            $pdo->commit();
            $successMessage = "New unique code generated: <strong>" . htmlspecialchars($uniqueCode) . "</strong> for " . htmlspecialchars($firstName . ' ' . $lastName) . " (" . htmlspecialchars($role) . ").";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = 'Error adding unique code: ' . $e->getMessage();
        }
    }
}

// Handle marking a code as used or deleting it
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['mark_used', 'delete_code'])) {
    $codeId = $_POST['code_id'] ?? null;
    if ($codeId) {
        try {
            if ($_POST['action'] === 'mark_used') {
                $stmt = $pdo->prepare("UPDATE UniqueCodes SET used = 1 WHERE id = :id");
                $stmt->execute([':id' => $codeId]);
                $successMessage = "Unique code marked as used.";
            } elseif ($_POST['action'] === 'delete_code') {
                $stmt = $pdo->prepare("DELETE FROM UniqueCodes WHERE id = :id");
                $stmt->execute([':id' => $codeId]);
                $successMessage = "Unique code deleted.";
            }
        } catch (PDOException $e) {
            $errorMessage = 'Database error: ' . $e->getMessage();
        }
    } else {
        $errorMessage = 'Invalid code ID.';
    }
}


// Fetch all unique codes to display
$stmt = $pdo->query("SELECT * FROM UniqueCodes ORDER BY created_at DESC");
$uniqueCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Unique Codes - Admin</title>
    <link rel="stylesheet" href="../CSS/admin_dashboard.css">
    <style>
        /* Basic styling for the unique codes page, you can integrate with your admin_dashboard.css */
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .form-section,
        .codes-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        .form-section form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-section label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
        }

        .form-section input[type="text"],
        .form-section input[type="email"],
        .form-section input[type="number"],
        .form-section select,
        .form-section textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .form-section button {
            grid-column: span 2;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .form-section button:hover {
            background-color: #0056b3;
        }

        .codes-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .codes-table th,
        .codes-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .codes-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .codes-table .used-code {
            background-color: #ffe0e0;
            opacity: 0.7;
        }

        .codes-table .unused-code {
            background-color: #e0ffe0;
        }

        .codes-table button {
            padding: 5px 10px;
            margin-right: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .codes-table .mark-used-btn {
            background-color: #28a745;
            color: white;
        }

        .codes-table .delete-code-btn {
            background-color: #dc3545;
            color: white;
        }

        .codes-table button:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>Admin Dashboard</h2>
        <a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="codes-section">
        <h2>Manage Unique Codes</h2>
        <div style="text-align: center; margin-top: 30px;">
            <a href="admin_dashboard.php" style="color: #007bff; text-decoration: none;">← Back to Admin Dashboard</a>
        </div>
        <h3>Existing Unique Codes</h3>
        <table class="codes-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Role</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($uniqueCodes)): ?>
                    <tr>
                        <td colspan="7">No unique codes found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($uniqueCodes as $code): ?>
                        <tr class="<?= $code['used'] ? 'used-code' : 'unused-code' ?>">
                            <td><?= htmlspecialchars($code['code']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($code['role'])) ?></td>
                            <td><?= htmlspecialchars($code['first_name'] . ' ' . $code['last_name']) ?></td>
                            <td><?= htmlspecialchars($code['email']) ?></td>
                            <td><?= $code['used'] ? 'Used' : 'Unused' ?></td>
                            <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($code['created_at']))) ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="code_id" value="<?= $code['id'] ?>">
                                    <?php if (!$code['used']): ?>
                                        <button type="submit" name="action" value="mark_used" class="mark-used-btn"
                                            onclick="return confirm('Are you sure you want to mark this code as USED? This cannot be undone if a user is mid-registration.')">Mark
                                            Used</button>
                                    <?php endif; ?>
                                    <button type="submit" name="action" value="delete_code" class="delete-code-btn"
                                        onclick="return confirm('Are you sure you want to DELETE this code? This action is irreversible.')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>
    </div>

    <script>
        // JavaScript to toggle role-specific fields
        function toggleRoleFields(role, prefix = '') {
            const parentFields = document.getElementById(prefix + 'parent-fields');
            const teacherFields = document.getElementById(prefix + 'teacher-fields');
            const studentFields = document.getElementById(prefix + 'student-fields');

            // Hide all by default
            parentFields.style.display = 'none';
            teacherFields.style.display = 'none';
            studentFields.style.display = 'none';

            // Reset required attributes
            const allInputs = document.querySelectorAll('#' + prefix + 'parent-fields input, #' + prefix +
                'parent-fields select, ' +
                '#' + prefix + 'teacher-fields input, ' +
                '#' + prefix + 'student-fields input');
            allInputs.forEach(input => input.removeAttribute('required'));

            // Show relevant fields and set required
            if (role === 'parent') {
                parentFields.style.display = 'block';
                document.getElementById(prefix + 'child-full-name').setAttribute('required', 'required');
                document.getElementById(prefix + 'parent-type').setAttribute('required', 'required');
            } else if (role === 'teacher') {
                teacherFields.style.display = 'block';
                document.getElementById(prefix + 'teacher-number').setAttribute('required', 'required');
                document.getElementById(prefix + 'subject-taught').setAttribute('required', 'required');
            } else if (role === 'student') {
                studentFields.style.display = 'block';
                document.getElementById(prefix + 'student-number').setAttribute('required', 'required');
                document.getElementById(prefix + 'year-of-study').setAttribute('required', 'required');
            }
        }

        // Initialize fields on page load if a role is pre-selected (though not in this current form)
        document.addEventListener('DOMContentLoaded', function() {
            const newRoleSelect = document.getElementById('new-role');
            if (newRoleSelect) {
                toggleRoleFields(newRoleSelect.value, 'new-');
            }
        });
    </script>
</body>

</html>