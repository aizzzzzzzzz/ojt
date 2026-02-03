<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/../private/config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== "student") {
    header("Location: student_login.php");
    exit;
}

$student_id = (int)$_SESSION['student_id'];

// Fetch student info
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch attendance history
$attendance_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY log_date DESC");
$attendance_stmt->execute([$student_id]);
$attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator("OJT System")
    ->setLastModifiedBy("OJT System")
    ->setTitle("Attendance History - " . ($student['first_name'] ?? 'Student'))
    ->setSubject("Attendance Records");

// Set headers
$sheet->setCellValue('A1', 'Attendance History for ' . ($student['first_name'] . ' ' . $student['last_name']));
$sheet->mergeCells('A1:I1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

$headers = ['Date', 'Time In', 'Lunch Out', 'Lunch In', 'Time Out', 'Status', 'Verified', 'Hours Worked', 'Daily Task'];
$sheet->fromArray($headers, NULL, 'A3');

// Style headers
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']],
    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
];
$sheet->getStyle('A3:I3')->applyFromArray($headerStyle);

// Add data
$row = 4;
foreach ($attendance as $record) {
    // Calculate hours
    $hours = '-';
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
    }
    
    $sheet->setCellValue('A' . $row, $record['log_date']);
    $sheet->setCellValue('B' . $row, (!empty($record['time_in']) && strpos($record['time_in'], '0000') === false) ? date('H:i:s', strtotime($record['time_in'])) : '-');
    $sheet->setCellValue('C' . $row, (!empty($record['lunch_out']) && strpos($record['lunch_out'], '0000') === false) ? date('H:i:s', strtotime($record['lunch_out'])) : '-');
    $sheet->setCellValue('D' . $row, (!empty($record['lunch_in']) && strpos($record['lunch_in'], '0000') === false) ? date('H:i:s', strtotime($record['lunch_in'])) : '-');
    $sheet->setCellValue('E' . $row, (!empty($record['time_out']) && strpos($record['time_out'], '0000') === false) ? date('H:i:s', strtotime($record['time_out'])) : '-');
    $sheet->setCellValue('F' . $row, $record['status'] ?? '-');
    $sheet->setCellValue('G' . $row, $record['verified'] == 1 ? 'Yes' : 'No');
    $sheet->setCellValue('H' . $row, $hours);
    $sheet->setCellValue('I' . $row, $record['daily_task'] ?? '-');
    
    // Add conditional formatting for verified status
    if ($record['verified'] == 1) {
        $sheet->getStyle('G' . $row)->getFont()->getColor()->setRGB('008000');
    } else {
        $sheet->getStyle('G' . $row)->getFont()->getColor()->setRGB('FF0000');
    }
    
    $row++;
}

// Auto-size columns
foreach (range('A', 'I') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// Add summary at the bottom
$sheet->setCellValue('A' . ($row + 2), 'Summary');
$sheet->mergeCells('A' . ($row + 2) . ':B' . ($row + 2));
$sheet->getStyle('A' . ($row + 2))->getFont()->setBold(true);

$sheet->setCellValue('A' . ($row + 3), 'Total Days:');
$sheet->setCellValue('B' . ($row + 3), count($attendance));

$sheet->setCellValue('A' . ($row + 4), 'Verified Days:');
$sheet->setCellValue('B' . ($row + 4), count(array_filter($attendance, function($a) { return $a['verified'] == 1; })));

// Set filename and output
$filename = 'attendance_' . ($student['first_name'] ?? 'student') . '_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;