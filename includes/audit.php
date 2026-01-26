<?php
function audit_log($pdo, $action, $target = null) {
    $user_type = isset($_SESSION['admin_id']) ? 'admin' : 'employer';
    $user_id   = $_SESSION['admin_id'] ?? $_SESSION['employer_id'] ?? 0;
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_type, user_id, action, target, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_type, $user_id, $action, $target, $ip]);
}
