<?php
session_start();

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== "employer") {
    header("Location: employer_login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "student_db");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = (int) $_POST['student_id'];

    $conn->query("DELETE FROM evaluations WHERE student_id = $student_id");

    $conn->query("DELETE FROM attendance WHERE student_id = $student_id");

    $conn->query("DELETE FROM students WHERE student_id = $student_id");

    $_SESSION['success'] = "Student deleted successfully.";
    header("Location: supervisor_dashboard.php");
    exit;
} else {
    header("Location: supervisor_dashboard.php");
    exit;
}
