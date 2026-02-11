<?php
session_start();
include __DIR__ . '/../includes/auth_check.php';
require_role('student');

// Debug mode
ini_set('display_errors', 1);
error_reporting(E_ALL);

include __DIR__ . '/../private/config.php';

$project_id = (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$student_id = $_SESSION['student_id'];

// Check if already approved for this project
$approvedCheckStmt = $pdo->prepare("SELECT 1 FROM project_submissions WHERE project_id = ? AND student_id = ? AND status = 'Approved' LIMIT 1");
$approvedCheckStmt->execute([$project_id, $student_id]);
if ($approvedCheckStmt->fetch()) {
    $_SESSION['error'] = 'You have already passed this project. No further submissions are allowed.';
    header("Location: student_submit_form.php?project_id=" . $project_id);
    exit;
}

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

    // FIXED: Determine if submission is on time or late with better validation
    $current_time = date('Y-m-d H:i:s');
    
    // Check if due_date exists and is valid
    if (empty($project['due_date']) || $project['due_date'] == '0000-00-00') {
        // If no due date or invalid, consider it On Time
        $submission_status = 'On Time';
    } else {
        // Ensure due_date has time component
        $due_date_str = $project['due_date'];
        if (strlen($due_date_str) == 10) { // YYYY-MM-DD format
            $due_date_str .= ' 23:59:59';
        }
        
        // Convert to timestamps
        $current_timestamp = strtotime($current_time);
        $due_timestamp = strtotime($due_date_str);
        
        // Debug timestamps
        echo "<p>DEBUG: Current timestamp: $current_timestamp (" . date('Y-m-d H:i:s', $current_timestamp) . ")</p>";
        echo "<p>DEBUG: Due timestamp: $due_timestamp (" . date('Y-m-d H:i:s', $due_timestamp) . ")</p>";
        
        if ($current_timestamp === false || $due_timestamp === false) {
            // Invalid timestamps
            $submission_status = 'On Time';
        } else {
            $submission_status = ($current_timestamp <= $due_timestamp) ? 'On Time' : 'Late';
        }
    }
    
    // Validate submission_status
    if (!in_array($submission_status, ['On Time', 'Late'])) {
        // Fallback to 'On Time' if invalid
        $submission_status = 'On Time';
    }

    // Public path for database
    $publicPath = 'storage/uploads/' . $filename;

    try {
        // Check if there's a rejected submission to update
        $checkStmt = $pdo->prepare("SELECT submission_id FROM project_submissions WHERE project_id = ? AND student_id = ? AND status = 'Rejected' ORDER BY submission_date DESC LIMIT 1");
        $checkStmt->execute([$project_id, $student_id]);
        $existingRejected = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingRejected) {
            // Update the existing rejected submission
            $updateStmt = $pdo->prepare("UPDATE project_submissions SET file_path = ?, status = 'Pending', submission_date = NOW(), submission_status = ?, remarks = '', graded_at = NULL WHERE submission_id = ?");
            $updateStmt->execute([$publicPath, $submission_status, $existingRejected['submission_id']]);
            $submission_id = $existingRejected['submission_id'];
            $message = 'Project resubmitted successfully!';
        } else {
            // Insert new submission
            $insertStmt = $pdo->prepare("INSERT INTO project_submissions (project_id, student_id, file_path, submission_status, status) VALUES (?, ?, ?, ?, 'Pending')");
            $insertStmt->execute([$project_id, $student_id, $publicPath, $submission_status]);
            $submission_id = $pdo->lastInsertId();
            $message = 'Project submitted successfully! (Attempt #' . $attempt_number . ')';
        }

        // Log activity
        if (function_exists('log_activity')) {
            log_activity($pdo, $student_id, 'student', "Submitted project: {$project['project_name']} ($submission_status)");
        }

        $_SESSION['success'] = $message;
        header("Location: student_dashboard.php");
        exit;

    } catch (PDOException $e) {
        // Delete the uploaded file if database error occurs
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $_SESSION['error'] = 'Database error occurred. Please try again. Error: ' . $e->getMessage();
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