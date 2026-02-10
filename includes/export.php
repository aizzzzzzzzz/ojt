<?php
// Export logic module for student dashboard

function handle_excel_export($pdo, $student_id, $start_date, $end_date) {
    // Clear any existing output
    if (ob_get_length()) ob_end_clean();

    // Validate date ranges and row limits
    if ($start_date && $end_date) {
        $start = strtotime($start_date);
        $end = strtotime($end_date);
        $now = time();

        if ($start > $end) {
            return "Start date cannot be after end date.";
        }

        if ($start > $now || $end > $now) {
            return "Cannot export future dates.";
        }

        // Limit date range to maximum 1 year
        if (($end - $start) > (365 * 24 * 60 * 60)) {
            return "Date range cannot exceed 1 year.";
        }
    }

    // Check if PhpSpreadsheet is available
    $phpspreadsheetPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($phpspreadsheetPath)) {
        return "Excel export feature requires PhpSpreadsheet library. Please contact administrator.";
    }

    require_once $phpspreadsheetPath;

    try {
        // Fetch student info
        $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch attendance history with optional date filter and row limit
        $sql = "SELECT * FROM attendance WHERE student_id = ?";
        $params = [$student_id];

        if ($start_date && $end_date) {
            $sql .= " AND log_date BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
        }

        $sql .= " ORDER BY log_date DESC LIMIT 1000"; // Row limit

        $attendance_stmt = $pdo->prepare($sql);
        $attendance_stmt->execute($params);
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

        // Set main title
        $sheet->setCellValue('A1', 'ATTENDANCE HISTORY REPORT');
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Student info
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

        // Set headers
        $headers = ['Date', 'Time In', 'Lunch Out', 'Lunch In', 'Time Out', 'Status', 'Verified', 'Hours Worked', 'Daily Task / Activity'];
        $sheet->fromArray($headers, NULL, 'A7');

        // Style headers
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

        // Add data
        $row = 8;
        $totalMinutes = 0;
        $verifiedCount = 0;

        foreach ($attendance as $record) {
            // Calculate hours
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

            // Add conditional formatting for verified status
            $verifiedStyle = $sheet->getStyle('G' . $row);
            if ($record['verified'] == 1) {
                $verifiedStyle->getFont()->getColor()->setRGB('008000');
            } else {
                $verifiedStyle->getFont()->getColor()->setRGB('FF6B6B');
            }

            // Add border to data rows
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

        // Add summary section
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

        // Style summary
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

        // Auto-size columns
        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Set column widths for specific columns
        $sheet->getColumnDimension('I')->setWidth(40); // Wider for tasks

        // Wrap text for task column
        $sheet->getStyle('I8:I' . ($row - 1))->getAlignment()->setWrapText(true);

        // Set filename
        $filename = 'Attendance_History_' . ($student['first_name'] ?? 'student') . '_' . date('Y-m-d') . '.xlsx';

        // Clear all output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1'); // For IE9
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');

        // Create and save file
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;

    } catch (Exception $e) {
        // Log detailed error
        error_log("Excel Export Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        return "Error generating Excel file. Please contact administrator.";
    }
}
?>
