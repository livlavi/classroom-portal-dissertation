<?php
session_start();

// Debug mode - set to false in production
$debug = true;

require_once 'db.php'; // Include the database connection

$error = '';
$success = false;
$debug_info = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug_info[] = "Form submitted via POST";

    $uniqueCode = trim(strtoupper($_POST['unique_code'])); // Convert to uppercase for consistency
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim(strtolower($_POST['email'])); // Convert to lowercase for consistency
    $username = trim(strtolower($_POST['username'])); // User chosen username
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $gdprConsent = isset($_POST['gdpr_consent']);
    $marketingConsent = isset($_POST['marketing_consent']);

    $debug_info[] = "Form data collected: Code=$uniqueCode, Email=$email, Name=$firstName $lastName, Username=$username";

    // Basic validation
    if (empty($uniqueCode) || empty($firstName) || empty($lastName) || empty($email) || empty($username) || empty($password) || empty($confirmPassword)) {
        $error = "All required fields are required.";
        $debug_info[] = "Validation failed: Missing required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
        $debug_info[] = "Validation failed: Invalid email";
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = "Username must be between 3 and 20 characters.";
        $debug_info[] = "Validation failed: Username length";
    } elseif (!preg_match('/^[a-z0-9._]+$/', $username)) {
        $error = "Username can only contain lowercase letters, numbers, dots, and underscores.";
        $debug_info[] = "Validation failed: Username format";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
        $debug_info[] = "Validation failed: Password too short";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        $error = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
        $debug_info[] = "Validation failed: Password complexity";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
        $debug_info[] = "Validation failed: Password mismatch";
    } elseif (!$gdprConsent) {
        $error = "You must agree to the data processing terms to register.";
        $debug_info[] = "Validation failed: GDPR consent not given";
    } else {
        $debug_info[] = "Basic validation passed";

        // Validate unique code
        try {
            $debug_info[] = "Checking unique code in database";
            $stmt = $pdo->prepare("SELECT * FROM UniqueCodes WHERE code = :code AND used = 0");
            $stmt->execute(['code' => $uniqueCode]);
            $codeDetails = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$codeDetails) {
                $error = "Invalid or already used unique code.";
                $debug_info[] = "Unique code not found or already used";

                // Debug: Check if code exists at all
                $stmt = $pdo->prepare("SELECT * FROM UniqueCodes WHERE code = :code");
                $stmt->execute(['code' => $uniqueCode]);
                $codeExists = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($codeExists) {
                    $debug_info[] = "Code exists but is marked as used";
                } else {
                    $debug_info[] = "Code does not exist in database";
                }
            } else {
                $debug_info[] = "Unique code found: " . json_encode($codeDetails);

                if (strtolower($codeDetails['email']) !== $email) {
                    $error = "The unique code does not match the provided email address.";
                    $debug_info[] = "Email mismatch: Code email = " . $codeDetails['email'] . ", Provided = " . $email;
                } elseif (strtolower(trim($codeDetails['first_name'])) !== strtolower($firstName)) {
                    $error = "The unique code does not match the provided first name.";
                    $debug_info[] = "First name mismatch: Code = " . $codeDetails['first_name'] . ", Provided = " . $firstName;
                } elseif (strtolower(trim($codeDetails['last_name'])) !== strtolower($lastName)) {
                    $error = "The unique code does not match the provided last name.";
                    $debug_info[] = "Last name mismatch: Code = " . $codeDetails['last_name'] . ", Provided = " . $lastName;
                } else {
                    $debug_info[] = "Unique code validation passed";

                    // Check if the email, username already exists
                    $stmt = $pdo->prepare("SELECT id, email, username FROM Users WHERE email = :email OR username = :username");
                    $stmt->execute(['email' => $email, 'username' => $username]);
                    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($existingUser) {
                        if ($existingUser['email'] === $email) {
                            $error = "This email is already registered. Please <a href='login.php'>log in</a>.";
                            $debug_info[] = "Email already exists in Users table";
                        } elseif ($existingUser['username'] === $username) {
                            $error = "This username is already taken. Please choose a different username.";
                            $debug_info[] = "Username already exists in Users table";
                        }
                    } else {
                        $debug_info[] = "No existing user found, proceeding with registration";

                        // Hash the password
                        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                        $debug_info[] = "Password hashed successfully";

                        // Start transaction
                        $pdo->beginTransaction();
                        $debug_info[] = "Transaction started";

                        try {
                            // Insert user into the Users table
                            $stmt = $pdo->prepare("INSERT INTO Users (username, password, email, first_name, last_name, role, created_at) 
                                                   VALUES (:username, :password, :email, :first_name, :last_name, :role, NOW())");
                            $result = $stmt->execute([
                                ':username' => $username,
                                ':password' => $hashedPassword,
                                ':email' => $email,
                                ':first_name' => $firstName,
                                ':last_name' => $lastName,
                                ':role' => $codeDetails['role']
                            ]);

                            if (!$result) {
                                throw new Exception("Failed to insert user: " . implode(", ", $stmt->errorInfo()));
                            }

                            $userId = $pdo->lastInsertId();
                            $debug_info[] = "User inserted successfully with ID: $userId";

                            // Insert into role-specific table based on role
                            switch ($codeDetails['role']) {
                                case 'teacher':
                                    $stmt = $pdo->prepare("INSERT INTO Teachers (user_id, first_name, last_name, email, teacher_number, address, subject_taught) 
                                                           VALUES (:user_id, :first_name, :last_name, :email, :teacher_number, :address, :subject_taught)");
                                    $result = $stmt->execute([
                                        ':user_id' => $userId,
                                        ':first_name' => $firstName,
                                        ':last_name' => $lastName,
                                        ':email' => $email,
                                        ':teacher_number' => 'T' . str_pad($userId, 6, '0', STR_PAD_LEFT),
                                        ':address' => 'To be updated',
                                        ':subject_taught' => 'To be updated'
                                    ]);
                                    $debug_info[] = "Teacher record inserted";
                                    break;

                                case 'student':
                                    $stmt = $pdo->prepare("INSERT INTO Students (user_id, first_name, last_name, email, student_number, year_of_study, address) 
                                                           VALUES (:user_id, :first_name, :last_name, :email, :student_number, :year_of_study, :address)");
                                    $result = $stmt->execute([
                                        ':user_id' => $userId,
                                        ':first_name' => $firstName,
                                        ':last_name' => $lastName,
                                        ':email' => $email,
                                        ':student_number' => 'S' . str_pad($userId, 6, '0', STR_PAD_LEFT),
                                        ':year_of_study' => 1,
                                        ':address' => 'To be updated'
                                    ]);
                                    $debug_info[] = "Student record inserted";
                                    break;

                                case 'parent':
                                    $stmt = $pdo->prepare("INSERT INTO Parents (user_id, first_name, last_name, email, parent_type, home_address, child_full_name) 
                                                           VALUES (:user_id, :first_name, :last_name, :email, :parent_type, :home_address, :child_full_name)");
                                    $result = $stmt->execute([
                                        ':user_id' => $userId,
                                        ':first_name' => $firstName,
                                        ':last_name' => $lastName,
                                        ':email' => $email,
                                        ':parent_type' => 'guardian',
                                        ':home_address' => 'To be updated',
                                        ':child_full_name' => 'To be updated'
                                    ]);
                                    $debug_info[] = "Parent record inserted";
                                    break;
                            }

                            // Mark the unique code as used
                            $stmt = $pdo->prepare("UPDATE UniqueCodes SET used = 1 WHERE code = :code");
                            $result = $stmt->execute(['code' => $uniqueCode]);
                            $debug_info[] = "Unique code marked as used";

                            // Commit transaction
                            $pdo->commit();
                            $debug_info[] = "Transaction committed successfully";

                            // Set success flag
                            $success = true;
                            $_SESSION['registration_success'] = "Registration successful! You can now log in with your credentials.";
                            $debug_info[] = "Registration completed successfully";

                            // For debugging - don't redirect immediately
                            if (!$debug) {
                                header("Location: login.php?registered=1");
                                exit();
                            }
                        } catch (Exception $e) {
                            // Rollback transaction on error
                            $pdo->rollBack();
                            $error = "Registration failed: " . $e->getMessage();
                            $debug_info[] = "Exception caught: " . $e->getMessage();
                            $debug_info[] = "Transaction rolled back";

                            // Log the error for debugging
                            error_log("Registration error: " . $e->getMessage());
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
            $debug_info[] = "Database exception: " . $e->getMessage();
            error_log("Database error during registration: " . $e->getMessage());
        }
    }
}

// Clear form data if there's a success flag or if user navigates back after successful registration
if (isset($_GET['clear']) || $success) {
    $_POST = array();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - School Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        background-color: #f8f9fa;
    }

    .registration-container {
        max-width: 600px;
        margin: 50px auto;
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    }

    .form-control:focus {
        border-color: #4e73df;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }

    .btn-primary {
        background-color: #4e73df;
        border-color: #4e73df;
    }

    .btn-primary:hover {
        background-color: #2e59d9;
        border-color: #2e59d9;
    }

    .password-requirements {
        font-size: 0.875rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    .error-message {
        color: #dc3545;
    }

    .success-message {
        color: #28a745;
    }

    .debug-info {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        padding: 1rem;
        margin-top: 1rem;
        font-size: 0.875rem;
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="registration-container">
            <h2 class="text-center mb-4">Create Your Account</h2>

            <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <strong>Success!</strong> Your account has been created successfully.
                You can now <a href="login.php" class="alert-link">log in with your credentials</a>.
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <strong>Error:</strong> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if ($debug && !empty($debug_info)): ?>
            <div class="debug-info">
                <h6>Debug Information:</h6>
                <ul>
                    <?php foreach ($debug_info as $info): ?>
                    <li><?php echo htmlspecialchars($info); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate id="registrationForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="unique_code" class="form-label">Unique Registration Code *</label>
                        <input type="text" class="form-control" id="unique_code" name="unique_code"
                            value="<?php echo !$success ? htmlspecialchars($_POST['unique_code'] ?? '') : ''; ?>"
                            placeholder="Enter your unique code" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email"
                            value="<?php echo !$success ? htmlspecialchars($_POST['email'] ?? '') : ''; ?>"
                            placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label">First Name *</label>
                        <input type="text" class="form-control" id="first_name" name="first_name"
                            value="<?php echo !$success ? htmlspecialchars($_POST['first_name'] ?? '') : ''; ?>"
                            placeholder="Enter your first name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">Last Name *</label>
                        <input type="text" class="form-control" id="last_name" name="last_name"
                            value="<?php echo !$success ? htmlspecialchars($_POST['last_name'] ?? '') : ''; ?>"
                            placeholder="Enter your last name" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">Username *</label>
                    <input type="text" class="form-control" id="username" name="username"
                        value="<?php echo !$success ? htmlspecialchars($_POST['username'] ?? '') : ''; ?>"
                        placeholder="Choose a username (3-20 characters)" required>
                    <div class="password-requirements">
                        Username must be 3-20 characters and can only contain lowercase letters, numbers, dots, and
                        underscores.
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Enter your password" required>
                        <div class="password-requirements">
                            Must be at least 8 characters with uppercase, lowercase, and number.
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                            placeholder="Confirm your password" required>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="gdpr_consent" name="gdpr_consent"
                            <?php echo (!$success && isset($_POST['gdpr_consent'])) ? 'checked' : ''; ?> required>
                        <label class="form-check-label" for="gdpr_consent">
                            I agree to the processing of my personal data in accordance with the
                            <a href="privacy-policy.php" target="_blank">Privacy Policy</a> and
                            <a href="terms.php" target="_blank">Terms of Service</a> *
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="marketing_consent" name="marketing_consent"
                            <?php echo (!$success && isset($_POST['marketing_consent'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="marketing_consent">
                            I agree to receive marketing communications and updates (optional)
                        </label>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
                </div>
            </form>

            <div class="text-center mt-4">
                <p>Already have an account? <a href="login.php">Log in here</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
    // Clear form after successful registration
    <?php if ($success): ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('registrationForm').reset();
        // Remove any validation classes
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.classList.remove('is-valid', 'is-invalid');
        });
    });
    <?php endif; ?>

    // Client-side password validation
    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        const confirmPassword = document.getElementById('confirm_password');

        // Check password strength
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        const isLongEnough = password.length >= 8;

        if (password && (!hasUpper || !hasLower || !hasNumber || !isLongEnough)) {
            this.classList.add('is-invalid');
        } else if (password) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        }

        // Check password match
        if (confirmPassword.value && password !== confirmPassword.value) {
            confirmPassword.classList.add('is-invalid');
        } else if (confirmPassword.value) {
            confirmPassword.classList.remove('is-invalid');
            confirmPassword.classList.add('is-valid');
        }
    });

    document.getElementById('confirm_password').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirmPassword = this.value;

        if (confirmPassword && password !== confirmPassword) {
            this.classList.add('is-invalid');
        } else if (confirmPassword) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        }
    });

    // Username validation
    document.getElementById('username').addEventListener('input', function() {
        const username = this.value;
        const isValid = /^[a-z0-9._]+$/.test(username) && username.length >= 3 && username.length <= 20;

        if (username && !isValid) {
            this.classList.add('is-invalid');
        } else if (username) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        }
    });
    </script>
</body>

</html>