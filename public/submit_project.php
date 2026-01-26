<?php
session_start();
include __DIR__ . '/../includes/auth_check.php';
require_role('student');

include __DIR__ . '/../private/config.php';

if (isset($_POST['save_code'])) {
    $project_id = $_POST['project_id'];
    $code = $_POST['code_content'];
    $student_id = $_SESSION['student_id'];

    // Get project due date
    $stmt = $pdo->prepare("SELECT due_date FROM projects WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        $_SESSION['error'] = 'Project not found.';
        header("Location: student_dashboard.php");
        exit;
    }

    // Determine submission status
    $current_time = date('Y-m-d H:i:s');
    $submission_status = (strtotime($current_time) <= strtotime($project['due_date'] . ' 23:59:59')) ? 'On Time' : 'Late';

    $uploadDir = __DIR__ . '/storage/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = 'project_' . $project_id . '_student_' . $student_id . '.html';
    $filePath = $uploadDir . $filename;

    file_put_contents($filePath, $code);

    // PUBLIC path saved to DB
    $publicPath = 'storage/uploads/' . $filename;

    $stmt = $pdo->prepare("
        INSERT INTO project_submissions (project_id, student_id, file_path, status, submission_status)
        VALUES (?, ?, ?, 'Pending', ?)
        ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), submission_date = NOW(), submission_status = VALUES(submission_status)
    ");
    $stmt->execute([$project_id, $student_id, $publicPath, $submission_status]);

    // Log activity
    log_activity($pdo, $student_id, 'student', "Submitted project $project_id ($submission_status)");

    $_SESSION['success'] = 'Project submitted successfully.';
    header("Location: student_dashboard.php");
    exit;
}
?>
<form method="post" enctype="multipart/form-data">
    <h3>Submit Project</h3>
    <?php if(!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <input type="file" name="project_file" required>
    <button type="submit" class="btn btn-primary">Upload</button>
</form>
