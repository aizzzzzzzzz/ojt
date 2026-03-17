<?php

require_once __DIR__ . '/../private/config.php';

function find_mysqldump_binary() {
    $envPath = getenv('MYSQLDUMP_PATH');
    if (!empty($envPath) && file_exists($envPath)) {
        return $envPath;
    }

    $candidates = [
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        'C:\\Program Files\\MariaDB 10.6\\bin\\mysqldump.exe',
        'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
        'mysqldump'
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === 'mysqldump' || file_exists($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function create_database_backup($outputDir = null) {
    global $host, $db, $user, $pass;
    $GLOBALS['last_backup_method'] = null;

    $outputDir = $outputDir ?: (__DIR__ . '/../private/backups');
    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            throw new RuntimeException('Failed to create backup directory.');
        }
    }

    $dumpBinary = find_mysqldump_binary();

    $timestamp = date('Ymd_His');
    $filename = "ojt_backup_{$timestamp}.sql";
    $outputPath = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!$dumpBinary || !function_exists('exec')) {
        $GLOBALS['last_backup_method'] = 'php';
        return create_database_backup_php($outputPath);
    }

    $args = [
        "--host={$host}",
        "--user={$user}",
        "--databases",
        $db,
        "--single-transaction",
        "--routines",
        "--events",
        "--triggers"
    ];

    if ($pass !== '') {
        $args[] = "--password={$pass}";
    }

    $escapedBinary = escapeshellarg($dumpBinary);
    $escapedArgs = array_map('escapeshellarg', $args);
    $command = $escapedBinary . ' ' . implode(' ', $escapedArgs) . ' > ' . escapeshellarg($outputPath);

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if ($exitCode !== 0 || !file_exists($outputPath) || filesize($outputPath) === 0) {
        @unlink($outputPath);
        $GLOBALS['last_backup_method'] = 'php';
        return create_database_backup_php($outputPath);
    }

    $GLOBALS['last_backup_method'] = 'mysqldump';
    return $outputPath;
}

function create_database_backup_php($outputPath) {
    global $pdo, $db;

    @set_time_limit(0);

    $fh = @fopen($outputPath, 'wb');
    if (!$fh) {
        throw new RuntimeException('Failed to create backup file.');
    }

    $write = function ($line) use ($fh) {
        if (@fwrite($fh, $line) === false) {
            throw new RuntimeException('Failed to write backup file.');
        }
    };

    $write("-- PHP backup for database `{$db}`\n");
    $write("-- Generated at " . date('Y-m-d H:i:s') . "\n\n");
    $write("SET NAMES utf8mb4;\n");
    $write("SET time_zone = '+00:00';\n");
    $write("SET foreign_key_checks = 0;\n\n");

    $tables = [];
    $tableStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($row = $tableStmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $createSql = $createStmt['Create Table'] ?? null;
        if ($createSql) {
            $write("DROP TABLE IF EXISTS `{$table}`;\n");
            $write($createSql . ";\n\n");
        }

        $columns = [];
        $colStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($col['Field'])) {
                continue;
            }
            $columns[] = '`' . str_replace('`', '``', $col['Field']) . '`';
        }

        if (!$columns) {
            $write("\n");
            continue;
        }

        $columnList = implode(',', $columns);
        $dataStmt = $pdo->query("SELECT * FROM `{$table}`");

        $chunk = [];
        $chunkSize = 200;
        $wroteRows = false;

        while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = "NULL";
                } else {
                    $values[] = $pdo->quote($value);
                }
            }
            $chunk[] = '(' . implode(',', $values) . ')';
            if (count($chunk) >= $chunkSize) {
                $write("INSERT INTO `{$table}` ({$columnList}) VALUES\n" . implode(",\n", $chunk) . ";\n");
                $chunk = [];
                $wroteRows = true;
            }
        }

        if ($chunk) {
            $write("INSERT INTO `{$table}` ({$columnList}) VALUES\n" . implode(",\n", $chunk) . ";\n");
            $wroteRows = true;
        }

        if (!$wroteRows) {
            $write("\n");
        }
    }

    $views = [];
    $viewStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    while ($row = $viewStmt->fetch(PDO::FETCH_NUM)) {
        $views[] = $row[0];
    }
    foreach ($views as $view) {
        $createStmt = $pdo->query("SHOW CREATE VIEW `{$view}`")->fetch(PDO::FETCH_ASSOC);
        $createSql = $createStmt['Create View'] ?? null;
        if ($createSql) {
            $write("DROP VIEW IF EXISTS `{$view}`;\n");
            $write($createSql . ";\n\n");
        }
    }

    $write("SET foreign_key_checks = 1;\n");

    fclose($fh);

    if (!file_exists($outputPath) || filesize($outputPath) === 0) {
        @unlink($outputPath);
        throw new RuntimeException('Backup failed using PHP fallback.');
    }

    return $outputPath;
}

function monthly_backup_state_path($outputDir) {
    return rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.monthly_backup.json';
}

function read_monthly_backup_state($path) {
    if (!is_file($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function write_monthly_backup_state($path, array $data) {
    $tmp = $path . '.tmp';
    $payload = json_encode($data, JSON_PRETTY_PRINT);
    if (@file_put_contents($tmp, $payload) === false) {
        throw new RuntimeException('Failed to write monthly backup state.');
    }
    @rename($tmp, $path);
}

function run_monthly_backup_if_due($outputDir = null, $force = false) {
    $outputDir = $outputDir ?: (__DIR__ . '/../private/backups');
    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            throw new RuntimeException('Failed to create backup directory.');
        }
    }

    $statePath = monthly_backup_state_path($outputDir);
    $state = read_monthly_backup_state($statePath);
    $currentMonth = date('Y-m');

    if (!$force && ($state['last_month'] ?? '') === $currentMonth) {
        return [
            'status' => 'skipped',
            'message' => "Monthly backup already created for {$currentMonth}.",
            'state' => $state
        ];
    }

    $backupPath = create_database_backup($outputDir);
    $state = [
        'last_month' => $currentMonth,
        'last_backup_at' => date('Y-m-d H:i:s'),
        'last_backup_file' => basename($backupPath)
    ];
    write_monthly_backup_state($statePath, $state);

    return [
        'status' => 'created',
        'message' => 'Monthly backup created: ' . basename($backupPath),
        'backup_path' => $backupPath,
        'state' => $state
    ];
}
