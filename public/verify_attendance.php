<?php
session_start();
include __DIR__ . '/../private/config.php';

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== "employer") {
    header("Location: employer_login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? null;
    $log_date   = $_POST['log_date'] ?? null;

    if ($student_id && $log_date) {

        // Fetch student name
        $student_stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) AS name FROM students WHERE student_id = ?");
        $student_stmt->execute([$student_id]);
        $student = $student_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            UPDATE attendance
            SET verified = 1
            WHERE student_id = ? AND log_date = ?
        ");
        $stmt->execute([$student_id, $log_date]);

        $_SESSION['verified_student'] = $student;
    }
}

header("Location: supervisor_dashboard.php");
exit;
?>
