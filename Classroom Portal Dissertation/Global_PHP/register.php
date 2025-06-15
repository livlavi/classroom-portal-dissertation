<?php
session_start();

// Enable error reporting for debugging. Turn OFF in production.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include your database connection file
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? '')); // Email from the user
    $username = trim(strtolower($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $consent = isset($_POST['consent']) ? true : false; // GDPR: Check if consent checkbox was ticked

    // --- Input Validation ---
    if (empty($code) || empty($email) || empty($username) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = "Username must be between 3 and 20 characters.";
    } elseif (!preg_match('/^[a-z0-9._]+$/', $username)) {
        $error = "Username can only contain lowercase letters, numbers, dots, and underscores.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        $error = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!$consent) { // GDPR: Check for consent
        $error = "You must agree to the Terms and Privacy Policy to register.";
    } else {
        try {
            $pdo->beginTransaction(); // Start a transaction for atomicity

            // --- 1. Verify Unique Code and Email ---
            $stmt = $pdo->prepare("SELECT id, role, email, first_name, last_name, used 
                                     FROM UniqueCodes 
                                     WHERE code = :code AND email = :email");
            $stmt->execute(['code' => $code, 'email' => $email]);
            $uniqueCodeEntry = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$uniqueCodeEntry) {
                $error = "Invalid unique code or email address. Please check your details.";
            } elseif ($uniqueCodeEntry['used']) {
                $error = "This unique code has already been used. Please log in.";
            } else {
                // --- 2. Check if Username/Email already exists in Users table ---
                $stmt = $pdo->prepare("SELECT id FROM Users WHERE username = :username OR email = :email");
                $stmt->execute(['username' => $username, 'email' => $email]);
                if ($stmt->fetch()) {
                    $error = "Username or email already exists. Please choose a different one or log in.";
                } else {
                    // --- 3. Create User Account (Users table) ---
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $role = $uniqueCodeEntry['role'];
                    $firstName = $uniqueCodeEntry['first_name'];
                    $lastName = $uniqueCodeEntry['last_name'];
                    $codeEmail = $uniqueCodeEntry['email']; // Email from the UniqueCodes table

                    $stmt = $pdo->prepare("INSERT INTO Users (username, password, email, first_name, last_name, role) 
                                             VALUES (:username, :password, :email, :first_name, :last_name, :role)");
                    $stmt->execute([
                        'username' => $username,
                        'password' => $hashed_password,
                        'email' => $codeEmail, // Use email from unique code entry
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'role' => $role
                    ]);
                    $new_user_id = $pdo->lastInsertId();

                    // --- 4. Create Role-Specific Entry ---
                    switch ($role) {
                        case 'teacher':
                            $stmt = $pdo->prepare("INSERT INTO Teachers (user_id, first_name, last_name, teacher_number, address, email, telephone, subject_taught)
                                                     VALUES (:user_id, :first_name, :last_name, :teacher_number, :address, :email, :telephone, :subject_taught)");
                            $stmt->execute([
                                'user_id' => $new_user_id,
                                'first_name' => $firstName,
                                'last_name' => $lastName,
                                'teacher_number' => 'TRC_' . $uniqueCodeEntry['id'], // Placeholder
                                'address' => 'Address to be updated', // Placeholder
                                'email' => $codeEmail,
                                'telephone' => NULL, // Can be NULL or placeholder
                                'subject_taught' => 'To be assigned' // Placeholder
                            ]);
                            break;
                        case 'parent':
                            $stmt = $pdo->prepare("INSERT INTO Parents (user_id, first_name, last_name, parent_type, email, home_address, telephone, child_full_name)
                                                     VALUES (:user_id, :first_name, :last_name, :parent_type, :email, :home_address, :telephone, :child_full_name)");
                            $stmt->execute([
                                'user_id' => $new_user_id,
                                'first_name' => $firstName,
                                'last_name' => $lastName,
                                'parent_type' => 'guardian', // Common default for initial
                                'email' => $codeEmail,
                                'home_address' => 'Home address to be updated', // Placeholder
                                'telephone' => NULL, // Can be NULL or placeholder
                                'child_full_name' => 'Child name to be linked' // Placeholder
                            ]);
                            break;
                        case 'student':
                            $stmt = $pdo->prepare("INSERT INTO Students (user_id, first_name, last_name, student_number, mother_name, father_name, year_of_study, main_teacher, address, email)
                                                     VALUES (:user_id, :first_name, :last_name, :student_number, :mother_name, :father_name, :year_of_study, :main_teacher, :address, :email)");
                            $stmt->execute([
                                'user_id' => $new_user_id,
                                'first_name' => $firstName,
                                'last_name' => $lastName,
                                'student_number' => 'STU_' . $uniqueCodeEntry['id'], // Placeholder
                                'mother_name' => NULL,
                                'father_name' => NULL,
                                'year_of_study' => 1, // Common default for initial
                                'main_teacher' => NULL,
                                'address' => 'Student address to be updated', // Placeholder
                                'email' => $codeEmail
                            ]);
                            break;
                        default:
                            throw new Exception("Invalid role specified in unique code.");
                    }

                    // --- 5. Mark Code as Used ---
                    $stmt = $pdo->prepare("UPDATE UniqueCodes SET used = TRUE WHERE id = :id");
                    $stmt->execute(['id' => $uniqueCodeEntry['id']]);

                    $pdo->commit(); // All operations successful
                    $success = "Account successfully created and activated! You can now log in with your new username and password.";
                    // Redirect to login page after success
                    header("Location: login.php?registered=1");
                    exit();
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack(); // Rollback on any error
            $error = "Registration failed: " . $e->getMessage();
            error_log("Code registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../Global_CSS/register.css">
    <title>Register with Code</title>

</head>

<body>
    <header>
        <div class="header-left">
            <img src="../Images/logo.png" alt="School Logo" class="navbar-logo">
            <h1>Classroom Management Portal</h1>
        </div>
        <nav>
            <a href="../index.php">About Us</a>
            <a href="#">Contact Support</a>
        </nav>
    </header>

    <div class="main-content">
        <div class="container">
            <h2>Register Your Account</h2>
            <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="code">Unique Code:</label>
                    <input type="text" id="code" name="code" value="<?= htmlspecialchars($_POST['code'] ?? '') ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="email">Your Email Address (as provided by the school):</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="username">Choose a Username:</label>
                    <input type="text" id="username" name="username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    <div class="password-requirements">
                        Between 3 and 20 characters (lowercase letters, numbers, dots, and underscores).
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Set Your Password:</label>
                    <input type="password" id="password" name="password" required>
                    <div class="password-requirements">
                        At least 8 characters, with one uppercase, one lowercase, and one number.
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="gdpr-consent">
                    <input type="checkbox" id="consent" name="consent" required>
                    <label for="consent">I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and
                        <a href="privacy_policy.php" target="_blank">Privacy Policy</a>.</label>
                </div>

                <button type="submit">Register Account</button>
            </form>
            <a href="login.php" class="login-link">Already registered? Log in here.</a>
        </div>
    </div>

    <footer>
        &copy; 2025 Classroom Management Portal. All rights reserved.
        <a href="privacy_policy.php" target="_blank">Privacy Policy</a> |
        <a href="terms.php" target="_blank">Terms of Service</a>
    </footer>
</body>

</html>