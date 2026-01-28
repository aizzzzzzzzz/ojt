<?php
session_start();
include __DIR__ . '/../private/config.php';

// Only students or employers can access
if (!isset($_SESSION['student_id']) && !isset($_SESSION['employer_id'])) {
    header("Location: index.php");
    exit;
}

$submission_id = $_GET['submission_id'] ?? 0;
if (!$submission_id) die("Invalid submission ID.");

// Fetch submission details
$stmt = $pdo->prepare("SELECT file_path FROM project_submissions WHERE submission_id = ?");
$stmt->execute([$submission_id]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$submission) die("Submission not found.");

// Correct path to storage/uploads/
$full_path = __DIR__ . '/../storage/uploads/' . $submission['file_path'];
if (!file_exists($full_path)) die("File not found.");

// Serve PDF directly
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($full_path) . '"');
header('Content-Length: ' . filesize($full_path));
readfile($full_path);
exit;
