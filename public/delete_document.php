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
        $checkStmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE id = ? AND employer_id = ?");
        $checkStmt->execute([$file_id, $employer_id]);
        $file = $checkStmt->fetch();
        
        if ($file) {
            // Delete the file from server
            if (file_exists($file['filepath'])) {
                unlink($file['filepath']);
            }
            
            // Delete from database
            $deleteStmt = $pdo->prepare("DELETE FROM uploaded_files WHERE id = ?");
            $deleteStmt->execute([$file_id]);
            
            write_audit_log('Delete Document', "Deleted file: " . $file['filename']);
            $_SESSION['success_message'] = "Document deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Document not found or you don't have permission to delete it.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting document: " . $e->getMessage();
    }
}

header("Location: upload_documents.php");
exit;
?>