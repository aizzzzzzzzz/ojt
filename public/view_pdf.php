<?php
session_start();
include_once __DIR__ . '/../private/config.php';

// Determine user type and ID
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

// Validate 'file' parameter
if (!isset($_GET['file'])) {
    die("Invalid request.");
}
$fileName = basename($_GET['file']); // sanitize input
$filePath = __DIR__ . '/../storage/uploads/' . $fileName;

// Check if file exists
if (!file_exists($filePath)) {
    die("File not found.");
}

// Verify permissions
if ($userType === 'student') {
    // Student can only access their own submission
    $stmt = $pdo->prepare("SELECT 1 FROM project_submissions WHERE file_path = ? AND student_id = ? LIMIT 1");
    $stmt->execute([$fileName, $userId]);
    if (!$stmt->fetch()) {
        die("Access denied.");
    }
} else {
    // Employer: verify they can only access submissions for their projects
    $stmt = $pdo->prepare("
        SELECT 1
        FROM project_submissions ps
        INNER JOIN projects p ON ps.project_id = p.project_id
        WHERE ps.file_path = ? AND p.employer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$fileName, $userId]);
    if (!$stmt->fetch()) {
        die("Access denied.");
    }
}

// Serve the PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
