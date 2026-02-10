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
    ) AS name,
    email
FROM students WHERE student_id = ? LIMIT 1");
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

// Fetch employer name
$employer_name = "(assigned organization)";
$emp_stmt = $pdo->prepare("SELECT name FROM employers WHERE employer_id = ?");
$emp_stmt->execute([$employer_id]);
$emp = $emp_stmt->fetch(PDO::FETCH_ASSOC);
if ($emp) {
    $employer_name = $emp['name'];
}

// Check if signature exists
$signaturePath = 'assets/signature_' . $employer_id . '_' . $student_id . '.png';
$signatureExists = file_exists($signaturePath);

// Handle certificate generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate'])) {
        // Check if signature exists
        if (!$signatureExists) {
            header("Location: add_signature.php?student_id=$student_id");
            exit;
        }

        // Generate certificate
        class CertificatePDF extends FPDF {
            public $certificate_no;
            public $signaturePath;

            function Footer() {
                $innerMargin = 8;
                $baseY = $this->GetPageHeight() - $innerMargin - 10;

                // Certificate number
                $this->SetXY($innerMargin + 2, $baseY);
                $this->SetFont('Times','',9);
                $this->Cell(100, 5, 'Certificate No: ' . $this->certificate_no, 0, 0, 'L');

                // Signature
                if (!empty($this->signaturePath) && file_exists($this->signaturePath)) {
                    $sigWidth = 35;
                    $sigHeight = 12;
                    $sigX = $this->GetPageWidth() - $innerMargin - $sigWidth - 2;
                    $sigY = $baseY - $sigHeight - 2;

                    $this->Image($this->signaturePath, $sigX, $sigY, $sigWidth, $sigHeight);
                    
                    $lineY = $sigY + $sigHeight;
                    $this->SetLineWidth(0.3);
                    $this->Line($sigX, $lineY, $sigX + $sigWidth, $lineY);
                    
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

        // Design elements
        $pdf->SetLineWidth(1);
        $pdf->Rect(5, 5, $pdf->GetPageWidth()-10, $pdf->GetPageHeight()-10);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect(8, 8, $pdf->GetPageWidth()-16, $pdf->GetPageHeight()-16);

        // Header
        $logoPath = 'assets/school_logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 12, 22);
        }

        $companyLogoPath = 'assets/company_logo.png';
        if (file_exists($companyLogoPath)) {
            $pdf->Image($companyLogoPath, $pdf->GetPageWidth() - 37, 12, 22);
        }

        // School info
        $pdf->SetY(15);
        $pdf->SetFont('Times','B',16);
        $pdf->Cell(0, 8, 'School of Engineering and Technology', 0, 1, 'C');
        $pdf->SetFont('Times','I',12);
        $pdf->Cell(0, 6, 'Excellence in Practical Education', 0, 1, 'C');
        $pdf->Ln(8);

        // Title
        $pdf->SetFont('Times','B',24);
        $pdf->Cell(0, 12, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');
        $pdf->SetLineWidth(0.8);
        $pdf->Line(40, $pdf->GetY(), $pdf->GetPageWidth()-40, $pdf->GetY());
        $pdf->Ln(8);

        // Content
        $pdf->SetFont('Times','',12);
        $pdf->Cell(0, 10, 'This is to certify that', 0, 1, 'C');
        $pdf->Ln(3);

        $pdf->SetFont('Times','B',20);
        $pdf->Cell(0, 10, $student['name'], 0, 1, 'C');
        $pdf->Ln(6);

        $pdf->SetFont('Times','',12);
        $pdf->MultiCell(0, 6, "has successfully completed the required On-the-Job Training (OJT) / Internship Program\nwith outstanding dedication and professional competence,", 0, 'C');
        $pdf->Ln(3);

        $pdf->SetFont('Times','B',14);
        $pdf->Cell(0, 4, "equivalent to $hours hours and $minutes minutes of supervised practical training", 0, 1, 'C');
        $pdf->Ln(3);

        $pdf->SetFont('Times','',12);
        $pdf->Cell(0, 8, 'at', 0, 1, 'C');
        $pdf->SetFont('Times','B',14);
        $pdf->Cell(0, 8, $employer_name, 0, 1, 'C');
        $pdf->Ln(6);

        $pdf->SetFont('Times','',11);
        $pdf->Cell(0, 6, 'Given this ' . date('jS') . ' day of ' . date('F, Y'), 0, 1, 'C');

        // Save certificate to database for student access
        $certificateFileName = 'certificate_' . $student_id . '_' . time() . '.pdf';
        $certificatePath = 'certificates/' . $certificateFileName;
        
        // Create certificates directory if it doesn't exist
        if (!is_dir('certificates')) {
            mkdir('certificates', 0777, true);
        }
        
        // Save PDF to server
        $pdf->Output('F', $certificatePath);
        
        // Store certificate info in database
        $certStmt = $pdo->prepare("INSERT INTO certificates 
            (student_id, employer_id, certificate_no, file_path, hours_completed, generated_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            file_path = ?, hours_completed = ?, generated_at = NOW()");
        
        $total_hours = $hours + ($minutes / 60);
        $certStmt->execute([
            $student_id, $employer_id, $certificate_no, $certificatePath, $total_hours,
            $certificatePath, $total_hours
        ]);

        // Output for download
        $pdf->Output('D', 'certificate_' . $student_id . '.pdf');

        // Cleanup signature file after successful generation
        if (file_exists($signaturePath)) {
            unlink($signaturePath);
        }

        exit;
    }
    
    // Handle "Generate Another" action
    if (isset($_POST['another'])) {
        // Clean up signature file for new certificate
        if (file_exists($signaturePath)) {
            unlink($signaturePath);
        }
        // Redirect back to add signature for new certificate
        header("Location: add_signature.php?student_id=$student_id");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Certificate</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container { 
            max-width: 800px;
            width: 100%;
            background: white; 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .success-message::before {
            content: '✓';
            background: #155724;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .student-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 5px solid #667eea;
        }
        .student-info h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .info-item {
            background: white;
            padding: 12px 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .info-label {
            font-weight: 600;
            color: #555;
            display: block;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .info-value {
            color: #222;
            font-size: 1.1em;
        }
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        .btn-icon {
            font-size: 1.2em;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            margin-left: 10px;
        }
        .status-complete {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .back-link {
            margin-top: 25px;
            text-align: center;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="color: #333; text-align: center; margin-bottom: 30px;">
            <span style="color: #667eea;">Certificate</span> Generation
        </h2>
        
        <?php if ($signatureExists): ?>
        <div class="success-message">
            ✓ Signature added successfully. Ready to generate certificate!
        </div>
        <?php endif; ?>
        
        <div class="student-info">
            <h3>Student Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?= htmlspecialchars($student['name']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Hours Completed</span>
                    <span class="info-value"><?= $hours ?> hr <?= $minutes ?> min</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Supervisor</span>
                    <span class="info-value"><?= htmlspecialchars($employer_name) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <?= $signatureExists ? 'Ready for Generation' : 'Signature Required' ?>
                        <span class="status-badge <?= $signatureExists ? 'status-complete' : 'status-pending' ?>">
                            <?= $signatureExists ? '✓' : '!' ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <?php if (!$signatureExists): ?>
                <!-- No signature yet -->
                <a href="add_signature.php?student_id=<?= $student_id ?>" class="btn btn-primary">
                    <span class="btn-icon">✍️</span>
                    Add Signature
                </a>
            <?php else: ?>
                <!-- Signature exists - ready to generate -->
                <form method="post" style="grid-column: 1 / -1;">
                    <button type="submit" name="generate" class="btn btn-success" style="width: 100%;">
                        <span class="btn-icon">📄</span>
                        Generate PDF Certificate
                    </button>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                        <button type="submit" name="another" class="btn btn-primary">
                            <span class="btn-icon">🔄</span>
                            Generate Another Certificate
                        </button>
                        
                        <a href="supervisor_dashboard.php" class="btn btn-secondary">
                            <span class="btn-icon">←</span>
                            Back to Dashboard
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="back-link">
            <a href="supervisor_dashboard.php">
                ← Return to Supervisor Dashboard
            </a>
        </div>
    </div>
</body>
</html>