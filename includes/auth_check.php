<?php
function require_role($required_role) {
    session_start();

    if (!isset($_SESSION['user_id'])) {
        header("Location: ../public/index.php");
        exit;
    }

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        if (isset($_SESSION['role'])) {
            if ($_SESSION['role'] === 'student') {
                header("Location: ../public/student_dashboard.php");
            } elseif ($_SESSION['role'] === 'employer') {
                header("Location: ../public/supervisor_dashboard.php
");
            } elseif ($_SESSION['role'] === 'admin') {
                header("Location: ../public/admin_dashboard.php");
            } else {
                header("Location: ../public/index.php");
            }
        } else {
            header("Location: ../public/index.php");
        }
        exit;
    }
}

function get_current_session_user() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'role' => $_SESSION['role']
    ];
}
?>

