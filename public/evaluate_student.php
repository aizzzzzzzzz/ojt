<?php
session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/evaluation_security.php';

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: employer_login.php");
    exit;
}

ensure_evaluation_security_schema($pdo);

function evaluation_verification_session_key(int $employerId, int $studentId): string {
    return "evaluation_verification_{$employerId}_{$studentId}";
}

function save_supervisor_signature(int $employerId, int $studentId): array {
    $signaturePath = 'assets/signature_' . $employerId . '_' . $studentId . '.png';
    $signatureSaved = false;
    $uploadError = '';

    if (!empty($_POST['signature_data'])) {
        $data = $_POST['signature_data'];
        if (preg_match('/^data:image\/(\w+);base64,/', $data)) {
            $data = substr($data, strpos($data, ',') + 1);
            $decodedData = base64_decode($data, true);
            if ($decodedData !== false && !empty($decodedData)) {
                if (!is_dir('assets')) mkdir('assets', 0777, true);
                file_put_contents($signaturePath, $decodedData);
                $signatureSaved = true;
            } else {
                $uploadError = 'Invalid signature data.';
            }
        } else {
            $uploadError = 'Invalid signature format.';
        }
    } elseif (!empty($_FILES['signature_file']['tmp_name'])) {
        if ($_FILES['signature_file']['error'] !== UPLOAD_ERR_OK) {
            $uploadError = 'File upload error: ' . $_FILES['signature_file']['error'];
        } else {
            $imgInfo = getimagesize($_FILES['signature_file']['tmp_name']);
            if (!$imgInfo) {
                $uploadError = 'Invalid image file.';
            } else {
                if (!is_dir('assets')) mkdir('assets', 0777, true);
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
                        $uploadError = 'Failed to create image resource.';
                    } else {
                        $white = imagecolorallocate($dst, 255, 255, 255);
                        if ($white === false) {
                            $uploadError = 'Failed to allocate color.';
                        } else {
                            imagefill($dst, 0, 0, $white);
                            if (!imagecopy($dst, $src, 0, 0, 0, 0, $w, $h)) {
                                $uploadError = 'Failed to copy image.';
                            } elseif (!imagepng($dst, $signaturePath)) {
                                $uploadError = 'Failed to save image.';
                            } else {
                                $signatureSaved = true;
                            }
                        }
                        imagedestroy($dst);
                    }
                    imagedestroy($src);
                } else {
                    $uploadError = 'Unsupported image format. Supported formats: JPEG, PNG.';
                }
            }
        }
    } else {
        $uploadError = 'Please draw a signature on the canvas or upload a signature file.';
    }

    return ['success' => $signatureSaved && $uploadError === '', 'error' => $uploadError, 'path' => $signatureSaved ? $signaturePath : null];
}

$employerId = (int) $_SESSION['employer_id'];
$supervisorStmt = $pdo->prepare("SELECT employer_id, name, email, company_id FROM employers WHERE employer_id = ? LIMIT 1");
$supervisorStmt->execute([$employerId]);
$supervisor = $supervisorStmt->fetch(PDO::FETCH_ASSOC);

if (!$supervisor) {
    session_destroy();
    header("Location: supervisor_login.php");
    exit;
}

$companyId = $supervisor['company_id'] ?? null;
$studentsStmt = $pdo->prepare("SELECT student_id, CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) AS name FROM students WHERE created_by = ? OR (? IS NOT NULL AND company_id = ?) ORDER BY name");
$studentsStmt->execute([$employerId, $companyId, $companyId]);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

$studentsById = [];
foreach ($students as $student) $studentsById[(int) $student['student_id']] = $student;

$success = '';
$info = '';
$error = '';
$selectedStudentId = (int) ($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$selectedStudent = $studentsById[$selectedStudentId] ?? null;
$selectedStudentName = $selectedStudent['name'] ?? null;
$evaluationAlreadyExists = false;
$verificationVerified = false;
$maskedSupervisorEmail = '';
$verificationSessionKey = null;
$currentVerificationKey = null;
$currentVerification = null;

if ($selectedStudentId > 0 && !$selectedStudent) {
    $error = "Selected student was not found under this supervisor.";
}

if ($selectedStudent) {
    $check = $pdo->prepare("SELECT evaluation_id FROM evaluations WHERE student_id = ?");
    $check->execute([$selectedStudentId]);
    $existingEvaluationId = $check->fetchColumn();
    $evaluationAlreadyExists = !empty($existingEvaluationId);

    if ($evaluationAlreadyExists) {
        $error = "This student already has a final evaluation.";
    } elseif (empty($supervisor['email']) || !filter_var($supervisor['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "A valid supervisor email is required before evaluation can continue. Please ask the admin to update your account.";
    } else {
        $verificationSessionKey = evaluation_verification_session_key($employerId, $selectedStudentId);
        $currentVerificationKey = $_SESSION[$verificationSessionKey] ?? null;

        if ($currentVerificationKey) {
            $currentVerification = get_evaluation_verification_request($pdo, $currentVerificationKey);
            if (!$currentVerification || (int) $currentVerification['employer_id'] !== $employerId || (int) $currentVerification['student_id'] !== $selectedStudentId) {
                unset($_SESSION[$verificationSessionKey]);
                $currentVerificationKey = null;
                $currentVerification = null;
            }
        }

        $issueVerificationCode = function () use ($pdo, $employerId, $selectedStudentId, $selectedStudentName, $supervisor, $verificationSessionKey, &$currentVerificationKey, &$currentVerification, &$info, &$error): void {
            $request = create_evaluation_verification_request($pdo, $employerId, $selectedStudentId, $supervisor['email']);
            $emailResult = send_evaluation_verification_code($supervisor['email'], $supervisor['name'] ?? 'Supervisor', $selectedStudentName ?? 'the selected student', $request['plain_code']);
            if ($emailResult === true) {
                $_SESSION[$verificationSessionKey] = $request['verification_key'];
                $currentVerificationKey = $request['verification_key'];
                $currentVerification = get_evaluation_verification_request($pdo, $currentVerificationKey);
                $info = "A one-time verification code was sent to " . mask_email_address($supervisor['email']) . ".";
            } else {
                $error = "Unable to send the verification code right now. " . $emailResult;
            }
        };

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_code'])) {
            $issueVerificationCode();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
            if (!$currentVerificationKey) {
                $error = "No verification request is active. Please request a new code.";
            } else {
                $verifyResult = verify_evaluation_verification_code($pdo, $currentVerificationKey, $employerId, $selectedStudentId, $_POST['verification_code'] ?? '');
                if ($verifyResult['success']) $info = $verifyResult['message']; else $error = $verifyResult['message'];
                $currentVerification = get_evaluation_verification_request($pdo, $currentVerificationKey);
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
            if (!$currentVerificationKey || !is_evaluation_verification_complete($pdo, $currentVerificationKey, $employerId, $selectedStudentId)) {
                $error = "Please verify the one-time email code before adding a signature and submitting the evaluation.";
            } else {
                $signatureResult = save_supervisor_signature($employerId, $selectedStudentId);
                if (!$signatureResult['success']) {
                    $error = $signatureResult['error'] ?: 'Signature is required before submitting the evaluation.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO evaluations (student_id, employer_id, evaluation_date, attendance_rating, work_quality_rating, initiative_rating, communication_rating, teamwork_rating, adaptability_rating, professionalism_rating, problem_solving_rating, technical_skills_rating, comments, signature_path) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $selectedStudentId, $employerId,
                        $_POST['attendance_rating'], $_POST['work_quality_rating'], $_POST['initiative_rating'],
                        $_POST['communication_rating'], $_POST['teamwork_rating'], $_POST['adaptability_rating'],
                        $_POST['professionalism_rating'], $_POST['problem_solving_rating'], $_POST['technical_skills_rating'],
                        $_POST['comments'] ?? '', $signatureResult['path']
                    ]);

                    $evaluationId = (int) $pdo->lastInsertId();
                    mark_evaluation_verification_used($pdo, $currentVerificationKey, $evaluationId);
                    unset($_SESSION[$verificationSessionKey]);

                    $ratings = [(float) $_POST['attendance_rating'], (float) $_POST['work_quality_rating'], (float) $_POST['initiative_rating'], (float) $_POST['communication_rating'], (float) $_POST['teamwork_rating'], (float) $_POST['adaptability_rating'], (float) $_POST['professionalism_rating'], (float) $_POST['problem_solving_rating'], (float) $_POST['technical_skills_rating']];
                    $averageRating = array_sum($ratings) / count($ratings);
                    $evaluationPassed = $averageRating >= 3.0;

                    $studentStmt = $pdo->prepare("SELECT first_name, middle_name, last_name, email, username FROM students WHERE student_id = ? LIMIT 1");
                    $studentStmt->execute([$selectedStudentId]);
                    $studentData = $studentStmt->fetch(PDO::FETCH_ASSOC);

                    $emailNotice = '';
                    if (!empty($studentData['email'])) {
                        $studentFullName = trim(($studentData['first_name'] ?? '') . ' ' . (!empty($studentData['middle_name']) ? $studentData['middle_name'] . ' ' : '') . ($studentData['last_name'] ?? ''));
                        $emailResult = send_evaluation_notification($studentData['email'], $studentFullName, $supervisor['name'] ?? 'Supervisor', $evaluationPassed, $averageRating);
                        if ($emailResult !== true) {
                            error_log("Failed to send evaluation notification: " . $emailResult);
                            $emailNotice = " Evaluation saved, but failed to send email notification.";
                        }
                    } else {
                        $emailNotice = " Evaluation saved, but the student has no email address on file.";
                    }

                    $success = "Evaluation submitted successfully!" . $emailNotice;
                    audit_log($pdo, 'Submit Evaluation', "Evaluation submitted for student: " . ($studentData['username'] ?? $selectedStudentId));
                    $currentVerificationKey = null;
                    $currentVerification = null;
                    $verificationVerified = false;
                    $evaluationAlreadyExists = true;
                }
            }
        } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$currentVerificationKey) {
            $issueVerificationCode();
        }

        if ($currentVerificationKey) {
            $currentVerification = get_evaluation_verification_request($pdo, $currentVerificationKey);
            $verificationVerified = is_evaluation_verification_complete($pdo, $currentVerificationKey, $employerId, $selectedStudentId);
            $maskedSupervisorEmail = mask_email_address($currentVerification['sent_to_email'] ?? $supervisor['email']);
        } else {
            $maskedSupervisorEmail = mask_email_address($supervisor['email']);
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
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--radius:14px;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
        *,*::before,*::after{box-sizing:border-box;} body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.5;min-height:100vh;margin:0;padding:28px 20px 60px;}
        .page{max-width:1000px;margin:0 auto;background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);overflow:hidden;}
        .topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;}
        .topbar h2{font-size:18px;font-weight:700;margin:0;} .topbar p{font-size:13px;color:var(--text-muted);margin:2px 0 0;}
        .inner{padding:24px 28px 32px;} .box{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:18px;margin-bottom:20px;}
        .success-msg,.info-msg,.error-msg{padding:12px 16px;border-radius:10px;font-size:14px;font-weight:500;margin-bottom:16px;border:1px solid transparent;}
        .success-msg{background:var(--green-lt);color:#15803d;border-color:#bbf7d0;} .info-msg{background:var(--accent-lt);color:var(--accent-dk);border-color:#c7d2fe;} .error-msg{background:var(--red-lt);color:#b91c1c;border-color:#fecaca;}
        .box h3{margin:0 0 6px;font-size:14px;font-weight:700;} .muted{color:var(--text-muted);font-size:13px;}
        .form-label{display:inline-block;margin-bottom:6px;font-weight:600;font-size:13px;}
        .form-select,.form-control{border:1px solid var(--border);border-radius:9px;min-height:44px;font-family:inherit;font-size:14px;width:100%;padding:9px 12px;background:var(--surface);color:var(--text);}
        .form-select:focus,.form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.12);outline:none;}
        .btn{font-family:inherit;font-size:13px;font-weight:600;border-radius:9px;padding:9px 18px;display:inline-flex;align-items:center;gap:6px;border:none;text-decoration:none;}
        .btn-primary{background:var(--accent);color:#fff;} .btn-success{background:var(--green);color:#fff;} .btn-outline-secondary{background:transparent;color:var(--text-muted);border:1.5px solid var(--border);}
        .ratings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 16px;margin-top:16px;} .actions-row{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;}
        .signature-wrapper{border:1px solid var(--border);background:var(--surface);border-radius:var(--radius);padding:14px;} .signature-pad{width:100%;height:220px;border:2px dashed var(--border);border-radius:10px;background:#fafbfd;cursor:crosshair;display:block;}
        .signature-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px;} .verify-code{max-width:220px;font-size:20px;letter-spacing:4px;text-align:center;font-weight:700;}
        .status{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:700;background:var(--green-lt);color:var(--green);margin-top:12px;}
        @media(max-width:768px){body{padding:10px 10px 40px;}.topbar,.inner{padding:14px 16px;}.ratings-grid{grid-template-columns:1fr;}.actions-row .btn{width:100%;justify-content:center;}}
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h2>Final Evaluation</h2>
            <p>Verify your supervisor email first, then add your signature and complete the evaluation.</p>
        </div>
        <a href="supervisor_dashboard.php" class="btn btn-outline-secondary">⬅ Back</a>
    </div>
    <div class="inner">
        <?php if ($success): ?><div class="success-msg"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($info): ?><div class="info-msg"><?= htmlspecialchars($info) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php if ($selectedStudentId === 0): ?>
            <div class="box">
                <h3>Choose Student</h3>
                <p class="muted">Select a student first. Opening the evaluation sends a one-time verification code to your email.</p>
                <form method="GET">
                    <label class="form-label" for="student_id">Student</label>
                    <select id="student_id" name="student_id" class="form-select" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= (int) $student['student_id'] ?>"><?= htmlspecialchars($student['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="actions-row" style="margin-top:16px;justify-content:flex-start;"><button type="submit" class="btn btn-primary">Send Verification Code</button></div>
                </form>
            </div>
        <?php elseif ($selectedStudent): ?>
            <div class="box">
                <h3><?= htmlspecialchars($selectedStudentName) ?></h3>
                <?php if (!empty($supervisor['email'])): ?><div class="muted">Supervisor verification email: <?= htmlspecialchars(mask_email_address($supervisor['email'])) ?></div><?php endif; ?>
            </div>

            <?php if (!$evaluationAlreadyExists && !$verificationVerified): ?>
                <div class="box">
                    <h3>Email Code Verification</h3>
                    <p class="muted">Enter the one-time code sent to <?= htmlspecialchars($maskedSupervisorEmail) ?> before the signature form is unlocked.</p>
                    <form method="POST" style="margin-top:16px;">
                        <input type="hidden" name="student_id" value="<?= (int) $selectedStudentId ?>">
                        <label class="form-label" for="verification_code">Verification Code</label>
                        <input id="verification_code" name="verification_code" type="text" inputmode="numeric" maxlength="6" class="form-control verify-code" placeholder="000000" required>
                        <div class="actions-row" style="margin-top:16px;justify-content:flex-start;"><button type="submit" name="verify_code" class="btn btn-success">Verify Code</button></div>
                    </form>
                    <form method="POST" style="margin-top:12px;">
                        <input type="hidden" name="student_id" value="<?= (int) $selectedStudentId ?>">
                        <button type="submit" name="resend_code" class="btn btn-outline-secondary">Resend Code</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($verificationVerified && !$evaluationAlreadyExists): ?>
                <div class="box">
                    <h3>Verification Status</h3>
                    <p class="muted">Email verification is complete. You may now add the supervisor signature and submit the evaluation.</p>
                    <div class="status">✓ Verified</div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="student_id" value="<?= (int) $selectedStudentId ?>">
                    <div class="box">
                        <h3>Supervisor Signature</h3>
                        <p class="muted">Please provide your signature after verification.</p>
                        <div class="signature-wrapper">
                            <canvas id="signature-pad" class="signature-pad" width="900" height="220"></canvas>
                            <input type="hidden" name="signature_data" id="signature-data">
                            <div class="signature-actions">
                                <button type="button" class="btn btn-outline-secondary" id="clear-signature">Clear Signature</button>
                                <span class="muted">or upload a file:</span>
                                <input type="file" class="form-control" name="signature_file" id="signature_file" accept="image/png,image/jpeg">
                            </div>
                        </div>
                    </div>

                    <div class="box">
                        <h3>Ratings</h3>
                        <p class="muted">Use the scale: 1 = Poor, 5 = Excellent.</p>
                        <?php $criteria = ["attendance_rating" => "Attendance","work_quality_rating" => "Quality of Work","initiative_rating" => "Initiative","communication_rating" => "Communication","teamwork_rating" => "Teamwork","adaptability_rating" => "Adaptability","professionalism_rating" => "Professionalism","problem_solving_rating" => "Problem Solving","technical_skills_rating" => "Technical Skills"]; ?>
                        <div class="ratings-grid">
                            <?php foreach ($criteria as $field => $label): ?>
                                <div>
                                    <label class="form-label" for="<?= htmlspecialchars($field) ?>"><?= htmlspecialchars($label) ?></label>
                                    <select id="<?= htmlspecialchars($field) ?>" name="<?= htmlspecialchars($field) ?>" class="form-select requires-signature" required>
                                        <option value="">Select Rating</option>
                                        <?php foreach ([1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very Good', 5 => 'Excellent'] as $rating => $desc): ?>
                                            <option value="<?= $rating ?>" <?= (($_POST[$field] ?? '') === (string) $rating) ? 'selected' : '' ?>><?= $rating ?> - <?= $desc ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="box">
                        <label class="form-label" for="comments">Comments (optional)</label>
                        <textarea id="comments" name="comments" class="form-control requires-signature" rows="4"><?= htmlspecialchars($_POST['comments'] ?? '') ?></textarea>
                    </div>

                    <div class="actions-row"><button type="submit" name="submit_evaluation" class="btn btn-primary requires-signature">Submit Evaluation</button></div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($verificationVerified && !$evaluationAlreadyExists): ?>
<script>
const canvas=document.getElementById('signature-pad'),ctx=canvas.getContext('2d'),signatureData=document.getElementById('signature-data'),clearBtn=document.getElementById('clear-signature'),signatureFile=document.getElementById('signature_file'),gatedFields=document.querySelectorAll('.requires-signature');let drawing=false,hasSignature=false;const setGatedState=(enabled)=>{gatedFields.forEach((el)=>{el.disabled=!enabled;});};const getCanvasPos=(event)=>{const rect=canvas.getBoundingClientRect();if(event.touches&&event.touches.length>0){return{x:event.touches[0].clientX-rect.left,y:event.touches[0].clientY-rect.top};}return{x:event.clientX-rect.left,y:event.clientY-rect.top};};const startDraw=(event)=>{drawing=true;hasSignature=true;signatureFile.value='';ctx.beginPath();const pos=getCanvasPos(event);ctx.moveTo(pos.x,pos.y);setGatedState(true);};const draw=(event)=>{if(!drawing)return;event.preventDefault();const pos=getCanvasPos(event);ctx.lineTo(pos.x,pos.y);ctx.strokeStyle='#111827';ctx.lineWidth=2;ctx.lineCap='round';ctx.stroke();};const endDraw=()=>{drawing=false;};clearBtn.addEventListener('click',()=>{ctx.clearRect(0,0,canvas.width,canvas.height);signatureData.value='';hasSignature=false;if(!signatureFile.value)setGatedState(false);});signatureFile.addEventListener('change',()=>{if(signatureFile.files.length>0){hasSignature=true;signatureData.value='';ctx.clearRect(0,0,canvas.width,canvas.height);setGatedState(true);}else if(!hasSignature){setGatedState(false);}});canvas.addEventListener('mousedown',startDraw);canvas.addEventListener('mousemove',draw);canvas.addEventListener('mouseup',endDraw);canvas.addEventListener('mouseleave',endDraw);canvas.addEventListener('touchstart',startDraw,{passive:false});canvas.addEventListener('touchmove',draw,{passive:false});canvas.addEventListener('touchend',endDraw);document.querySelector('form[enctype="multipart/form-data"]').addEventListener('submit',(event)=>{if(signatureFile.files.length===0&&!hasSignature){event.preventDefault();alert('Please provide a supervisor signature before submitting.');return;}if(signatureFile.files.length===0&&hasSignature)signatureData.value=canvas.toDataURL('image/png');});setGatedState(false);
</script>
<?php endif; ?>
</body>
</html>
