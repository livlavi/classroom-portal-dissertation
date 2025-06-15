<?php
require_once 'db.php';

try {
    // Fetch all parents with child_full_name
    $stmt = $pdo->query("SELECT id AS parent_id, child_full_name FROM Parents WHERE child_full_name IS NOT NULL");
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($parents as $parent) {
        $childName = trim($parent['child_full_name']);
        $parentId = $parent['parent_id'];

        if (empty($childName)) continue;

        // Split full name into first and last
        $parts = explode(' ', $childName, 2);
        if (count($parts) < 2) continue;
        [$firstName, $lastName] = $parts;

        // Find matching student user ID
        $studentStmt = $pdo->prepare("
            SELECT u.id 
            FROM Users u 
            WHERE u.first_name = :first AND u.last_name = :last AND u.role = 'student'
        ");
        $studentStmt->execute(['first' => $firstName, 'last' => $lastName]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            $studentId = $student['id'];

            // Check if link already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Parent_Student WHERE parent_id = :pid AND student_id = :sid");
            $checkStmt->execute(['pid' => $parentId, 'sid' => $studentId]);
            if ($checkStmt->fetchColumn() == 0) {
                // Insert into Parent_Student
                $insertStmt = $pdo->prepare("INSERT INTO Parent_Student (parent_id, student_id) VALUES (:pid, :sid)");
                $insertStmt->execute(['pid' => $parentId, 'sid' => $studentId]);
                echo "Linked parent ID $parentId with student ID $studentId<br>";
            }
        }
    }

    echo "Sync completed!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
