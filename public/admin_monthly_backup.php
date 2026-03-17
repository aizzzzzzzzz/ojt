<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/backup.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_dashboard.php");
    exit;
}

check_csrf($_POST['csrf_token'] ?? '');

try {
    $result = run_monthly_backup_if_due(__DIR__ . '/../private/backups');
    if ($result['status'] === 'created') {
        write_audit_log('Monthly Database Backup', $result['state']['last_backup_file'] ?? null);
        $message = $result['message'];
        $_SESSION['monthly_backup_status'] = [
            'type' => 'success',
            'message' => $message
        ];
    } else {
        $_SESSION['monthly_backup_status'] = [
            'type' => 'info',
            'message' => $result['message']
        ];
    }
} catch (Throwable $e) {
    $_SESSION['monthly_backup_status'] = [
        'type' => 'error',
        'message' => $e->getMessage()
    ];
}

header("Location: admin_dashboard.php");
exit;
