<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

session_start();
include_once __DIR__ . '/../private/config.php';
date_default_timezone_set('Asia/Manila');

include_once __DIR__ . '/../includes/auth.php';
include_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/attendance.php';
include_once __DIR__ . '/../includes/projects.php';
include_once __DIR__ . '/../includes/export.php';

$student_id = authenticate_student();
$today = date('Y-m-d');
$messages = [];

function get_student_schedule_settings($pdo, $student_id) {
    $defaults = [
        'work_start' => '08:00:00',
        'work_end' => '17:00:00',
        'late_grace_minutes' => 10,
        'eod_grace_hours' => 3,
    ];

    $student_stmt = $pdo->prepare("SELECT created_by, company_id FROM students WHERE student_id = ? LIMIT 1");
    $student_stmt->execute([$student_id]);
    $student_meta = $student_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student_meta) {
        return $defaults;
    }

    $employer_stmt = null;
    if (!empty($student_meta['created_by'])) {
        $employer_stmt = $pdo->prepare("
            SELECT work_start, work_end, late_grace_minutes, eod_grace_hours
            FROM employers
            WHERE employer_id = ?
            LIMIT 1
        ");
        $employer_stmt->execute([$student_meta['created_by']]);
    } elseif (!empty($student_meta['company_id'])) {
        $employer_stmt = $pdo->prepare("
            SELECT work_start, work_end, late_grace_minutes, eod_grace_hours
            FROM employers
            WHERE company_id = ?
            ORDER BY employer_id ASC
            LIMIT 1
        ");
        $employer_stmt->execute([$student_meta['company_id']]);
    }

    $schedule = $employer_stmt ? $employer_stmt->fetch(PDO::FETCH_ASSOC) : null;
    if (!$schedule) {
        return $defaults;
    }

    return [
        'work_start' => $schedule['work_start'] ?? $defaults['work_start'],
        'work_end' => $schedule['work_end'] ?? $defaults['work_end'],
        'late_grace_minutes' => max(1, min(30, (int)($schedule['late_grace_minutes'] ?? $defaults['late_grace_minutes']))),
        'eod_grace_hours' => max(1, min(6, (int)($schedule['eod_grace_hours'] ?? $defaults['eod_grace_hours']))),
    ];
}

if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    if (ob_get_length()) ob_end_clean();

    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;

    if ($start_date && $end_date) {
        $start = strtotime($start_date);
        $end = strtotime($end_date);
        $now = time();

        if ($start > $end) {
            $_SESSION['error'] = "Start date cannot be after end date.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        if ($start > $now || $end > $now) {
            $_SESSION['error'] = "Cannot export future dates.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        if (($end - $start) > (365 * 24 * 60 * 60)) {
            $_SESSION['error'] = "Date range cannot exceed 1 year.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    $phpspreadsheetPath = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($phpspreadsheetPath)) {
        require_once $phpspreadsheetPath;

        try {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            $sql = "SELECT * FROM attendance WHERE student_id = ?";
            $params = [$student_id];

            if ($start_date && $end_date) {
                $sql .= " AND log_date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
            }

            $sql .= " ORDER BY log_date DESC LIMIT 1000";

            $attendance_stmt = $pdo->prepare($sql);
            $attendance_stmt->execute($params);
            $attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            $spreadsheet->getProperties()
                ->setCreator("OJT System")
                ->setLastModifiedBy("OJT System")
                ->setTitle("Attendance History - " . ($student['first_name'] ?? 'Student'))
                ->setSubject("Attendance Records");
            
            $sheet->setCellValue('A1', 'ATTENDANCE HISTORY REPORT');
            $sheet->mergeCells('A1:I1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $sheet->setCellValue('A2', 'Student Name:');
            $sheet->setCellValue('B2', ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
            $sheet->setCellValue('A3', 'Student ID:');
            $sheet->setCellValue('B3', $student['student_id'] ?? '');
            $sheet->setCellValue('A4', 'Date Range:');
            
            if ($start_date && $end_date) {
                $sheet->setCellValue('B4', date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date)));
            } else {
                $sheet->setCellValue('B4', 'All Records');
            }
            
            $sheet->setCellValue('A5', 'Generated On:');
            $sheet->setCellValue('B5', date('F d, Y h:i A'));
            
            $headers = ['Date', 'Time In', 'Lunch Out', 'Lunch In', 'Time Out', 'Status', 'Verified', 'Hours Worked', 'End of Day Task'];
            $sheet->fromArray($headers, NULL, 'A7');
            
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID, 
                    'startColor' => ['rgb' => '2E7D32']
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ];
            $sheet->getStyle('A7:I7')->applyFromArray($headerStyle);
            
            $row = 8;
            $totalMinutes = 0;
            $verifiedCount = 0;
            
            foreach ($attendance as $record) {
                $hours = '-';
                $minutesWorked = 0;
                
                if (!empty($record['time_in']) && !empty($record['time_out']) && 
                    strpos($record['time_in'], '0000') === false && 
                    strpos($record['time_out'], '0000') === false) {
                    
                    $minutesWorked = max(0, (strtotime($record['time_out']) - strtotime($record['time_in'])) / 60);
                    
                    if (!empty($record['lunch_in']) && !empty($record['lunch_out']) && 
                        strpos($record['lunch_in'], '0000') === false && 
                        strpos($record['lunch_out'], '0000') === false) {
                        $minutesWorked -= max(0, (strtotime($record['lunch_in']) - strtotime($record['lunch_out'])) / 60);
                    }
                    
                    $hours = floor($minutesWorked / 60) . "h " . ($minutesWorked % 60) . "m";
                    $totalMinutes += $minutesWorked;
                }
                
                if ($record['verified'] == 1) {
                    $verifiedCount++;
                }
                
                $sheet->setCellValue('A' . $row, $record['log_date']);
                $sheet->setCellValue('B' . $row, (!empty($record['time_in']) && strpos($record['time_in'], '0000') === false) ? date('h:i A', strtotime($record['time_in'])) : '-');
                $sheet->setCellValue('C' . $row, (!empty($record['lunch_out']) && strpos($record['lunch_out'], '0000') === false) ? date('h:i A', strtotime($record['lunch_out'])) : '-');
                $sheet->setCellValue('D' . $row, (!empty($record['lunch_in']) && strpos($record['lunch_in'], '0000') === false) ? date('h:i A', strtotime($record['lunch_in'])) : '-');
                $sheet->setCellValue('E' . $row, (!empty($record['time_out']) && strpos($record['time_out'], '0000') === false) ? date('h:i A', strtotime($record['time_out'])) : '-');
                $sheet->setCellValue('F' . $row, $record['status'] ?? '-');
                $sheet->setCellValue('G' . $row, $record['verified'] == 1 ? 'Verified' : 'Pending');
                $sheet->setCellValue('H' . $row, $hours);
                $sheet->setCellValue('I' . $row, $record['daily_task'] ?? '-');
                
                $verifiedStyle = $sheet->getStyle('G' . $row);
                if ($record['verified'] == 1) {
                    $verifiedStyle->getFont()->getColor()->setRGB('008000');
                } else {
                    $verifiedStyle->getFont()->getColor()->setRGB('FF6B6B');
                }
                
                $dataStyle = [
                    'borders' => [
                        'outline' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC'],
                        ],
                    ],
                ];
                $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray($dataStyle);
                
                $row++;
            }
            
            $summaryRow = $row + 2;
            $sheet->setCellValue('A' . $summaryRow, 'SUMMARY');
            $sheet->mergeCells('A' . $summaryRow . ':B' . $summaryRow);
            $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true)->setSize(14);
            
            $summaryRow++;
            $sheet->setCellValue('A' . $summaryRow, 'Total Days:');
            $sheet->setCellValue('B' . $summaryRow, count($attendance));
            
            $summaryRow++;
            $sheet->setCellValue('A' . $summaryRow, 'Verified Days:');
            $sheet->setCellValue('B' . $summaryRow, $verifiedCount);
            
            $summaryRow++;
            $sheet->setCellValue('A' . $summaryRow, 'Verification Rate:');
            $verificationRate = count($attendance) > 0 ? ($verifiedCount / count($attendance)) * 100 : 0;
            $sheet->setCellValue('B' . $summaryRow, round($verificationRate, 1) . '%');
            
            $summaryRow++;
            $sheet->setCellValue('A' . $summaryRow, 'Total Hours:');
            $totalHours = floor($totalMinutes / 60);
            $totalMinutesRemainder = $totalMinutes % 60;
            $sheet->setCellValue('B' . $summaryRow, $totalHours . 'h ' . $totalMinutesRemainder . 'm');
            
            $summaryStyle = [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F5E9']],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '81C784'],
                    ],
                ],
            ];
            $sheet->getStyle('A' . ($row + 2) . ':B' . $summaryRow)->applyFromArray($summaryStyle);
            
            foreach (range('A', 'I') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            
            $sheet->getColumnDimension('I')->setWidth(40);
            
            $sheet->getStyle('I8:I' . ($row - 1))->getAlignment()->setWrapText(true);
            
            $filename = 'Attendance_History_' . ($student['first_name'] ?? 'student') . '_' . date('Y-m-d') . '.xlsx';
            
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: cache, must-revalidate');
            header('Pragma: public');
            
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
            
        } catch (Exception $e) {
            error_log("Excel Export Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $_SESSION['error'] = "Error generating Excel file. Please contact administrator.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    } else {
        $_SESSION['error'] = "Excel export feature requires PhpSpreadsheet library. Please contact administrator.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_task'])) {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $messages[] = "Invalid request. Please try again.";
    } else {
        $task = trim($_POST['daily_task']);

        $check = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND log_date = ? LIMIT 1");
        $check->execute([$student_id, $today]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $upd = $pdo->prepare("UPDATE attendance SET daily_task = ? WHERE id = ?");
            $upd->execute([$task, $existing['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO attendance (student_id, employer_id, log_date, daily_task, status) VALUES (?, NULL, ?, ?, 'present')");
            $ins->execute([$student_id, $today, $task]);
        }

        $_SESSION['success'] = "Daily task saved successfully.";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_action'])) {
    $action = $_POST['attendance_action'];
    $allowed = ['time_in','lunch_out','lunch_in','time_out'];
    if (!in_array($action, $allowed)) {
        $messages[] = "Invalid action.";
    } else {

        // --- Server-side time window enforcement ---
        $post_schedule = get_student_schedule_settings($pdo, $student_id);
        $post_work_start_str = $post_schedule['work_start'];
        $post_work_end_str   = $post_schedule['work_end'];
        $post_late_grace_minutes = $post_schedule['late_grace_minutes'];
        $post_eod_grace_hours    = $post_schedule['eod_grace_hours'];
        $post_tz             = new DateTimeZone('Asia/Manila');
        $post_now_dt         = new DateTime('now', $post_tz);
        $post_work_start_dt  = new DateTime($today . ' ' . $post_work_start_str, $post_tz);
        $post_time_in_cutoff = clone $post_work_start_dt;
        $post_time_in_cutoff->modify("+{$post_late_grace_minutes} minutes");
        $post_eod_cutoff     = new DateTime($today . ' ' . $post_work_end_str, $post_tz);
        $post_eod_cutoff->modify("+{$post_eod_grace_hours} hours");

        if ($action === 'time_in') {
            if ($post_now_dt < $post_work_start_dt) {
                $messages[] = "Time In is not allowed yet. Work starts at " . $post_work_start_dt->format('H:i') . ".";
            } elseif ($post_now_dt > $post_time_in_cutoff) {
                $messages[] = "Time In is no longer allowed. The {$post_late_grace_minutes}-minute grace period ended at " . $post_time_in_cutoff->format('H:i') . ".";
            }
        } else {
            if ($post_now_dt > $post_eod_cutoff) {
                $messages[] = "Attendance actions are no longer available for today. Cutoff was " . $post_eod_cutoff->format('H:i') . ".";
            }
        }
        // --- End time window enforcement ---

        if (empty($messages)) try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND log_date = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([$student_id, $today]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $now = $pdo->query("SELECT DATE_FORMAT(UTC_TIMESTAMP() + INTERVAL 8 HOUR, '%Y-%m-%d %H:%i:%s')")->fetchColumn();

            if (!$row) {
                if ($action !== 'time_in') {
                    $messages[] = "You must Time In first.";
                    $pdo->rollBack();
                } else {
                    $insert = $pdo->prepare("INSERT INTO attendance (student_id, employer_id, log_date, time_in, status) VALUES (?, NULL, ?, ?, 'present')");
                    $insert->execute([$student_id, $today, $now]);
                    $pdo->commit();

                    if (function_exists('notify_attendance_update')) {
                        notify_attendance_update($student_id, $action, $now);
                    }

                    $_SESSION['success'] = "Time In recorded at " . date('H:i:s', strtotime($now)) . ".";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            } else {
                $updates = [];
                $params = [];
                switch ($action) {
                    case 'time_in':
                        if ($row['time_in']) { $messages[] = "Time In already recorded ({$row['time_in']})."; $pdo->rollBack(); break; }
                        $updates[] = "time_in = ?"; $params[] = $now;
                        break;
                    case 'lunch_out':
                        if (!$row['time_in']) { $messages[] = "You need to Time In first."; $pdo->rollBack(); break; }
                        if ($row['lunch_out']) { $messages[] = "Lunch Out already recorded ({$row['lunch_out']})."; $pdo->rollBack(); break; }
                        $updates[] = "lunch_out = ?"; $params[] = $now;
                        break;
                    case 'lunch_in':
                        if (!$row['lunch_out']) { $messages[] = "You need to Lunch Out first."; $pdo->rollBack(); break; }
                        if ($row['lunch_in']) { $messages[] = "Lunch In already recorded ({$row['lunch_in']})."; $pdo->rollBack(); break; }
                        $updates[] = "lunch_in = ?"; $params[] = $now;
                        break;
                    case 'time_out':
                        if (!$row['time_in']) { $messages[] = "You need to Time In first."; $pdo->rollBack(); break; }
                        if ($row['time_out']) { $messages[] = "Time Out already recorded ({$row['time_out']})."; $pdo->rollBack(); break; }
                        $updates[] = "time_out = ?"; $params[] = $now;
                        break;
                }

                if (!empty($updates) && $pdo->inTransaction()) {
                    $sql = "UPDATE attendance SET " . implode(", ", $updates) . " WHERE student_id = ? AND log_date = ?";
                    $params[] = $student_id;
                    $params[] = $today;
                    $upd = $pdo->prepare($sql);
                    $upd->execute($params);
                    $pdo->commit();
                    $_SESSION['success'] = ucfirst(str_replace('_',' ', $action)) . " recorded at " . date('H:i:s', strtotime($now)) . ".";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $messages[] = "Database error: " . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch employer work schedule for time window enforcement
$schedule = get_student_schedule_settings($pdo, $student_id);
$work_start_str = $schedule['work_start'];
$work_end_str   = $schedule['work_end'];
$late_grace_minutes = $schedule['late_grace_minutes'];
$eod_grace_hours    = $schedule['eod_grace_hours'];

$tz               = new DateTimeZone('Asia/Manila');
$now_dt           = new DateTime('now', $tz);
$work_start_dt    = new DateTime($today . ' ' . $work_start_str, $tz);
$time_in_cutoff   = clone $work_start_dt;
$time_in_cutoff->modify("+{$late_grace_minutes} minutes");
$eod_cutoff_dt    = new DateTime($today . ' ' . $work_end_str, $tz);
$eod_cutoff_dt->modify("+{$eod_grace_hours} hours");

// Boolean flags used by both POST handler and attendance_tab.php
$before_work_start      = $now_dt < $work_start_dt;
$time_in_window_open    = $now_dt >= $work_start_dt && $now_dt <= $time_in_cutoff;
$time_in_window_closed  = $now_dt > $time_in_cutoff;
$eod_window_open        = $now_dt <= $eod_cutoff_dt;

$attendance_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY log_date DESC");
$attendance_stmt->execute([$student_id]);
$attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

$today_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND log_date = ? LIMIT 1");
$today_stmt->execute([$student_id, $today]);
$today_row = $today_stmt->fetch(PDO::FETCH_ASSOC);

$total_minutes = 0;
foreach ($attendance as $row) {
    if ($row['verified'] == 1 && !empty($row['time_in']) && !empty($row['time_out'])) {

        $time_in = strtotime($row['time_in']);
        $time_out = strtotime($row['time_out']);
        $minutesWorked = max(0, ($time_out - $time_in) / 60);

        if (!empty($row['lunch_out']) && !empty($row['lunch_in'])) {
            $lunch_out = strtotime($row['lunch_out']);
            $lunch_in = strtotime($row['lunch_in']);
            $minutesWorked -= max(0, ($lunch_in - $lunch_out) / 60);
        }

        $total_minutes += max(0, $minutesWorked);
    }
}

$hours = floor($total_minutes/60);
$minutes = $total_minutes % 60;
$statusClass = ($hours >= 200) ? 'completed' : 'in-progress';
$statusText = ($hours >= 200) ? 'Completed' : 'In Progress';

$evaluation_stmt = $pdo->prepare("SELECT e.*, em.name AS supervisor_name FROM evaluations e LEFT JOIN employers em ON e.employer_id = em.employer_id WHERE e.student_id = ? ORDER BY e.evaluation_date DESC LIMIT 1");
$evaluation_stmt->execute([$student_id]);
$student_evaluation = $evaluation_stmt->fetch(PDO::FETCH_ASSOC);

$certificate_check_stmt = $pdo->prepare("SELECT certificate_id FROM certificates WHERE student_id = ? ORDER BY generated_at DESC LIMIT 1");
$certificate_check_stmt->execute([$student_id]);
$has_generated_certificate = (bool) $certificate_check_stmt->fetch(PDO::FETCH_ASSOC);

$projects_stmt = $pdo->prepare("SELECT * FROM projects ORDER BY created_at DESC");
$projects_stmt->execute();
$projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);

$submitError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_file'])) {
    $project_id = (int)$_POST['project_id'];
    $remarks = trim($_POST['remarks'] ?? '');
    $submission_type = $_POST['submission_type'] ?? 'code'; 
    
    $uploadDir = __DIR__ . '/../storage/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    try {
        if ($submission_type === 'code') {
            $code = trim($_POST['code_content'] ?? '');

            if (empty($code)) {
                $submitError = "Code cannot be empty.";
            } else {
                $fileName = $student_id . '_project_' . $project_id . '_' . time() . '.txt';
                $filePath = $uploadDir . $fileName;

                if (!is_writable($uploadDir)) {
                    $submitError = "Upload directory is not writable.";
                } elseif (file_put_contents($filePath, $code) === false) {
                    $submitError = "Error saving code file. Please try again.";
                } else {
                    $checkStmt = $pdo->prepare("SELECT submission_id FROM project_submissions WHERE project_id = ? AND student_id = ? AND status = 'Rejected' ORDER BY submission_date DESC LIMIT 1");
                    $checkStmt->execute([$project_id, $student_id]);
                    $existingRejected = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existingRejected) {
                        $updateStmt = $pdo->prepare("UPDATE project_submissions SET file_path = ?, status = 'Pending', submission_date = NOW(), submission_status = 'On Time', remarks = '', graded_at = NULL WHERE submission_id = ?");
                        $updateStmt->execute([$fileName, $existingRejected['submission_id']]);
                        $message = 'Project resubmitted successfully!';
                    } else {
                        $insertStmt = $pdo->prepare("INSERT INTO project_submissions (project_id, student_id, file_path, submission_status, status, remarks) VALUES (?, ?, ?, 'On Time', 'Pending', ?)");
                        $insertStmt->execute([$project_id, $student_id, $fileName, $remarks]);
                        $message = 'Project submitted successfully!';
                    }

                    $_SESSION['success'] = $message;
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            }
        } else {
            $uploadedFile = $_FILES['submission_file'];

            if (empty($uploadedFile['tmp_name']) || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
                $submitError = "Please select a valid file to upload.";
            } else {
                $originalName = basename($uploadedFile['name']);
                $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExts = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'php', 'html', 'css', 'java', 'js', 'py', 'cpp', 'c', 'sql'];

                if (!in_array($fileExt, $allowedExts)) {
                    $submitError = "File type not allowed. Allowed: " . implode(', ', $allowedExts);
                } elseif ($uploadedFile['size'] > 10 * 1024 * 1024) {
                    $submitError = "File too large (maximum 10MB).";
                } else {
                    $uniqueFileName = $student_id . '_project_' . $project_id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                    $filePath = $uploadDir . $uniqueFileName;

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
                    finfo_close($finfo);

                    $allowedMimes = [
                        'text/plain', 'application/pdf', 'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/zip', 'application/x-rar-compressed',
                        'text/html', 'text/css', 'text/javascript', 'application/javascript',
                        'text/x-php', 'text/x-java-source', 'text/x-python',
                        'text/x-c', 'text/x-c++'
                    ];

                    if (!in_array($mimeType, $allowedMimes)) {
                        $submitError = "File type verification failed. Please upload a valid file.";
                    } elseif (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
                        $submitError = "Error uploading file. Please try again.";
                    } else {
                        $checkStmt = $pdo->prepare("SELECT submission_id FROM project_submissions WHERE project_id = ? AND student_id = ? AND status = 'Rejected' ORDER BY submission_date DESC LIMIT 1");
                        $checkStmt->execute([$project_id, $student_id]);
                        $existingRejected = $checkStmt->fetch(PDO::FETCH_ASSOC);

                        if ($existingRejected) {
                            $updateStmt = $pdo->prepare("UPDATE project_submissions SET file_path = ?, status = 'Pending', submission_date = NOW(), submission_status = 'On Time', remarks = '', graded_at = NULL WHERE submission_id = ?");
                            $updateStmt->execute([$uniqueFileName, $existingRejected['submission_id']]);
                            $message = 'Project resubmitted successfully!';
                        } else {
                            $insertStmt = $pdo->prepare("INSERT INTO project_submissions (project_id, student_id, file_path, submission_status, status) VALUES (?, ?, ?, 'On Time', 'Pending')");
                            $insertStmt->execute([$project_id, $student_id, $uniqueFileName]);
                            $message = 'Project submitted successfully! (Attempt #' . $attempt_number . ')';
                        }

                        $_SESSION['success'] = $message;
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit;
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        
        if ($e->getCode() == 'HY093') {
            $submitError = "Database error: Parameter mismatch. Please contact administrator.";
        } else {
            $submitError = "Database error occurred. Please try again.";
        }
    } catch (Exception $e) {
        error_log("General Error: " . $e->getMessage());
        $submitError = "An error occurred. Please try again.";
    }
}

$submissions_stmt = $pdo->prepare("SELECT ps.*, p.project_name FROM project_submissions ps JOIN projects p ON ps.project_id = p.project_id WHERE ps.student_id = ? ORDER BY ps.submission_date DESC");
$submissions_stmt->execute([$student_id]);
$submissions = $submissions_stmt->fetchAll(PDO::FETCH_ASSOC);

$defaultCode = "<?php\necho 'Hello PHP!';\n?>\n<h1>Hello HTML + CSS + JS!</h1>\n<style>h1{color:#0b3d91;}</style>\n<script>console.log('Hello JS');</script>";
$safeDefaultCode = str_replace('</script>', '</scr"+"ipt>', $defaultCode);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Student Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <?php if (!empty($is_local)): ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
    <?php endif; ?>
<style>
    :root {
        --bg:         #f1f4f9;
        --surface:    #ffffff;
        --surface2:   #f8fafc;
        --border:     #e3e8f0;
        --text:       #111827;
        --text-muted: #6b7280;
        --accent:     #4361ee;
        --accent-dk:  #3451d1;
        --accent-lt:  #eef1fd;
        --green:      #16a34a;
        --green-lt:   #dcfce7;
        --red:        #dc2626;
        --red-lt:     #fee2e2;
        --amber:      #d97706;
        --amber-lt:   #fef3c7;
        --radius:     14px;
        --shadow-sm:  0 1px 2px rgba(0,0,0,0.05);
        --shadow:     0 1px 4px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.06);
        --shadow-md:  0 2px 8px rgba(0,0,0,0.07), 0 8px 28px rgba(0,0,0,0.07);
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
        font-family: 'DM Sans', 'Segoe UI', sans-serif;
        background: var(--bg);
        color: var(--text);
        line-height: 1.6;
        min-height: 100vh;
        padding: 28px 20px 60px;
        margin: 0;
    }

    .dashboard-container {
        background: var(--surface);
        border-radius: 20px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-md);
        width: 100%;
        max-width: 1440px;
        margin: 0 auto;
        padding: 0;
        overflow: hidden;
    }

    /* Topbar */
    .topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 32px;
        border-bottom: 1px solid var(--border);
        gap: 16px;
        flex-wrap: wrap;
    }

    .topbar-left h2 { font-size: 20px; font-weight: 700; color: var(--text); margin: 0; letter-spacing: -0.3px; }
    .topbar-left p  { font-size: 13px; color: var(--text-muted); margin: 2px 0 0; }
    .topbar-right   { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

    .badge-role { background: var(--accent-lt); color: var(--accent); font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 20px; }

    .btn-logout {
        display: inline-flex;
        align-items: center;
        background: var(--red);
        color: #fff !important;
        padding: 8px 16px;
        border-radius: 9px;
        text-decoration: none !important;
        font-weight: 600;
        font-size: 13px;
        transition: background 0.2s, transform 0.15s;
        border: none;
        cursor: pointer;
    }

    .btn-logout:hover { background: #b91c1c; transform: translateY(-1px); }

    .dashboard-inner { padding: 24px 32px 36px; }

    /* Messages */
    .success-msg { background: var(--green-lt); color: #15803d; padding: 12px 16px; border-radius: 10px; border: 1px solid #bbf7d0; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
    .error-msg   { background: var(--red-lt);   color: #b91c1c; padding: 12px 16px; border-radius: 10px; border: 1px solid #fecaca; font-size: 14px; font-weight: 500; margin-bottom: 16px; }

    /* Summary strip */
    .summary {
        display: flex;
        gap: 24px;
        flex-wrap: wrap;
        align-items: center;
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 14px 20px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .summary p { margin: 0; }

    .status.completed { color: var(--green); font-weight: 700; }
    .status.in-progress { color: var(--amber); font-weight: 700; }

    /* Tabs */
    .tab-switcher {
        display: flex;
        gap: 4px;
        margin-bottom: 20px;
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 11px;
        padding: 4px;
        width: fit-content;
    }

    .tab-button {
        padding: 9px 20px;
        border: none;
        background: transparent;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        border-radius: 8px;
        color: var(--text-muted);
        transition: all 0.18s;
        font-family: inherit;
    }

    .tab-button.active { background: var(--surface); color: var(--accent); box-shadow: var(--shadow-sm); border: 1px solid var(--border); }
    .tab-button:hover:not(.active) { color: var(--text); background: var(--surface); }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Buttons */
    .action-btn, .btn-primary, .btn-export {
        padding: 9px 16px;
        border-radius: 9px;
        border: none;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.18s;
        font-family: inherit;
        text-decoration: none;
    }

    .btn-primary, .action-btn.btn-primary { background: var(--accent); color: #fff !important; }
    .btn-primary:hover, .action-btn.btn-primary:hover { background: var(--accent-dk); transform: translateY(-1px); }

    .btn-disabled { background: #e5e7eb; color: #9ca3af; cursor: not-allowed; padding: 9px 16px; border-radius: 9px; border: none; font-weight: 600; font-size: 13px; font-family: inherit; }

    .btn-export-excel { background: var(--green); color: #fff; }
    .btn-export-excel:hover { background: #15803d; transform: translateY(-1px); }
    .btn-export-all { background: var(--accent); color: #fff; }
    .btn-export-all:hover { background: var(--accent-dk); transform: translateY(-1px); }

    /* Attendance actions */
    .attendance-actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-start; margin-bottom: 16px; }

    /* Cards / sections */
    .task-section, .attendance-section {
        margin-bottom: 16px;
        padding: 18px 20px;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--surface2);
    }

    .task-section h3, .attendance-section h3 { margin: 0 0 12px; font-size: 15px; font-weight: 700; color: var(--text); }

    /* Export panel */
    .export-panel {
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px 20px;
        margin: 16px 0;
    }

    .export-panel h5 { font-size: 15px; font-weight: 700; color: var(--text); margin: 0 0 14px; }

    .export-form { display: flex; flex-direction: column; align-items: flex-start; gap: 12px; width: 100%; }
    .export-controls { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
    .date-input-group { display: flex; flex-direction: column; gap: 4px; }
    .date-input-group label { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
    .export-buttons { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
    .export-buttons .btn-export { height: 38px; min-width: 140px; }

    /* Form controls */
    .form-control, textarea {
        border-radius: 9px;
        border: 1px solid var(--border);
        padding: 9px 12px;
        font-size: 14px;
        font-family: inherit;
        color: var(--text);
        background: var(--surface);
        transition: border-color 0.2s, box-shadow 0.2s;
        width: 100%;
    }

    .form-control:focus, textarea:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(67,97,238,0.12);
        outline: none;
    }

    /* Table */
    .table-section { overflow-x: auto; border-radius: var(--radius); border: 1px solid var(--border); margin-top: 16px; }
    .desktop-view table { width: 100%; border-collapse: collapse; background: var(--surface); }

    th {
        background: var(--surface2);
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--text-muted);
        padding: 11px 14px;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    td { padding: 11px 14px; border-bottom: 1px solid var(--border); font-size: 13px; color: var(--text); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: var(--accent-lt); transition: background 0.15s; }

    /* Mobile cards */
    .mobile-view { display: none; }

    .attendance-card {
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        margin-bottom: 12px;
        overflow: hidden;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: var(--surface);
        border-bottom: 1px solid var(--border);
        font-size: 14px;
        font-weight: 600;
        flex-wrap: wrap;
        gap: 8px;
    }

    .status-badge { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }

    .status-text { padding: 3px 8px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .verified-badge   { background: var(--green-lt); color: var(--green); font-size: 12px; font-weight: 600; padding: 3px 8px; border-radius: 20px; }
    .unverified-badge { background: var(--red-lt); color: var(--red); font-size: 12px; font-weight: 600; padding: 3px 8px; border-radius: 20px; }

    .card-body { padding: 14px 16px; }
    .time-info { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 10px; }
    .time-row { display: flex; flex-direction: column; gap: 1px; }
    .time-row .label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
    .time-row .value { font-size: 14px; font-weight: 500; color: var(--text); }

    .task-info { font-size: 13px; color: var(--text-muted); border-top: 1px solid var(--border); padding-top: 10px; margin-top: 8px; }
    .task-info p { margin: 4px 0 0; color: var(--text); }

    /* Projects */
    .projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; margin-top: 16px; }

    .project-card {
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .project-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }

    /* Sidebar buttons for student_attendance_content */
    .student-sidebar-buttons { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }

    .sidebar-btn {
        padding: 9px 16px;
        border: 1.5px solid var(--border);
        border-radius: 9px;
        background: var(--surface2);
        color: var(--text-muted);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.18s;
        font-family: inherit;
    }

    .sidebar-btn.active, .sidebar-btn:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

    /* Modal */
    .modal-content { border-radius: 16px !important; border: 1px solid var(--border) !important; }
    .modal-header  { border-bottom: 1px solid var(--border) !important; }
    .modal-footer  { border-top: 1px solid var(--border) !important; }

    /* Full screen IDE */
    #fullscreenIDE { border-radius: 0; }

    @media (max-width: 768px) {
        body { padding: 10px 10px 40px; }
        .dashboard-container { border-radius: 14px; }
        .topbar, .dashboard-inner { padding: 14px 16px; }
        .tab-switcher { width: 100%; }
        .tab-button { flex: 1; text-align: center; font-size: 12px; padding: 8px 10px; }
        .summary { gap: 12px; font-size: 13px; }
        .desktop-view { display: none; }
        .mobile-view { display: block; }
        .attendance-actions { gap: 8px; }
        .action-btn, .btn-primary, .btn-export { font-size: 12px; padding: 8px 12px; width: 100%; justify-content: center; }
        .export-controls { flex-direction: column; }
        .time-info { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>
<div class="dashboard-container">

    <div class="topbar">
        <div class="topbar-left">
            <h2>Welcome, <?= htmlspecialchars(isset($student['first_name']) ? trim(explode(' ', $student['first_name'])[0]) : 'Student') ?>!</h2>
            <p>OJT Student Portal</p>
        </div>
        <div class="topbar-right">
            <span class="badge-role">Student</span>
            <?php if ($has_generated_certificate): ?>
                <a href="download_certificate.php" class="action-btn btn-primary" style="text-decoration:none;">📄 Certificate</a>
            <?php endif; ?>
            <?php if ($student_evaluation): ?>
                <a href="download_evaluation.php" class="action-btn btn-primary" style="text-decoration:none;">📝 Evaluation</a>
            <?php endif; ?>
            <a href="logout.php" class="btn-logout">⎋ Logout</a>
        </div>
    </div>

    <div class="dashboard-inner">

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="success-msg"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="error-msg"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (!empty($messages)) foreach ($messages as $m): ?>
        <div class="error-msg"><?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>

<div class="summary" id="summarySection">
    <p><strong>Total Hours:</strong> <?= $hours ?> hr <?= $minutes ?> min / 200h</p>
    <p><strong>Status:</strong> <span class="status <?= $statusClass ?>"><?= $statusText ?></span></p>
    <p><strong>Today:</strong> <?= $today ?></p>
</div>

<div class="tab-switcher">
    <button class="tab-button active" onclick="switchTab('attendance', this)">Attendance</button>
    <button class="tab-button" onclick="switchTab('export', this)">Export History</button>
    <button class="tab-button" onclick="switchTab('projects', this)">Projects</button>
</div>

<?php include_once __DIR__ . '/../templates/attendance_tab.php'; ?>

<?php include_once __DIR__ . '/../templates/export_tab.php'; ?>

<?php include_once __DIR__ . '/../templates/projects_tab.php'; ?>

<div class="modal fade" id="verifiedModal" tabindex="-1" aria-labelledby="verifiedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verifiedModalLabel">Attendance Verified</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Your attendance was verified today.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

</div>

<div id="fullscreenIDE" class="fullscreen-ide" style="display: none;">
    <div class="ide-header">
        <h4 id="ideProjectName">Project IDE</h4>
        <button onclick="closeFullScreenIDE()" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer;">✕</button>
    </div>
    <div class="ide-content">
        <div class="code-panel">
            <div class="panel-header">Code Editor</div>
            <div class="panel-content">
                <textarea id="fullscreenEditor"></textarea>
            </div>
        </div>
        <div class="output-panel">
            <div class="panel-header">Output Preview</div>
            <div class="panel-content">
                <iframe id="fullscreenPreview" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
        </div>
    </div>
    <div class="ide-controls">
        <button onclick="runCode()" class="btn btn-primary">▶️ Run Code</button>
        <button onclick="generatePDF()" class="btn btn-success">📄 Generate PDF & Submit</button>
        <button onclick="closeFullScreenIDE()" class="btn btn-secondary">❌ Close</button>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let lastAttendanceCheck = null;
    let lastProjectCheck = null;
    let lastCertificateCheck = null;
    let hasAttendanceBaseline = false;
    let hasProjectBaseline = false;
    let hasCertificateBaseline = false;
    let hasAutoRefreshed = false;
    const POLL_INTERVAL = 15000;

    function triggerSingleRefresh(message, type) {
        if (hasAutoRefreshed) return;
        hasAutoRefreshed = true;
        showNotification(message + ' Refreshing...', type);
        setTimeout(() => location.reload(), 1500);
    }
    
    async function checkAttendanceUpdates() {
        try {
            const url = 'api/check_attendance.php?since=' + encodeURIComponent(lastAttendanceCheck || '') + '&student_id=<?= htmlspecialchars($student_id) ?>';
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success && data.latest_timestamp) {
                if (!hasAttendanceBaseline) {
                    lastAttendanceCheck = data.latest_timestamp;
                    hasAttendanceBaseline = true;
                    return;
                }

                if (lastAttendanceCheck && data.latest_timestamp > lastAttendanceCheck) {
                    triggerSingleRefresh('Your attendance has been verified!', 'success');
                }
                lastAttendanceCheck = data.latest_timestamp;
            } else if (data.success && !hasAttendanceBaseline) {
                hasAttendanceBaseline = true;
            }
        } catch (err) {
            console.error('Error checking attendance updates:', err);
        }
    }

    async function checkProjectUpdates() {
        try {
            const url = 'api/check_updates.php?since=' + encodeURIComponent(lastProjectCheck || '') + '&type=project&student_id=<?= htmlspecialchars($student_id) ?>';
            const response = await fetch(url);
            const data = await response.json();

            if (data.success && data.projects?.latest_timestamp) {
                const latestProjectTime = data.projects.latest_timestamp;

                if (!hasProjectBaseline) {
                    lastProjectCheck = latestProjectTime;
                    hasProjectBaseline = true;
                    return;
                }

                if (lastProjectCheck && latestProjectTime > lastProjectCheck && data.projects.has_updates) {
                    triggerSingleRefresh('Your project submission has been graded!', 'info');
                }

                lastProjectCheck = latestProjectTime;
            } else if (data.success && !hasProjectBaseline) {
                hasProjectBaseline = true;
            }
        } catch (err) {
            console.error('Error checking project updates:', err);
        }
    }

    async function checkCertificateUpdates() {
        try {
            const url = 'api/check_updates.php?since=' + encodeURIComponent(lastCertificateCheck || '') + '&type=certificate&student_id=<?= htmlspecialchars($student_id) ?>';
            const response = await fetch(url);
            const data = await response.json();

            if (data.success && data.certificates?.latest_timestamp) {
                const latestCertificateTime = data.certificates.latest_timestamp;

                if (!hasCertificateBaseline) {
                    lastCertificateCheck = latestCertificateTime;
                    hasCertificateBaseline = true;
                    return;
                }

                if (lastCertificateCheck && latestCertificateTime > lastCertificateCheck && data.certificates.has_updates) {
                    triggerSingleRefresh('New certificate generated!', 'success');
                }

                lastCertificateCheck = latestCertificateTime;
            } else if (data.success && !hasCertificateBaseline) {
                hasCertificateBaseline = true;
            }
        } catch (err) {
            console.error('Error checking certificate updates:', err);
        }
    }
    
    function showNotification(message, type) {
        const existing = document.querySelector('.polling-notification');
        if (existing) existing.remove();
        
        const notification = document.createElement('div');
        notification.className = 'alert alert-' + type + ' alert-dismissible fade show polling-notification';
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.minWidth = '300px';
        notification.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        
        document.body.appendChild(notification);
        
        setTimeout(function() {
            notification.remove();
        }, 5000);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        checkAttendanceUpdates();
        checkProjectUpdates();
        checkCertificateUpdates();
        
        setInterval(checkAttendanceUpdates, POLL_INTERVAL);
        setInterval(checkProjectUpdates, POLL_INTERVAL);
        setInterval(checkCertificateUpdates, POLL_INTERVAL);
    });
</script>
<script>

function switchTab(tabName, button) {
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => content.classList.remove('active'));

    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(btn => btn.classList.remove('active'));

    document.getElementById(tabName + '-tab').classList.add('active');

    button.classList.add('active');

    const welcomeHeader = document.getElementById('welcomeHeader');
    const summarySection = document.getElementById('summarySection');

    if (tabName === 'projects') {
        if (welcomeHeader) welcomeHeader.style.display = 'none';
        if (summarySection) summarySection.style.display = 'none';

        document.getElementById('submissionSection').style.display = 'none';
        document.getElementById('projects-section').style.display = 'block';

        if (!window.codeEditor) {
            setTimeout(initCodeEditor, 100);
        }
    } else {
        if (welcomeHeader) welcomeHeader.style.display = 'flex';
        if (summarySection) summarySection.style.display = 'block';
    }
}

function selectProjectForSubmission(projectId, projectName) {
    document.getElementById('projects-section').style.display = 'none';
    document.getElementById('submissionSection').style.display = 'block';

    document.getElementById('selectedProjectName').textContent = projectName;
    document.getElementById('projectId').value = projectId;

    document.getElementById('submissionForm').reset();
    closeFullscreenPreview();
    document.getElementById('submissionFile').value = '';

    switchSubmissionTab('code');

    setTimeout(() => {
        if (window.codeEditor && typeof window.codeEditor.setValue === 'function') {
            window.codeEditor.setValue(`<?php echo htmlspecialchars($defaultCode); ?>`);
        } else {
            const ta = document.getElementById('codeEditor');
            if (ta) ta.value = `<?php echo htmlspecialchars($defaultCode); ?>`;
        }
    }, 100);
}

function cancelSubmission() {
    document.getElementById('submissionSection').style.display = 'none';
    document.getElementById('projects-section').style.display = 'block';
}

function switchSubmissionTab(tabType) {
    const submissionType = document.getElementById('submissionType');

    if (tabType === 'code') {
        submissionType.value = 'code';
        document.getElementById('codeTab').style.display = 'flex';
        document.getElementById('fileTab').style.display = 'none';
        document.getElementById('codeTabBtn').style.borderBottom = '3px solid #28a745';
        document.getElementById('codeTabBtn').style.color = '#28a745';
        document.getElementById('fileTabBtn').style.borderBottom = 'none';
        document.getElementById('fileTabBtn').style.color = '#999';

        if (!window.codeEditor) {
            setTimeout(initCodeEditor, 50);
        }
    } else {
        submissionType.value = 'file';
        document.getElementById('codeTab').style.display = 'none';
        document.getElementById('fileTab').style.display = 'block';
        document.getElementById('codeTabBtn').style.borderBottom = 'none';
        document.getElementById('codeTabBtn').style.color = '#999';
        document.getElementById('fileTabBtn').style.borderBottom = '3px solid #28a745';
        document.getElementById('fileTabBtn').style.color = '#28a745';
    }
}

function runCodePreview(event) {
    if (event) event.preventDefault();
    let code = '';
    if (window.codeEditor && typeof window.codeEditor.getValue === 'function') {
        window.codeEditor.save();
        code = window.codeEditor.getValue();
    }
    if (!code) {
        const ta = document.getElementById('codeEditor') || document.querySelector('textarea[name="code_content"]');
        if (ta) code = ta.value;
    }
    if (!code.trim()) {
        alert('No code to preview. Please write some code first.');
        return;
    }
    // Open fullscreen overlay
    let overlay = document.getElementById('previewOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'previewOverlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:#fff;display:flex;flex-direction:column;';
        const header = document.createElement('div');
        header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:12px 20px;background:#111827;color:#fff;flex-shrink:0;';
        header.innerHTML = '<span style="font-size:14px;font-weight:700;">&#9654; Preview Output</span>'
            + '<div style="display:flex;gap:10px;">'
            + '<button onclick="refreshPreview()" style="background:#374151;border:none;color:#d1d5db;padding:6px 14px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;">&#8635; Refresh</button>'
            + '<button onclick="closeFullscreenPreview()" style="background:#dc2626;border:none;color:#fff;padding:6px 14px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;">&#x2715; Close</button>'
            + '</div>';
        const iframe = document.createElement('iframe');
        iframe.id = 'previewOverlayFrame';
        iframe.style.cssText = 'flex:1;border:none;width:100%;';
        overlay.appendChild(header);
        overlay.appendChild(iframe);
        document.body.appendChild(overlay);
    }
    overlay.style.display = 'flex';
    overlay._lastCode = code;
    document.getElementById('previewOverlayFrame').srcdoc = code;
    document.body.style.overflow = 'hidden';
}

function refreshPreview() {
    const overlay = document.getElementById('previewOverlay');
    if (!overlay) return;
    let code = '';
    if (window.codeEditor && typeof window.codeEditor.getValue === 'function') {
        window.codeEditor.save();
        code = window.codeEditor.getValue();
    }
    if (!code) {
        const ta = document.getElementById('codeEditor') || document.querySelector('textarea[name="code_content"]');
        if (ta) code = ta.value;
    }
    overlay._lastCode = code;
    document.getElementById('previewOverlayFrame').srcdoc = code || overlay._lastCode || '';
}

function closeFullscreenPreview() {
    const overlay = document.getElementById('previewOverlay');
    if (overlay) overlay.style.display = 'none';
    document.body.style.overflow = '';
}

function createPlainEditor(textarea) {
    return {
        getValue: () => textarea.value,
        setValue: (value) => { textarea.value = value ?? ''; },
        save: () => {},
        refresh: () => {},
        focus: () => textarea.focus(),
        toTextArea: () => {}
    };
}

function initCodeEditor() {
    const textarea = document.getElementById('codeEditor');
    if (!textarea) {
        console.error('codeEditor textarea not found!');
        return;
    }
    
    if (window.codeEditor && window.codeEditor.toTextArea) {
        try {
            window.codeEditor.toTextArea();
        } catch (e) {
            console.log('Error cleaning up editor:', e);
        }
    }

    if (window.CodeMirror) {
        window.codeEditor = CodeMirror.fromTextArea(textarea, {
            lineNumbers: true,
            theme: "monokai",
            tabSize: 4,
            lineWrapping: true,
            matchBrackets: true,
            autoCloseBrackets: true,
            mode: "application/x-httpd-php",
            value: `<?php echo htmlspecialchars($defaultCode); ?>`,
            viewportMargin: Infinity,
            indentUnit: 4,
            extraKeys: {
                "Ctrl-Space": "autocomplete"
            }
        });
    } else {
        window.codeEditor = createPlainEditor(textarea);
    }
    
    setTimeout(() => {
        if (window.codeEditor) {
            window.codeEditor.refresh();
            window.codeEditor.focus();
        }
    }, 150);
    
    return window.codeEditor;
}

function openFullScreenIDE(projectId, projectName) {
    currentProjectId = projectId;
    document.getElementById('ideProjectName').textContent = 'Project: ' + projectName;
    document.getElementById('fullscreenIDE').style.display = 'flex';

    if (!window.fullscreenEditor) {
        const fullscreenTextarea = document.getElementById('fullscreenEditor');
        if (!fullscreenTextarea) {
            return;
        }

        if (window.CodeMirror) {
            window.fullscreenEditor = CodeMirror.fromTextArea(fullscreenTextarea, {
                lineNumbers: true,
                theme: 'default',
                tabSize: 4,
                lineWrapping: true,
                matchBrackets: true,
                autoCloseBrackets: true,
                mode: 'htmlmixed',
                value: `<?php echo htmlspecialchars($defaultCode); ?>`
            });
        } else {
            if (!fullscreenTextarea.value.trim()) {
                fullscreenTextarea.value = `<?php echo htmlspecialchars($defaultCode); ?>`;
            }
            window.fullscreenEditor = createPlainEditor(fullscreenTextarea);
        }
    }
}

function closeFullScreenIDE() {
    document.getElementById('fullscreenIDE').style.display = 'none';
}

function runCode() {
    if (window.fullscreenEditor) {
        const code = window.fullscreenEditor.getValue();
        const iframe = document.getElementById('fullscreenPreview');
        iframe.srcdoc = code;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('submissionForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const submissionType = document.getElementById('submissionType').value;
            
            if (submissionType === 'code') {
                if (window.codeEditor) {
                    window.codeEditor.save();
                }
                
                const codeTextarea = document.getElementById('codeEditor');
                if (!codeTextarea || codeTextarea.value.trim() === '') {
                    e.preventDefault();
                    alert('Please write some code before submitting');
                    return false;
                }
            } else {
                const fileInput = document.getElementById('submissionFile');
                if (!fileInput.files.length) {
                    e.preventDefault();
                    alert('Please select a file to submit');
                    return false;
                }
            }
            return true;
        });
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFullscreenPreview(); });

    const today = new Date().toISOString().split('T')[0];
    const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');
    
    if (startDateInput && !startDateInput.value) {
        startDateInput.value = firstDay;
    }
    if (endDateInput && !endDateInput.value) {
        endDateInput.value = today;
    }

    <?php if (!empty($today_row) && $today_row['verified'] == 1): ?>
    const todayDate = '<?= date('Y-m-d') ?>';
    const modalShownKey = 'attendance_modal_shown_' + todayDate;

    if (!localStorage.getItem(modalShownKey)) {
        var myModal = new bootstrap.Modal(document.getElementById('verifiedModal'), {});
        myModal.show();
        localStorage.setItem(modalShownKey, 'true');
    }
    <?php endif; ?>
});a
</script>

    </div><!-- /.dashboard-inner -->
</div><!-- /.dashboard-container -->
</body>
</html>
