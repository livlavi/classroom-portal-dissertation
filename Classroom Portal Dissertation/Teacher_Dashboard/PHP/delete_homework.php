<?php
session_start();
require_once '../../Global_PHP/db.php';
$homework_id = $_POST['id'] ?? null;
if ($homework_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM Homework WHERE id = :id AND teacher_id = :teacher_id");
        $stmt->execute(['id' => $homework_id, 'teacher_id' => $_SESSION['user_id']]);
        echo "Homework deleted successfully!";
        header("Location: manage_homework.php");
        exit();
    } catch (PDOException $e) {
        error_log("Error deleting homework: " . $e->getMessage());
        echo "An error occurred while deleting homework.";
    }
} else {
    echo "Invalid homework ID.";
}
?>