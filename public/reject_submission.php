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

$stmt = $pdo->prepare("UPDATE project_submissions SET status = 'rejected' WHERE submission_id = ?");
$stmt->execute([$submission_id]);

$_SESSION['success_message'] = "Submission rejected.";
header("Location: manage_projects.php");
exit;
