<?php
session_start();

require_once __DIR__ . '/../includes/middleware.php';
include __DIR__ . '/../private/config.php';

if (!isset($_SESSION['employer_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: employer_login.php");
    exit;
}

if (isset($_SESSION['employer_id'])) {
    $user_type = 'employer';
    $user_id = $_SESSION['employer_id'];
} else {
    $user_type = 'admin';
    $user_id = $_SESSION['admin_id'];
}

if (isset($_GET['file_id'])) {
    $file_id = intval($_GET['file_id']);
    
    try {
        $stmt = $pdo->prepare("SELECT filename, filepath FROM uploaded_files WHERE id = ? AND uploader_type = ? AND uploader_id = ?");
        $stmt->execute([$file_id, $user_type, $user_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            $deleteStmt = $pdo->prepare("DELETE FROM uploaded_files WHERE id = ?");
            $deleteStmt->execute([$file_id]);
            
            if (file_exists($file['filepath'])) {
                unlink($file['filepath']);
            }
            
            write_audit_log_manual($user_type, $user_id, 'Delete Document', 'Deleted file: ' . $file['filename']);
            
            $_SESSION['success_message'] = "File deleted successfully!";
        } else {
            $_SESSION['error_message'] = "File not found or you don't have permission to delete it.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting file: " . $e->getMessage();
    }
}

header("Location: upload_documents.php");
exit;
?>