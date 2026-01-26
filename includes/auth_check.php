<?php
// Central authentication and role-based access control (RBAC) check
function require_role($required_role) {
    session_start();

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../public/index.php");
        exit;
    }

    // Check if user has the required role
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        // Redirect based on current role or lack thereof
        if (isset($_SESSION['role'])) {
            if ($_SESSION['role'] === 'student') {
                header("Location: ../public/student_dashboard.php");
            } elseif ($_SESSION['role'] === 'employer') {
                header("Location: ../public/employer_dashboard.php");
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

// Function to get current user info
function get_current_user() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'role' => $_SESSION['role']
    ];
}
?>
