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

$fileName = basename($_GET['file']);
$filePath = __DIR__ . '/../storage/uploads/' . $fileName;

// Verify permissions AND check if file exists in database
if ($userType === 'student') {
    // Student can only access their own submission
    $stmt = $pdo->prepare("SELECT * FROM project_submissions WHERE file_path = ? AND student_id = ? LIMIT 1");
    $stmt->execute([$fileName, $userId]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        die("Access denied.");
    }
} else {
    // Employer: verify they can only access submissions for their projects
    $stmt = $pdo->prepare("
        SELECT ps.*
        FROM project_submissions ps
        INNER JOIN projects p ON ps.project_id = p.project_id
        WHERE ps.file_path = ? AND p.created_by = ?
        LIMIT 1
    ");
    $stmt->execute([$fileName, $userId]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        die("Access denied.");
    }
}

// Check if physical file exists
if (!file_exists($filePath)) {
    die("File not found.");
}

// Clear output buffers
ob_clean();
ob_start();

// Get MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// Set headers
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
header('Content-Transfer-Encoding: binary');
header('Cache-Control: public, must-revalidate, max-age=0');
header('Pragma: public');
header('Expires: 0');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

ob_end_flush();
readfile($filePath);
exit;