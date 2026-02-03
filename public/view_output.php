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

$fileName = basename($_GET['file']);
$filePath = __DIR__ . '/../storage/uploads/' . $fileName;

$stmt = $pdo->prepare("SELECT * FROM project_submissions WHERE file_path = ? AND student_id = ? LIMIT 1");
$stmt->execute([$fileName, $student_id]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission || !file_exists($filePath)) {
    die("File not found or access denied.");
}

ob_clean();
ob_start();

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

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