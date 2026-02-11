<?php
session_start();
require '../private/config.php';

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== "employer") {
    header("Location: employer_login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = (int) $_POST['student_id'];

    $stmt1 = $pdo->prepare("DELETE FROM evaluations WHERE student_id = ?");
    $stmt1->execute([$student_id]);

    $stmt2 = $pdo->prepare("DELETE FROM attendance WHERE student_id = ?");
    $stmt2->execute([$student_id]);

    $stmt3 = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt3->execute([$student_id]);

    $_SESSION['success'] = "Student deleted successfully.";
    header("Location: supervisor_dashboard.php");
    exit;
} else {
    header("Location: supervisor_dashboard.php");
    exit;
}
