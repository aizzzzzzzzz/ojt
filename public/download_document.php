<?php
session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';

// Only supervisor or admin can download documents
if (empty($_SESSION['employer_id']) && empty($_SESSION['admin_id'])) {
    http_response_code(403);
    die('Access denied.');
}

$doc_id = (int) ($_GET['id'] ?? 0);

if ($doc_id <= 0) {
    http_response_code(400);
    die('Invalid document ID.');
}

// Get document info
$stmt = $pdo->prepare("SELECT id, filename, filepath FROM moa_documents WHERE id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc || empty($doc['filepath']) || !file_exists($doc['filepath'])) {
    http_response_code(404);
    die('Document not found.');
}

// Serve the file
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($doc['filename']) . '"');
header('Content-Length: ' . filesize($doc['filepath']));
header('Cache-Control: no-cache');
readfile($doc['filepath']);
exit;
