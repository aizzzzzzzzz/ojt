<?php

function audit_log($pdo, $action, $target = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $user_type = isset($_SESSION['admin_id']) ? 'admin' : 'employer';
    $user_id   = $_SESSION['admin_id'] ?? $_SESSION['employer_id'] ?? 0;
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_type, user_id, action, target, ip_address, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_type, $user_id, $action, $target, $ip]);
}

function log_activity($pdo, $action, $target = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $user_id = $_SESSION['student_id'] ?? 0;
    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if ($user_id > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, role, action, target, ip_address, created_at)
            VALUES (?, 'student', ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $action, $target, $ip]);
    }
}

function write_audit_log_manual($user_type, $user_id, $action, $target = null) {
    global $pdo;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
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

function write_activity_log_manual($pdo, $user_id, $action, $target = null) {
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
