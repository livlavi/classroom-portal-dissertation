<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../Global_PHP/auth.php';
require_once '../../Global_PHP/db.php';
require_once '../../Global_PHP/getting_informations.php';

requireRole(['teacher']); // Ensure only teachers can access

// Fetch teacher details
$teacherDetails = fetchUserDetails($pdo, $_SESSION['user_id'], 'teacher');
$studentsByYear = fetchStudentsByYear($pdo);

$firstName = $teacherDetails['first_name'] ?? '';
$lastName = $teacherDetails['last_name'] ?? '';
$profilePhoto = $teacherDetails['photo_path'] ?? null;

$_SESSION['first_name'] = $firstName;
$_SESSION['last_name'] = $lastName;

$yearRange = range(1, 6);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $due_date = $_POST['due_date'];
    $subject = $_POST['subject'];
    $selected_students = $_POST['students'] ?? []; // Only students

    // Handle file upload
    $file_path = null;
    if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../Documents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES['attachment']['name']);
        $file_path = $upload_dir . $file_name;
        move_uploaded_file($_FILES['attachment']['tmp_name'], $file_path);
    }

    try {
        $pdo->beginTransaction();

        // Insert into Assessments
        $stmt = $pdo->prepare(
            "INSERT INTO Assessments (teacher_id, title, description, due_date, attachment, subject)
             VALUES (:teacher_id, :title, :description, :due_date, :attachment, :subject)"
        );
        $stmt->execute([
            'teacher_id' => $_SESSION['user_id'],
            'title'      => $title,
            'description' => $description,
            'due_date'   => $due_date,
            'attachment' => $file_path,
            'subject'    => $subject
        ]);
        $assessment_id = $pdo->lastInsertId();

        // Link to students
        if (!empty($selected_students)) {
            $stmt = $pdo->prepare(
                "INSERT INTO Assessment_Students (assessment_id, student_id)
                 VALUES (:assessment_id, :student_id)"
            );
            foreach ($selected_students as $sid) {
                $stmt->execute([':assessment_id' => $assessment_id, ':student_id' => $sid]);
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Assessment added successfully!";
        header("Location: teacher_dashboard.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error adding assessment: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred while adding the assessment.";
        header("Location: teacher_dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en-GB">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Assessment</title>
    <link rel="stylesheet" href="../CSS/teacher_dashboard.css">
    <style>
    .checkbox-container {
        max-height: 200px;
        overflow-y: auto;
        padding: 5px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .checkbox-container label {
        display: block;
        margin: 5px 0;
    }

    .alert {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    </style>
</head>

<body>
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
    <?php unset($_SESSION['success_message']);
    endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
    <?php unset($_SESSION['error_message']);
    endif; ?>

    <div class="sidebar">
        <h2>Teacher Dashboard</h2>
        <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
        <div class="admin-profile">
            <?php if ($profilePhoto): ?>
            <img src="../../Images/<?= htmlspecialchars($profilePhoto) ?>" class="profile-photo">
            <?php else: ?>
            <div class="placeholder-photo"><?= substr($firstName, 0, 1) . substr($lastName, 0, 1) ?></div>
            <?php endif; ?>
            <p><?= htmlspecialchars("$firstName $lastName") ?></p>
        </div>
        <a href="teacher_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <button id="dark-mode-toggle">Dark Mode</button>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <input type="text" id="search-bar" placeholder="Search...">
            <ul id="search-results"></ul>
            <div class="profile-actions">
                <a href="../../Global_PHP/profile.php"><button><i class="fas fa-user"></i> Profile</button></a>
                <a href="../../Global_PHP/logout.php"><button><i class="fas fa-sign-out-alt"></i> Logout</button></a>
            </div>
        </div>
        <div class="dashboard-grid">
            <div class="section" id="manage-assessments">
                <h3>Add Assessment</h3>
                <form action="add_assessment.php" method="POST" enctype="multipart/form-data">
                    <label>Title:</label><input type="text" name="title" required><br><br>
                    <label>Subject:</label>
                    <select name="subject" required>
                        <option value="">Select subject</option>
                        <option value="Maths">Maths</option>
                        <option value="English">English</option>
                        <option value="Geography">Geography</option>
                        <option value="Science">Science</option>
                        <option value="History">History</option>
                    </select><br><br>
                    <label>Description:</label><textarea name="description" rows="4" required></textarea><br><br>
                    <label>Due Date:</label><input type="date" name="due_date" required><br><br>
                    <label>Attachment (Optional):</label><input type="file" name="attachment"><br><br>

                    <h3>Students</h3>
                    <?php foreach ($yearRange as $year): ?>
                    <div class="year-group">
                        <h4>Year <?= $year ?></h4>
                        <div class="checkbox-container">
                            <?php foreach ($studentsByYear[$year] ?? [] as $st): ?>
                            <label><input type="checkbox" name="students[]" value="<?= $st['id'] ?>">
                                <?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <button type="submit">Add Assessment</button>
                </form>
            </div>
        </div>
    </div>

    <script src="../JavaScript/teacher_dashboard.js"></script>
</body>

</html>