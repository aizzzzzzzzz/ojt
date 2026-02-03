<?php
// includes/middleware.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../private/config.php';

// ----------------- Auth -----------------
function require_admin() {
    if (empty($_SESSION['admin_id'])) {
        header("Location: ../admin_login.php");
        exit;
    }
}

function require_employer() {
    if (empty($_SESSION['employer_id'])) {
        header("Location: ../employer_login.php");
        exit;
    }
}

// ----------------- CSRF -----------------
function check_csrf($token) {
    if (!isset($token) || !validate_csrf_token($token)) {
        write_audit_log('CSRF Validation Failed', null);
        die("CSRF token validation failed.");
    }
}

// ----------------- Audit Logging -----------------
function write_audit_log($action, $target = null) {
    global $pdo;

    // FIXED: Determine user type correctly
    $user_id = 0;
    $user_type = 'guest';
    
    // Check employer first, then admin
    if (isset($_SESSION['employer_id']) && !empty($_SESSION['employer_id'])) {
        $user_type = 'employer';
        $user_id = $_SESSION['employer_id'];
    } elseif (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
        $user_type = 'admin';
        $user_id = $_SESSION['admin_id'];
    }
    
    // Also check for student if needed
    if ($user_type === 'guest' && isset($_SESSION['student_id']) && !empty($_SESSION['student_id'])) {
        // If you track student actions in audit_logs
        $user_type = 'student';
        $user_id = $_SESSION['student_id'];
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    if ($user_id > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_type, user_id, action, target, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_type, $user_id, $action, $target, $ip]);
            return true;
        } catch (PDOException $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }
    return false;
}

// Add a manual audit log function for more control
function write_audit_log_manual($user_type, $user_id, $action, $target = null) {
    global $pdo;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_type, user_id, action, target, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_type, $user_id, $action, $target, $ip]);
        return true;
    } catch (PDOException $e) {
        error_log("Manual audit log error: " . $e->getMessage());
        return false;
    }
}
?>