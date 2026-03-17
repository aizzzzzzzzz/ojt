<?php
require_once __DIR__ . '/../includes/backup.php';

try {
    $backupPath = create_database_backup(__DIR__ . '/../private/backups');
    echo "Backup created: {$backupPath}" . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Backup failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
