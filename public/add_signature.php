<?php
include_once __DIR__ . '/../private/config.php';

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

$employer_name = "Not Assigned";
$emp_stmt = $pdo->prepare("SELECT name FROM employers WHERE employer_id = (SELECT employer_id FROM attendance WHERE student_id = ? AND employer_id IS NOT NULL LIMIT 1)");
$emp_stmt->execute([$student_id]);
$emp = $emp_stmt->fetch(PDO::FETCH_ASSOC);
if ($emp) {
    $employer_name = $emp['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_signature'])) {
    $signaturePath = 'assets/signature_' . $employer_id . '_' . $student_id . '.png';
    $signature_saved = false;

    if (!empty($_POST['signature_data'])) {
        $data = $_POST['signature_data'];
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $decoded_data = base64_decode($data);
            if ($decoded_data !== false && !empty($decoded_data)) {
                if (!is_dir('assets')) {
                    mkdir('assets', 0777, true);
                }
                file_put_contents($signaturePath, $decoded_data);
                $signature_saved = true;
            }
        }
    }

    elseif (!empty($_FILES['signature_file']['tmp_name'])) {
        $upload_error = '';
        if ($_FILES['signature_file']['error'] !== UPLOAD_ERR_OK) {
            $upload_error = 'File upload error: ' . $_FILES['signature_file']['error'];
        } else {
            $imgInfo = getimagesize($_FILES['signature_file']['tmp_name']);
            if (!$imgInfo) {
                $upload_error = 'Invalid image file.';
            } else {
                if (!is_dir('assets')) {
                    mkdir('assets', 0777, true);
                }
                switch ($imgInfo['mime']) {
                    case 'image/jpeg':
                        $src = imagecreatefromjpeg($_FILES['signature_file']['tmp_name']);
                        break;
                    case 'image/png':
                        $src = imagecreatefrompng($_FILES['signature_file']['tmp_name']);
                        break;
                    default:
                        $src = null;
                }
                if ($src) {
                    $w = imagesx($src);
                    $h = imagesy($src);
                    $dst = imagecreatetruecolor($w, $h);
                    if (!$dst) {
                        $upload_error = 'Failed to create image resource.';
                    } else {
                        $white = imagecolorallocate($dst, 255, 255, 255);
                        if ($white === false) {
                            $upload_error = 'Failed to allocate color.';
                        } else {
                            imagefill($dst, 0, 0, $white);
                            if (!imagecopy($dst, $src, 0, 0, 0, 0, $w, $h)) {
                                $upload_error = 'Failed to copy image.';
                            } else {
                                if (!imagepng($dst, $signaturePath)) {
                                    $upload_error = 'Failed to save image.';
                                } else {
                                    $signature_saved = true;
                                }
                            }
                            imagedestroy($dst);
                        }
                    }
                    imagedestroy($src);
                } else {
                    $upload_error = 'Unsupported image format. Supported formats: JPEG, PNG.';
                }
            }
        }
        if (!empty($upload_error)) {
            echo "<p style='color: red;'>Error: " . htmlspecialchars($upload_error) . "</p>";
            echo "<br><a href='add_signature.php?student_id=" . htmlspecialchars($student_id) . "'>Back</a>";
            exit;
        }
    }

    if ($signature_saved) {
        
        
        require_once __DIR__ . '/../lib/fpdf.php';
        require_once __DIR__ . '/../includes/email.php';
        
        
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
        
        
        $emp_stmt = $pdo->prepare("SELECT name, company FROM employers WHERE employer_id = ?");
        $emp_stmt->execute([$employer_id]);
        $emp = $emp_stmt->fetch(PDO::FETCH_ASSOC);
        
        $employer_name = $emp['company'] ?? '(assigned organization)';
        $supervisor_name = $emp['name'] ?? '(supervisor name)';
        
        
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

        $companyLogoPath = 'assets/company_logo.png';
        if (file_exists($companyLogoPath)) {
            $pdf->Image($companyLogoPath, $pdf->GetPageWidth() - 37, 12, 22);
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

        
        if (!empty($student['email'])) {
            $capitalized_student_name = ucwords(strtolower($student['name']));
            $email_result = send_evaluation_notification($student['email'], $capitalized_student_name, $supervisor_name);
            if ($email_result !== true) {
                error_log("Failed to send certificate notification: " . $email_result);
            }
        }

        
        if (file_exists($signaturePath)) {
            unlink($signaturePath);
        }

        
        $_SESSION['success_message'] = "Certificate generated successfully and notification email sent to student!";
        header("Location: supervisor_dashboard.php");
        exit;
    } else {
        $error = "Please draw a signature on the canvas or upload a signature file.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Signature</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .signature-pad { border: 1px solid #ccc; border-radius: 5px; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add Signature for Certificate</h2>
        <p><strong>Student:</strong> <?= htmlspecialchars($student['name']) ?></p>
        <p><strong>Hours Completed:</strong> <?= $hours ?> hr <?= $minutes ?> min</p>
        <p><strong>Employer:</strong> <?= htmlspecialchars($employer_name) ?></p>
        <?php if (isset($error)): ?>
            <p style="color: red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <h4>Draw Signature</h4>
            <canvas id="signature-pad" class="signature-pad" width="400" height="200"></canvas><br>
            <button type="button" onclick="clearSignature()">Clear</button><br><br>
            <input type="hidden" name="signature_data" id="signature-data">
            <button type="submit" name="add_signature" class="btn">Add Signature</button>
        </form>
        <br><a href="supervisor_dashboard.php">Back</a>
    </div>
    <script>
        const canvas = document.getElementById('signature-pad');
        const ctx = canvas.getContext('2d');
        const blankDataURL = canvas.toDataURL();
        let drawing = false;
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        function startDrawing(e) {
            drawing = true;
            ctx.beginPath();
            ctx.moveTo(e.offsetX, e.offsetY);
        }
        function draw(e) {
            if (!drawing) return;
            ctx.lineTo(e.offsetX, e.offsetY);
            ctx.stroke();
        }
        function stopDrawing() {
            drawing = false;
        }
        function clearSignature() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        document.querySelector('form').addEventListener('submit', function(e) {
            const dataURL = canvas.toDataURL();
            if (dataURL === blankDataURL) {
                e.preventDefault();
                alert('Please draw a signature on the canvas before submitting.');
                return false;
            }
            document.getElementById('signature-data').value = dataURL;
        });
    </script>
</body>
</html>
