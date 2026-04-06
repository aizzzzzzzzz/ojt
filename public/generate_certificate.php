<?php
ob_start();
session_start();
include_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../lib/fpdf.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/Blockchain.php';

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

$eval_check = $pdo->prepare("SELECT * FROM evaluations WHERE student_id = ? AND employer_id = ?");
$eval_check->execute([$student_id, $employer_id]);
$evaluation = $eval_check->fetch(PDO::FETCH_ASSOC);

if (!$evaluation) {
    header("Location: supervisor_dashboard.php");
    exit;
}

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

$attendance_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY log_date DESC");
$attendance_stmt->execute([$student_id]);
$attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_minutes = 0;
foreach ($attendance as $row) {
    if ($row['verified'] == 1 && !empty($row['time_in']) && !empty($row['time_out'])) {
        $time_in = strtotime($row['time_in']);
        $time_out = strtotime($row['time_out']);
        $minutesWorked = max(0, ($time_out - $time_in) / 60);
        // Auto-deduct 60 minutes if shift is greater than 4 hours (240 minutes)
        if ($minutesWorked > 240) {
            $minutesWorked -= 60;
        }
        $total_minutes += max(0, $minutesWorked);
    }
}

$hours = floor($total_minutes / 60);
$minutes = $total_minutes % 60;

$employer_name = "(assigned organization)";
$supervisor_name = "(supervisor name)";
$emp_stmt = $pdo->prepare("SELECT name, company, company_id FROM employers WHERE employer_id = ?");
$emp_stmt->execute([$employer_id]);
$emp = $emp_stmt->fetch(PDO::FETCH_ASSOC);
if ($emp) {
    $employer_name = $emp['company'];
    $supervisor_name = $emp['name'];
}

$company_logo_path = '';
$company_logo_candidates = [];
if (!empty($emp) && !empty($emp['company_id'])) {
    $company_logo_candidates[] = 'assets/company_logo_company_' . $emp['company_id'];
}
$company_logo_candidates[] = 'assets/company_logo_employer_' . $employer_id;
$company_logo_candidates[] = 'assets/company_logo';
$company_logo_exts = ['png', 'jpg', 'jpeg'];
foreach ($company_logo_candidates as $candidate) {
    foreach ($company_logo_exts as $ext) {
        $candidate_path = $candidate . '.' . $ext;
        if (file_exists($candidate_path)) {
            $company_logo_path = $candidate_path;
            break 2;
        }
    }
}

$signaturePath = '';
if (!empty($evaluation['signature_path'])) {
    $signaturePath = $evaluation['signature_path'];
} else {
    $signaturePath = 'assets/signature_' . $employer_id . '_' . $student_id . '.png';
}
$signatureExists = !empty($signaturePath) && file_exists($signaturePath);

$certificateHashColumns = [];
try {
    $columnStmt = $pdo->query("SHOW COLUMNS FROM certificate_hashes");
    $certificateHashColumns = array_column($columnStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
} catch (Throwable $e) {
    error_log("Could not load certificate_hashes columns: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate'])) {

        class CertificatePDF extends FPDF {
            public $certificate_no;
            public $signaturePath;
            public $supervisorName;

            function Footer() {
                $innerMargin = 8;
                $baseY = $this->GetPageHeight() - $innerMargin - 10;

                $this->SetXY($innerMargin + 2, $baseY);
                $this->SetFont('Times','',9);
                $this->Cell(100, 5, 'Certificate No: ' . $this->certificate_no, 0, 0, 'L');

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
                    $this->Cell($sigWidth, 4, $this->supervisorName, 0, 0, 'C');
                }
            }
        }

        $certificate_no = 'CERT-' . date('Y') . '-' . $student_id . '-' . str_pad($employer_id, 3, '0', STR_PAD_LEFT);

        $pdf = new CertificatePDF('L', 'mm', 'A4');
        $pdf->certificate_no = $certificate_no;
        $pdf->signaturePath = $signaturePath;
        $pdf->supervisorName = $supervisor_name;
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $pdf->SetLineWidth(1);
        $pdf->Rect(5, 5, $pdf->GetPageWidth()-10, $pdf->GetPageHeight()-10);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect(8, 8, $pdf->GetPageWidth()-16, $pdf->GetPageHeight()-16);

        $logoPath = 'assets/school_logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 12, 22);
        }

        if (!empty($company_logo_path) && file_exists($company_logo_path)) {
            $companyLogoSize = 30;
            $companyLogoX = $pdf->GetPageWidth() - 15 - $companyLogoSize;
            $pdf->Image($company_logo_path, $companyLogoX, 12, $companyLogoSize);
        }

        $pdf->SetY(15);
        $pdf->SetFont('Times','B',16);
        $pdf->Cell(0, 8, 'School of Engineering and Technology', 0, 1, 'C');
        $pdf->SetFont('Times','I',12);
        $pdf->Cell(0, 6, 'Excellence in Practical Education', 0, 1, 'C');
        $pdf->Ln(8);

        $pdf->SetFont('Times','B',24);
        $pdf->Cell(0, 12, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');
        $pdf->SetLineWidth(0.8);
        $pdf->Line(40, $pdf->GetY(), $pdf->GetPageWidth()-40, $pdf->GetY());
        $pdf->Ln(8);

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
        $pdf->Cell(0, 4, "200 hours of supervised practical training", 0, 1, 'C');
        $pdf->Ln(3);

        $pdf->SetFont('Times','',12);
        $pdf->Cell(0, 8, 'at', 0, 1, 'C');
        $pdf->SetFont('Times','B',14);
        $pdf->Cell(0, 8, $employer_name, 0, 1, 'C');
        $pdf->Ln(6);

        $pdf->SetFont('Times','',11);
        $pdf->Cell(0, 6, 'Given this ' . date('jS') . ' day of ' . date('F, Y'), 0, 1, 'C');

        $certificateFileName = 'certificate_' . $student_id . '_' . time() . '.pdf';
        $certificatePath = 'certificates/' . $certificateFileName;
        
        if (!is_dir('certificates')) {
            mkdir('certificates', 0777, true);
        }
        
        $pdf->Output('F', $certificatePath);
        
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

        $hashStmt = $pdo->prepare("INSERT INTO certificate_hashes (student_id, certificate_hash, generated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE certificate_hash = VALUES(certificate_hash), generated_at = NOW()");
        $hashStmt->execute([$student_id, $certificate_no]);

        $blockchain = new Blockchain();
        $blockchainResult = $blockchain->addCertificate(
            $student_id,
            $certificate_no,
            $student['name'],
            $employer_name,
            $total_hours
        );

        $dataHash = Blockchain::buildCertificateHash(
            $student_id,
            $certificate_no,
            $student['name'],
            $employer_name,
            $total_hours
        );

        $optionalUpdates = [];
        $optionalParams = [];

        if (in_array('data_hash', $certificateHashColumns, true)) {
            $optionalUpdates[] = "data_hash = ?";
            $optionalParams[] = $dataHash;
        }

        if (in_array('chain_status', $certificateHashColumns, true)) {
            $optionalUpdates[] = "chain_status = ?";
            $optionalParams[] = $blockchainResult['chain_status'] ?? 'pending';
        }

        if (in_array('tx_hash', $certificateHashColumns, true)) {
            $optionalUpdates[] = "tx_hash = ?";
            $optionalParams[] = $blockchainResult['tx_hash'] ?? null;
        }

        if (in_array('chain_network', $certificateHashColumns, true)) {
            $optionalUpdates[] = "chain_network = ?";
            $optionalParams[] = $blockchainResult['network'] ?? 'sepolia';
        }

        if (in_array('anchored_at', $certificateHashColumns, true)) {
            if (($blockchainResult['chain_status'] ?? '') === 'confirmed') {
                $optionalUpdates[] = "anchored_at = NOW()";
            }
        }

        if (!empty($optionalUpdates)) {
            $optionalParams[] = $student_id;
            $updateSql = "UPDATE certificate_hashes SET " . implode(', ', $optionalUpdates) . " WHERE student_id = ?";
            try {
                $optionalStmt = $pdo->prepare($updateSql);
                $optionalStmt->execute($optionalParams);
            } catch (Throwable $e) {
                error_log("Optional blockchain metadata update failed: " . $e->getMessage());
            }
        }
        
        error_log("Blockchain addCertificate result: " . json_encode($blockchainResult));

        if (!empty($student['email'])) {
            $capitalized_student_name = ucwords(strtolower($student['name']));
            $email_result = send_certificate_notification($student['email'], $capitalized_student_name, $supervisor_name, $certificate_no);
            if ($email_result !== true) {
                error_log("Failed to send certificate notification: " . $email_result);
            }
        }

        
        $_SESSION['success_message'] = "Certificate generated successfully and notification email sent to student!";

        // Log supervisor certificate generation to audit_logs
        audit_log($pdo, 'Generate Certificate', "Certificate generated for student ID: $student_id, Certificate No: $certificate_no");

        header("Location: supervisor_dashboard.php");
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
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;
            --text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;
            --accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;
            --red-lt:#fee2e2;--amber:#d97706;--amber-lt:#fef3c7;
            --radius:14px;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'DM Sans', 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            margin: 0;
            padding: 32px 20px 60px;
            line-height: 1.6;
        }
        .page-card {
            max-width: 760px;
            width: 100%;
            margin: 0 auto;
            background: var(--surface);
            border-radius: 20px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        .page-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 28px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-topbar h2 { font-size: 18px; font-weight: 700; margin: 0; letter-spacing: -.3px; }
        .page-topbar p  { font-size: 13px; color: var(--text-muted); margin: 2px 0 0; }
        .page-inner { padding: 24px 28px 32px; }
        .success-msg {
            background: var(--green-lt); color: #15803d;
            padding: 12px 16px; border-radius: 10px;
            border: 1px solid #bbf7d0; font-size: 14px;
            font-weight: 500; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .warning-msg {
            background: var(--amber-lt); color: var(--amber);
            padding: 12px 16px; border-radius: 10px;
            border: 1px solid #fde68a; font-size: 14px;
            font-weight: 500; margin-bottom: 20px;
        }
        .info-block {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 24px;
        }
        .info-block h3 {
            font-size: 14px; font-weight: 700; margin: 0 0 14px;
            padding-bottom: 10px; border-bottom: 1px solid var(--border);
            color: var(--text);
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .info-item {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
        }
        .info-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .6px; color: var(--text-muted);
            display: block; margin-bottom: 4px;
        }
        .info-value { font-size: 15px; font-weight: 600; color: var(--text); }
        .status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 600; margin-left: 6px;
        }
        .status-complete { background: var(--green-lt); color: var(--green); }
        .status-pending  { background: var(--amber-lt); color: var(--amber); }
        .btn-group { display: flex; flex-direction: column; gap: 10px; margin-top: 4px; }
        .btn {
            font-family: inherit; font-size: 14px; font-weight: 600;
            border-radius: 9px; padding: 11px 20px; transition: all .18s;
            cursor: pointer; display: inline-flex; align-items: center;
            justify-content: center; gap: 8px; border: none; text-decoration: none;
            width: 100%;
        }
        .btn-primary   { background: var(--accent);  color: #fff; }
        .btn-primary:hover   { background: var(--accent-dk); transform: translateY(-1px); color: #fff; }
        .btn-success   { background: var(--green);   color: #fff; }
        .btn-success:hover   { background: #15803d;  transform: translateY(-1px); color: #fff; }
        .btn-secondary { background: var(--surface2); color: var(--text); border: 1.5px solid var(--border); }
        .btn-secondary:hover { background: var(--border); }
        .btn-outline-secondary {
            background: transparent; color: var(--text-muted);
            border: 1.5px solid var(--border); border-radius: 9px;
            padding: 7px 14px; font-size: 13px; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center;
            gap: 6px; text-decoration: none; font-family: inherit; transition: all .18s;
        }
        .btn-outline-secondary:hover { background: var(--surface2); color: var(--text); }
        @media (max-width: 600px) {
            body { padding: 10px 10px 40px; }
            .page-card { border-radius: 14px; }
            .page-topbar, .page-inner { padding: 14px 16px; }
            .btn-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2>Generate Certificate</h2>
            <p>Review student information and generate the PDF certificate</p>
        </div>
        <a href="supervisor_dashboard.php" class="btn-outline-secondary">⬅ Back</a>
    </div>
    <div class="page-inner">

        <?php if ($signatureExists): ?>
            <div class="success-msg">✓ Supervisor signature on file — ready to generate.</div>
        <?php else: ?>
            <div class="warning-msg">⚠️ No signature found. The certificate will be generated without a signature.</div>
        <?php endif; ?>

        <div class="info-block">
            <h3>Student Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?= htmlspecialchars(ucwords(strtolower($student['name']))) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Hours Completed</span>
                    <span class="info-value"><?= $hours ?> hr <?= $minutes ?> min</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Supervisor</span>
                    <span class="info-value"><?= htmlspecialchars($supervisor_name) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <?= $signatureExists ? 'Ready' : 'Signature Missing' ?>
                        <span class="status-badge <?= $signatureExists ? 'status-complete' : 'status-pending' ?>">
                            <?= $signatureExists ? '✓ Signed' : '! Unsigned' ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <form method="post">
            <button type="submit" name="generate" class="btn btn-success">
                📄 Generate PDF Certificate
            </button>
        </form>

    </div>
</div>
</body>
</html>