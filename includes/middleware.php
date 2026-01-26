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

    $user_id   = $_SESSION['admin_id'] ?? $_SESSION['employer_id'] ?? null;
    $user_type = isset($_SESSION['admin_id']) ? 'admin' : (isset($_SESSION['employer_id']) ? 'employer' : 'guest');
    $ip        = $_SERVER['REMOTE_ADDR'] ?? null;

    if ($user_id) {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_type, user_id, action, target, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_type, $user_id, $action, $target, $ip]);
    }
}
?>
