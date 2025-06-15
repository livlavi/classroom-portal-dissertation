<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include authentication handler and database connection.
require_once 'auth.php';
require_once 'db.php';

// Ensure only logged-in users can access this page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role']; // Get user role from session

$error_message = '';
$success_message = '';

// Retrieve and clear any session-based messages (from previous redirects)
if (isset($_SESSION['profile_success_message'])) {
    $success_message = $_SESSION['profile_success_message'];
    unset($_SESSION['profile_success_message']);
}
if (isset($_SESSION['profile_error_message'])) {
    $error_message = $_SESSION['profile_error_message'];
    unset($_SESSION['profile_error_message']);
}

// --- Fetch Current User Details (including password hash) ---
try {
    // We need the password hash to verify current password for changes
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.password, u.email, u.first_name, u.last_name, a.telephone 
                             FROM Users u
                             LEFT JOIN Admins a ON u.id = a.user_id /* Joins for telephone, specific to Admin table in your schema */
                             WHERE u.id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // If user not found in DB despite being logged in, destroy session and redirect
        session_destroy();
        header("Location: login.php?error=user_not_found");
        exit();
    }

    // Fetch the profile photo path
    $stmt = $pdo->prepare("SELECT photo_path FROM ProfilePhotos WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $profilePhoto = $stmt->fetchColumn();
} catch (PDOException $e) {
    $_SESSION['profile_error_message'] = "Error fetching user details: " . $e->getMessage();
    error_log("Profile page DB fetch error: " . $e->getMessage()); // Log the error for debugging
    header("Location: profile.php"); // Redirect to show the error
    exit();
}

// --- Handle Form Submissions ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which action is being requested
    if (isset($_POST['update_personal_details'])) {
        // Personal Details, Email, Telephone, and Photo Update
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $telephone = trim($_POST['telephone'] ?? '');

        if (empty($firstName) || empty($lastName) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['profile_error_message'] = "Please fill in all required fields correctly (first name, last name, email).";
        } else {
            try {
                $pdo->beginTransaction(); // Start a transaction for atomicity

                // Check if the new email is already taken by another user
                $stmt = $pdo->prepare("SELECT id FROM Users WHERE email = :email AND id != :user_id");
                $stmt->execute([':email' => $email, ':user_id' => $user_id]);
                if ($stmt->fetch()) {
                    throw new Exception("This email address is already in use by another account.");
                }

                // Update the Users table (first_name, last_name, email)
                $stmt = $pdo->prepare("UPDATE Users SET first_name = :first_name, last_name = :last_name, email = :email 
                                         WHERE id = :user_id");
                $stmt->execute([
                    ':first_name' => $firstName,
                    ':last_name' => $lastName,
                    ':email' => $email,
                    ':user_id' => $user_id
                ]);

                // Update role-specific table for telephone (currently only Admin table in your schema)
                if ($user_role === 'admin') {
                    $stmt = $pdo->prepare("UPDATE Admins SET telephone = :telephone WHERE user_id = :user_id");
                    $stmt->execute([
                        ':telephone' => $telephone,
                        ':user_id' => $user_id
                    ]);
                }

                // Handle profile photo upload (existing logic)
                if (isset($_FILES['profile_photo']) && !empty($_FILES['profile_photo']['name'])) {
                    $uploadDir = __DIR__ . '/../Images/'; // Ensure this path is correct for your 'Images' folder
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true); // Create directory if it doesn't exist
                    }
                    $fileName = uniqid('photo_') . '_' . basename($_FILES['profile_photo']['name']);
                    $filePath = $uploadDir . $fileName;

                    // Validate file type
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    $fileMimeType = mime_content_type($_FILES['profile_photo']['tmp_name']);
                    if (!in_array($fileMimeType, $allowedTypes)) {
                        throw new Exception("Invalid file type. Only JPEG, PNG, and GIF images are allowed.");
                    }

                    // Check file size (e.g., max 5MB)
                    if ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) { // 5 MB
                        throw new Exception("File size exceeds 5MB limit.");
                    }

                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $filePath)) {
                        // Check if the user already has a profile photo entry
                        $stmt = $pdo->prepare("SELECT photo_path FROM ProfilePhotos WHERE user_id = :user_id");
                        $stmt->execute(['user_id' => $user_id]);
                        $existingPhoto = $stmt->fetchColumn();

                        if ($existingPhoto) {
                            // Update existing photo path
                            $stmt = $pdo->prepare("UPDATE ProfilePhotos SET photo_path = :photo_path WHERE user_id = :user_id");
                            $stmt->execute(['photo_path' => $fileName, 'user_id' => $user_id]);

                            // Delete the old physical photo file
                            if (file_exists($uploadDir . $existingPhoto)) {
                                unlink($uploadDir . $existingPhoto);
                            }
                        } else {
                            // Insert new photo path
                            $stmt = $pdo->prepare("INSERT INTO ProfilePhotos (user_id, photo_path) VALUES (:user_id, :photo_path)");
                            $stmt->execute(['user_id' => $user_id, 'photo_path' => $fileName]);
                        }
                        $profilePhoto = $fileName; // Update variable for current display
                    } else {
                        throw new Exception("Error uploading profile photo.");
                    }
                }

                $pdo->commit(); // Commit the transaction if all operations are successful
                $_SESSION['profile_success_message'] = "Personal details updated successfully!";
                // Update the $user array on the current page to reflect changes
                $user['first_name'] = $firstName;
                $user['last_name'] = $lastName;
                $user['email'] = $email;
                if ($user_role === 'admin') { // Only update if admin was processed
                    $user['telephone'] = $telephone;
                }
            } catch (Exception $e) {
                $pdo->rollBack(); // Rollback on any error
                $_SESSION['profile_error_message'] = "Error updating personal details: " . $e->getMessage();
                error_log("Profile personal details update error: " . $e->getMessage());
            }
        }
        header("Location: profile.php"); // Redirect to self to show messages and prevent re-submission
        exit();
    } elseif (isset($_POST['change_username_password'])) {
        // This handles both username AND password if the form is submitted
        $newUsername = trim(strtolower($_POST['new_username'] ?? ''));
        $currentPassword = $_POST['current_password'] ?? ''; // Used for both username & password verification
        $newPassword = $_POST['new_password'] ?? '';
        $confirmNewPassword = $_POST['confirm_new_password'] ?? '';

        // --- Username Change Logic ---
        $username_changed = false;
        if (!empty($newUsername) && $newUsername !== strtolower($user['username'])) {
            // New username provided and it's different from current
            if (strlen($newUsername) < 3 || strlen($newUsername) > 20) {
                $_SESSION['profile_error_message'] = "Username must be between 3 and 20 characters.";
            } elseif (!preg_match('/^[a-z0-9._]+$/', $newUsername)) {
                $_SESSION['profile_error_message'] = "Username can only contain lowercase letters, numbers, dots, and underscores.";
            } else {
                // Check if new username is already taken by *another* user
                try {
                    $stmt = $pdo->prepare("SELECT id FROM Users WHERE username = :new_username AND id != :user_id");
                    $stmt->execute(['new_username' => $newUsername, 'user_id' => $user_id]);
                    if ($stmt->fetch()) {
                        $_SESSION['profile_error_message'] = "This username is already taken by another user. Please choose a different one.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['profile_error_message'] = "Database error checking new username: " . $e->getMessage();
                    error_log("Profile username check error: " . $e->getMessage());
                }
            }
            if (empty($_SESSION['profile_error_message'])) {
                $username_changed = true; // Mark for update if validation passes
            }
        }

        // --- Password Change Logic ---
        $password_changed = false;
        if (!empty($newPassword) || !empty($confirmNewPassword)) {
            // New password fields are being used
            if (empty($currentPassword)) {
                $_SESSION['profile_error_message'] = "Current password is required to change password.";
            } elseif (strlen($newPassword) < 8) {
                $_SESSION['profile_error_message'] = "New password must be at least 8 characters long.";
            } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $newPassword)) {
                $_SESSION['profile_error_message'] = "New password must contain at least one uppercase letter, one lowercase letter, and one number.";
            } elseif ($newPassword !== $confirmNewPassword) {
                $_SESSION['profile_error_message'] = "New passwords do not match.";
            } else {
                $password_changed = true; // Mark for update if validation passes
            }
        }

        // --- Execute Changes if Valid and Current Password is Correct ---
        if ($username_changed || $password_changed) {
            // Verify current password once for both changes
            if (!password_verify($currentPassword, $user['password'])) {
                $_SESSION['profile_error_message'] = "Incorrect current password for username/password change.";
            }
        } else {
            // If neither username nor password fields were meaningfully changed, and no other error,
            // this form submission might be empty or just for current password check.
            if (empty($newUsername) && empty($newPassword) && empty($confirmNewPassword)) {
                $_SESSION['profile_error_message'] = "No changes requested for username or password.";
            }
        }


        if (empty($_SESSION['profile_error_message'])) {
            try {
                $pdo->beginTransaction();

                if ($username_changed) {
                    $stmt = $pdo->prepare("UPDATE Users SET username = :new_username WHERE id = :user_id");
                    $stmt->execute(['new_username' => $newUsername, 'user_id' => $user_id]);
                    $_SESSION['username'] = $newUsername; // Update session
                    $user['username'] = $newUsername; // Update local array
                    $_SESSION['profile_success_message'] = "Username updated successfully! ";
                }

                if ($password_changed) {
                    $hashedNewPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE Users SET password = :new_password WHERE id = :user_id");
                    $stmt->execute(['new_password' => $hashedNewPassword, 'user_id' => $user_id]);
                    $_SESSION['profile_success_message'] .= ($username_changed ? "And " : "") . "Password updated successfully!";
                    // Optionally, force re-login after password change for stronger security
                    // session_destroy(); header("Location: login.php?reason=password_changed"); exit();
                }

                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['profile_error_message'] = "Error updating username/password: " . $e->getMessage();
                error_log("Profile username/password update error: " . $e->getMessage());
            }
        }
        header("Location: profile.php"); // Redirect to self
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Classroom Management Portal</title>
    <link rel="stylesheet" href="../Global_CSS/profile.css">
    <style>
        /* General body and container styles */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            background-color: #3498DB;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        header h1 {
            margin: 0;
            font-size: 1.8em;
        }

        header nav a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            margin-left: 10px;
            /* Space out nav links */
        }

        header nav a:hover {
            background-color: rgb(5, 51, 91);
        }

        main {
            flex: 1;
            padding: 20px;
            max-width: 800px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }

        h2,
        h3 {
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .profile-photo-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3498DB;
        }

        form {
            display: grid;
            gap: 15px;
        }

        form label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        form input[type="text"],
        form input[type="email"],
        form input[type="password"],
        form input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }

        form button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
            width: auto;
        }

        form button:hover {
            background-color: #0056b3;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .password-requirements {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }

        .button-group {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .button-group .btn-secondary {
            background-color: rgb(5, 51, 91);
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .button-group .btn-secondary:hover {
            background-color: #CC4E49;
            /* Assuming this is the color you intended from the previous comment */
        }

        /* --- Modal Styles --- */
        .modal {
            display: none;
            /* Hidden by default */
            position: fixed;
            /* Stay in place */
            z-index: 1000;
            /* Sit on top */
            left: 0;
            top: 0;
            width: 100%;
            /* Full width */
            height: 100%;
            /* Full height */
            overflow: auto;
            /* Enable scroll if needed */
            background-color: rgba(0, 0, 0, 0.6);
            /* Black w/ opacity */
            justify-content: center;
            /* Center horizontally */
            align-items: center;
            /* Center vertically */
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            /* For older browsers / fallback */
            padding: 30px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            /* Needed for close button positioning */
        }

        .close-button {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
        }

        /* Adjust form styles inside modal if necessary */
        .modal-content form {
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <header>
        <h1>Profile</h1>
        <nav>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="../Admin_Dashboard/PHP/admin_dashboard.php">Back to Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'parent'): ?>
                <a href="../Parent_Dashboard/PHP/parent_dashboard.php">Back to Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'teacher'): ?>
                <a href="../Teacher_Dashboard/PHP/teacher_dashboard.php">Back to Dashboard</a>
            <?php elseif ($_SESSION['role'] === 'student'): ?>
                <a href="../Student_Dashboard/PHP/student_dashboard.php">Back to Dashboard</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
            <a href="#" id="openChangeDetailsModalBtn">Change Login Details</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
    <main>
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <div class="profile-photo-container">
            <?php if ($profilePhoto): ?>
                <img src="../Images/<?= htmlspecialchars($profilePhoto) ?>" alt="Profile Photo" class="profile-photo">
            <?php else: ?>
                <img src="https://placehold.co/150x150/e0e0e0/ffffff?text=No+Photo" alt="No Photo" class="profile-photo">
            <?php endif; ?>
        </div>

        <h3>Update Personal Details & Photo</h3>
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="update_personal_details" value="1">

            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>"
                required>

            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>"
                required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

            <?php if (isset($user['telephone'])): ?>
                <label for="telephone">Telephone:</label>
                <input type="text" id="telephone" name="telephone" value="<?= htmlspecialchars($user['telephone']) ?>">
            <?php endif; ?>

            <label for="profile_photo">Change Profile Photo:</label>
            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/gif">
            <small class="password-requirements">Max 5MB. Accepts JPEG, PNG, GIF.</small>

            <button type="submit">Save Personal Details & Photo</button>
        </form>

    </main>

    <div id="changeDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Change Username & Password</h3>
            <form action="" method="POST">
                <input type="hidden" name="change_username_password" value="1">

                <label for="new_username">New Username (optional):</label>
                <input type="text" id="new_username" name="new_username" value=""
                    placeholder="Leave blank to keep current">
                <div class="password-requirements">
                    Username must be 3-20 chars, lowercase letters, numbers, dots, and underscores.
                </div>

                <label for="new_password">New Password (optional):</label>
                <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current">
                <div class="password-requirements">
                    At least 8 characters with uppercase, lowercase, and a number.
                </div>

                <label for="confirm_new_password">Confirm New Password:</label>
                <input type="password" id="confirm_new_password" name="confirm_new_password"
                    placeholder="Confirm new password">
                <small class="password-requirements">Required if changing password.</small>

                <label for="current_password">Your Current Password (required for any change):</label>
                <input type="password" id="current_password" name="current_password"
                    placeholder="Enter your current password" required>

                <button type="submit">Update Username & Password</button>
            </form>
        </div>
    </div>

    <script>
        // Get the modal, the button that opens it, and the <span> element that closes it
        var modal = document.getElementById("changeDetailsModal");
        var btn = document.getElementById("openChangeDetailsModalBtn");
        var span = document.getElementsByClassName("close-button")[0];

        // When the user clicks the button, open the modal 
        btn.onclick = function() {
            modal.style.display = "flex"; // Use flex to center the modal content
        }

        // When the user clicks on <span> (x), close the modal
        span.onclick = function() {
            modal.style.display = "none";
        }

        // When the user clicks anywhere outside of the modal content, close it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        // Optional: If there was a validation error from the username/password form submission,
        // reopen the modal automatically when the page reloads.
        <?php if ($error_message && isset($_POST['change_username_password'])): ?>
            window.onload = function() {
                modal.style.display = "flex";
            };
        <?php endif; ?>
    </script>
</body>

</html>