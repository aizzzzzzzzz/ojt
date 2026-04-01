<?php
/**
 * Auto-Mark Absent Script
 * 
 * This script automatically marks students as absent if:
 * 1. They have no attendance record for the day AND it's past 7:00 PM
 * 2. OR they have an attendance record that is NOT verified by the supervisor
 * 
 * Run this via cron job daily at 7:00 PM or manually access it
 */

session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/audit.php';

// Only allow admin or employer to run this
$is_admin = isset($_SESSION['admin_id']) && $_SESSION['role'] === 'admin';
$is_employer = isset($_SESSION['employer_id']) && $_SESSION['role'] === 'employer';

// Also allow CLI execution for cron jobs
$is_cli = php_sapi_name() === 'cli';

if (!$is_admin && !$is_employer && !$is_cli) {
    http_response_code(403);
    die("Access denied. This script can only be run by admins, employers, or via CLI.");
}

$today = date('Y-m-d');
$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$cutoff_time = new DateTime('19:00:00', new DateTimeZone('Asia/Manila'));

// Check if current time is past 7 PM
$is_past_7pm = $now >= $cutoff_time;

// For testing purposes, allow manual override via GET parameter
$force_run = isset($_GET['force']) && $_GET['force'] === '1';

$results = [
    'total_students' => 0,
    'marked_absent' => 0,
    'already_present' => 0,
    'already_absent' => 0,
    'unverified_present' => 0,
    'errors' => []
];

try {
    // Get all students
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

        // Check if attendance record exists for today
        $stmt = $pdo->prepare("
            SELECT id, status, verified, time_in, time_out 
            FROM attendance 
            WHERE student_id = ? AND log_date = ?
        ");
        $stmt->execute([$student_id, $today]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendance) {
            // No attendance record exists
            if ($is_past_7pm || $force_run) {
                // Mark as absent
                $insert_stmt = $pdo->prepare("
                    INSERT INTO attendance (student_id, log_date, status, reason, verified)
                    VALUES (?, ?, 'Absent', 'Auto-marked: No attendance recorded by 7:00 PM', 0)
                ");
                $insert_stmt->execute([$student_id, $today]);

                $results['marked_absent']++;
                
                if ($is_cli || $is_admin) {
                    audit_log($pdo, 'Auto Mark Absent', "Student ID $student_id ($student_name) marked as absent - no attendance by 7PM");
                } else {
                    audit_log($pdo, 'Auto Mark Absent', "Student ID $student_id ($student_name) marked as absent - no attendance by 7PM");
                }
            }
        } else {
            // Attendance record exists
            if ($attendance['status'] === 'Absent') {
                $results['already_absent']++;
            } elseif ($attendance['status'] === 'Present') {
                if ($attendance['verified'] == 1) {
                    $results['already_present']++;
                } else {
                    // Present but not verified - mark as absent if past 7 PM
                    if ($is_past_7pm || $force_run) {
                        $update_stmt = $pdo->prepare("
                            UPDATE attendance 
                            SET status = 'Absent', 
                                reason = COALESCE(reason, 'Auto-marked: Attendance not verified by supervisor by 7:00 PM'),
                                verified = 0
                            WHERE student_id = ? AND log_date = ?
                        ");
                        $update_stmt->execute([$student_id, $today]);

                        $results['unverified_present']++;
                        
                        if ($is_cli || $is_admin) {
                            audit_log($pdo, 'Auto Mark Absent', "Student ID $student_id ($student_name) changed from Present to Absent - unverified by 7PM");
                        }
                    } else {
                        $results['unverified_present']++;
                    }
                }
            }
        }
    }

} catch (PDOException $e) {
    $results['errors'][] = "Database error: " . $e->getMessage();
    error_log("Auto-mark absent error: " . $e->getMessage());
}

// Output results
if ($is_cli) {
    echo "=== Auto-Mark Absent Results ===\n";
    echo "Date: $today\n";
    echo "Current Time: " . $now->format('H:i:s') . "\n";
    echo "Past 7 PM: " . ($is_past_7pm ? 'Yes' : 'No') . "\n";
    echo "Force Run: " . ($force_run ? 'Yes' : 'No') . "\n";
    echo "--------------------------------\n";
    echo "Total Students: {$results['total_students']}\n";
    echo "Marked Absent: {$results['marked_absent']}\n";
    echo "Already Present (Verified): {$results['already_present']}\n";
    echo "Already Absent: {$results['already_absent']}\n";
    echo "Unverified Present: {$results['unverified_present']}\n";
    
    if (!empty($results['errors'])) {
        echo "\nErrors:\n";
        foreach ($results['errors'] as $error) {
            echo "- $error\n";
        }
    }
} else {
    // HTML output for browser access
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Auto-Mark Absent Results</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background: #f1f4f9;
                padding: 40px 20px;
            }
            .results-container {
                max-width: 700px;
                margin: 0 auto;
                background: white;
                border-radius: 16px;
                padding: 32px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            }
            .results-container h2 {
                color: #111827;
                margin-bottom: 8px;
            }
            .results-container .subtitle {
                color: #6b7280;
                font-size: 14px;
                margin-bottom: 24px;
            }
            .stat-card {
                background: #f8fafc;
                border: 1px solid #e3e8f0;
                border-radius: 12px;
                padding: 16px 20px;
                margin-bottom: 12px;
            }
            .stat-card .label {
                font-size: 12px;
                color: #6b7280;
                text-transform: uppercase;
                font-weight: 600;
                letter-spacing: 0.5px;
            }
            .stat-card .value {
                font-size: 28px;
                font-weight: 700;
                color: #111827;
                margin-top: 4px;
            }
            .stat-card.success { background: #dcfce7; border-color: #86efac; }
            .stat-card.success .value { color: #16a34a; }
            .stat-card.warning { background: #fef3c7; border-color: #fcd34d; }
            .stat-card.warning .value { color: #d97706; }
            .stat-card.info { background: #e0f2fe; border-color: #7dd3fc; }
            .stat-card.info .value { color: #0284c7; }
            .btn-back {
                display: inline-block;
                padding: 10px 20px;
                background: #4361ee;
                color: white;
                text-decoration: none;
                border-radius: 9px;
                font-weight: 600;
                font-size: 14px;
                margin-top: 20px;
                transition: background 0.2s;
            }
            .btn-back:hover { background: #3451d1; color: white; }
        </style>
    </head>
    <body>
        <div class="results-container">
            <h2>📊 Auto-Mark Absent Results</h2>
            <p class="subtitle">Date: <?= $today ?> | Time: <?= $now->format('H:i:s') ?></p>

            <?php if ($is_past_7pm || $force_run): ?>
                <div class="alert alert-success mb-4">
                    ✓ Auto-marking completed successfully
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-4">
                    ⚠️ It's not yet 7:00 PM. Use <code>?force=1</code> to run manually.
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="stat-card">
                        <div class="label">Total Students</div>
                        <div class="value"><?= $results['total_students'] ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stat-card success">
                        <div class="label">Marked Absent</div>
                        <div class="value"><?= $results['marked_absent'] ?></div>
                    </div>
                </div>
            </div>

            <div class="row mt-2">
                <div class="col-md-4">
                    <div class="stat-card info">
                        <div class="label">Already Present</div>
                        <div class="value"><?= $results['already_present'] ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card warning">
                        <div class="label">Already Absent</div>
                        <div class="value"><?= $results['already_absent'] ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card warning">
                        <div class="label">Unverified Present</div>
                        <div class="value"><?= $results['unverified_present'] ?></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($results['errors'])): ?>
                <div class="alert alert-danger mt-4">
                    <strong>Errors:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($results['errors'] as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
                <?php if ($is_admin): ?>
                    <a href="auto_mark_absent.php?force=1" class="btn-back" style="background: #16a34a; margin-left: 10px;">
                        ↻ Run Again (Force)
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
