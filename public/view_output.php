<?php
session_start();
include_once __DIR__ . '/../private/config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit;
}

$student_id = (int)$_SESSION['student_id'];

if (!isset($_GET['file'])) {
    die("Invalid request.");
}

$fileName = basename($_GET['file']); // sanitize
$filePath = __DIR__ . '/../storage/uploads/' . $fileName;

// Check if the student owns this submission
$stmt = $pdo->prepare("SELECT * FROM project_submissions WHERE file_path = ? AND student_id = ? LIMIT 1");
$stmt->execute([$fileName, $student_id]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission || !file_exists($filePath)) {
    die("File not found or access denied.");
}

// Serve the PDF directly
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
