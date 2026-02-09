<?php
session_start();
include __DIR__ . '/../includes/auth_check.php';
require_role('student');

include __DIR__ . '/../private/config.php';

$project_id = (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$student_id = $_SESSION['student_id'];

// If form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['project_file'])) {
    
    // Get project details
    $stmt = $pdo->prepare("SELECT due_date, project_name FROM projects WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        $_SESSION['error'] = 'Project not found.';
        header("Location: student_dashboard.php");
        exit;
    }

    // REMOVED: Don't check if rejected - allow resubmission

    // File validation
    $allowed_types = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'text/html' => 'html',
        'text/plain' => 'txt',
        'application/zip' => 'zip'
    ];
    
    $file_type = $_FILES['project_file']['type'];
    $file_size = $_FILES['project_file']['size'];
    $max_size = 10 * 1024 * 1024; // 10MB

    if (!array_key_exists($file_type, $allowed_types)) {
        $_SESSION['error'] = 'Invalid file type. Allowed: PDF, DOC, DOCX, HTML, TXT, ZIP.';
        header("Location: student_submit_form.php?project_id=" . $project_id);
        exit;
    }

    if ($file_size > $max_size) {
        $_SESSION['error'] = 'File too large. Maximum size: 10MB.';
        header("Location: student_submit_form.php?project_id=" . $project_id);
        exit;
    }

    // Create upload directory
    $uploadDir = __DIR__ . '/../storage/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

        // Change this section:
    // Generate unique filename with attempt number
    $extension = $allowed_types[$file_type];

    // Get submission count for this student+project
    $countStmt = $pdo->prepare("SELECT COUNT(*) as attempt_count FROM project_submissions WHERE project_id = ? AND student_id = ?");
    $countStmt->execute([$project_id, $student_id]);
    $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $attempt_number = $countResult['attempt_count'] + 1;

    $filename = 'project_' . $project_id . '_student_' . $student_id . '_attempt_' . $attempt_number . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($_FILES['project_file']['tmp_name'], $filePath)) {
        $_SESSION['error'] = 'Failed to upload file. Please try again.';
        header("Location: student_submit_form.php?project_id=" . $project_id);
        exit;
    }

    // Determine if submission is on time or late
    $current_time = date('Y-m-d H:i:s');
    $submission_status = (strtotime($current_time) <= strtotime($project['due_date'] . ' 23:59:59')) ? 'On Time' : 'Late';

    // Public path for database
    $publicPath = 'storage/uploads/' . $filename;

    try {
        // ALWAYS INSERT new submission (never update)
        $stmt = $pdo->prepare("
            INSERT INTO project_submissions (project_id, student_id, file_path, submission_date, submission_status, status)
            VALUES (?, ?, ?, NOW(), ?, 'Pending')
        ");
        $stmt->execute([$project_id, $student_id, $publicPath, $submission_status]);

        // Log activity
        if (function_exists('log_activity')) {
            log_activity($pdo, $student_id, 'student', "Submitted project: {$project['project_name']} (Attempt #{$attempt_number}, $submission_status)");
        }

        $_SESSION['success'] = 'Project submitted successfully! (Attempt #' . $attempt_number . ')';
        header("Location: student_dashboard.php");
        exit;

    } catch (PDOException $e) {
        // Delete the uploaded file if database error occurs
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        error_log("Database error in submit_project.php: " . $e->getMessage());
        $_SESSION['error'] = 'Database error occurred. Please try again.';
        header("Location: student_submit_form.php?project_id=" . $project_id);
        exit;
    }
} else {
    // If no file uploaded
    $_SESSION['error'] = 'No file selected for upload.';
    header("Location: student_submit_form.php?project_id=" . $project_id);
    exit;
}
?>