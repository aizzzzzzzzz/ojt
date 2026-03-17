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
    $backupPath = create_database_backup(__DIR__ . '/../private/backups');
    if (($GLOBALS['last_backup_method'] ?? '') === 'php') {
        $_SESSION['monthly_backup_status'] = [
            'type' => 'warning',
            'message' => 'Backup used PHP fallback (mysqldump unavailable).'
        ];
    }
    write_audit_log('Database Backup', basename($backupPath));

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($backupPath) . '"');
    header('Content-Length: ' . filesize($backupPath));
    readfile($backupPath);
    exit;
} catch (Throwable $e) {
    $_SESSION['backup_error'] = $e->getMessage();
    header("Location: admin_dashboard.php");
    exit;
}
