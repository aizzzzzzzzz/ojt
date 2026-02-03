<?php
session_start();
if (!isset($_SESSION['employer_id'])) {
    header("Location: employer_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_id = $_POST['submission_id'] ?? 0;
    $remarks = $_POST['remarks'] ?? '';
    $status = $_POST['status'] ?? 'Pending';

    // Get project_id for redirect
    $stmt = $pdo->prepare("SELECT project_id FROM project_submissions WHERE submission_id = ?");
    $stmt->execute([$submission_id]);
    $project_id = $stmt->fetchColumn();

    if ($project_id) {
        // Update submission
        $update_stmt = $pdo->prepare("
            UPDATE project_submissions
            SET remarks = ?, status = ?, graded_at = NOW()
            WHERE submission_id = ?
        ");
        $update_stmt->execute([$remarks, $status, $submission_id]);

        // Log activity
        include __DIR__ . '/../includes/audit.php';
        audit_log($pdo, "Graded submission ID: $submission_id with status: $status");

        // Redirect back
        header("Location: project_submissions.php?project_id=$project_id");
        exit;
    } else {
        echo "Invalid submission.";
        exit;
    }
} else {
    header("Location: supervisor_dashboard.php");
    exit;
}
?>
