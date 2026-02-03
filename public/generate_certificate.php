<?php
ob_start();
session_start();
include_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../lib/fpdf.php';

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== "employer") {
    header("Location: employer_login.php");
    exit;
}

$employer_id = (int)$_SESSION['employer_id'];

if (!isset($_GET['student_id'])) {
    header("Location: supervisor_dashboard.php");
    exit;
}

$student_id = (int)$_GET['student_id'];

// Verify the student is associated with this employer and has been evaluated
$eval_check = $pdo->prepare("SELECT * FROM evaluations WHERE student_id = ? AND employer_id = ?");
$eval_check->execute([$student_id, $employer_id]);
$evaluation = $eval_check->fetch(PDO::FETCH_ASSOC);

if (!$evaluation) {
    header("Location: supervisor_dashboard.php");
    exit;
}

// Fetch student info
$stmt = $pdo->prepare("SELECT *,
CONCAT(
    first_name,
    IF(middle_name IS NOT NULL AND middle_name != '', CONCAT(' ', middle_name), ''),
    ' ',
    last_name
) AS name
FROM students
WHERE student_id = ? LIMIT 1
");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header("Location: supervisor_dashboard.php");
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

// Removed hours check for testing purposes
// if ($hours < 200 && !isset($_GET['test_cert'])) {
//     header("Location: supervisor_dashboard.php");
//     exit;
// }

$employer_name = "Not Assigned";

if ($employer_name === "Not Assigned") {
    $employer_name = "(assigned organization)";
}
$emp_stmt = $pdo->prepare("SELECT name FROM employers WHERE employer_id = (SELECT employer_id FROM attendance WHERE student_id = ? AND employer_id IS NOT NULL LIMIT 1)");
$emp_stmt->execute([$student_id]);
$emp = $emp_stmt->fetch(PDO::FETCH_ASSOC);
if ($emp) {
    $employer_name = $emp['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {

    // Signature is now handled in add_signature.php, so we just use the existing file
    $signaturePath = 'assets/signature_' . $employer_id . '_' . $student_id . '.png';

    // Check if signature exists
    if (!file_exists($signaturePath)) {
        header("Location: add_signature.php?student_id=$student_id");
        exit;
    }

    class CertificatePDF extends FPDF {
    public $certificate_no;
    public $signaturePath;

    function Footer() {

        $innerMargin = 8;

        // Base line aligned with inner border
        $baseY = $this->GetPageHeight() - $innerMargin - 10;

        // ===== Certificate number (bottom-left) =====
        $this->SetXY($innerMargin + 2, $baseY);
        $this->SetFont('Times','',9);
        $this->Cell(100, 5, 'Certificate No: ' . $this->certificate_no, 0, 0, 'L');

        // ===== Signature (bottom-right) =====
        if (!empty($this->signaturePath) && file_exists($this->signaturePath)) {

            $sigWidth  = 35;
            $sigHeight = 12;

            // Position signature safely ABOVE the baseline
            $sigX = $this->GetPageWidth() - $innerMargin - $sigWidth - 2;
            $sigY = $baseY - $sigHeight - 2;

            // Signature image
            $this->Image($this->signaturePath, $sigX, $sigY, $sigWidth, $sigHeight);

            // Signature line
            $lineY = $sigY + $sigHeight;
            $this->SetLineWidth(0.3);
            $this->Line($sigX, $lineY, $sigX + $sigWidth, $lineY);

            // Labels
            $this->SetXY($sigX, $lineY + 1);
            $this->SetFont('Times','',8);
            $this->Cell($sigWidth, 4, 'Authorized Signature', 0, 2, 'C');
            $this->Cell($sigWidth, 4, 'Supervisor', 0, 0, 'C');
        }
    }

}

    $certificate_no = 'CERT-' . date('Y') . '-' . $student_id . '-' . str_pad($employer_id, 3, '0', STR_PAD_LEFT);

    $pdf = new CertificatePDF('L', 'mm', 'A4');
    $pdf->certificate_no = $certificate_no;
    $pdf->signaturePath = $signaturePath;
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    // Add minimal decorative border to use full page
    $pdf->SetLineWidth(1);
    $pdf->Rect(5, 5, $pdf->GetPageWidth()-10, $pdf->GetPageHeight()-10);
    $pdf->SetLineWidth(0.2);
    $pdf->Rect(8, 8, $pdf->GetPageWidth()-16, $pdf->GetPageHeight()-16);

    // Header with logos - positioned for full page usage
    $logoPath = 'assets/school_logo.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 15, 12, 22);
    }

    $companyLogoPath = 'assets/company_logo.png';
    if (file_exists($companyLogoPath)) {
        $pdf->Image($companyLogoPath, $pdf->GetPageWidth() - 37, 12, 22);
    }

    // School name header - positioned for full page usage
    $pdf->SetY(15);
    $pdf->SetFont('Times','B',16);
    $pdf->Cell(0, 8, 'School of Engineering and Technology', 0, 1, 'C');
    $pdf->SetFont('Times','I',12);
    $pdf->Cell(0, 6, 'Excellence in Practical Education', 0, 1, 'C');

    $pdf->Ln(8);

    // Certificate title with decorative line - optimized for landscape
    $pdf->SetFont('Times','B',24);
    $pdf->Cell(0, 12, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');

    // Decorative line - adjusted for landscape width
    $pdf->SetLineWidth(0.8);
    $pdf->Line(40, $pdf->GetY(), $pdf->GetPageWidth()-40, $pdf->GetY());
    $pdf->Ln(8);

    // Main certificate text - adjusted for landscape
    $pdf->SetFont('Times','',12);
    $pdf->Cell(0, 10, 'This is to certify that', 0, 1, 'C');
    $pdf->Ln(3);

    // Student name in larger, bold font - optimized for landscape
    $pdf->SetFont('Times','B',20);
    $pdf->Cell(0, 10, $student['name'], 0, 1, 'C');
    $pdf->Ln(6);

    // Achievement description - adjusted for landscape width
    $pdf->SetFont('Times','',12);
    $pdf->MultiCell(0, 6, "has successfully completed the required On-the-Job Training (OJT) / Internship Program\nwith outstanding dedication and professional competence,", 0, 'C');
    $pdf->Ln(3);

    // Hours completed - adjusted for landscape
    $pdf->SetFont('Times','B',14);
    $pdf->Cell(0, 4, "equivalent to $hours hours and $minutes minutes of supervised practical training", 0, 1, 'C');
    $pdf->Ln(3);

    // Employer information - adjusted for landscape
    $pdf->SetFont('Times','',12);
    $pdf->Cell(0, 8, 'at', 0, 1, 'C');
    $pdf->SetFont('Times','B',14);
    $pdf->Cell(0, 8, $employer_name, 0, 1, 'C');
    $pdf->Ln(6);

    // Date - adjusted for landscape
    $pdf->SetFont('Times','',11);
    $pdf->Cell(0, 6, 'Given this ' . date('jS') . ' day of ' . date('F, Y'), 0, 1, 'C');

    // Certificate number at bottom left and signature at bottom right - same line

    $pdf->Output('D', 'certificate_' . $student_id . '.pdf');

    if (!empty($signaturePath) && file_exists($signaturePath)) {
        unlink($signaturePath);
    }

    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Certificate</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Generate Completion Certificate</h2>
        <p><strong>Student:</strong> <?= htmlspecialchars($student['name']) ?></p>
        <p><strong>Hours Completed:</strong> <?= $hours ?> hr <?= $minutes ?> min</p>
        <p><strong>Employer:</strong> <?= htmlspecialchars($employer_name) ?></p>

        <form method="post">
            <button type="submit" name="generate" class="btn">Generate PDF Certificate</button>
        </form>

        <br><a href="supervisor_dashboard.php
">Back to Dashboard</a>
    </div>
</body>
</html>
