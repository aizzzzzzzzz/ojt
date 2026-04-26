<?php

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/audit.php';

$cron_token = $auto_absent_cron_token ?? '';

if (empty($cron_token)) {
    $tokenFile = __DIR__ . '/../private/auto_absent_token.php';
    if (is_file($tokenFile)) {
        $loaded = include $tokenFile;
        if (is_string($loaded) && $loaded !== '') {
            $cron_token = $loaded;
        }
    }
}

$token = $_GET['token'] ?? '';

$is_cli = php_sapi_name() === 'cli';
$has_valid_token = !empty($cron_token) && !empty($token) && hash_equals($cron_token, $token);

if (!$is_cli && !$has_valid_token) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "Forbidden - Invalid or missing cron token\n";
    echo "Token provided: " . substr($token, 0, 10) . "...\n";
    echo "Expected token length: " . strlen($cron_token) . "\n";
    echo "Provided token length: " . strlen($token) . "\n";
    exit;
}

$today = date('Y-m-d');
$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$cutoff_time = new DateTime('14:00:00', new DateTimeZone('Asia/Manila'));
$is_past_2pm = $now >= $cutoff_time;

$force_run = isset($_GET['force']) && $_GET['force'] === '1';

if (!$is_past_2pm && !$force_run) {
    if ($is_cli) {
        echo "Not yet 2:00 PM. Current time: " . $now->format('H:i:s') . "\n";
        echo "Use ?force=1 to run manually.\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'skipped',
            'message' => 'Not yet 2:00 PM. Current time: ' . $now->format('H:i:s'),
            'suggestion' => 'Use ?force=1 to run manually'
        ]);
    }
    exit;
}

$results = [
    'total_students' => 0,
    'marked_absent' => 0,
    'already_present' => 0,
    'already_absent' => 0,
    'errors' => []
];

try {
    $stmt = $pdo->query("
        SELECT s.student_id, s.first_name, s.last_name, s.email,
               e.employer_id as supervisor_id, e.name as supervisor_name
        FROM students s
        LEFT JOIN employers e ON s.created_by = e.employer_id
        WHERE s.student_id IS NOT NULL
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results['total_students'] = count($students);

    foreach ($students as $student) {
        $student_id = $student['student_id'];
        $student_name = $student['first_name'] . ' ' . $student['last_name'];

        $stmt = $pdo->prepare("
            SELECT id, status, verified, time_in, time_out 
            FROM attendance 
            WHERE student_id = ? AND log_date = ?
        ");
        $stmt->execute([$student_id, $today]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendance) {
            $insert_stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, log_date, status, reason, verified)
                VALUES (?, ?, 'Absent', 'Auto-marked: No attendance by 2:00 PM', 0)
            ");
            $insert_stmt->execute([$student_id, $today]);
            $results['marked_absent']++;
            
            audit_log($pdo, 'Auto Mark Absent', "Student ID $student_id ($student_name) - no attendance by 2PM");
        } else {
            if ($attendance['status'] === 'Absent') {
                $results['already_absent']++;
            } else {
                $results['already_present']++;
            }
        }
    }

    $results['status'] = 'success';
    $results['message'] = "Auto-marking completed. {$results['marked_absent']} students marked absent (no attendance recorded).";

} catch (PDOException $e) {
    $results['status'] = 'error';
    $results['errors'][] = "Database error: " . $e->getMessage();
    error_log("Auto-mark absent cron error: " . $e->getMessage());
}

if ($is_cli) {
    echo "=== Auto-Mark Absent Cron Job ===\n";
    echo "Date: $today\n";
    echo "Time: " . $now->format('H:i:s') . "\n";
    echo "Status: {$results['status']}\n";
    echo "--------------------------------\n";
    echo "Total Students: {$results['total_students']}\n";
    echo "Marked Absent: {$results['marked_absent']}\n";
    echo "Already Present (Verified): {$results['already_present']}\n";
    echo "Already Absent: {$results['already_absent']}\n";
    echo "Unverified → Absent: {$results['unverified_changed']}\n";
    
    if (!empty($results['errors'])) {
        echo "\nErrors:\n";
        foreach ($results['errors'] as $error) {
            echo "- $error\n";
        }
    }
} else {
    header('Content-Type: application/json');
    echo json_encode($results, JSON_PRETTY_PRINT);
}
?>
