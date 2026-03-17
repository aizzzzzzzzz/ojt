<?php
session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/email.php';

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: employer_login.php");
    exit;
}

$employer_id = $_SESSION['employer_id'];

$students = $pdo->query("SELECT student_id, CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) AS name FROM students ORDER BY name")
               ->fetchAll(PDO::FETCH_ASSOC);

$success = "";
$error = "";

$selected_student_id = $_GET['student_id'] ?? '';
$selected_student_name = null;

if ($selected_student_id !== '') {
    foreach ($students as $student) {
        if ((string) $student['student_id'] === (string) $selected_student_id) {
            $selected_student_name = $student['name'];
            break;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = $_POST['student_id'] ?? null;

    if (!$student_id) {
        $error = "Please select a student.";
    } else {

        $check = $pdo->prepare("SELECT evaluation_id FROM evaluations WHERE student_id = ?");
        $check->execute([$student_id]);

        if ($check->rowCount() > 0) {
            $error = "This student already has a final evaluation.";
        } else {
            $signaturePath = 'assets/signature_' . $employer_id . '_' . $student_id . '.png';
            $signature_saved = false;
            $upload_error = '';

            if (!empty($_POST['signature_data'])) {
                $data = $_POST['signature_data'];
                if (preg_match('/^data:image\/(\w+);base64,/', $data)) {
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
            } elseif (!empty($_FILES['signature_file']['tmp_name'])) {
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
            } else {
                $upload_error = 'Please draw a signature on the canvas or upload a signature file.';
            }

            if (!empty($upload_error) || !$signature_saved) {
                $error = $upload_error ?: 'Signature is required before submitting the evaluation.';
            } else {

            $signature_db_path = $signaturePath;

            $sql = "INSERT INTO evaluations (
                        student_id, employer_id, evaluation_date,
                        attendance_rating, work_quality_rating, initiative_rating,
                        communication_rating, teamwork_rating, adaptability_rating,
                        professionalism_rating, problem_solving_rating, technical_skills_rating,
                        comments, signature_path
                    )
                    VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $student_id,
                $employer_id,
                $_POST['attendance_rating'],
                $_POST['work_quality_rating'],
                $_POST['initiative_rating'],
                $_POST['communication_rating'],
                $_POST['teamwork_rating'],
                $_POST['adaptability_rating'],
                $_POST['professionalism_rating'],
                $_POST['problem_solving_rating'],
                $_POST['technical_skills_rating'],
                $_POST['comments'],
                $signature_db_path
            ]);

            $ratings = [
                (float) $_POST['attendance_rating'],
                (float) $_POST['work_quality_rating'],
                (float) $_POST['initiative_rating'],
                (float) $_POST['communication_rating'],
                (float) $_POST['teamwork_rating'],
                (float) $_POST['adaptability_rating'],
                (float) $_POST['professionalism_rating'],
                (float) $_POST['problem_solving_rating'],
                (float) $_POST['technical_skills_rating']
            ];
            $average_rating = array_sum($ratings) / count($ratings);
            $evaluation_passed = $average_rating >= 3.0;

            $student_stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, email FROM students WHERE student_id = ? LIMIT 1");
            $student_stmt->execute([$student_id]);
            $student = $student_stmt->fetch(PDO::FETCH_ASSOC);

            $supervisor_stmt = $pdo->prepare("SELECT name FROM employers WHERE employer_id = ? LIMIT 1");
            $supervisor_stmt->execute([$employer_id]);
            $supervisor = $supervisor_stmt->fetch(PDO::FETCH_ASSOC);
            $supervisor_name = $supervisor['name'] ?? 'Supervisor';

            $email_notice = '';
            if (!empty($student['email'])) {
                $student_name = trim($student['first_name'] . ' ' . (!empty($student['middle_name']) ? $student['middle_name'] . ' ' : '') . $student['last_name']);
                $email_result = send_evaluation_notification(
                    $student['email'],
                    $student_name,
                    $supervisor_name,
                    $evaluation_passed,
                    $average_rating
                );

                if ($email_result !== true) {
                    error_log("Failed to send evaluation notification: " . $email_result);
                    $email_notice = " Evaluation saved, but failed to send email notification.";
                }
            } else {
                $email_notice = " Evaluation saved, but student has no email address on file.";
            }

            $success = "Evaluation submitted successfully!" . $email_notice;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Evaluation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #2c3e50;
            line-height: 1.5;
        }

        .evaluation-page {
            padding: 20px;
        }

        .evaluation-card {
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.96);
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            padding: 28px;
        }

        .top-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .page-title {
            margin: 0;
            font-size: 1.9rem;
            font-weight: 700;
            color: #1f3b57;
        }

        .page-subtitle {
            margin: 6px 0 0;
            color: #5f7488;
            font-size: 0.98rem;
        }

        .student-block {
            background: #f8f9fa;
            border: 1px solid #dfe6ee;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 22px;
        }

        .student-name {
            margin: 0;
            font-size: 1rem;
            color: #1f3b57;
        }

        .section-card {
            background: #f8f9fa;
            border: 1px solid #dfe6ee;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 22px;
        }

        .section-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f3b57;
        }

        .section-help {
            margin: 6px 0 0;
            color: #5f7488;
            font-size: 0.92rem;
        }

        .ratings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 16px;
            margin-top: 16px;
        }

        .rating-item label,
        .form-label {
            display: inline-block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #274b6d;
        }

        .form-select,
        .form-control {
            border-color: #c7d5e4;
            min-height: 46px;
        }

        .form-select:focus,
        .form-control:focus {
            border-color: #7ab4f8;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .actions-row {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 4px;
        }

        .signature-wrapper {
            border: 1px solid #dfe6ee;
            background: #fff;
            border-radius: 12px;
            padding: 14px;
        }

        .signature-pad {
            width: 100%;
            height: 220px;
            border: 1px dashed #b9c7d6;
            border-radius: 10px;
            background: #fafbfd;
            cursor: crosshair;
        }

        .signature-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .evaluation-page {
                padding: 14px;
            }

            .evaluation-card {
                padding: 18px;
                border-radius: 12px;
            }

            .top-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .ratings-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .actions-row {
                flex-direction: column-reverse;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>

<div class="evaluation-page">
    <div class="evaluation-card">
        <div class="top-actions">
            <div>
                <h1 class="page-title">Final Evaluation Form</h1>
                <p class="page-subtitle">Rate student performance across core OJT criteria.</p>
            </div>
            <a href="supervisor_dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($selected_student_id !== '' && $selected_student_name === null): ?>
            <div class="alert alert-warning">Selected student was not found. Please choose a valid student.</div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="student-block">

                <?php if ($selected_student_name === null): ?>
                    <select id="student_id" name="student_id" class="form-select" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['student_id'] ?>">
                                <?= htmlspecialchars($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars((string) $selected_student_id) ?>">
                    <p class="student-name"><strong>Student:</strong> <?= htmlspecialchars($selected_student_name) ?></p>
                <?php endif; ?>
            </div>

            <div class="section-card">
                <h2 class="section-title">Supervisor Signature</h2>
                <p class="section-help">Please provide your signature before completing the evaluation details.</p>

                <div class="signature-wrapper">
                    <canvas id="signature-pad" class="signature-pad" width="900" height="220"></canvas>
                    <input type="hidden" name="signature_data" id="signature-data">

                    <div class="signature-actions">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="clear-signature">Clear Signature</button>
                        <span class="text-muted">or upload a file:</span>
                        <input type="file" class="form-control form-control-sm" name="signature_file" id="signature_file" accept="image/png,image/jpeg">
                    </div>
                </div>
            </div>

            <div class="section-card">
                <h2 class="section-title">Ratings</h2>
                <p class="section-help">Use the scale: 1 = Poor, 5 = Excellent.</p>

                <?php
                $criteria = [
                    "attendance_rating" => "Attendance",
                    "work_quality_rating" => "Quality of Work",
                    "initiative_rating" => "Initiative",
                    "communication_rating" => "Communication",
                    "teamwork_rating" => "Teamwork",
                    "adaptability_rating" => "Adaptability",
                    "professionalism_rating" => "Professionalism",
                    "problem_solving_rating" => "Problem Solving",
                    "technical_skills_rating" => "Technical Skills"
                ];
                ?>

                <div class="ratings-grid">
                    <?php foreach ($criteria as $field => $label): ?>
                        <div class="rating-item">
                            <label for="<?= $field ?>"><?= $label ?></label>
                            <select id="<?= $field ?>" name="<?= $field ?>" class="form-select requires-signature" required>
                                <option value="">Select Rating</option>
                                <option value="1">1 - Poor</option>
                                <option value="2">2 - Fair</option>
                                <option value="3">3 - Good</option>
                                <option value="4">4 - Very Good</option>
                                <option value="5">5 - Excellent</option>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="section-card">
                <label class="form-label" for="comments">Comments (optional)</label>
                <textarea id="comments" name="comments" class="form-control requires-signature" rows="4"></textarea>
            </div>

            <div class="actions-row">
                <button type="submit" class="btn btn-primary requires-signature">Submit Evaluation</button>
            </div>
        </form>
    </div>
</div>

<script>
    const canvas = document.getElementById('signature-pad');
    const ctx = canvas.getContext('2d');
    const signatureData = document.getElementById('signature-data');
    const clearBtn = document.getElementById('clear-signature');
    const signatureFile = document.getElementById('signature_file');
    const gatedFields = document.querySelectorAll('.requires-signature');

    let drawing = false;
    let hasSignature = false;

    const setGatedState = (enabled) => {
        gatedFields.forEach((el) => {
            el.disabled = !enabled;
        });
    };

    const getCanvasPos = (event) => {
        const rect = canvas.getBoundingClientRect();
        if (event.touches && event.touches.length > 0) {
            return {
                x: event.touches[0].clientX - rect.left,
                y: event.touches[0].clientY - rect.top
            };
        }
        return {
            x: event.clientX - rect.left,
            y: event.clientY - rect.top
        };
    };

    const startDraw = (event) => {
        drawing = true;
        hasSignature = true;
        signatureFile.value = '';
        ctx.beginPath();
        const pos = getCanvasPos(event);
        ctx.moveTo(pos.x, pos.y);
        setGatedState(true);
    };

    const draw = (event) => {
        if (!drawing) return;
        event.preventDefault();
        const pos = getCanvasPos(event);
        ctx.lineTo(pos.x, pos.y);
        ctx.strokeStyle = '#1f3b57';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.stroke();
    };

    const endDraw = () => {
        drawing = false;
    };

    clearBtn.addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        signatureData.value = '';
        hasSignature = false;
        if (!signatureFile.value) {
            setGatedState(false);
        }
    });

    signatureFile.addEventListener('change', () => {
        if (signatureFile.files.length > 0) {
            hasSignature = true;
            signatureData.value = '';
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            setGatedState(true);
        } else if (!hasSignature) {
            setGatedState(false);
        }
    });

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', endDraw);
    canvas.addEventListener('mouseleave', endDraw);
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', endDraw);

    document.querySelector('form').addEventListener('submit', (event) => {
        if (signatureFile.files.length === 0 && !hasSignature) {
            event.preventDefault();
            alert('Please provide a supervisor signature before submitting.');
            return;
        }
        if (signatureFile.files.length === 0 && hasSignature) {
            signatureData.value = canvas.toDataURL('image/png');
        }
    });

    setGatedState(false);
</script>

</body>
</html>
