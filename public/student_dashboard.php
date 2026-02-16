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
            
            $headers = ['Date', 'Time In', 'Lunch Out', 'Lunch In', 'Time Out', 'Status', 'Verified', 'Hours Worked', 'Daily Task / Activity'];
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
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND log_date = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([$student_id, $today]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $now = date('Y-m-d H:i:s');

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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
<style>
    body {
        margin: 0;
        padding: 0;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #e8f5e8, #d1ecf1);
        color: #333;
        line-height: 1.6;
    }

    .dashboard-container {
        background: rgba(255, 255, 255, 0.95);
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 1200px;
        margin: 20px auto;
        text-align: center;
    }

    .welcome-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .welcome-header h2 {
        margin: 0;
        font-size: 28px;
        font-weight: 600;
        color: #2c3e50;
    }

    .tab-switcher {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 10px;
    }

    .tab-button {
        padding: 12px 24px;
        border: none;
        background: none;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        border-radius: 8px 8px 0 0;
        transition: all 0.3s ease;
        margin: 0 5px;
    }

    .tab-button.active {
        background: linear-gradient(90deg, #28a745, #85e085);
        color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .tab-button.active:hover {
        background: linear-gradient(90deg, #28a745, #85e085);
        color: white;
    }

    .tab-button:hover {
        background: #f8f9fa;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .export-panel {
        background: #f8fff8;
        border: 1px solid #c3e6cb;
        border-radius: 10px;
        padding: 20px;
        margin: 20px auto;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        max-width: 800px;
        width: 100%;
        text-align: center;
    }

    .export-panel h5 {
        margin-top: 0;
        color: #2c3e50;
        margin-bottom: 20px;
        font-size: 1.25rem;
    }

    .export-form {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
        width: 100%;
    }

    .export-form label {
        font-weight: 600;
        color: #2c3e50;
        text-align: left;
        width: 100%;
        max-width: 600px;
    }

    .export-form .form-control {
        border-radius: 6px;
        border: 1px solid #ddd;
        padding: 8px 12px;
        font-size: 14px;
        transition: border-color 0.3s ease;
        height: 38px;
    }

    .export-form .form-control:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }

    .export-controls {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: flex-end;
        gap: 15px;
        width: 100%;
        max-width: 600px;
    }

    .date-input-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
        flex: 1;
        min-width: 150px;
    }

    .date-input-group label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 14px;
        text-align: left;
    }

    .date-input-group .form-control {
        width: 100%;
        padding: 6px 8px;
        font-size: 14px;
        height: 38px;
    }

    .export-buttons {
        display: flex;
        gap: 10px;
        justify-content: center;
        width: 100%;
        margin-top: 10px;
    }

    .export-buttons .btn-export {
        height: 38px;
        padding: 8px 15px;
        font-size: 14px;
        min-width: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-export {
        padding: 8px 15px;
        border-radius: 6px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        height: 38px;
    }

    .btn-export-excel {
        background: linear-gradient(90deg, #2E7D32, #4CAF50);
        color: white;
    }

    .btn-export-excel:hover {
        background: linear-gradient(90deg, #1B5E20, #388E3C);
        transform: translateY(-2px);
    }

    .btn-export-all {
        background: linear-gradient(90deg, #1565C0, #2196F3);
        color: white;
    }

    .btn-export-all:hover {
        background: linear-gradient(90deg, #0D47A1, #1976D2);
        transform: translateY(-2px);
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .attendance-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: center;
        margin-bottom: 20px;
    }

    .action-btn {
        padding: 12px 18px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        min-width: 140px;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background: linear-gradient(90deg, #28a745, #85e085);
        color: white;
    }

    .btn-primary:hover {
        background: linear-gradient(90deg, #218838, #6c9e6c);
        transform: translateY(-2px);
    }

    .btn-disabled {
        background: #ddd;
        color: #666;
        cursor: not-allowed;
    }

    .btn-logout {
        background: linear-gradient(90deg, #dc3545, #c82333);
        color: white;
    }

    .btn-logout:hover {
        background: linear-gradient(90deg, #c82333, #a02622);
        transform: translateY(-2px);
    }

    .summary {
        text-align: left;
        margin-bottom: 20px;
        padding: 20px;
        border-radius: 10px;
        background: linear-gradient(90deg, #f8fff8, #e8f5e8);
        border: 1px solid #c3e6cb;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .success-msg {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid #c3e6cb;
        text-align: left;
        font-weight: 500;
    }

    .error-msg {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid #f5c6cb;
        text-align: left;
        font-weight: 500;
    }

    .task-section, .attendance-section {
        margin-bottom: 20px;
        padding: 20px;
        border-radius: 10px;
        border: 1px solid #e0e0e0;
        background: #f8f9fa;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .task-section h3, .attendance-section h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #2c3e50;
    }

    .table-section {
        overflow-x: auto;
        margin-top: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 10px;
        overflow: hidden;
    }

    th, td {
        padding: 12px;
        border-bottom: 1px solid #e0e0e0;
        text-align: center;
    }

    th {
        background: linear-gradient(90deg, #f8f9fa, #e9ecef);
        font-weight: 600;
        color: #2c3e50;
    }

    tr:nth-child(even) {
        background: #f8f9fa;
    }

    tr:hover {
        background: #e3f2fd;
        transition: background 0.3s ease;
    }

    .status.completed {
        color: green;
        font-weight: bold;
    }

    .status.in-progress {
        color: orange;
        font-weight: bold;
    }

    .mobile-view {
        display: none;
    }

    .attendance-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        overflow: hidden;
        border: 1px solid #e0e0e0;
    }

    .card-header {
        background: linear-gradient(90deg, #f8f9fa, #e9ecef);
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        border-bottom: 1px solid #e0e0e0;
    }

    .status-badge {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .status-text {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: bold;
    }

    .verified-badge {
        color: green;
        font-weight: bold;
        font-size: 12px;
    }

    .unverified-badge {
        color: red;
        font-weight: bold;
        font-size: 12px;
    }

    .card-body {
        padding: 15px;
    }

    .time-info {
        margin-bottom: 15px;
    }

    .time-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .time-row:last-child {
        border-bottom: none;
    }

    .label {
        font-weight: 600;
        color: #2c3e50;
        min-width: 100px;
    }

    .value {
        color: #333;
        font-weight: 500;
    }

    .task-info {
        background: #f8f9fa;
        padding: 12px;
        border-radius: 8px;
        border-left: 4px solid #28a745;
    }

    .task-info p {
        margin: 0;
        color: #333;
        line-height: 1.4;
    }

    .projects-section {
        margin-bottom: 20px;
        padding: 20px;
        border-radius: 10px;
        border: 1px solid #e0e0e0;
        background: #f8f9fa;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .projects-section h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #2c3e50;
    }

    .projects-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .project-card {
        background: white;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #ddd;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .project-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-color: #28a745;
    }

    .project-card.disabled {
        background: #f5f5f5;
        cursor: not-allowed;
        opacity: 0.6;
    }

    .project-card.disabled:hover {
        transform: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        border-color: #ddd;
    }

    .project-card h5 {
        margin: 0 0 10px 0;
        color: #2c3e50;
    }

    .project-card p {
        margin: 0;
        font-size: 14px;
        color: #666;
    }

    .code-editor-section {
        background: white;
        padding: 20px;
        border-radius: 10px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        margin-top: 20px;
    }

    .code-editor-section h4 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #2c3e50;
    }

    .editor-controls {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
        flex-wrap: wrap;
        align-items: center;
    }

    .editor-controls select {
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid #ddd;
        font-size: 14px;
    }

    #codeTab {
        display: flex;
        gap: 15px;
        margin-bottom: 15px;
        height: calc(100vh - 320px);
        min-height: 400px;
    }

    .editor-half,
    .preview-half {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .editor-half h6, .preview-half h6 {
        margin: 0 0 10px 0;
        font-size: 16px;
        color: #2c3e50;
        font-weight: 600;
    }

    #codeEditorContainer {
        flex: 1;
        border: 1px solid #ddd;
        border-radius: 6px;
        overflow: hidden;
        background: #fff;
    }

    .CodeMirror {
        height: 100% !important;
        font-size: 14px !important;
        line-height: 1.5 !important;
    }

    .fullscreen-ide .CodeMirror {
        height: 100% !important;
        border-radius: 0;
        border: none;
    }

    .CodeMirror-scroll {
        height: 100%;
    }

    .fullscreen-ide .CodeMirror-scroll {
        height: 100% !important;
    }

    .fullscreen-ide {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: white;
        z-index: 9999;
        display: flex;
        flex-direction: column;
    }

    .ide-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        background: #f8f9fa;
        border-bottom: 1px solid #e0e0e0;
        flex-shrink: 0;
    }

    .ide-header h4 {
        margin: 0;
        color: #2c3e50;
    }

    .ide-content {
        flex: 1;
        display: flex;
        overflow: hidden;
        min-height: 0;
    }

    .code-panel {
        flex: 1;
        display: flex;
        flex-direction: column;
        border-right: 1px solid #e0e0e0;
        overflow: hidden;
    }

    .output-panel {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .panel-header {
        padding: 10px;
        background: #f8f9fa;
        border-bottom: 1px solid #e0e0e0;
        font-weight: 600;
        color: #2c3e50;
        flex-shrink: 0;
    }

    .panel-content {
        flex: 1;
        padding: 10px;
        overflow: hidden;
        position: relative;
    }

    .fullscreen-ide .panel-content {
        padding: 0;
    }

    .ide-controls {
        display: flex;
        gap: 10px;
        padding: 15px;
        background: #f8f9fa;
        border-top: 1px solid #e0e0e0;
        flex-shrink: 0;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #2c3e50;
    }

    .form-control {
        border-radius: 6px;
        border: 1px solid #ddd;
        padding: 10px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }

    .form-control:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }

    .submit-code-btn {
        margin-top: 15px;
    }

    .submissions-list {
        margin-top: 30px;
    }

    .submission-card {
        background: white;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #28a745;
        margin-bottom: 15px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .submission-card h6 {
        margin: 0 0 10px 0;
        color: #2c3e50;
    }

    .submission-meta {
        font-size: 13px;
        color: #999;
        margin-bottom: 10px;
    }

    @media (max-width: 768px) { 
        .dashboard-container {
            padding: 20px;
            margin: 10px;
        }

        .welcome-header {
            flex-direction: column;
            text-align: center;
        }

        .welcome-header h2 {
            font-size: 24px;
        }

        .export-panel {
            margin: 20px 0;
            padding: 15px;
        }

        .export-form {
            align-items: stretch;
        }

        .export-controls {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }

        .date-input-group {
            width: 100%;
        }

        .date-input-group .form-control {
            width: 100%;
        }

        .export-buttons {
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
        }

        .export-buttons .btn-export {
            width: 100%;
            min-width: unset;
        }

        .attendance-actions {
            flex-direction: column;
        }

        .action-btn {
            width: 100%;
            min-width: unset;
        }

        .summary, .task-section, .attendance-section, .projects-section {
            padding: 15px;
        }

        .projects-grid {
            grid-template-columns: 1fr;
        }

        .CodeMirror {
            height: 300px;
        }

        .CodeMirror-scroll {
            height: 300px;
        }

        #codeEditorContainer {
            height: 300px;
        }

        .editor-controls {
            flex-direction: column;
        }

        .editor-controls select {
            width: 100%;
        }

        .desktop-view {
            display: none;
        }

        .mobile-view {
            display: block;
        }
    }
</style>
</head>
<body>
<div class="dashboard-container">
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

<div class="welcome-header" id="welcomeHeader">
    <h2>Welcome, <?= 
        htmlspecialchars(
            isset($student['first_name']) 
                ? trim(explode(' ', $student['first_name'])[0]) 
                : 'Student'
        ) 
    ?></h2>
    <div class="action-buttons">
        <a href="logout.php" class="action-btn btn-logout" style="text-decoration:none;">🚪 Logout</a>
        <a href="download_certificate.php" class="action-btn btn-primary" style="text-decoration:none;">📄 Download Certificate</a>
    </div>
</div>

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
    // Polling mechanism for real-time updates (replaces WebSocket)
    let lastAttendanceCheck = null;
    let lastUpdatesCheck = null;
    const POLL_INTERVAL = 15000; // Poll every 15 seconds
    
    // Check for attendance verification updates
    async function checkAttendanceUpdates() {
        try {
            const url = 'api/check_attendance.php?since=' + encodeURIComponent(lastAttendanceCheck || '') + '&student_id=<?= htmlspecialchars($student_id) ?>';
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success && data.latest_timestamp) {
                if (lastAttendanceCheck && data.latest_timestamp > lastAttendanceCheck) {
                    // Attendance was verified
                    showNotification('Your attendance has been verified! Refreshing...', 'success');
                    setTimeout(() => location.reload(), 1500);
                }
                lastAttendanceCheck = data.latest_timestamp;
            }
        } catch (err) {
            console.error('Error checking attendance updates:', err);
        }
    }
    
    // Check for project submission status updates
    async function checkProjectUpdates() {
        try {
            const url = 'api/check_updates.php?since=' + encodeURIComponent(lastUpdatesCheck || '') + '&type=project&student_id=<?= htmlspecialchars($student_id) ?>';
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success && data.projects && data.projects.has_updates) {
                // New project updates
                showNotification('Your project submission has been graded! Refreshing...', 'info');
                setTimeout(() => location.reload(), 1500);
            }
            
            // Update last check timestamp
            if (data.projects?.latest_timestamp) {
                lastUpdatesCheck = data.projects.latest_timestamp;
            }
        } catch (err) {
            console.error('Error checking project updates:', err);
        }
    }
    
    // Show notification
    function showNotification(message, type) {
        // Remove any existing notifications first
        const existing = document.querySelector('.polling-notification');
        if (existing) existing.remove();
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'alert alert-' + type + ' alert-dismissible fade show polling-notification';
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.minWidth = '300px';
        notification.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            notification.remove();
        }, 5000);
    }
    
    // Initialize polling when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Initial check
        checkAttendanceUpdates();
        checkProjectUpdates();
        
        // Set up polling intervals
        setInterval(checkAttendanceUpdates, POLL_INTERVAL);
        setInterval(checkProjectUpdates, POLL_INTERVAL);
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
    document.getElementById('editorPreview').srcdoc = '';
    document.getElementById('submissionFile').value = '';

    switchSubmissionTab('code');

    setTimeout(() => {
        if (window.codeEditor && typeof window.codeEditor.setValue === 'function') {
            window.codeEditor.setValue(`<?php echo htmlspecialchars($defaultCode); ?>`);
        } else {
            initCodeEditor();
        }

        const runBtn = document.getElementById('runCodeBtn');
        if (runBtn) {
            const newRunBtn = runBtn.cloneNode(true);
            runBtn.parentNode.replaceChild(newRunBtn, runBtn);
            newRunBtn.addEventListener('click', runCodePreview);
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
    event.preventDefault();
    if (window.codeEditor && typeof window.codeEditor.getValue === 'function') {
        const code = window.codeEditor.getValue();
        const iframe = document.getElementById('editorPreview');
        iframe.srcdoc = code;
    } else {
        console.error('Code editor not initialized yet.');
    }
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
        window.fullscreenEditor = CodeMirror.fromTextArea(document.getElementById('fullscreenEditor'), {
            lineNumbers: true,
            theme: 'default',
            tabSize: 4,
            lineWrapping: true,
            matchBrackets: true,
            autoCloseBrackets: true,
            mode: 'htmlmixed',
            value: `<?php echo htmlspecialchars($defaultCode); ?>`
        });
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
});
</script>

</body>
</html>