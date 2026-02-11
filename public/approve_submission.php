<?php
session_start();
include __DIR__ . '/../includes/auth_check.php';
require_role('employer');

include __DIR__ . '/../private/config.php';

$submission_id = (int)($_GET['id'] ?? 0);
if (!$submission_id) {
    header("Location: manage_projects.php");
    exit;
}

// Check if submission belongs to a project created by this employer (IDOR prevention)
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

    // Log activity
    log_activity($pdo, $_SESSION['employer_id'], 'employer', "Approved submission $submission_id");

    $_SESSION['success_message'] = "Submission approved successfully.";
    header("Location: project_submissions.php?project_id=" . ($_GET['project_id'] ?? 0));
    exit;
}

// If GET request, show form
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
</head>
<body>
<div class="container mt-5">
<h2>Approve Submission</h2>
<p><strong>Student:</strong> <?= htmlspecialchars($submission['username']) ?></p>
<p><strong>Submission Date:</strong> <?= htmlspecialchars($submission['submission_date']) ?></p>
<p><strong>Status:</strong> <?= htmlspecialchars($submission['submission_status'] ?? 'Unknown') ?></p>

<form method="post">
    <div class="mb-3">
        <label for="remarks" class="form-label">Remarks (optional)</label>
        <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
    </div>
    <button type="submit" class="btn btn-success">Approve</button>
    <a href="project_submissions.php?project_id=<?= $_GET['project_id'] ?? 0 ?>" class="btn btn-secondary">Cancel</a>
</form>
</div>
</body>
</html>
