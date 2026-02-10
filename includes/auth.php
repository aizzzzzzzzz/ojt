<?php
// Authentication module for student dashboard
function authenticate_student() {
    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== "student") {
        header("Location: student_login.php");
        exit;
    }
    return (int)$_SESSION['student_id'];
}
?>
