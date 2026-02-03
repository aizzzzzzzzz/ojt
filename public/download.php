<?php
session_start();

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== "employer") {
    header("Location: employer_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';

$employer_id = $_SESSION['employer_id'];
$file_id = $_GET['file_id'] ?? 0;

if ($file_id) {
    try {
        // Verify the file belongs to this employer
        $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE id = ? AND employer_id = ?");
        $stmt->execute([$file_id, $employer_id]);
        $file = $stmt->fetch();
        
        if ($file && file_exists($file['filepath'])) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
            header('Content-Length: ' . filesize($file['filepath']));
            readfile($file['filepath']);
            write_audit_log('Download Document', "Downloaded: " . $file['filename']);
            exit;
        } else {
            header("Location: upload_documents.php?error=not_found");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: upload_documents.php?error=database");
        exit;
    }
}

header("Location: upload_documents.php");
exit;
?>