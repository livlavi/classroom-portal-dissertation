<?php
// C:\Users\livla\OneDrive\Desktop\Dissertation\Classroom Portal\tests\UserRelationshipServiceTest.php

use PHPUnit\Framework\TestCase;
use App\UserRelationshipService; // Import the service class you're testing

class UserRelationshipServiceTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        // Arrange: Create a fresh instance of your service for each test
        // For unit tests, we don't need a real DB connection here.
        // If UserRelationshipService required a PDO, we'd mock it here for unit tests.
        $this->service = new UserRelationshipService();
    }

    // Test Cases for Linking
    public function testParentCanBeLinkedToAStudent()
    {
        // Act: Call the method you want to test
        $result = $this->service->linkParentToStudent(1, 101);

        // Assert: Check the outcome
        $this->assertTrue($result, 'Linking parent 1 to student 101 should succeed.');
    }

    public function testCannotLinkParentToNonExistentUsers()
    {
        // Act & Assert for invalid IDs (assuming your service validates this)
        $this->assertFalse($this->service->linkParentToStudent(0, 101), 'Linking with parent ID 0 should fail.');
        $this->assertFalse($this->service->linkParentToStudent(1, 0), 'Linking with student ID 0 should fail.');
    }

    public function testCannotLinkParentToSelf()
    {
        // Act & Assert
        $this->assertFalse($this->service->linkParentToStudent(50, 50), 'Parent cannot link to self.');
    }

    // Test Cases for Checking Links
    public function testIsStudentLinkedToParentReturnsTrueForExistingLink()
    {
        // Arrange: Assuming the dummy logic in UserRelationshipService has parent 1 linked to student 101
        // In a real scenario, you might mock the DB query to return true here
        $this->assertTrue($this->service->isStudentLinkedToParent(1, 101), 'Student 101 should be linked to parent 1.');
    }

    public function testIsStudentLinkedToParentReturnsFalseForNonExistingLink()
    {
        // Arrange: Assuming parent 1 is NOT linked to student 102 in dummy logic
        $this->assertFalse($this->service->isStudentLinkedToParent(1, 102), 'Student 102 should NOT be linked to parent 1.');
    }

    public function testIsStudentLinkedToParentReturnsFalseForInvalidIds()
    {
        $this->assertFalse($this->service->isStudentLinkedToParent(0, 101), 'Check with parent ID 0 should be false.');
        $this->assertFalse($this->service->isStudentLinkedToParent(1, 0), 'Check with student ID 0 should be false.');
        $this->assertFalse($this->service->isStudentLinkedToParent(999, 999), 'Check with non-existent IDs should be false.');
    }

    // Test Cases for Unlinking
    public function testParentCanBeUnlinkedFromAStudent()
    {
        // Act: Call the unlink method
        $result = $this->service->unlinkParentFromStudent(1, 101);

        // Assert:
        $this->assertTrue($result, 'Unlinking parent 1 from student 101 should succeed.');
        // After unlinking, if we were using a real DB, we'd check the DB.
        // With dummy logic, we can't test "after unlinking" state easily without mocks.
    }

    public function testCannotUnlinkWithInvalidIds()
    {
        $this->assertFalse($this->service->unlinkParentFromStudent(0, 101), 'Unlinking with parent ID 0 should fail.');
        $this->assertFalse($this->service->unlinkParentFromStudent(1, 0), 'Unlinking with student ID 0 should fail.');
    }

    // Test Cases for Getting Linked Students
    public function testGetStudentsLinkedToParentReturnsCorrectStudents()
    {
        // Arrange: Based on dummy data in UserRelationshipService
        $expectedStudents = [102, 103];
        $actualStudents = $this->service->getStudentsLinkedToParent(2);
        $this->assertEquals($expectedStudents, $actualStudents, 'Parent 2 should be linked to students 102 and 103.');
    }

    public function testGetStudentsLinkedToParentReturnsEmptyArrayIfNoStudentsLinked()
    {
        // Arrange: Based on dummy data, assuming parent 3 has no linked students
        $this->assertEmpty($this->service->getStudentsLinkedToParent(3), 'Parent 3 should have no linked students.');
    }

    public function testGetStudentsLinkedToParentReturnsEmptyArrayForNonExistentParent()
    {
        $this->assertEmpty($this->service->getStudentsLinkedToParent(999), 'Non-existent parent should have no linked students.');
    }
}
