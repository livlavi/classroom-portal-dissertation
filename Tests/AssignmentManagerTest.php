<?php
// C:\Users\livla\OneDrive\Desktop\Dissertation\Classroom Portal\tests\AssignmentManagerTest.php

use PHPUnit\Framework\TestCase;
use App\AssignmentManager;

class AssignmentManagerTest extends TestCase
{
    private $manager;

    protected function setUp(): void
    {
        $this->manager = new AssignmentManager();
    }

    // --- Test Cases for createAssignment ---

    public function testCanCreateAssignmentWithValidData()
    {
        $assignmentId = $this->manager->createAssignment(1, 'New Test Assignment', 'Description', '2025-12-31 10:00:00', 100);
        $this->assertIsInt($assignmentId);
        $this->assertGreaterThan(0, $assignmentId);
    }

    public function testCannotCreateAssignmentWithInvalidCourseId()
    {
        $this->assertFalse($this->manager->createAssignment(0, 'Invalid Course', 'Desc', '2025-12-31 10:00:00', 100));
    }

    public function testCannotCreateAssignmentWithEmptyTitle()
    {
        $this->assertFalse($this->manager->createAssignment(1, '', 'Desc', '2025-12-31 10:00:00', 100));
    }

    public function testCannotCreateAssignmentWithInvalidDueDate()
    {
        $this->assertFalse($this->manager->createAssignment(1, 'Title', 'Desc', 'invalid-date', 100));
        $this->assertFalse($this->manager->createAssignment(1, 'Title', 'Desc', '2025-12-31', 100)); // Missing time
    }

    public function testCannotCreateAssignmentWithZeroOrNegativeMaxPoints()
    {
        $this->assertFalse($this->manager->createAssignment(1, 'Title', 'Desc', '2025-12-31 10:00:00', 0));
        $this->assertFalse($this->manager->createAssignment(1, 'Title', 'Desc', '2025-12-31 10:00:00', -10));
    }

    // --- Test Cases for getAssignment ---

    public function testCanGetExistingAssignment()
    {
        $assignment = $this->manager->getAssignment(1001); // Based on dummy data
        $this->assertIsArray($assignment);
        $this->assertEquals(1001, $assignment['id']);
        $this->assertEquals('Math Homework 1', $assignment['title']);
    }

    public function testGetNonExistentAssignmentReturnsNull()
    {
        $assignment = $this->manager->getAssignment(99999);
        $this->assertNull($assignment);
    }

    // --- Test Cases for submitAssignment ---

    public function testCanSubmitAssignmentWithValidData()
    {
        $this->assertTrue($this->manager->submitAssignment(1001, 501, 'Student submission text.')); // 1001 exists
    }

    public function testCannotSubmitAssignmentWithInvalidAssignmentId()
    {
        $this->assertFalse($this->manager->submitAssignment(0, 501, 'Submission.'));
        $this->assertFalse($this->manager->submitAssignment(99999, 501, 'Submission.')); // Non-existent assignment
    }

    public function testCannotSubmitAssignmentWithInvalidStudentId()
    {
        $this->assertFalse($this->manager->submitAssignment(1001, 0, 'Submission.'));
    }

    public function testCannotSubmitAssignmentWithEmptyContent()
    {
        $this->assertFalse($this->manager->submitAssignment(1001, 501, ''));
        $this->assertFalse($this->manager->submitAssignment(1001, 501, '   '));
    }

    // --- Test Cases for gradeSubmission ---

    public function testCanGradeSubmissionWithValidData()
    {
        $this->assertTrue($this->manager->gradeSubmission(1, 85, 'Good work!')); // Assuming submission ID 1 exists
    }

    public function testCannotGradeSubmissionWithInvalidSubmissionId()
    {
        $this->assertFalse($this->manager->gradeSubmission(0, 85, 'Feedback'));
        $this->assertFalse($this->manager->gradeSubmission(99999, 85, 'Feedback')); // Non-existent submission
    }

    public function testCannotGradeSubmissionWithNegativeGrade()
    {
        $this->assertFalse($this->manager->gradeSubmission(1, -10, 'Negative grade'));
    }
}
