<?php
session_start();

// Check if user is logged in as employer or admin
if ((!isset($_SESSION['employer_id']) && !isset($_SESSION['admin_id'])) ||
    ($_SESSION['role'] !== "employer" && $_SESSION['role'] !== "admin")) {
    header("Location: employer_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';

// Determine uploader type and id
if (isset($_SESSION['employer_id'])) {
    $uploader_type = 'employer';
    $uploader_id = $_SESSION['employer_id'];
} else {
    $uploader_type = 'admin';
    $uploader_id = $_SESSION['admin_id'];
}

$file_id = $_GET['file_id'] ?? 0;

if ($file_id) {
    try {
        // Verify the file belongs to this user
        $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE id = ? AND uploader_type = ? AND uploader_id = ?");
        $stmt->execute([$file_id, $uploader_type, $uploader_id]);
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
