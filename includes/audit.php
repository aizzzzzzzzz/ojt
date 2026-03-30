<?php

/**
 * Log supervisor/employer/admin actions to audit_logs table
 * Used for tracking all actions by supervisors, employers, and admins
 */
function audit_log($pdo, $action, $target = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $user_type = isset($_SESSION['admin_id']) ? 'admin' : 'employer';
    $user_id   = $_SESSION['admin_id'] ?? $_SESSION['employer_id'] ?? 0;
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

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

/**
 * Log student actions to activity_logs table
 * Used for tracking all student activities
 * Note: Uses global $pdo, no need to pass it
 */
function log_activity($action, $target = null) {
    global $pdo;
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $user_id = $_SESSION['student_id'] ?? 0;
    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if ($user_id > 0 && isset($pdo)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, role, action, target, ip_address, created_at)
                VALUES (?, 'student', ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $action, $target, $ip]);
            return true;
        } catch (PDOException $e) {
            error_log("Activity log error: " . $e->getMessage());
            return false;
        }
    }
    return false;
}

/**
 * Manual activity log function - allows specifying user ID
 */
function write_activity_log_manual($user_id, $action, $target = null) {
    global $pdo;

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, role, action, target, ip_address, created_at)
            VALUES (?, 'student', ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $action, $target, $ip]);
        return true;
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}
