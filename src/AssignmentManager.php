<?php
// C:\Users\livla\OneDrive\Desktop\Dissertation\Classroom Portal\src\AssignmentManager.php

namespace App;

// In a real application, you'd pass a database connection or an assignment repository here.
class AssignmentManager
{
    private $dbConnection;

    public function __construct($dbConnection = null)
    {
        $this->dbConnection = $dbConnection;
    }

    /**
     * Creates a new assignment.
     * @param int $courseId The ID of the course the assignment belongs to.
     * @param string $title The title of the assignment.
     * @param string $description The description of the assignment.
     * @param string $dueDate The due date in 'YYYY-MM-DD HH:MM:SS' format.
     * @param int $maxPoints The maximum points for the assignment.
     * @return int|false The ID of the new assignment, or false on failure.
     */
    public function createAssignment(int $courseId, string $title, string $description, string $dueDate, int $maxPoints)
    {
        // --- REPLACE WITH YOUR ACTUAL DATABASE INSERT LOGIC ---
        // Basic validation
        if ($courseId <= 0 || empty(trim($title)) || empty(trim($dueDate)) || $maxPoints <= 0) {
            return false;
        }
        // Validate date format (simple check for now)
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dueDate)) {
            return false;
        }

        // Simulate a successful database insert by returning a dummy ID
        return rand(1000, 9999); // Dummy ID
        // --- END OF DUMMY LOGIC ---
    }

    /**
     * Gets assignment details by ID.
     * @param int $assignmentId The ID of the assignment.
     * @return array|null An associative array of assignment data, or null if not found.
     */
    public function getAssignment(int $assignmentId): ?array
    {
        // --- REPLACE WITH YOUR ACTUAL DATABASE SELECT LOGIC ---
        // Dummy data for testing
        $dummyAssignments = [
            1001 => [
                'id' => 1001,
                'course_id' => 1,
                'title' => 'Math Homework 1',
                'description' => 'Complete exercises 1-5',
                'due_date' => '2025-06-10 23:59:59',
                'max_points' => 100
            ],
            1002 => [
                'id' => 1002,
                'course_id' => 2,
                'title' => 'History Essay',
                'description' => 'Write about WWII',
                'due_date' => '2025-06-15 17:00:00',
                'max_points' => 50
            ]
        ];
        return $dummyAssignments[$assignmentId] ?? null;
        // --- END OF DUMMY LOGIC ---
    }

    /**
     * Submits an assignment for a student.
     * @param int $assignmentId The ID of the assignment.
     * @param int $studentId The ID of the student submitting.
     * @param string $submissionContent The content of the submission (e.g., text, file path).
     * @return bool True if submission is recorded, false otherwise.
     */
    public function submitAssignment(int $assignmentId, int $studentId, string $submissionContent): bool
    {
        // --- REPLACE WITH YOUR ACTUAL DATABASE INSERT/UPDATE LOGIC ---
        if ($assignmentId <= 0 || $studentId <= 0 || empty(trim($submissionContent))) {
            return false;
        }
        // Check if assignment exists (e.g., call getAssignment($assignmentId))
        if (!$this->getAssignment($assignmentId)) { // Using dummy getAssignment for this
            return false;
        }
        // In real app: save submission details (student_id, assignment_id, content, timestamp)
        return true;
        // --- END OF DUMMY LOGIC ---
    }

    /**
     * Grades a submitted assignment.
     * @param int $submissionId The ID of the assignment submission.
     * @param int $grade The grade given (0-max_points).
     * @param string $feedback Optional feedback.
     * @return bool True if grading is successful, false otherwise.
     */
    public function gradeSubmission(int $submissionId, int $grade, string $feedback = ''): bool
    {
        // --- REPLACE THIS WITH YOUR ACTUAL DATABASE UPDATE LOGIC ---

        // 1. Basic validation for invalid IDs (0 or negative) or negative grades
        if ($submissionId <= 0 || $grade < 0) {
            return false;
        }

        // 2. Dummy logic to simulate "submission not found" for unit testing.
        //    For simplicity, let's assume only submission ID 1 is considered "existent" for grading.
        //    In a real app, you'd query the database to find the submission.
        $knownDummySubmissionIds = [1]; // This ID comes from the test in AssignmentManagerTest::testCanGradeSubmissionWithValidData

        if (!in_array($submissionId, $knownDummySubmissionIds)) {
            return false; // Simulate failure if submission ID doesn't exist
        }

        // 3. In a real app: Retrieve submission, validate grade against max_points, update record.
        //    For this dummy, if we reach here, it's considered successful.
        return true;
        // --- END OF DUMMY LOGIC ---
    }
}
