<?php
session_start();
include_once __DIR__ . '/../private/config.php';

if (!isset($_SESSION['student_id']) && !isset($_SESSION['employer_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: student_login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$attendance_id = (int)$_GET['id'];

$is_student = isset($_SESSION['student_id']);
$is_supervisor = isset($_SESSION['employer_id']);
$is_admin = isset($_SESSION['admin_id']);
$current_user_id = $_SESSION['student_id'] ?? $_SESSION['employer_id'] ?? $_SESSION['admin_id'];

if ($is_student) {
    $student_id = (int)$_SESSION['student_id'];
    $stmt = $pdo->prepare("SELECT dtr_picture, log_date FROM attendance WHERE id = ? AND student_id = ? LIMIT 1");
    $stmt->execute([$attendance_id, $student_id]);
} else {
    $stmt = $pdo->prepare("SELECT dtr_picture, log_date FROM attendance WHERE id = ? LIMIT 1");
    $stmt->execute([$attendance_id]);
}

$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record || empty($record['dtr_picture'])) {
    die("DTR picture not found or access denied.");
}

$filePath = __DIR__ . '/../' . $record['dtr_picture'];

$realPath = realpath($filePath);
$allowedDir = realpath(__DIR__ . '/../storage/uploads/dtr/');

if (!$realPath || strpos($realPath, $allowedDir) !== 0 || !file_exists($realPath)) {
    die("File not found or access denied.");
}

ob_clean();
ob_start();

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $realPath);
finfo_close($finfo);

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mimeType, $allowedMimes)) {
    die("Invalid file type.");
}

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . basename($realPath) . '"');
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: public, max-age=86400');
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));

ob_end_flush();
readfile($realPath);
exit;
