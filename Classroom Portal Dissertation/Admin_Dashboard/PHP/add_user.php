<?php
// Start the session to manage user login state.
session_start();
// Include the database connection file. This file is assumed to contain
// the PDO database connection object ($pdo).
require_once '../../Global_PHP/db.php';

// --- Admin Authentication Check (Currently commented out) ---
// This block is typically used to ensure that only authenticated administrators
// can access this page. If uncommented, it would redirect non-admin users
// or unauthenticated users to the login page.
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header("Location: login.php");
//     exit(); // Always exit after a header redirect to prevent further script execution.
// }

// Initialize variables to store error and success messages.
$error = '';
$success = '';

/**
 * Function to generate a unique, random alphanumeric code for user invitations.
 * It ensures the generated code does not already exist in the UniqueCodes table.
 *
 * @param PDO $pdo The PDO database connection object.
 * @return string The unique uppercase alphanumeric code.
 */
function generateUniqueCode($pdo)
{
    do {
        // Generate a random string using MD5 hash of a unique ID, then take the first 8 characters.
        // uniqid(rand(), true) creates a more unique string by adding entropy from rand().
        $code = substr(md5(uniqid(rand(), true)), 0, 8);

        // Prepare a SQL statement to check if the generated code already exists in the database.
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM UniqueCodes WHERE code = :code");
        // Execute the statement, binding the generated code to the :code placeholder.
        $stmt->execute(['code' => $code]);
        // Fetch the count. If count > 0, the code already exists.
        $exists = $stmt->fetchColumn() > 0;
        // Continue looping if the code already exists, ensuring uniqueness.
    } while ($exists);

    // Return the unique code converted to uppercase for consistency.
    return strtoupper($code);
}

// Check if the form has been submitted using the POST method.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and trim input data from the form to prevent common injection attacks
    // and remove leading/trailing whitespace.
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role']; // Role is a dropdown, likely safe, but good practice to validate later.
    $address = trim($_POST['address']);

    // Initialize role-specific fields, checking if they are set before trimming.
    // This prevents PHP notices if a field is not present for a given role.
    $childFullName = isset($_POST['child_full_name']) ? trim($_POST['child_full_name']) : '';
    $parentType = isset($_POST['parent_type']) ? $_POST['parent_type'] : '';
    $yearOfStudy = isset($_POST['year_of_study']) ? (int)$_POST['year_of_study'] : 0; // Cast to integer for student year.
    $subjectTaught = isset($_POST['subject_taught']) ? trim($_POST['subject_taught']) : '';

    // --- Input Validation ---
    // Check if all essential fields are filled.
    if (empty($firstName) || empty($lastName) || empty($email) || empty($role) || empty($address)) {
        $error = "All required fields must be filled.";
    }
    // Validate email format.
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    // Validate that the selected role is one of the allowed roles.
    elseif (!in_array($role, ['teacher', 'parent', 'student'])) {
        $error = "Invalid role selected.";
    }
    // Role-specific validation checks.
    elseif ($role === 'parent' && empty($childFullName)) {
        $error = "Child's full name is required for parents.";
    }
    // Year of study must be between 1 and 6 for students.
    elseif ($role === 'student' && ($yearOfStudy < 1 || $yearOfStudy > 6)) {
        $error = "Year of study must be between 1 and 6 for students.";
    }
    // Subject taught is required for teachers.
    elseif ($role === 'teacher' && empty($subjectTaught)) {
        $error = "Subject taught is required for teachers.";
    }
    // If all initial validations pass, proceed to database checks and insertion.
    else {
        try {
            // Check if the email already exists in the UniqueCodes table.
            // This prevents generating multiple unique codes for the same email.
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM UniqueCodes WHERE email = :email");
            $stmt->execute(['email' => $email]);

            if ($stmt->fetchColumn() > 0) {
                $error = "A unique code for this email already exists.";
            } else {
                // Check if the email is already registered in the main Users table.
                // This prevents creating an invitation for an already active user.
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE email = :email");
                $stmt->execute(['email' => $email]);

                if ($stmt->fetchColumn() > 0) {
                    $error = "This email is already registered in the system.";
                } else {
                    // Generate a unique code using the defined function.
                    $uniqueCode = generateUniqueCode($pdo);

                    // Insert the generated unique code and user details into the UniqueCodes table.
                    // This table acts as an invitation/pre-registration record.
                    $stmt = $pdo->prepare("INSERT INTO UniqueCodes (code, role, email, first_name, last_name) 
                                         VALUES (:code, :role, :email, :first_name, :last_name)");
                    $stmt->execute([
                        'code' => $uniqueCode,
                        'role' => $role,
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName
                    ]);

                    // Set a success message. The unique code is NOT displayed here
                    // for security/privacy reasons, but is made available in the 'Manage Unique Codes' section.
                    $success = "User invitation created successfully!<br>
                                The unique code has been generated for <strong>$firstName $lastName</strong> ($email) with the role of <strong>" . ucfirst($role) . "</strong>.<br><br>
                                You can view and manage all unique codes in the <strong>'Manage Unique Codes'</strong> section of the admin dashboard.";

                    // Clear the $_POST array to clear the form fields after successful submission.
                    $_POST = array();
                }
            }
        } catch (PDOException $e) {
            // Log any database-related errors for debugging purposes.
            error_log("Database error: " . $e->getMessage());
            // Display a generic error message to the user, optionally including the specific error for development.
            $error = "An error occurred. Please try again later. Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en-GB">

<head>
    <meta charset="UTF-8"> <!-- Specifies the character encoding for the document. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Ensures responsive behavior for different devices. -->
    <title>Add User - Admin Panel</title> <!-- Sets the title that appears in the browser tab. -->
    <style>
        /* Basic styling for the body, centers content, sets background. */
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            /* Centers the content horizontally. */
            padding: 20px;
            background-color: #f5f5f5;
        }

        /* Container for the main form content, adds padding, rounded corners, and shadow. */
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        /* Styles for main headings. */
        h1,
        h2 {
            color: #333;
            margin-bottom: 20px;
        }

        /* Grouping for form elements, provides consistent vertical spacing. */
        .form-group {
            margin-bottom: 20px;
        }

        /* Styles for form labels. */
        label {
            display: block;
            /* Makes labels appear on their own line. */
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        /* Styles for required field indicators (asterisk). */
        .required {
            color: #dc3545;
            /* Red color for visual emphasis. */
        }

        /* Universal styling for text inputs, email inputs, number inputs, select dropdowns, and textareas. */
        input[type="text"],
        input[type="email"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            /* Makes inputs take full width of their container. */
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
            /* Includes padding and border in the element's total width/height. */
        }

        /* Specific styling for textareas, allows vertical resizing. */
        textarea {
            height: 80px;
            resize: vertical;
        }

        /* Styles for primary action buttons (e.g., Generate Unique Code). */
        button {
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            /* Indicates the element is clickable. */
            font-size: 16px;
        }

        /* Hover effect for primary buttons. */
        button:hover {
            background-color: #0056b3;
        }

        /* Styles for secondary buttons (e.g., Clear Form). */
        .btn-secondary {
            background-color: #6c757d;
            margin-left: 10px;
        }

        /* Hover effect for secondary buttons. */
        .btn-secondary:hover {
            background-color: #545b62;
        }

        /* Styles for error message display box. */
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        /* Styles for success message display box, with relative positioning for close button. */
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px 40px 12px 12px;
            /* Extra padding on right for close button. */
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            position: relative;
            /* Allows absolute positioning of child elements. */
        }

        /* Styles for the close button within the success message. */
        .success .close-btn {
            position: absolute;
            /* Positions button relative to the success message box. */
            top: 8px;
            right: 12px;
            background: none;
            border: none;
            font-size: 20px;
            font-weight: bold;
            color: #155724;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Hover effect for the close button. */
        .success .close-btn:hover {
            color: #0c3d14;
            background-color: rgba(21, 87, 36, 0.1);
            border-radius: 50%;
            /* Makes hover effect circular. */
        }

        /* Base styles for role-specific fields, initially hidden. */
        .role-specific {
            display: none;
            /* Hidden by default, shown via JavaScript. */
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
        }

        /* Styling for the GDPR notice box. */
        .gdpr-notice {
            background-color: #e3f2fd;
            border: 1px solid #2196f3;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        /* Heading for GDPR notice. */
        .gdpr-notice h3 {
            margin-top: 0;
            color: #1976d2;
        }

        /* Styling for checkbox groups, uses flexbox for alignment. */
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            /* Aligns checkbox and text at the top. */
            gap: 8px;
            /* Space between checkbox and label. */
            margin-top: 10px;
        }

        /* Ensures checkbox input is not full width. */
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        /* Resets label margins within checkbox group. */
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
            font-size: 14px;
        }

        /* Grouping for buttons, uses flexbox for horizontal alignment. */
        .button-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        /* Styling for information boxes. */
        .info-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        /* Heading for information box. */
        .info-box h4 {
            margin-top: 0;
            color: #856404;
        }

        /* Styling for a specific link to manage unique codes. */
        .manage-codes-link {
            background-color: #17a2b8;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            /* Allows margins and padding. */
            margin-top: 15px;
            font-size: 14px;
        }

        /* Hover effects for the manage codes link. */
        .manage-codes-link:hover {
            background-color: #138496;
            color: white;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <!-- Link to navigate back to the admin dashboard. -->
    <div style="text-align: left; margin-top: 30px;">
        <a href="admin_dashboard.php" style="color: #007bff; text-decoration: none;">← Back to Admin Dashboard</a>
    </div>
    <br>
    <!-- Main container for the "Create User Invitation" form. -->
    <div class="container">
        <h1>Create User Invitation</h1>

        <!-- Information Box explaining the invitation process. -->
        <div class="info-box">
            <h4>How it works:</h4>
            <p>1. Fill in the user's details below to generate a unique registration code<br>
                2. The system will create an invitation code (not a full account yet)<br>
                3. Give the unique code to the person so they can complete their own registration<br>
                4. They will use the same email address you enter here when they register</p>
            <p><strong>Note:</strong> After creating the invitation, you can view and manage all unique codes in the
                <strong>"Manage Unique Codes"</strong> section.
            </p>
        </div>

        <!-- GDPR / Data Protection Notice to inform the administrator. -->
        <div class="gdpr-notice">
            <h3>Data Protection Notice</h3>
            <p>By creating a user invitation, you confirm that you have the necessary permissions to process their
                personal data.
                The information collected will be used solely for educational administration purposes and will be stored
                securely in accordance with GDPR and data protection regulations.</p>
            <p><strong>Data collected includes:</strong> Name, email, address, role-specific identifiers, and any
                additional
                information necessary for system access and educational administration.</p>
        </div>

        <!-- PHP block to display error messages if any occurred during form submission. -->
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- PHP block to display success messages after a successful user invitation creation. -->
        <?php if ($success): ?>
            <div class="success" id="successMessage">
                <?php echo $success; ?>
                <!-- Link to the page where unique codes can be managed. -->
                <a href="manage_unique_codes.php" class="manage-codes-link">View All Unique Codes</a>
                <!-- Close button for the success message. -->
                <button type="button" class="close-btn" onclick="closeSuccessMessage()" title="Close">&times;</button>
            </div>
        <?php endif; ?>

        <!-- The main form for creating user invitations. -->
        <form method="POST" action="" id="addUserForm">
            <!-- Form group for First Name input. -->
            <div class="form-group">
                <label for="first_name">First Name: <span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" required
                    value="<?php echo ($success == '' && isset($_POST['first_name'])) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
            </div>

            <!-- Form group for Last Name input. -->
            <div class="form-group">
                <label for="last_name">Last Name: <span class="required">*</span></label>
                <input type="text" id="last_name" name="last_name" required
                    value="<?php echo ($success == '' && isset($_POST['last_name'])) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
            </div>

            <!-- Form group for School Email input. -->
            <div class="form-group">
                <label for="email">School Email: <span class="required">*</span></label>
                <input type="email" id="email" name="email" required
                    value="<?php echo ($success == '' && isset($_POST['email'])) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <small style="color: #666;">This email must match exactly when the user registers</small>
            </div>

            <!-- Form group for Address textarea. -->
            <div class="form-group">
                <label for="address">Address: <span class="required">*</span></label>
                <textarea id="address" name="address" required
                    placeholder="Enter full address"><?php echo ($success == '' && isset($_POST['address'])) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
            </div>

            <!-- Form group for Role selection dropdown. -->
            <div class="form-group">
                <label for="role">Role: <span class="required">*</span></label>
                <select id="role" name="role" required onchange="toggleRoleFields()">
                    <option value="">Select a role...</option>
                    <option value="teacher"
                        <?php echo ($success == '' && isset($_POST['role']) && $_POST['role'] === 'teacher') ? 'selected' : ''; ?>>
                        Teacher
                    </option>
                    <option value="parent"
                        <?php echo ($success == '' && isset($_POST['role']) && $_POST['role'] === 'parent') ? 'selected' : ''; ?>>
                        Parent
                    </option>
                    <option value="student"
                        <?php echo ($success == '' && isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : ''; ?>>
                        Student
                    </option>
                </select>
            </div>

            <!-- Parent-specific fields, dynamically displayed/hidden by JavaScript. -->
            <div id="parent-fields" class="role-specific">
                <h3>Parent Information</h3>
                <div class="form-group">
                    <label for="child_full_name">Child's Full Name: <span class="required">*</span></label>
                    <input type="text" id="child_full_name" name="child_full_name"
                        value="<?php echo ($success == '' && isset($_POST['child_full_name'])) ? htmlspecialchars($_POST['child_full_name']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="parent_type">Parent Type:</label>
                    <select id="parent_type" name="parent_type">
                        <option value="mother"
                            <?php echo ($success == '' && isset($_POST['parent_type']) && $_POST['parent_type'] === 'mother') ? 'selected' : ''; ?>>
                            Mother</option>
                        <option value="father"
                            <?php echo ($success == '' && isset($_POST['parent_type']) && $_POST['parent_type'] === 'father') ? 'selected' : ''; ?>>
                            Father</option>
                        <option value="guardian"
                            <?php echo ($success == '' && isset($_POST['parent_type']) && $_POST['parent_type'] === 'guardian') ? 'selected' : ''; ?>>
                            Guardian</option>
                    </select>
                </div>
            </div>

            <!-- Student-specific fields, dynamically displayed/hidden by JavaScript. -->
            <div id="student-fields" class="role-specific">
                <h3>Student Information</h3>
                <div class="form-group">
                    <label for="year_of_study">Year of Study: <span class="required">*</span></label>
                    <select id="year_of_study" name="year_of_study">
                        <option value="">Select year...</option>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>"
                                <?php echo ($success == '' && isset($_POST['year_of_study']) && $_POST['year_of_study'] == $i) ? 'selected' : ''; ?>>
                                Year <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <!-- Teacher-specific fields, dynamically displayed/hidden by JavaScript. -->
            <div id="teacher-fields" class="role-specific">
                <h3>Teacher Information</h3>
                <div class="form-group">
                    <label for="subject_taught">Subject Taught: <span class="required">*</span></label>
                    <input type="text" id="subject_taught" name="subject_taught"
                        value="<?php echo ($success == '' && isset($_POST['subject_taught'])) ? htmlspecialchars($_POST['subject_taught']) : ''; ?>">
                </div>
            </div>

            <!-- GDPR Consent checkbox and label. This checkbox ensures admin acknowledges
                 data processing responsibilities. -->
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="gdpr_consent" name="gdpr_consent" required>
                    <label for="gdpr_consent">
                        I confirm that I have the legal authority to collect and process this personal data,
                        and that the data subject has been informed of their rights under GDPR. <span
                            class="required">*</span>
                    </label>
                </div>
            </div>

            <!-- Button group for form submission and actions. -->
            <div class="button-group">
                <button type="submit">Generate Unique Code</button>
                <button type="button" class="btn-secondary" onclick="resetForm()">Clear Form</button>
                <a href="manage_unique_codes.php" class="manage-codes-link">Manage All Codes</a>
            </div>
        </form>
    </div>

    <script>
        /**
         * Toggles the visibility of role-specific input fields based on the
         * selected user role in the dropdown.
         */
        function toggleRoleFields() {
            const role = document.getElementById('role').value; // Get the selected role.
            const parentFields = document.getElementById('parent-fields');
            const studentFields = document.getElementById('student-fields');
            const teacherFields = document.getElementById('teacher-fields');

            // Hide all role-specific fields first.
            parentFields.style.display = 'none';
            studentFields.style.display = 'none';
            teacherFields.style.display = 'none';

            // Show the relevant fields based on the selected role.
            if (role === 'parent') {
                parentFields.style.display = 'block';
            } else if (role === 'student') {
                studentFields.style.display = 'block';
            } else if (role === 'teacher') {
                teacherFields.style.display = 'block';
            }
        }

        /**
         * Resets the form fields and hides any active success messages.
         */
        function resetForm() {
            document.getElementById('addUserForm').reset(); // Reset all form elements to their initial state.
            toggleRoleFields(); // Call to hide any role-specific fields that might have been shown.

            // Hide the success message if it's currently visible.
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                successMessage.style.display = 'none';
            }
        }

        /**
         * Hides the success message box when its close button is clicked.
         */
        function closeSuccessMessage() {
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                successMessage.style.display = 'none';
            }
        }

        // Event listener to ensure JavaScript functions run after the DOM is fully loaded.
        document.addEventListener('DOMContentLoaded', function() {
            // Call toggleRoleFields on page load to correctly display/hide fields
            // if the form was reloaded (e.g., due to a PHP validation error).
            toggleRoleFields();

            // This PHP block executes if a success message was just displayed.
            // In that case, the PHP logic already clears $_POST, so we just ensure
            // role fields are hidden if they were shown before the post.
            <?php if ($success != ''): ?>
                setTimeout(function() {
                    toggleRoleFields();
                }, 100); // A small delay might be useful if elements are still rendering.
            <?php endif; ?>
        });
    </script>
</body>

</html>