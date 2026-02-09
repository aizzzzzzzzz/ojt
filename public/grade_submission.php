<?php
session_start();
if (!isset($_SESSION['employer_id'])) {
    header("Location: supervisor_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    if (!$submission_id || !in_array($status, ['Pending', 'Approved', 'Rejected'])) {
        $_SESSION['error'] = 'Invalid data provided.';
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'manage_projects.php'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE project_submissions 
            SET status = ?, 
                remarks = ?,
                graded_at = NOW()
            WHERE submission_id = ?
        ");
        $stmt->execute([$status, $remarks, $submission_id]);
        
        $_SESSION['success'] = 'Submission graded successfully.';
        
    } catch (PDOException $e) {
        error_log("Grade submission error: " . $e->getMessage());
        $_SESSION['error'] = 'Database error occurred.';
    }
    
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'manage_projects.php'));
    exit;
} else {
    header("Location: manage_projects.php");
    exit;
}
?>