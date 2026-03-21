<?php
session_start();
include __DIR__ . '/../includes/auth_check.php';
require_role('employer');

include __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/email.php';

$submission_id = (int)($_GET['id'] ?? 0);
if (!$submission_id) {
    header("Location: manage_projects.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT ps.submission_id
    FROM project_submissions ps
    INNER JOIN projects p ON ps.project_id = p.project_id
    WHERE ps.submission_id = ? AND p.created_by = ?
");
$stmt->execute([$submission_id, $_SESSION['employer_id']]);
$authorized_submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$authorized_submission) {
    $_SESSION['error'] = 'Access denied or submission not found.';
    header("Location: manage_projects.php");
    exit;
}

$remarks = $_POST['remarks'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE project_submissions SET status = 'Approved', remarks = ?, graded_at = NOW() WHERE submission_id = ?");
    $stmt->execute([$remarks, $submission_id]);

    $project_id = $_GET['project_id'] ?? 0;
    if ($project_id) {
        $updateProjectStmt = $pdo->prepare("UPDATE projects SET status = 'Completed' WHERE project_id = ?");
        $updateProjectStmt->execute([$project_id]);
    }

    log_activity($pdo, $_SESSION['employer_id'], 'employer', "Approved submission $submission_id and disabled project $project_id");

    error_log("DEBUG: Attempting to send approval email for submission_id: $submission_id");
    $student_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM students WHERE student_id = (SELECT student_id FROM project_submissions WHERE submission_id = ?)");
    $student_stmt->execute([$submission_id]);
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
    error_log("DEBUG: Student data: " . print_r($student, true));

    if ($student && !empty($student['email'])) {
        $student_name = ucwords(strtolower($student['first_name'] . ' ' . $student['last_name']));
        $supervisor_stmt = $pdo->prepare("SELECT name FROM employers WHERE employer_id = ?");
        $supervisor_stmt->execute([$_SESSION['employer_id']]);
        $supervisor = $supervisor_stmt->fetch(PDO::FETCH_ASSOC);
        $supervisor_name = $supervisor ? $supervisor['name'] : 'Supervisor';

        $email_result = send_project_approval_notification($student['email'], $student_name, $supervisor_name, $remarks);
        if ($email_result !== true) {
            error_log("Failed to send approval notification: " . $email_result);
        }
    }

    $_SESSION['success_message'] = "Submission approved successfully.";
    header("Location: project_submissions.php?project_id=" . ($_GET['project_id'] ?? 0));
    exit;
}

$stmt = $pdo->prepare("SELECT ps.*, s.username FROM project_submissions ps JOIN students s ON ps.student_id = s.student_id WHERE ps.submission_id = ?");
$stmt->execute([$submission_id]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    $_SESSION['error'] = 'Submission not found.';
    header("Location: manage_projects.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approve Submission</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<style>

    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--amber:#d97706;--amber-lt:#fef3c7;--radius:14px;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:32px 20px 60px;}
    .page-card{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);width:100%;margin:0 auto;overflow:hidden;}
    .page-topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;}
    .page-topbar h2{font-size:18px;font-weight:700;margin:0;letter-spacing:-.3px;}
    .page-topbar p{font-size:13px;color:var(--text-muted);margin:2px 0 0;}
    .page-inner{padding:24px 28px 32px;}
    .page-inner h3{font-size:15px;font-weight:700;margin:24px 0 12px;padding-bottom:9px;border-bottom:1px solid var(--border);}
    .page-inner h3:first-child{margin-top:0;}
    .success-msg{background:var(--green-lt);color:#15803d;padding:12px 16px;border-radius:10px;border:1px solid #bbf7d0;font-size:14px;font-weight:500;margin-bottom:16px;}
    .error-msg{background:var(--red-lt);color:#b91c1c;padding:12px 16px;border-radius:10px;border:1px solid #fecaca;font-size:14px;font-weight:500;margin-bottom:16px;}
    .form-label{font-size:13px;font-weight:600;color:var(--text);margin-bottom:5px;display:block;}
    .form-text{font-size:12px;color:var(--text-muted);margin-top:4px;}
    input[type=text],input[type=email],input[type=password],input[type=number],input[type=time],input[type=date],textarea,select,.form-control{border-radius:9px;border:1px solid var(--border);padding:9px 12px;font-size:14px;font-family:inherit;color:var(--text);background:var(--surface);transition:border-color .2s,box-shadow .2s;width:100%;}
    input:focus,textarea:focus,select:focus,.form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.12);outline:none;}
    .mb-3{margin-bottom:16px;}.mb-4{margin-bottom:24px;}
    .btn{font-family:inherit;font-size:13px;font-weight:600;border-radius:9px;padding:9px 18px;transition:all .18s;cursor:pointer;display:inline-flex;align-items:center;gap:6px;border:none;text-decoration:none;}
    .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);color:#fff;}
    .btn-success{background:var(--green);color:#fff;}.btn-success:hover{background:#15803d;transform:translateY(-1px);color:#fff;}
    .btn-secondary{background:var(--surface2);color:var(--text);border:1.5px solid var(--border);}.btn-secondary:hover{background:var(--border);}
    .btn-outline-secondary{background:transparent;color:var(--text-muted);border:1.5px solid var(--border);border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-family:inherit;transition:all .18s;}
    .btn-outline-secondary:hover{background:var(--surface2);color:var(--text);}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .span-2{grid-column:span 2;}
    @media(max-width:640px){body{padding:10px 10px 40px;}.page-card{border-radius:14px;}.page-topbar,.page-inner{padding:14px 16px;}.form-grid{grid-template-columns:1fr;}.span-2{grid-column:span 1;}}

    .page-card { max-width: 620px; }
    .info-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border); font-size:14px; }
    .info-row:last-child { border-bottom:none; }
    .info-row .label { font-weight:600; color:var(--text-muted); font-size:12px; text-transform:uppercase; letter-spacing:.5px; }
    .info-row .value { color:var(--text); font-weight:500; }
</style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2>Approve Submission</h2>
            <p>Review and approve the student's project submission</p>
        </div>
        <a href="project_submissions.php?project_id=<?= $_GET['project_id'] ?? 0 ?>" class="btn-outline-secondary">⬅ Back</a>
    </div>
    <div class="page-inner">

        <div style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:0 16px;margin-bottom:24px;">
            <div class="info-row">
                <span class="label">Student</span>
                <span class="value"><?= htmlspecialchars($submission['username']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Submitted</span>
                <span class="value"><?= htmlspecialchars($submission['submission_date']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Status</span>
                <span class="value"><?= htmlspecialchars($submission['submission_status'] ?? 'Unknown') ?></span>
            </div>
        </div>

        <form method="post">
            <div class="mb-3">
                <label class="form-label" for="remarks">Remarks / Grade <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
                <textarea id="remarks" name="remarks" rows="4" placeholder="Add feedback or a grade for the student..."></textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-success" style="flex:1;justify-content:center;">✓ Approve Submission</button>
                <a href="project_submissions.php?project_id=<?= $_GET['project_id'] ?? 0 ?>" class="btn btn-secondary" style="flex:1;justify-content:center;">Cancel</a>
            </div>
        </form>

    </div>
</div>
</body>
</html>