<?php
ob_start();
session_start();
include_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../lib/fpdf.php';
require_once __DIR__ . '/../includes/email.php';

if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== "student") {
    header("Location: student_login.php");
    exit;
}

$student_id = (int)$_SESSION['student_id'];

// Fetch student info
$stmt = $pdo->prepare("SELECT *,
    CONCAT(
        first_name,
        IF(middle_name IS NOT NULL AND middle_name != '', CONCAT(' ', middle_name), ''),
        ' ',
        last_name
    ) AS name,
    email
FROM students WHERE student_id = ? LIMIT 1");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header("Location: student_dashboard.php");
    exit;
}

// Calculate total hours
$attendance_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY log_date DESC");
$attendance_stmt->execute([$student_id]);
$attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

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

$hours = floor($total_minutes / 60);
$minutes = $total_minutes % 60;

// Check if student has completed required hours (temporarily set to 0 for demo/testing)
if ($hours < 0) {
    $_SESSION['error'] = "You must complete at least 200 hours to download your certificate.";
    header("Location: student_dashboard.php");
    exit;
}

// Fetch employer name
$employer_name = "(assigned organization)";
$emp_stmt = $pdo->prepare("SELECT name FROM employers WHERE employer_id = ?");
$emp_stmt->execute([$student['employer_id']]);
$emp = $emp_stmt->fetch(PDO::FETCH_ASSOC);
if ($emp) {
    $employer_name = $emp['name'];
}

// Fetch existing certificate from database
$cert_stmt = $pdo->prepare("SELECT * FROM certificates WHERE student_id = ? ORDER BY generated_at DESC LIMIT 1");
$cert_stmt->execute([$student_id]);
$certificate = $cert_stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    $_SESSION['error'] = "No certificate available. Please contact your supervisor to generate your certificate.";
    header("Location: student_dashboard.php");
    exit;
}

// Serve the existing certificate from database
$certificatePath = $certificate['file_path'];

if (!file_exists($certificatePath)) {
    $_SESSION['error'] = "Certificate file not found. Please contact your supervisor.";
    header("Location: student_dashboard.php");
    exit;
}

// Output the existing certificate file
ob_end_clean();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($certificatePath) . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($certificatePath);
exit;

// Send evaluation notification email
send_evaluation_notification($student['email'], $student['name'], $student_id);

// Output PDF
ob_end_clean();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="OJT_Certificate_' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $student['name']) . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
$pdf->Output('D');
exit;
?>
