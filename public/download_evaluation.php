<?php
ob_start();
session_start();

include_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../lib/fpdf.php';

if (!isset($_SESSION['student_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: student_login.php");
    exit;
}

$student_id = (int) $_SESSION['student_id'];

$student_stmt = $pdo->prepare("
    SELECT
        student_id,
        CONCAT(
            first_name,
            IF(middle_name IS NOT NULL AND middle_name != '', CONCAT(' ', middle_name), ''),
            ' ',
            last_name
        ) AS full_name
    FROM students
    WHERE student_id = ?
    LIMIT 1
");
$student_stmt->execute([$student_id]);
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    $_SESSION['error'] = "Student record not found.";
    header("Location: student_dashboard.php");
    exit;
}

$evaluation_stmt = $pdo->prepare("
    SELECT e.*, em.name AS supervisor_name
    FROM evaluations e
    LEFT JOIN employers em ON em.employer_id = e.employer_id
    WHERE e.student_id = ?
    ORDER BY e.evaluation_date DESC
    LIMIT 1
");
$evaluation_stmt->execute([$student_id]);
$evaluation = $evaluation_stmt->fetch(PDO::FETCH_ASSOC);

if (!$evaluation) {
    $_SESSION['error'] = "No evaluation available yet.";
    header("Location: student_dashboard.php");
    exit;
}

$criteria = [
    'Attendance' => (int) ($evaluation['attendance_rating'] ?? 0),
    'Quality of Work' => (int) ($evaluation['work_quality_rating'] ?? 0),
    'Initiative' => (int) ($evaluation['initiative_rating'] ?? 0),
    'Communication' => (int) ($evaluation['communication_rating'] ?? 0),
    'Teamwork' => (int) ($evaluation['teamwork_rating'] ?? 0),
    'Adaptability' => (int) ($evaluation['adaptability_rating'] ?? 0),
    'Professionalism' => (int) ($evaluation['professionalism_rating'] ?? 0),
    'Problem Solving' => (int) ($evaluation['problem_solving_rating'] ?? 0),
    'Technical Skills' => (int) ($evaluation['technical_skills_rating'] ?? 0),
];

$average = round(array_sum($criteria) / count($criteria), 2);
$evaluation_date = !empty($evaluation['evaluation_date']) ? date('F d, Y', strtotime($evaluation['evaluation_date'])) : '-';
$comments = trim((string) ($evaluation['comments'] ?? ''));
if ($comments === '') {
    $comments = 'No comments provided.';
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'OJT Evaluation Report', 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(40, 7, 'Student:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, $student['full_name'], 0, 1, 'L');

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(40, 7, 'Supervisor:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, (string) ($evaluation['supervisor_name'] ?? 'N/A'), 0, 1, 'L');

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(40, 7, 'Evaluation Date:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, $evaluation_date, 0, 1, 'L');
$pdf->Ln(4);

$criteria_width = 135;
$score_width = 40;

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(235, 244, 255);
$pdf->Cell($criteria_width, 8, 'Criteria', 1, 0, 'L', true);
$pdf->Cell($score_width, 8, 'Rating (1-5)', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 11);
foreach ($criteria as $label => $score) {
    $pdf->Cell($criteria_width, 8, $label, 1, 0, 'L');
    $pdf->Cell($score_width, 8, (string) $score, 1, 1, 'C');
}

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell($criteria_width, 8, 'Average Rating', 1, 0, 'L');
$pdf->Cell($score_width, 8, number_format($average, 2) . '/5', 1, 1, 'C');
$pdf->Ln(6);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'Comments', 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 7, $comments, 1, 'L');

$filename = 'OJT_Evaluation_' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $student['full_name']) . '.pdf';

ob_end_clean();
$pdf->Output('D', $filename);
exit;

