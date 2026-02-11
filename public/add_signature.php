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
        header("Location: generate_certificate.php?student_id=$student_id");
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
