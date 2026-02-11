<?php
session_start();
if (!isset($_SESSION['employer_id'])) {
    header("Location: supervisor_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/email.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    // Debug logging
    error_log("Grade submission attempt: submission_id=$submission_id, status=$status, remarks=$remarks");

    if (!$submission_id || !in_array($status, ['Pending', 'Approved', 'Rejected'])) {
        $_SESSION['error'] = 'Invalid data provided.';
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'manage_projects.php'));
        exit;
    }

    // Check if submission belongs to a project created by this employer (IDOR prevention)
    $stmt = $pdo->prepare("
        SELECT ps.status
        FROM project_submissions ps
        INNER JOIN projects p ON ps.project_id = p.project_id
        WHERE ps.submission_id = ? AND p.created_by = ?
    ");
    $stmt->execute([$submission_id, $_SESSION['employer_id']]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        $_SESSION['error'] = 'Access denied or submission not found.';
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'manage_projects.php'));
        exit;
    }

    if ($submission['status'] === 'Rejected') {
        $_SESSION['error'] = 'Cannot grade a rejected submission.';
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'manage_projects.php'));
        exit;
    }

    // Workaround: Use direct SQL query instead of prepared statement
    $remarks_escaped = $pdo->quote($remarks);
    $status_escaped = $pdo->quote($status);

    $sql = "UPDATE project_submissions SET status = $status_escaped, remarks = $remarks_escaped, graded_at = NOW() WHERE submission_id = $submission_id";

    try {
        $result = $pdo->exec($sql);

        if ($result > 0) {
            $_SESSION['success'] = 'Submission graded successfully.';

            // Send email notification to student
            error_log("DEBUG: Attempting to send " . strtolower($status) . " email for submission_id: $submission_id");
            $student_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM students WHERE student_id = (SELECT student_id FROM project_submissions WHERE submission_id = ?)");
            $student_stmt->execute([$submission_id]);
            $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
            error_log("DEBUG: Student data: " . print_r($student, true));

            if ($student && !empty($student['email'])) {
                $student_name = ucwords(strtolower($student['first_name'] . ' ' . $student['last_name']));
                $supervisor_stmt = $pdo->prepare("SELECT name FROM employers WHERE employer_id = ?");
                $supervisor_stmt->execute([$_SESSION['employer_id']]);
                $supervisor = $supervisor_stmt->fetch(PDO::FETCH_ASSOC);
                $supervisor_name = $supervisor ? $supervisor['name'] : 'Supervisor';

                if ($status === 'Approved') {
                    // Disable the project by setting status to 'Completed'
                    $project_stmt = $pdo->prepare("SELECT project_id FROM project_submissions WHERE submission_id = ?");
                    $project_stmt->execute([$submission_id]);
                    $project = $project_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($project) {
                        $updateProjectStmt = $pdo->prepare("UPDATE projects SET status = 'Completed' WHERE project_id = ?");
                        $updateProjectStmt->execute([$project['project_id']]);
                    }

                    $email_result = send_project_approval_notification($student['email'], $student_name, $supervisor_name, $remarks);
                } elseif ($status === 'Rejected') {
                    $email_result = send_project_rejection_notification($student['email'], $student_name, $supervisor_name);
                }

                if ($email_result !== true) {
                    error_log("Failed to send " . strtolower($status) . " notification: " . $email_result);
                }
            }
        } else {
            $_SESSION['error'] = 'No changes made or submission not found.';
        }

    } catch (PDOException $e) {
        error_log("Grade submission error: " . $e->getMessage() . " | SQL: $sql");
        $_SESSION['error'] = 'Database error occurred. Please try again. Error: ' . $e->getMessage();
    }
    
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'manage_projects.php'));
    exit;
} else {
    header("Location: manage_projects.php");
    exit;
}
?>