<?php
session_start();
include_once __DIR__ . '/../private/config.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== "student") {
    header("Location: student_login.php");
    exit;
}

$student_id = (int)$_SESSION['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_id']) && isset($_POST['output_html'])) {
    $project_id = (int)$_POST['project_id'];
    $output_html = $_POST['output_html'];

    require_once __DIR__ . '/../lib/mpdf/vendor/autoload.php';

    $mpdf = new \Mpdf\Mpdf([
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10
    ]);

    $mpdf->WriteHTML('<h2>Project Output</h2><hr>');
    $mpdf->WriteHTML($output_html);

    $uploadDir = __DIR__ . '/../storage/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileName = $student_id . '_project_' . $project_id . '_pdf_' . time() . '.pdf';
    $filePath = $uploadDir . $fileName;

    $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);

    $stmt = $pdo->prepare("INSERT INTO project_submissions (project_id, student_id, file_path, status, submission_date, remarks, submission_status) VALUES (?, ?, ?, 'submitted', NOW(), ?, 'pending')");
    $stmt->execute([$project_id, $student_id, $fileName, 'Generated PDF output only']);

    $_SESSION['success'] = "Project submitted successfully!";
    header("Location: student_dashboard.php");
    exit;

} else {
    header("Location: student_dashboard.php");
    exit;
}
