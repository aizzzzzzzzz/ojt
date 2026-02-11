<?php
session_start();
include __DIR__ . '/../private/config.php';

if (!isset($_SESSION['student_id']) && !isset($_SESSION['employer_id'])) {
    header("Location: index.php");
    exit;
}

$submission_id = $_GET['submission_id'] ?? 0;
if (!$submission_id) die("Invalid submission ID.");

$userType = '';
$userId = 0;

if (isset($_SESSION['student_id'])) {
    $userType = 'student';
    $userId = (int)$_SESSION['student_id'];
} elseif (isset($_SESSION['employer_id'])) {
    $userType = 'employer';
    $userId = (int)$_SESSION['employer_id'];
} else {
    header("Location: index.php");
    exit;
}

if ($userType === 'student') {
    $stmt = $pdo->prepare("SELECT file_path FROM project_submissions WHERE submission_id = ? AND student_id = ?");
    $stmt->execute([$submission_id, $userId]);
} else {
    $stmt = $pdo->prepare("
        SELECT ps.file_path
        FROM project_submissions ps
        INNER JOIN projects p ON ps.project_id = p.project_id
        WHERE ps.submission_id = ? AND p.created_by = ?
    ");
    $stmt->execute([$submission_id, $userId]);
}

$submission = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$submission) die("Submission not found or access denied.");

$full_path = __DIR__ . '/../storage/uploads/' . $submission['file_path'];
if (!file_exists($full_path)) die("File not found.");

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($full_path) . '"');
header('Content-Length: ' . filesize($full_path));
readfile($full_path);
exit;
