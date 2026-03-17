<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/backup.php';

$token = $_GET['token'] ?? '';
if (empty($backup_cron_token) || !hash_equals($backup_cron_token, $token)) {
    log_security_event('Monthly Backup Cron', 'Invalid token', 'WARN');
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "Forbidden\n";
    exit;
}

try {
    $result = run_monthly_backup_if_due(__DIR__ . '/../private/backups');
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $result['status'],
        'message' => $result['message'],
        'last_backup_file' => $result['state']['last_backup_file'] ?? null,
        'last_backup_at' => $result['state']['last_backup_at'] ?? null
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
