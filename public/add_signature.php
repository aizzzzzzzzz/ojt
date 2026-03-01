<?php
session_start();
include_once __DIR__ . '/../private/config.php';

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== "employer") {
    header("Location: employer_login.php");
    exit;
}

$employer_id = (int)$_SESSION['employer_id'];
$error = '';

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
    $upload_error = '';

    if (!empty($_POST['signature_data'])) {
        $data = $_POST['signature_data'];
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $decoded_data = base64_decode($data, true);
            if ($decoded_data !== false && !empty($decoded_data)) {
                if (!is_dir('assets')) {
                    mkdir('assets', 0777, true);
                }
                file_put_contents($signaturePath, $decoded_data);
                $signature_saved = true;
            } else {
                $upload_error = 'Invalid signature data.';
            }
        } else {
            $upload_error = 'Invalid signature format.';
        }
    }

    elseif (!empty($_FILES['signature_file']['tmp_name'])) {
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
    }
    else {
        $upload_error = 'Please draw a signature on the canvas or upload a signature file.';
    }

    if (!empty($upload_error)) {
        $error = $upload_error;
    } elseif ($signature_saved) {
        
        
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #e9f5ff; }
        .signature-wrapper {
            border: 1px solid #cfd8e3;
            border-radius: 10px;
            background: #fff;
            display: inline-block;
            padding: 10px;
        }
        .signature-pad {
            border: 1px dashed #9fb3c8;
            border-radius: 8px;
            background: #ffffff;
            touch-action: none;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="mx-auto bg-white shadow-sm rounded-3 p-4" style="max-width: 760px;">
            <h2 class="h4 mb-3">Add Signature for Certificate</h2>
            <p class="mb-1"><strong>Student:</strong> <?= htmlspecialchars($student['name']) ?></p>
            <p class="mb-1"><strong>Hours Completed:</strong> <?= $hours ?> hr <?= $minutes ?> min</p>
            <p class="mb-3"><strong>Employer:</strong> <?= htmlspecialchars($employer_name) ?></p>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <h4 class="h6">Draw Signature</h4>
            <div class="signature-wrapper mb-2">
                <canvas id="signature-pad" class="signature-pad" width="600" height="220"></canvas>
            </div>
            <div class="mb-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSignature()">Clear Canvas</button>
            </div>

            <div class="mb-3">
                <label for="signature_file" class="form-label mb-1">Or Upload Signature (PNG/JPG)</label>
                <input type="file" class="form-control" name="signature_file" id="signature_file" accept="image/png,image/jpeg">
                <div class="form-text">Use this if you already have a digital signature image.</div>
            </div>

            <input type="hidden" name="signature_data" id="signature-data">
            <div class="d-flex gap-2">
                <button type="submit" name="add_signature" class="btn btn-primary">Add Signature</button>
                <a href="supervisor_dashboard.php" class="btn btn-outline-secondary">Back</a>
            </div>
        </form>
        </div>
    </div>
    <script>
        const canvas = document.getElementById('signature-pad');
        const ctx = canvas.getContext('2d');
        let drawing = false;

        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#1f2937';

        function getPosition(event) {
            const rect = canvas.getBoundingClientRect();
            const isTouch = event.touches && event.touches.length > 0;
            const clientX = isTouch ? event.touches[0].clientX : event.clientX;
            const clientY = isTouch ? event.touches[0].clientY : event.clientY;
            return { x: clientX - rect.left, y: clientY - rect.top };
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        canvas.addEventListener('touchstart', startDrawing, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDrawing);

        function startDrawing(e) {
            e.preventDefault();
            const pos = getPosition(e);
            drawing = true;
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
        }

        function draw(e) {
            if (!drawing) return;
            e.preventDefault();
            const pos = getPosition(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
        }

        function stopDrawing() {
            drawing = false;
        }

        function clearSignature() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        function hasSignatureStroke() {
            const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
            for (let i = 3; i < pixels.length; i += 4) {
                if (pixels[i] !== 0) return true;
            }
            return false;
        }

        document.querySelector('form').addEventListener('submit', function(e) {
            const hasUpload = document.getElementById('signature_file').files.length > 0;
            if (!hasUpload && !hasSignatureStroke()) {
                e.preventDefault();
                alert('Please draw a signature or upload a signature file before submitting.');
                return false;
            }
            if (hasSignatureStroke()) {
                document.getElementById('signature-data').value = canvas.toDataURL('image/png');
            } else {
                document.getElementById('signature-data').value = '';
            }
        });
    </script>
</body>
</html>
