<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$today = date('Y-m-d');

try {
    if ($userRole === 'student') {
        $stmt = $pdo->prepare("
            SELECT a.id, a.title, a.description, a.due_date, a.attachment, a.subject, 
                   t.first_name AS teacher_first_name, t.last_name AS teacher_last_name
            FROM Assessments a
            JOIN Assessment_Students as_s ON a.id = as_s.assessment_id
            JOIN Users t ON a.teacher_id = t.id
            WHERE as_s.student_id = :user_id AND a.due_date >= :today
            ORDER BY a.due_date DESC
        ");
        $stmt->execute(['user_id' => $userId, 'today' => $today]);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT assessment_id FROM Submitted_Assessments WHERE student_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $submittedAssessmentIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'assessment_id');
    } elseif ($userRole === 'parent') {
        $stmt = $pdo->prepare("SELECT child_user_id FROM Parents WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        $childId = $child['child_user_id'] ?? null;

        if ($childId) {
            $stmt = $pdo->prepare("
                SELECT a.id, a.title, a.description, a.due_date, a.attachment, a.subject, 
                       t.first_name AS teacher_first_name, t.last_name AS teacher_last_name
                FROM Assessments a
                JOIN Assessment_Students as_s ON a.id = as_s.assessment_id
                JOIN Users t ON a.teacher_id = t.id
                WHERE as_s.student_id = :child_id AND a.due_date >= :today
                ORDER BY a.due_date DESC
            ");
            $stmt->execute(['child_id' => $childId, 'today' => $today]);
            $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $assessments = [];
        }
    } else {
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching assessments: " . $e->getMessage());
    $assessments = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View Assessments</title>
    <link rel="stylesheet" href="../CSS/view_assessments.css">
    <style>
    .dashboard-btn {
        background-color: #3498db;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        cursor: pointer;
        margin-bottom: 20px;
    }

    .dashboard-btn:hover {
        background-color: #2980b9;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    th,
    td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    th {
        background-color: #007bff;
        color: white;
    }

    tr:nth-child(even) {
        background-color: #f2f2f2;
    }

    tr:hover {
        background-color: #ddd;
    }

    .no-assessments {
        padding: 20px;
        color: #666;
    }

    .open-btn,
    .view-reviewed-btn {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 3px;
        cursor: pointer;
        margin-left: 5px;
    }

    .open-btn:hover,
    .view-reviewed-btn:hover {
        background-color: #218838;
    }

    .assessment-details {
        margin-top: 20px;
        padding: 15px;
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 5px;
        display: none;
    }

    .assessment-details.active {
        display: block;
    }

    .assessment-details textarea {
        width: 100%;
        height: 150px;
        margin-bottom: 10px;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        resize: vertical;
    }

    .assessment-details .disabled {
        background-color: #e9ecef;
        color: #6c757d;
        cursor: not-allowed;
        opacity: 0.7;
    }

    .submitted-message {
        color: #28a745;
        font-style: italic;
    }

    .alert {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
        display: none;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    body.dark-mode {
        background-color: #333;
        color: #fff;
    }

    body.dark-mode table {
        background-color: #444;
        color: #fff;
    }

    body.dark-mode th {
        background-color: #0056b3;
    }

    body.dark-mode tr:nth-child(even) {
        background-color: #555;
    }

    body.dark-mode tr:hover {
        background-color: #666;
    }

    body.dark-mode .no-assessments {
        color: #ccc;
    }

    body.dark-mode .open-btn,
    body.dark-mode .view-reviewed-btn {
        background-color: #28a745;
        color: white;
    }

    body.dark-mode .open-btn:hover,
    body.dark-mode .view-reviewed-btn:hover {
        background-color: #218838;
    }

    body.dark-mode .assessment-details {
        background-color: #444;
        border-color: #555;
    }

    body.dark-mode .assessment-details textarea {
        background-color: #555;
        border-color: #666;
        color: #fff;
    }

    body.dark-mode .assessment-details .disabled {
        background-color: #6c757d;
        color: #fff;
    }

    body.dark-mode .submitted-message {
        color: #28a745;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: #fff;
        margin: 10% auto;
        padding: 20px;
        border-radius: 8px;
        width: 70%;
        max-width: 800px;
        position: relative;
    }

    .close-modal {
        position: absolute;
        top: 8px;
        right: 12px;
        color: #aaa;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
    }

    .close-modal:hover {
        color: #333;
    }

    textarea {
        width: 100%;
        height: 150px;
        margin-bottom: 10px;
    }
    </style>
</head>

<body>
    <h1>Assessments</h1>


    <button class="dashboard-btn" onclick="window.location.href='../Student_Dashboard/PHP/student_dashboard.php'">Return
        to Dashboard</button>

    <?php if ($userRole === 'student'): ?> <?php if (empty($assessments)): ?> <p>No pending assessments available.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Subject</th>
                <th>Description</th>
                <th>Due Date</th>
                <th>Attachment</th>
                <th>Teacher</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($assessments as $assessment): ?>
            <tr>
                <td><?= htmlspecialchars($assessment['title']) ?></td>
                <td><?= htmlspecialchars($assessment['subject']) ?></td>
                <td><?= htmlspecialchars($assessment['description']) ?></td>
                <td><?= htmlspecialchars($assessment['due_date']) ?></td>
                <td>
                    <?php if ($assessment['attachment']): ?>
                    <a href="<?= htmlspecialchars($assessment['attachment']) ?>" target="_blank">Download</a>
                    <?php else: ?>No attachment<?php endif; ?>
                </td>
                <td><?= htmlspecialchars($assessment['teacher_first_name'] . ' ' . $assessment['teacher_last_name']) ?>
                </td>
                <td>
                    <?php if (!in_array($assessment['id'], $submittedAssessmentIds)): ?>
                    <button class="open-btn"
                        onclick="openModal(<?= htmlspecialchars(json_encode($assessment)) ?>)">Open</button>
                    <?php else: ?>
                    <span class="submitted-message">Submitted</span>
                    <button class="view-reviewed-btn"
                        onclick="location.href='view_reviewed_assessments.php?assessment_id=<?= $assessment['id'] ?>'">
                        View Reviewed
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php else: ?>
    <h2>Child's Assessments</h2>
    <!-- Parent table (similar to above, without actions) -->
    <?php endif; ?>

    <!-- Modal -->
    <div id="assessmentModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle"></h2>
            <p><strong>Subject:</strong> <span id="modalSubject"></span></p>
            <p><strong>Description:</strong> <span id="modalDescription"></span></p>
            <p><strong>Due Date:</strong> <span id="modalDueDate"></span></p>
            <p><strong>Attachment:</strong> <span id="modalAttachment"></span></p>

            <form id="modalForm" action="submit_assessment.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="assessment_id" id="modalAssessmentId">
                <label>Your Solution:</label>
                <textarea name="submission_content" required></textarea>
                <br>
                <label>Upload File:</label>
                <input type="file" name="submission_attachment">
                <br><br>
                <button type="submit">Submit Assessment</button>
            </form>
        </div>
    </div>

    <script>
    const modal = document.getElementById('assessmentModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubject = document.getElementById('modalSubject');
    const modalDescription = document.getElementById('modalDescription');
    const modalDueDate = document.getElementById('modalDueDate');
    const modalAttachment = document.getElementById('modalAttachment');
    const modalAssessmentId = document.getElementById('modalAssessmentId');

    function openModal(assessment) {
        modalTitle.textContent = assessment.title;
        modalSubject.textContent = assessment.subject;
        modalDescription.textContent = assessment.description;
        modalDueDate.textContent = assessment.due_date;
        modalAssessmentId.value = assessment.id;

        if (assessment.attachment) {
            modalAttachment.innerHTML = `<a href="${assessment.attachment}" target="_blank">Download</a>`;
        } else {
            modalAttachment.textContent = "No attachment";
        }

        modal.style.display = "block";
    }

    function closeModal() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    };
    </script>
</body>

</html>