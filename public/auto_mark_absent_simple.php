<?php
/**
 * Auto-Mark Absent Cron - Simple Version
 * For InfinityFree hosting
 * 
 * URL: http://your-domain.com/public/auto_mark_absent_simple.php?token=TOKEN_HERE
 */

session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/audit.php';

// Hardcoded token for simplicity
define('AUTO_ABSENT_TOKEN', 'auto_absent_a7f3c9e2b1d4f8a6');

$token = $_GET['token'] ?? '';

// Check token
$is_cli = php_sapi_name() === 'cli';
$has_valid_token = !empty($token) && hash_equals(AUTO_ABSENT_TOKEN, $token);

if (!$is_cli && !$has_valid_token) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "Forbidden - Invalid or missing cron token\n";
    echo "Token provided: " . htmlspecialchars(substr($token, 0, 15)) . "...\n";
    echo "Expected: auto_absent_a7f3c9e2b1d4f8a6\n";
    exit;
}

$today = date('Y-m-d');
$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$cutoff_time = new DateTime('19:00:00', new DateTimeZone('Asia/Manila'));
$is_past_7pm = $now >= $cutoff_time;

// For testing, allow force run
$force_run = isset($_GET['force']) && $_GET['force'] === '1';

if (!$is_past_7pm && !$force_run) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'skipped',
        'message' => 'Not yet 7:00 PM. Current time: ' . $now->format('H:i:s'),
        'suggestion' => 'Use ?force=1 to run manually'
    ]);
    exit;
}

$results = [
    'total_students' => 0,
    'marked_absent' => 0,
    'already_present' => 0,
    'already_absent' => 0,
    'unverified_changed' => 0,
    'errors' => []
];

try {
    // Get all students
    $stmt = $pdo->query("
        SELECT s.student_id, s.first_name, s.last_name
        FROM students s
        WHERE s.student_id IS NOT NULL
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results['total_students'] = count($students);

    foreach ($students as $student) {
        $student_id = $student['student_id'];
        $student_name = $student['first_name'] . ' ' . $student['last_name'];

        // Check if attendance record exists for today
        $stmt = $pdo->prepare("
            SELECT id, status, verified 
            FROM attendance 
            WHERE student_id = ? AND log_date = ?
        ");
        $stmt->execute([$student_id, $today]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendance) {
            // No attendance record - mark as absent
            $insert_stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, log_date, status, reason, verified)
                VALUES (?, ?, 'Absent', 'Auto-marked: No attendance by 7:00 PM', 0)
            ");
            $insert_stmt->execute([$student_id, $today]);
            $results['marked_absent']++;
            
            audit_log($pdo, 'Auto Mark Absent', "Student ID $student_id - no attendance by 7PM");
        } else {
            // Attendance exists
            if ($attendance['status'] === 'Absent') {
                $results['already_absent']++;
            } elseif ($attendance['status'] === 'Present' && $attendance['verified'] == 0) {
                // Present but unverified - change to absent
                $update_stmt = $pdo->prepare("
                    UPDATE attendance 
                    SET status = 'Absent', 
                        reason = COALESCE(reason, 'Auto-marked: Unverified by 7:00 PM')
                    WHERE student_id = ? AND log_date = ?
                ");
                $update_stmt->execute([$student_id, $today]);
                $results['unverified_changed']++;
                
                audit_log($pdo, 'Auto Mark Absent', "Student ID $student_id - unverified changed to absent");
            } elseif ($attendance['status'] === 'Present' && $attendance['verified'] == 1) {
                $results['already_present']++;
            }
        }
    }

    $results['status'] = 'success';
    $results['message'] = "Auto-marking completed. {$results['marked_absent']} students marked absent, {$results['unverified_changed']} unverified changed to absent.";

} catch (PDOException $e) {
    $results['status'] = 'error';
    $results['errors'][] = "Database error: " . $e->getMessage();
    error_log("Auto-mark absent error: " . $e->getMessage());
}

// Output
header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
?>
