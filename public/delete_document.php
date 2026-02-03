<?php
session_start();

require_once __DIR__ . '/../includes/middleware.php';
include __DIR__ . '/../private/config.php';

// Check if user is logged in as employer or admin
if (!isset($_SESSION['employer_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: employer_login.php");
    exit;
}

// Determine user type
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
        // Get file info before deleting
        $stmt = $pdo->prepare("SELECT filename, filepath FROM uploaded_files WHERE id = ? AND uploader_type = ? AND uploader_id = ?");
        $stmt->execute([$file_id, $user_type, $user_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            // Delete from database
            $deleteStmt = $pdo->prepare("DELETE FROM uploaded_files WHERE id = ?");
            $deleteStmt->execute([$file_id]);
            
            // Delete physical file
            if (file_exists($file['filepath'])) {
                unlink($file['filepath']);
            }
            
            // Log the deletion with correct user info
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