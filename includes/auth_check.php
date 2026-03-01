<?php
function require_role($required_role) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $role = $_SESSION['role'] ?? null;
    $isAuthenticated =
        ($role === 'student' && !empty($_SESSION['student_id'])) ||
        ($role === 'employer' && !empty($_SESSION['employer_id'])) ||
        ($role === 'admin' && !empty($_SESSION['admin_id']));

    if (!$isAuthenticated) {
        header("Location: ../index.php");
        exit;
    }

    if ($role !== $required_role) {
        if ($role === 'student') {
            header("Location: student_dashboard.php");
        } elseif ($role === 'employer') {
            header("Location: supervisor_dashboard.php");
        } elseif ($role === 'admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: ../index.php");
        }
        exit;
    }
}

function get_current_session_user() {
    $role = $_SESSION['role'] ?? null;

    if ($role === 'student' && !empty($_SESSION['student_id'])) {
        $id = (int) $_SESSION['student_id'];
    } elseif ($role === 'employer' && !empty($_SESSION['employer_id'])) {
        $id = (int) $_SESSION['employer_id'];
    } elseif ($role === 'admin' && !empty($_SESSION['admin_id'])) {
        $id = (int) $_SESSION['admin_id'];
    } else {
        return null;
    }

    return [
        'id' => $id,
        'role' => $role,
    ];
}
?>
