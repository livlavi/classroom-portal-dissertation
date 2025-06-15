<?php
require_once 'db.php';

/**
 * Fetch all users grouped by role.
 */
function fetchUsers($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, first_name, last_name, role FROM Users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $groupedUsers = [
            'teacher' => [],
            'student' => [],
            'parent' => [],
            'admin' => [],
        ];
        foreach ($users as $user) {
            $groupedUsers[$user['role']][] = $user;
        }
        return $groupedUsers;
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch students grouped by year of study.
 */
function fetchStudentsByYear($pdo) {
    try {
        $stmt = $pdo->query("SELECT u.id, u.first_name, u.last_name, s.year_of_study 
                             FROM Users u 
                             JOIN Students s ON u.id = s.user_id 
                             WHERE u.role = 'student' 
                             ORDER BY s.year_of_study");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $groupedStudents = [];
        foreach ($students as $student) {
            $year = $student['year_of_study'];
            if (!isset($groupedStudents[$year])) {
                $groupedStudents[$year] = [];
            }
            $groupedStudents[$year][] = $student;
        }
        return $groupedStudents;
    } catch (PDOException $e) {
        error_log("Error fetching students by year: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch parents grouped by their child's year of study.
 */
function fetchParentsByChildYear($pdo) {
    try {
        $stmt = $pdo->query("SELECT u.id, u.first_name, u.last_name, p.child_full_name, s.year_of_study
                             FROM Users u
                             JOIN Parents p ON u.id = p.user_id
                             LEFT JOIN Students s ON CONCAT(s.first_name, ' ', s.last_name) = p.child_full_name
                             WHERE u.role = 'parent'
                             ORDER BY s.year_of_study");
        $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $groupedParents = [];
        foreach ($parents as $parent) {
            $year = $parent['year_of_study'] ?: 'Unknown';
            if (!isset($groupedParents[$year])) {
                $groupedParents[$year] = [];
            }
            $groupedParents[$year][] = $parent;
        }
        return $groupedParents;
    } catch (PDOException $e) {
        error_log("Error fetching parents by child year: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch recent notifications.
 */
function fetchNotifications($pdo) {
    try {
        $stmt = $pdo->query("SELECT message FROM Notifications ORDER BY created_at DESC LIMIT 5");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch admin details.
 */
function fetchAdminDetails($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, p.photo_path 
                               FROM Users u
                               LEFT JOIN ProfilePhotos p ON u.id = p.user_id
                               WHERE u.id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching admin details: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch user counts grouped by role.
 */
function fetchAnalyticsData($pdo) {
    try {
        $stmt = $pdo->query("SELECT role, COUNT(*) AS count FROM Users GROUP BY role");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching analytics data: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch detailed data for a single user by ID and role.
 */
function fetchUserDetails($pdo, $user_id, $role) {
    try {
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM Users WHERE id = :user_id AND role = :role");
        $stmt->execute(['user_id' => $user_id, 'role' => $role]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $data = [
            'success' => true,
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name']
        ];

        switch ($role) {
            case 'student':
                $stmt = $pdo->prepare("SELECT student_number, year_of_study 
                                       FROM Students 
                                       WHERE user_id = :user_id");
                $stmt->execute(['user_id' => $user_id]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($student) {
                    $data['student_number'] = $student['student_number'];
                    $data['year_of_study'] = $student['year_of_study'];
                }
                break;

            case 'teacher':
                $stmt = $pdo->prepare("SELECT teacher_number, subject_taught 
                                       FROM Teachers 
                                       WHERE user_id = :user_id");
                $stmt->execute(['user_id' => $user_id]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($teacher) {
                    $data['teacher_number'] = $teacher['teacher_number'];
                    $data['subject_taught'] = $teacher['subject_taught'];
                }
                break;

            case 'parent':
                $stmt = $pdo->prepare("SELECT child_full_name 
                                       FROM Parents 
                                       WHERE user_id = :user_id");
                $stmt->execute(['user_id' => $user_id]);
                $parent = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($parent) {
                    $data['child_full_name'] = $parent['child_full_name'];
                }
                break;

            case 'admin':
                // No additional fields for admins
                break;

            default:
                return ['success' => false, 'message' => 'Invalid role'];
        }

        return $data;
    } catch (PDOException $e) {
        error_log("Error fetching user details: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}
?>