<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';

require_admin(); // only admins can download

// Validate GET parameter
$file_id = isset($_GET['file_id']) ? (int)$_GET['file_id'] : 0;
if (!$file_id) {
    die("Invalid file request.");
}

// Fetch file info from database
$stmt = $pdo->prepare("SELECT filename, filepath FROM uploaded_files WHERE id = ?");
$stmt->execute([$file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file || !file_exists($file['filepath'])) {
    die("File not found.");
}

// Optional: Log the download
write_audit_log('File Download', "Downloaded file: " . $file['filename']);

// Serve the file
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file['filepath']));
readfile($file['filepath']);
exit;
