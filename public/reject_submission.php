<?php
session_start();
if (!isset($_SESSION['employer_id'])) {
    header("Location: employer_login.php");
    exit;
}

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

$stmt = $pdo->prepare("UPDATE project_submissions SET status = 'rejected' WHERE submission_id = ?");
$stmt->execute([$submission_id]);

$_SESSION['success_message'] = "Submission rejected.";
header("Location: manage_projects.php");
exit;
