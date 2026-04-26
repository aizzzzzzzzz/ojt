<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/audit.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$is_admin = isset($_SESSION['admin_id']) && $_SESSION['role'] === 'admin';
$is_student = isset($_SESSION['student_id']) && $_SESSION['role'] === 'student';

if (!$is_admin && !$is_student) {
    header("Location: login.php");
    exit;
}

$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_status = $_GET['status'] ?? '';

if ($is_admin && isset($_GET['student_id'])) {
    $student_id = (int)$_GET['student_id'];
} elseif ($is_student) {
    $student_id = (int)$_SESSION['student_id'];
} else {
    header("Location: login.php");
    exit;
}

if ($is_admin) {
    write_audit_log('Export Attendance', "Admin exported attendance for student ID: $student_id");
} else {
    log_activity('Export Attendance', "Exported attendance history to Excel");
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found.");
}

$attendance_query = "SELECT * FROM attendance WHERE student_id = ?";
$params = [$student_id];

if ($filter_date_from) {
    $attendance_query .= " AND log_date >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $attendance_query .= " AND log_date <= ?";
    $params[] = $filter_date_to;
}

if ($filter_status) {
    $attendance_query .= " AND status = ?";
    $params[] = $filter_status === 'present' ? 'Present' : 'Absent';
}

$attendance_query .= " ORDER BY log_date DESC";

$attendance_stmt = $pdo->prepare($attendance_query);
$attendance_stmt->execute($params);
$attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$spreadsheet->getProperties()
    ->setCreator("OJT System")
    ->setLastModifiedBy("OJT System")
    ->setTitle("Attendance History - " . ($student['first_name'] ?? 'Student'))
    ->setSubject("Attendance Records");

$sheet->setCellValue('A1', 'Attendance History for ' . ($student['first_name'] . ' ' . $student['last_name']));
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

$headers = ['Date', 'Time In', 'Time Out', 'Status', 'Verified', 'Hours Worked', 'Daily Task'];
$sheet->fromArray($headers, NULL, 'A3');

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']],
    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
];
$sheet->getStyle('A3:G3')->applyFromArray($headerStyle);

$row = 4;
foreach ($attendance as $record) {
    $hours = '-';
    if (!empty($record['time_in']) && !empty($record['time_out']) && 
        strpos($record['time_in'], '0000') === false && 
        strpos($record['time_out'], '0000') === false) {
        
        $minutesWorked = max(0, (strtotime($record['time_out']) - strtotime($record['time_in'])) / 60);
        
        if ($minutesWorked > 240) {
            $minutesWorked -= 60;
        }
        
        $hours = floor($minutesWorked / 60) . "h " . ($minutesWorked % 60) . "m";
    }
    
    $sheet->setCellValue('A' . $row, $record['log_date']);
    $sheet->setCellValue('B' . $row, (!empty($record['time_in']) && strpos($record['time_in'], '0000') === false) ? date('H:i:s', strtotime($record['time_in'])) : '-');
    $sheet->setCellValue('C' . $row, (!empty($record['time_out']) && strpos($record['time_out'], '0000') === false) ? date('H:i:s', strtotime($record['time_out'])) : '-');
    $sheet->setCellValue('D' . $row, $record['status'] ?? '-');
    $sheet->setCellValue('E' . $row, $record['verified'] == 1 ? 'Yes' : 'No');
    $sheet->setCellValue('F' . $row, $hours);
    $sheet->setCellValue('G' . $row, $record['daily_task'] ?? '-');
    
    if ($record['verified'] == 1) {
        $sheet->getStyle('E' . $row)->getFont()->getColor()->setRGB('008000');
    } else {
        $sheet->getStyle('E' . $row)->getFont()->getColor()->setRGB('FF0000');
    }
    
    $row++;
}

foreach (range('A', 'I') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

$sheet->setCellValue('A' . ($row + 2), 'Summary');
$sheet->mergeCells('A' . ($row + 2) . ':B' . ($row + 2));
$sheet->getStyle('A' . ($row + 2))->getFont()->setBold(true);

$sheet->setCellValue('A' . ($row + 3), 'Total Days:');
$sheet->setCellValue('B' . ($row + 3), count($attendance));

$sheet->setCellValue('A' . ($row + 4), 'Verified Days:');
$sheet->setCellValue('B' . ($row + 4), count(array_filter($attendance, function($a) { return $a['verified'] == 1; })));

$filename_parts = [];
if ($filter_date_from) $filename_parts[] = $filter_date_from;
if ($filter_date_to) $filename_parts[] = $filter_date_to;
$date_suffix = !empty($filename_parts) ? '_' . implode('-to-', $filename_parts) : '';

$filename = 'attendance_' . ($student['first_name'] ?? 'student') . '_' . date('Y-m-d') . $date_suffix . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;