<?php
session_start();
if (!isset($_SESSION['employer_id'])) {
    header("Location: employer_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/email.php';

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

// Send email notification to student
$student_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM students WHERE student_id = (SELECT student_id FROM project_submissions WHERE submission_id = ?)");
$student_stmt->execute([$submission_id]);
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

if ($student && !empty($student['email'])) {
    $student_name = ucwords(strtolower($student['first_name'] . ' ' . $student['last_name']));
    $supervisor_stmt = $pdo->prepare("SELECT name FROM employers WHERE employer_id = ?");
    $supervisor_stmt->execute([$_SESSION['employer_id']]);
    $supervisor = $supervisor_stmt->fetch(PDO::FETCH_ASSOC);
    $supervisor_name = $supervisor ? $supervisor['name'] : 'Supervisor';

    $email_result = send_project_rejection_notification($student['email'], $student_name, $supervisor_name);
    if ($email_result !== true) {
        error_log("Failed to send rejection notification: " . $email_result);
    }
}

$_SESSION['success_message'] = "Submission rejected.";
header("Location: manage_projects.php");
exit;
