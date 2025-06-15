<?php
// C:\Users\livla\OneDrive\Desktop\Dissertation\Classroom Portal\src\UserRelationshipService.php

namespace App;

class UserRelationshipService
{
    private $dbConnection; // This would be your PDO object or DB wrapper

    public function __construct($dbConnection = null)
    {
        $this->dbConnection = $dbConnection;
    }

    /**
     * Links a parent user to a student user.
     * @param int $parentId The ID of the parent user.
     * @param int $studentId The ID of the student user.
     * @return bool True if linked successfully, false otherwise.
     */
    public function linkParentToStudent(int $parentId, int $studentId): bool
    {
        // Example dummy logic for now:
        if ($parentId <= 0 || $studentId <= 0 || $parentId === $studentId) {
            return false; // Basic validation
        }
        return true;
    }

    /**
     * Unlinks a parent user from a student user.
     * @param int $parentId The ID of the parent user.
     * @param int $studentId The ID of the student user.
     * @return bool True if unlinked successfully, false otherwise.
     */
    public function unlinkParentFromStudent(int $parentId, int $studentId): bool
    {
        // Example dummy logic:
        if ($parentId <= 0 || $studentId <= 0) {
            return false; // Basic validation
        }
        //Delete from 'parent_student_links' table
        return true;
    }

    /**
     * Checks if a specific student is linked to a specific parent.
     * @param int $parentId The ID of the parent.
     * @param int $studentId The ID of the student.
     * @return bool True if linked, false otherwise.
     */
    public function isStudentLinkedToParent(int $parentId, int $studentId): bool
    {
        // Example dummy logic (for unit tests, we'll mock this or use simple rules):
        // For now, let's say parent 1 is linked to student 101, and parent 2 to student 102
        $dummyLinks = [
            1 => [101],
            2 => [102, 103],
        ];
        return isset($dummyLinks[$parentId]) && in_array($studentId, $dummyLinks[$parentId]);
    }

    /**
     * Retrieves a list of student IDs linked to a parent.
     * @param int $parentId The ID of the parent user.
     * @return array An array of student IDs.
     */
    public function getStudentsLinkedToParent(int $parentId): array
    {

        $dummyLinks = [
            1 => [101],
            2 => [102, 103],
            3 => [], // Parent with no linked students
        ];
        return $dummyLinks[$parentId] ?? [];

    }
}
