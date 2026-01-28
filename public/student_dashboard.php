<?php
session_start();
include_once __DIR__ . '/../private/config.php';
date_default_timezone_set('Asia/Manila');

$editorPreview = '';
if (isset($_POST['code_editor'])) {
    $code = $_POST['code_editor'];
    ob_start();
    eval('?>' . $code);
    $editorPreview = ob_get_clean();
}

if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== "student") {
    header("Location: student_login.php");
    exit;
}

$student_id = (int)$_SESSION['student_id'];
$today = date('Y-m-d');
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_task'])) {
    $task = trim($_POST['daily_task']);

    $check = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND log_date = ? LIMIT 1");
    $check->execute([$student_id, $today]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $upd = $pdo->prepare("UPDATE attendance SET daily_task = ? WHERE id = ?");
        $upd->execute([$task, $existing['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO attendance (student_id, employer_id, log_date, daily_task, status) VALUES (?, NULL, ?, ?, 'present')");
        $ins->execute([$student_id, $today, $task]);
    }

    $_SESSION['success'] = "Daily task saved successfully.";
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_action'])) {
    $action = $_POST['attendance_action'];
    $allowed = ['time_in','lunch_out','lunch_in','time_out'];
    if (!in_array($action, $allowed)) {
        $messages[] = "Invalid action.";
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND log_date = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([$student_id, $today]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $now = date('Y-m-d H:i:s');

            if (!$row) {
                if ($action !== 'time_in') {
                    $messages[] = "You must Time In first.";
                    $pdo->rollBack();
                } else {
                    $insert = $pdo->prepare("INSERT INTO attendance (student_id, employer_id, log_date, time_in, status) VALUES (?, NULL, ?, ?, 'present')");
                    $insert->execute([$student_id, $today, $now]);
                    $pdo->commit();
                    $_SESSION['success'] = "Time In recorded at " . date('H:i:s', strtotime($now)) . ".";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            } else {
                $updates = [];
                $params = [];
                switch ($action) {
                    case 'time_in':
                        if ($row['time_in']) { $messages[] = "Time In already recorded ({$row['time_in']})."; $pdo->rollBack(); break; }
                        $updates[] = "time_in = ?"; $params[] = $now;
                        break;
                    case 'lunch_out':
                        if (!$row['time_in']) { $messages[] = "You need to Time In first."; $pdo->rollBack(); break; }
                        if ($row['lunch_out']) { $messages[] = "Lunch Out already recorded ({$row['lunch_out']})."; $pdo->rollBack(); break; }
                        $updates[] = "lunch_out = ?"; $params[] = $now;
                        break;
                    case 'lunch_in':
                        if (!$row['lunch_out']) { $messages[] = "You need to Lunch Out first."; $pdo->rollBack(); break; }
                        if ($row['lunch_in']) { $messages[] = "Lunch In already recorded ({$row['lunch_in']})."; $pdo->rollBack(); break; }
                        $updates[] = "lunch_in = ?"; $params[] = $now;
                        break;
                    case 'time_out':
                        if (!$row['time_in']) { $messages[] = "You need to Time In first."; $pdo->rollBack(); break; }
                        if ($row['time_out']) { $messages[] = "Time Out already recorded ({$row['time_out']})."; $pdo->rollBack(); break; }
                        $updates[] = "time_out = ?"; $params[] = $now;
                        break;
                }

                if (!empty($updates) && $pdo->inTransaction()) {
                    $sql = "UPDATE attendance SET " . implode(", ", $updates) . " WHERE student_id = ? AND log_date = ?";
                    $params[] = $student_id;
                    $params[] = $today;
                    $upd = $pdo->prepare($sql);
                    $upd->execute($params);
                    $pdo->commit();
                    $_SESSION['success'] = ucfirst(str_replace('_',' ', $action)) . " recorded at " . date('H:i:s', strtotime($now)) . ".";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $messages[] = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch student info
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch attendance history
$attendance_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY log_date DESC");
$attendance_stmt->execute([$student_id]);
$attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

// Today's row
$today_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND log_date = ? LIMIT 1");
$today_stmt->execute([$student_id, $today]);
$today_row = $today_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate total accumulated minutes safely
$total_minutes = 0;
foreach ($attendance as $row) {
    // Only count verified attendance
    if ($row['verified'] == 1 && !empty($row['time_in']) && !empty($row['time_out'])) {

        $time_in = strtotime($row['time_in']);
        $time_out = strtotime($row['time_out']);
        $minutesWorked = max(0, ($time_out - $time_in) / 60);

        if (!empty($row['lunch_out']) && !empty($row['lunch_in'])) {
            $lunch_out = strtotime($row['lunch_out']);
            $lunch_in = strtotime($row['lunch_in']);
            $minutesWorked -= max(0, ($lunch_in - $lunch_out) / 60);
        }

        $total_minutes += max(0, $minutesWorked);
    }
}

$hours = floor($total_minutes/60);
$minutes = $total_minutes % 60;
$statusClass = ($hours >= 200) ? 'completed' : 'in-progress';
$statusText = ($hours >= 200) ? 'Completed' : 'In Progress';

// Fetch projects for student
$projects_stmt = $pdo->prepare("SELECT * FROM projects ORDER BY created_at DESC");
$projects_stmt->execute();
$projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle file submission
$submitError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_file'])) {
    $project_id = (int)$_POST['project_id'];
    $remarks = trim($_POST['remarks'] ?? '');
    $submission_type = $_POST['submission_type'] ?? 'code'; // 'code' or 'file'
    
    $uploadDir = __DIR__ . '/../storage/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    try {
        if ($submission_type === 'code') {
            // Handle code submission
            $code = $_POST['code_content'] ?? '';
            
            if (empty($code)) {
                $submitError = "Code cannot be empty.";
            } else {
                // Save code to .txt file (generic code file)
                $fileName = $student_id . '_project_' . $project_id . '_' . time() . '.txt';
                $filePath = $uploadDir . $fileName;
                
                if (file_put_contents($filePath, $code) !== false) {
                    $stmt = $pdo->prepare("INSERT INTO project_submissions (project_id, student_id, file_path, status, submission_date, remarks, submission_status) VALUES (?, ?, ?, 'submitted', NOW(), ?, 'pending')");
                    $stmt->execute([$project_id, $student_id, $fileName]);
                    $_SESSION['success'] = "Code submitted successfully!";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $submitError = "Error saving code. Please try again.";
                }
            }
        } else {
            // Handle file upload
            if (empty($_FILES['submission_file']['tmp_name'])) {
                $submitError = "Please select a file to submit.";
            } else {
                $fileName = $_FILES['submission_file']['name'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExts = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'php', 'html', 'css', 'java', 'js'];
                
                if (!in_array($fileExt, $allowedExts)) {
                    $submitError = "File type not allowed. Allowed: " . implode(', ', $allowedExts);
                } elseif ($_FILES['submission_file']['size'] > 10 * 1024 * 1024) {
                    $submitError = "File too large (max 10MB).";
                } else {
                    $uniqueFileName = $student_id . '_project_' . $project_id . '_' . time() . '_' . basename($fileName);
                    $filePath = $uploadDir . $uniqueFileName;
                    
                    if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $filePath)) {
                        $stmt = $pdo->prepare("INSERT INTO project_submissions (project_id, student_id, file_path, status, submission_date, remarks, submission_status) VALUES (?, ?, ?, 'submitted', NOW(), ?, 'pending')");
                        $stmt->execute([$project_id, $student_id, $uniqueFileName]);
                        $_SESSION['success'] = "File submitted successfully!";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit;
                    } else {
                        $submitError = "Error uploading file. Please try again.";
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $submitError = "Error submitting: " . $e->getMessage();
    }
}

// Fetch student's submissions
$submissions_stmt = $pdo->prepare("SELECT ps.*, p.project_name FROM project_submissions ps JOIN projects p ON ps.project_id = p.project_id WHERE ps.student_id = ? ORDER BY ps.submission_date DESC");
$submissions_stmt->execute([$student_id]);
$submissions = $submissions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Default code for editor
$defaultCode = "<?php\necho 'Hello PHP!';\n?>\n<h1>Hello HTML + CSS + JS!</h1>\n<style>h1{color:#0b3d91;}</style>\n<script>console.log('Hello JS');</script>";
$safeDefaultCode = str_replace('</script>', '</scr"+"ipt>', $defaultCode);
?>
<script>
var defaultCode = <?php echo json_encode($defaultCode); ?>;
var safeDefaultCode = <?php echo json_encode($safeDefaultCode); ?>;
</script>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Student Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
<style>
    body {
        margin: 0;
        padding: 0;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #e8f5e8, #d1ecf1);
        color: #333;
        line-height: 1.6;
    }

    .dashboard-container {
        background: rgba(255, 255, 255, 0.95);
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 1200px;
        margin: 20px auto;
        text-align: center;
    }

    .welcome-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .welcome-header h2 {
        margin: 0;
        font-size: 28px;
        font-weight: 600;
        color: #2c3e50;
    }

    /* Tab Switcher Styles */
    .tab-switcher {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 10px;
    }

    .tab-button {
        padding: 12px 24px;
        border: none;
        background: none;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        border-radius: 8px 8px 0 0;
        transition: all 0.3s ease;
        margin: 0 5px;
    }

    .tab-button.active {
        background: linear-gradient(90deg, #28a745, #85e085);
        color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .tab-button.active:hover {
        background: linear-gradient(90deg, #28a745, #85e085);
        color: white;
    }

    .tab-button:hover {
        background: #f8f9fa;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    /* Tab Switcher Styles */
    .tab-switcher {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 10px;
    }

    .tab-button {
        padding: 12px 24px;
        border: none;
        background: none;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        border-radius: 8px 8px 0 0;
        transition: all 0.3s ease;
        margin: 0 5px;
    }

    .tab-button.active {
        background: linear-gradient(90deg, #28a745, #85e085);
        color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .tab-button:hover {
        background: #f8f9fa;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .attendance-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: center;
        margin-bottom: 20px;
    }

    .action-btn {
        padding: 12px 18px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        min-width: 140px;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background: linear-gradient(90deg, #28a745, #85e085);
        color: white;
    }

    .btn-primary:hover {
        background: linear-gradient(90deg, #218838, #6c9e6c);
        transform: translateY(-2px);
    }

    .btn-disabled {
        background: #ddd;
        color: #666;
        cursor: not-allowed;
    }

    .summary {
        text-align: left;
        margin-bottom: 20px;
        padding: 20px;
        border-radius: 10px;
        background: linear-gradient(90deg, #f8fff8, #e8f5e8);
        border: 1px solid #c3e6cb;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .success-msg {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid #c3e6cb;
        text-align: left;
        font-weight: 500;
    }

    .error-msg {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid #f5c6cb;
        text-align: left;
        font-weight: 500;
    }

    .task-section, .attendance-section {
        margin-bottom: 20px;
        padding: 20px;
        border-radius: 10px;
        border: 1px solid #e0e0e0;
        background: #f8f9fa;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .task-section h3, .attendance-section h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #2c3e50;
    }

    .table-section {
        overflow-x: auto;
        margin-top: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 10px;
        overflow: hidden;
    }

    th, td {
        padding: 12px;
        border-bottom: 1px solid #e0e0e0;
        text-align: center;
    }

    th {
        background: linear-gradient(90deg, #f8f9fa, #e9ecef);
        font-weight: 600;
        color: #2c3e50;
    }

    tr:nth-child(even) {
        background: #f8f9fa;
    }

    tr:hover {
        background: #e3f2fd;
        transition: background 0.3s ease;
    }

    .status.completed {
        color: green;
        font-weight: bold;
    }

    .status.in-progress {
        color: orange;
        font-weight: bold;
    }

    /* Mobile Card View Styles */
    .mobile-view {
        display: none;
    }

    .attendance-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        overflow: hidden;
        border: 1px solid #e0e0e0;
    }

    .card-header {
        background: linear-gradient(90deg, #f8f9fa, #e9ecef);
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        border-bottom: 1px solid #e0e0e0;
    }

    .status-badge {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .status-text {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: bold;
    }

    .verified-badge {
        color: green;
        font-weight: bold;
        font-size: 12px;
    }

    .unverified-badge {
        color: red;
        font-weight: bold;
        font-size: 12px;
    }

    .card-body {
        padding: 15px;
    }

    .time-info {
        margin-bottom: 15px;
    }

    .time-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .time-row:last-child {
        border-bottom: none;
    }

    .label {
        font-weight: 600;
        color: #2c3e50;
        min-width: 100px;
    }

    .value {
        color: #333;
        font-weight: 500;
    }

    .task-info {
        background: #f8f9fa;
        padding: 12px;
        border-radius: 8px;
        border-left: 4px solid #28a745;
    }

    .task-info p {
        margin: 0;
        color: #333;
        line-height: 1.4;
    }

    /* Code Editor Styles */
    .projects-section {
        margin-bottom: 20px;
        padding: 20px;
        border-radius: 10px;
        border: 1px solid #e0e0e0;
        background: #f8f9fa;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .projects-section h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #2c3e50;
    }

    .projects-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .project-card {
        background: white;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #ddd;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .project-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-color: #28a745;
    }

    .project-card h5 {
        margin: 0 0 10px 0;
        color: #2c3e50;
    }

    .project-card p {
        margin: 0;
        font-size: 14px;
        color: #666;
    }

    .code-editor-section {
        background: white;
        padding: 20px;
        border-radius: 10px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        margin-top: 20px;
    }

    .code-editor-section h4 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #2c3e50;
    }

    .editor-controls {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
        flex-wrap: wrap;
        align-items: center;
    }

    .editor-controls select {
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid #ddd;
        font-size: 14px;
    }

    /* Code Editor Split Screen Styles - ADD THIS */
    #codeTab {
        display: flex;
        height: 500px;
        gap: 15px;
        margin-bottom: 15px;
    }

    .editor-half, .preview-half {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .editor-half h6, .preview-half h6 {
        margin: 0 0 10px 0;
        font-size: 16px;
        color: #2c3e50;
        font-weight: 600;
    }

    #codeEditorContainer {
        flex: 1;
        border: 1px solid #ddd;
        border-radius: 6px;
        overflow: hidden;
        background: #fff;
    }

    .CodeMirror {
        height: 100% !important;
        font-size: 14px !important;
        line-height: 1.5 !important;
    }

    .fullscreen-ide .CodeMirror {
        height: 100% !important;
        border-radius: 0;
        border: none;
    }

    .CodeMirror-scroll {
        height: 100%;
    }

    .fullscreen-ide .CodeMirror-scroll {
        height: 100% !important;
    }



    /* Full Screen IDE Styles */
    .fullscreen-ide {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: white;
        z-index: 9999;
        display: flex;
        flex-direction: column;
    }

    .ide-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        background: #f8f9fa;
        border-bottom: 1px solid #e0e0e0;
        flex-shrink: 0;
    }

    .ide-header h4 {
        margin: 0;
        color: #2c3e50;
    }

    .ide-content {
        flex: 1;
        display: flex;
        overflow: hidden; /* Prevent expansion */
        min-height: 0; /* Important for nested flex containers */
    }

    .code-panel {
        flex: 1;
        display: flex;
        flex-direction: column;
        border-right: 1px solid #e0e0e0;
        overflow: hidden;
    }

    .output-panel {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .panel-header {
        padding: 10px;
        background: #f8f9fa;
        border-bottom: 1px solid #e0e0e0;
        font-weight: 600;
        color: #2c3e50;
        flex-shrink: 0; /* Prevent header from shrinking */
    }

    .panel-content {
        flex: 1;
        padding: 10px;
        overflow: hidden; /* Ensure content stays within bounds */
        position: relative;
    }

    .fullscreen-ide .panel-content {
        padding: 0;
    }

    .ide-controls {
        display: flex;
        gap: 10px;
        padding: 15px;
        background: #f8f9fa;
        border-top: 1px solid #e0e0e0;
        flex-shrink: 0;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #2c3e50;
    }

    .form-control {
        border-radius: 6px;
        border: 1px solid #ddd;
        padding: 10px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }

    .form-control:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }

    .submit-code-btn {
        margin-top: 15px;
    }

    .submissions-list {
        margin-top: 30px;
    }

    .submission-card {
        background: white;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #28a745;
        margin-bottom: 15px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .submission-card h6 {
        margin: 0 0 10px 0;
        color: #2c3e50;
    }

    .submission-meta {
        font-size: 13px;
        color: #999;
        margin-bottom: 10px;
    }

    @media (max-width: 768px) { 
        .dashboard-container {
            padding: 20px;
            margin: 10px;
        }

        .welcome-header {
            flex-direction: column;
            text-align: center;
        }

        .welcome-header h2 {
            font-size: 24px;
        }

        .attendance-actions {
            flex-direction: column;
        }

        .action-btn {
            width: 100%;
            min-width: unset;
        }

        .summary, .task-section, .attendance-section, .projects-section {
            padding: 15px;
        }

        .projects-grid {
            grid-template-columns: 1fr;
        }

        .CodeMirror {
            height: 300px;
        }

        .CodeMirror-scroll {
            height: 300px;
        }

        #codeEditorContainer {
            height: 300px;
        }

        .editor-controls {
            flex-direction: column;
        }

        .editor-controls select {
            width: 100%;
        }

        /* Hide desktop table and show mobile cards */
        .desktop-view {
            display: none;
        }

        .mobile-view {
            display: block;
        }
    }
</style>
</head>
<body>
<div class="dashboard-container">
<?php if (!empty($_SESSION['success'])): ?>
    <div class="success-msg"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (!empty($messages)) foreach ($messages as $m): ?>
    <div class="error-msg"><?= htmlspecialchars($m) ?></div>
<?php endforeach; ?>

<div class="welcome-header" id="welcomeHeader">
    <h2>Welcome, <?= htmlspecialchars($student['name'] ?? 'Student') ?></h2>
    <div class="action-buttons">
        <a href="logout.php" class="action-btn btn-primary" style="text-decoration:none;">🚪 Logout</a>
        <button class="action-btn btn-primary" onclick="window.print()">🖨️ Print</button>
    </div>
</div>



<div class="summary" id="summarySection">
    <p><strong>Total Hours:</strong> <?= $hours ?> hr <?= $minutes ?> min / 200h</p>
    <p><strong>Status:</strong> <span class="status <?= $statusClass ?>"><?= $statusText ?></span></p>
    <p><strong>Today:</strong> <?= $today ?></p>
</div>

<!-- Tab Switcher -->
<div class="tab-switcher">
<button class="tab-button active" onclick="switchTab('attendance', this)">📅 Attendance</button>
<button class="tab-button" onclick="switchTab('projects', this)">📝 Projects</button>
</div>

<!-- Attendance Tab Content -->
<div id="attendance-tab" class="tab-content active">
    <div style="margin-bottom:20px; text-align:left; padding:16px; border:1px solid #e0e0e0; background:#fff; border-radius:10px;">
        <h3 style="margin-top:0;">Daily Task / Activity</h3>

        <form method="POST">
            <textarea
                name="daily_task"
                rows="4"
                style="width:100%; padding:10px; border-radius:8px; border:1px solid #ccc; font-size:14px;"
                placeholder="Write what you did today..."
            ><?= htmlspecialchars($today_row['daily_task'] ?? '') ?></textarea>

            <button type="submit" name="save_task"
                class="action-btn btn-primary"
                style="margin-top:10px;">
                💾 Save Task
            </button>
        </form>

        <p style="color:#777; margin-top:8px; font-size:13px;">
            You can only write/edit your task for <strong><?= $today ?></strong>.
        </p>
    </div>

    <div style="text-align:left; margin-bottom:20px; border-radius:10px; padding:16px; border:1px solid #e0e0e0; background:#f8f9fa;">
    <h3 style="margin-top:0;">Attendance Actions</h3>
    <div class="attendance-actions">
    <?php
    $time_in_done    = !empty($today_row['time_in']);
    $lunch_out_done  = !empty($today_row['lunch_out']);
    $lunch_in_done   = !empty($today_row['lunch_in']);
    $time_out_done   = !empty($today_row['time_out']);
    $actions = [
        'time_in' => '🟢 Time In',
        'lunch_out' => '🍽️ Lunch Out',
        'lunch_in' => '🍽️ Lunch In',
        'time_out' => '🔴 Time Out'
    ];
    foreach($actions as $key=>$label):
        $done = ${$key.'_done'};
        $disabled = '';
        if ($key=='lunch_out' && (!$time_in_done || $done)) $disabled=true;
        if ($key=='lunch_in' && (!$lunch_out_done || $done)) $disabled=true;
        if ($key=='time_out' && (!$time_in_done || $done)) $disabled=true;
    ?>
    <form method="post" style="margin:0;">
        <input type="hidden" name="attendance_action" value="<?= $key ?>">
        <button type="submit" class="action-btn <?= $done||$disabled?'btn-disabled':'btn-primary' ?>" <?= $done||$disabled?'disabled':'' ?>>
            <?= $label ?>
        </button>
    </form>
    <?php endforeach; ?>
    </div>
    <p style="margin-top:10px; color:#666; font-size:14px;">Note: Buttons disable after recording.</p>
    </div>

    <h3>Attendance History</h3>

    <!-- Desktop Table View -->
    <div class="table-section desktop-view">
    <table>
    <thead>
    <tr>
    <th>Date</th>
    <th>Time In</th>
    <th>Lunch Out</th>
    <th>Lunch In</th>
    <th>Time Out</th>
    <th>Status</th>
    <th>Verified</th>
    <th>Hours (Daily)</th>
    <th>Task</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach($attendance as $row): ?>
    <tr>
    <td data-label="Date"><?= htmlspecialchars($row['log_date']) ?></td>
    <td data-label="Time In"><?= (strpos($row['time_in'], '0000') === false && !empty($row['time_in'])) ? date('H:i:s', strtotime($row['time_in'])) : '-' ?></td>
    <td data-label="Lunch Out"><?= (strpos($row['lunch_out'], '0000') === false && !empty($row['lunch_out'])) ? date('H:i:s', strtotime($row['lunch_out'])) : '-' ?></td>
    <td data-label="Lunch In"><?= (strpos($row['lunch_in'], '0000') === false && !empty($row['lunch_in'])) ? date('H:i:s', strtotime($row['lunch_in'])) : '-' ?></td>
    <td data-label="Time Out"><?= (strpos($row['time_out'], '0000') === false && !empty($row['time_out'])) ? date('H:i:s', strtotime($row['time_out'])) : '-' ?></td>
    <td data-label="Status">
        <?php
        $status = $row['status'] ?: '---';
        $status_class = '';
        if (strtolower($status) === 'present') $status_class = "style='color: green; font-weight: bold;'";
        if (strtolower($status) === 'absent')  $status_class = "style='color: red; font-weight: bold;'";
        if (strtolower($status) === 'excused') $status_class = "style='color: orange; font-weight: bold;'";
        ?>
        <span <?= $status_class ?>><?= htmlspecialchars($status) ?></span>
    <td data-label="Verified">
        <?php if ($row['verified'] == 1): ?>
            <span style="color:green; font-weight:bold;">✓ Verified</span>
        <?php else: ?>
            <span style="color:orange; font-weight:bold;">⏳ Pending</span>
        <?php endif; ?>
    </td>
    <td data-label="Hours (Daily)">
    <?php
    $minutesWorked = 0;

    if (!empty($row['time_in']) && !empty($row['time_out']) && strpos($row['time_in'], '0000') === false && strpos($row['time_out'], '0000') === false) {
        $minutesWorked = max(0, (strtotime($row['time_out']) - strtotime($row['time_in'])) / 60);

        if (!empty($row['lunch_in']) && !empty($row['lunch_out']) && strpos($row['lunch_in'], '0000') === false && strpos($row['lunch_out'], '0000') === false) {
            $minutesWorked -= max(0, (strtotime($row['lunch_in']) - strtotime($row['lunch_out'])) / 60);
        }

        echo floor($minutesWorked / 60) . " hr " . ($minutesWorked % 60) . " min";
    } else {
        echo "-";
    }
    ?>
    </td>

    <td data-label="Task">
        <?= !empty($row['daily_task']) ? htmlspecialchars($row['daily_task']) : '-' ?>
    </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>

    <!-- Mobile Card View -->
    <div class="mobile-view">
    <?php foreach($attendance as $row): ?>
    <div class="attendance-card">
        <div class="card-header">
            <strong>Date: <?= htmlspecialchars($row['log_date']) ?></strong>
            <span class="status-badge">
                <?php
                $status = $row['status'] ?: '---';
                $status_class = '';
                if (strtolower($status) === 'present') $status_class = "style='background: #d4edda; color: #155724;'";
                if (strtolower($status) === 'absent')  $status_class = "style='background: #f8d7da; color: #721c24;'";
                if (strtolower($status) === 'excused') $status_class = "style='background: #fff3cd; color: #856404;'";
                ?>
                <span class="status-text" <?= $status_class ?>><?= htmlspecialchars($status) ?></span>
                <?php if ($row['verified'] == 1): ?>
                    <span class="verified-badge">✓ Verified</span>
                <?php else: ?>
                    <span class="unverified-badge">✗ Not Verified</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="card-body">
            <div class="time-info">
                <div class="time-row">
                    <span class="label">Time In:</span>
                    <span class="value"><?= (strpos($row['time_in'], '0000') === false && !empty($row['time_in'])) ? date('H:i:s', strtotime($row['time_in'])) : '-' ?></span>
                </div>
                <div class="time-row">
                    <span class="label">Lunch Out:</span>
                    <span class="value"><?= (strpos($row['lunch_out'], '0000') === false && !empty($row['lunch_out'])) ? date('H:i:s', strtotime($row['lunch_out'])) : '-' ?></span>
                </div>
                <div class="time-row">
                    <span class="label">Lunch In:</span>
                    <span class="value"><?= (strpos($row['lunch_in'], '0000') === false && !empty($row['lunch_in'])) ? date('H:i:s', strtotime($row['lunch_in'])) : '-' ?></span>
                </div>
                <div class="time-row">
                    <span class="label">Time Out:</span>
                    <span class="value"><?= (strpos($row['time_out'], '0000') === false && !empty($row['time_out'])) ? date('H:i:s', strtotime($row['time_out'])) : '-' ?></span>
                </div>
                <div class="time-row">
                    <span class="label">Hours Worked:</span>
                    <span class="value">
                    <?php
                    $minutesWorked = 0;
                    if (!empty($row['time_in']) && !empty($row['time_out']) && strpos($row['time_in'], '0000') === false && strpos($row['time_out'], '0000') === false) {
                        $minutesWorked = max(0, (strtotime($row['time_out']) - strtotime($row['time_in'])) / 60);
                        if (!empty($row['lunch_in']) && !empty($row['lunch_out']) && strpos($row['lunch_in'], '0000') === false && strpos($row['lunch_out'], '0000') === false) {
                            $minutesWorked -= max(0, (strtotime($row['lunch_in']) - strtotime($row['lunch_out'])) / 60);
                        }
                        echo floor($minutesWorked / 60) . " hr " . ($minutesWorked % 60) . " min";
                    } else {
                        echo "-";
                    }
                    ?>
                    </span>
                </div>
            </div>
            <div class="task-info">
                <strong>Task:</strong>
                <p><?= !empty($row['daily_task']) ? htmlspecialchars($row['daily_task']) : 'No task recorded' ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>

<!-- Projects Tab Content -->
<div id="projects-tab" class="tab-content">
    <div class="projects-section" id="projects-section">
    <h3>📝 OJT Projects</h3>
    <?php if (!empty($submitError)): ?>
        <div class="error-msg"><?= htmlspecialchars($submitError) ?></div>
    <?php endif; ?>

    <div id="projectsGrid" style="display:block;">
        <?php if (!empty($projects)): ?>
            <div class="projects-grid">
                <?php foreach ($projects as $project): ?>
                    <div class="project-card" onclick="selectProjectForSubmission(<?= $project['project_id'] ?>, '<?= htmlspecialchars($project['project_name']) ?>')">
                        <h5><?= htmlspecialchars($project['project_name']) ?></h5>
                        <p><?= htmlspecialchars(substr($project['description'], 0, 100)) ?>...</p>
                        <div style="font-size: 12px; color: #999; margin-top: 10px;">
                            <div>📅 Due: <?= date('M d, Y', strtotime($project['due_date'])) ?></div>
                            <div>Status: <span style="color: #28a745; font-weight: bold;"><?= ucfirst($project['status']) ?></span></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: #999;">No projects available yet.</p>
        <?php endif; ?>
    </div>
    </div>

    <!-- File Submission Section -->
    <div class="code-editor-section" id="submissionSection" style="display:none;">
        <h4>📤 Submit for: <span id="selectedProjectName"></span></h4>
        <div style="margin-bottom: 15px;">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cancelSubmission()">← Back to Projects</button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="submissionForm">
            <input type="hidden" name="project_id" id="projectId" value="">
            <input type="hidden" name="submission_type" id="submissionType" value="code">

            <!-- Submission Type Toggle -->
            <div style="margin-bottom: 20px; display: flex; gap: 10px; border-bottom: 2px solid #e0e0e0; padding-bottom: 15px;">
                <button type="button" class="btn btn-sm" id="codeTabBtn" style="border: none; border-bottom: 3px solid #28a745; padding: 8px 15px; background: none; color: #28a745; font-weight: 600;" onclick="switchSubmissionTab('code')">
                    ✏️ Write Code
                </button>
                <button type="button" class="btn btn-sm" id="fileTabBtn" style="border: none; padding: 8px 15px; background: none; color: #999; font-weight: 600;" onclick="switchSubmissionTab('file')">
                    📎 Upload File
                </button>
            </div>

            <!-- Code Editor Tab -->
            <small style="color: #666; display: block; margin-bottom: 10px;">💡 Supports: PHP, HTML, CSS, Java, JavaScript, and more</small>
            <div id="codeTab" style="display: none;">
                <div class="editor-half">
                    <h6>Code Editor:</h6>
                    <div id="codeEditorContainer">
                        <textarea id="codeEditor" name="code_content"><?php echo htmlspecialchars($defaultCode); ?></textarea>
                    </div>
                </div>

                <div class="preview-half">
                    <div class="preview-controls">
                        <h6>Preview Output:</h6>
                        <button type="button" id="runCodeBtn" class="btn btn-primary btn-sm">▶️ Run Code</button>
                    </div>
                    <iframe id="editorPreview" style="height: 100%;"></iframe>
                </div>
            </div>

            <!-- File Upload Tab -->
            <div id="fileTab" style="display: none;">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">📎 Upload File:</label>
                    <input type="file" name="submission_file" id="submissionFile" class="form-control" accept=".pdf,.doc,.docx,.txt,.zip,.rar,.php,.html,.css,.java,.js">
                </div>
            </div>

            <!-- Common Fields -->
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">📝 Remarks (Optional):</label>
                <textarea name="remarks" class="form-control" rows="3" placeholder="Add any notes or comments about your submission..."></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="submit_file" class="btn btn-success">📤 Submit Project</button>
                <button type="button" class="btn btn-secondary" onclick="cancelSubmission()">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Submissions History -->
    <?php if (!empty($submissions)): ?>
        <div class="submissions-list" style="margin-top: 30px;">
            <h5>📋 Your Submissions</h5>
            <?php foreach ($submissions as $sub): ?>
                <div class="submission-card">
                    <h6><?= htmlspecialchars($sub['project_name']) ?></h6>
                    <div class="submission-meta">
                        <div>📅 Submitted: <strong><?= date('M d, Y H:i', strtotime($sub['submission_date'])) ?></strong></div>
                        <div>Status: <strong style="color: <?= $sub['status'] == 'Approved' ? '#28a745' : ($sub['status'] == 'Rejected' ? '#dc3545' : '#ffc107') ?>;"><?= ucfirst($sub['status']) ?></strong></div>
                        <?php if (!empty($sub['remarks'])): ?>
                            <div>📝 Your Notes: <?= htmlspecialchars($sub['remarks']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($sub['graded_at'])): ?>
                            <div>✅ Graded on: <?= date('M d, Y', strtotime($sub['graded_at'])) ?></div>
                        <?php endif; ?>
                        <div style="margin-top: 10px;">
                            <a href="view_output.php?file=<?= urlencode($sub['file_path']) ?>" class="btn btn-sm btn-outline-primary" target="_blank">👁️ View Output</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Verified Attendance Modal -->
<div class="modal fade" id="verifiedModal" tabindex="-1" aria-labelledby="verifiedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verifiedModalLabel">Attendance Verified</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Your attendance was verified today.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

</div>

<!-- Full Screen IDE Modal -->
<div id="fullscreenIDE" class="fullscreen-ide" style="display: none;">
    <div class="ide-header">
        <h4 id="ideProjectName">Project IDE</h4>
        <button onclick="closeFullScreenIDE()" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer;">✕</button>
    </div>
    <div class="ide-content">
        <div class="code-panel">
            <div class="panel-header">Code Editor</div>
            <div class="panel-content">
                <textarea id="fullscreenEditor"></textarea>
            </div>
        </div>
        <div class="output-panel">
            <div class="panel-header">Output Preview</div>
            <div class="panel-content">
                <iframe id="fullscreenPreview" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
        </div>
    </div>
    <div class="ide-controls">
        <button onclick="runCode()" class="btn btn-primary">▶️ Run Code</button>
        <button onclick="generatePDF()" class="btn btn-success">📄 Generate PDF & Submit</button>
        <button onclick="closeFullScreenIDE()" class="btn btn-secondary">❌ Close</button>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ========== GLOBAL FUNCTIONS ==========

// Tab switching function
function switchTab(tabName, button) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => content.classList.remove('active'));

    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(btn => btn.classList.remove('active'));

    // Show selected tab content
    document.getElementById(tabName + '-tab').classList.add('active');

    // Add active class to clicked button
    button.classList.add('active');

    // Hide/show welcome header and summary based on tab
    const welcomeHeader = document.getElementById('welcomeHeader');
    const summarySection = document.getElementById('summarySection');

    if (tabName === 'projects') {
        if (welcomeHeader) welcomeHeader.style.display = 'none';
        if (summarySection) summarySection.style.display = 'none';

        document.getElementById('submissionSection').style.display = 'none';
        document.getElementById('projects-section').style.display = 'block';

        // Initialize CodeMirror if not already done
        if (!window.codeEditor) {
            // Wait a bit for the tab to be visible
            setTimeout(initCodeEditor, 100);
        }
    } else {
        if (welcomeHeader) welcomeHeader.style.display = 'flex';
        if (summarySection) summarySection.style.display = 'block';
    }
}

// Project selection for submission
function selectProjectForSubmission(projectId, projectName) {
    // Show submission section
    document.getElementById('projects-section').style.display = 'none';
    document.getElementById('submissionSection').style.display = 'block';

    // Set project info
    document.getElementById('selectedProjectName').textContent = projectName;
    document.getElementById('projectId').value = projectId;

    // Reset form and preview
    document.getElementById('submissionForm').reset();
    document.getElementById('editorPreview').srcdoc = '';
    document.getElementById('submissionFile').value = '';

    // Switch to code tab by default
    switchSubmissionTab('code');

    // Initialize CodeMirror and attach events after display is set
    setTimeout(() => {
        if (!window.codeEditor) {
            initCodeEditor();
        } else {
            // Reset editor content to default
            window.codeEditor.setValue(<?php echo json_encode($safeDefaultCode); ?>);
        }

        // Attach run button event
        const runBtn = document.getElementById('runCodeBtn');
        if (runBtn) {
            runBtn.addEventListener('click', runCodePreview);
        }
    }, 100);
}

function cancelSubmission() {
    // Hide submission section, show project list
    document.getElementById('submissionSection').style.display = 'none';
    document.getElementById('projects-section').style.display = 'block';
}

function switchSubmissionTab(tabType) {
    const submissionType = document.getElementById('submissionType');

    if (tabType === 'code') {
        submissionType.value = 'code';
        document.getElementById('codeTab').style.display = 'flex';
        document.getElementById('fileTab').style.display = 'none';
        document.getElementById('codeTabBtn').style.borderBottom = '3px solid #28a745';
        document.getElementById('codeTabBtn').style.color = '#28a745';
        document.getElementById('fileTabBtn').style.borderBottom = 'none';
        document.getElementById('fileTabBtn').style.color = '#999';
    } else {
        submissionType.value = 'file';
        document.getElementById('codeTab').style.display = 'none';
        document.getElementById('fileTab').style.display = 'block';
        document.getElementById('codeTabBtn').style.borderBottom = 'none';
        document.getElementById('codeTabBtn').style.color = '#999';
        document.getElementById('fileTabBtn').style.borderBottom = '3px solid #28a745';
        document.getElementById('fileTabBtn').style.color = '#28a745';
    }
}

// Run code for preview
function runCodePreview() {
    if (window.codeEditor) {
        const code = window.codeEditor.getValue();
        const iframe = document.getElementById('editorPreview');
        iframe.srcdoc = code;
    }
}

// Initialize CodeMirror editor
function initCodeEditor() {
    const textarea = document.getElementById('codeEditor');
    if (!textarea) {
        console.error('codeEditor textarea not found!');
        return;
    }
    
    // Destroy existing editor instance if it exists
    if (window.codeEditor) {
        window.codeEditor.toTextArea();
        window.codeEditor = null;
    }
    
    window.codeEditor = CodeMirror.fromTextArea(textarea, {
        lineNumbers: true,
        theme: "monokai",
        tabSize: 4,
        lineWrapping: true,
        matchBrackets: true,
        autoCloseBrackets: true,
        mode: "application/x-httpd-php",
        value: defaultCode || safeDefaultCode || "<?php echo 'Hello PHP!'; ?>\\n<h1>Hello HTML + CSS + JS!</h1>",
        viewportMargin: Infinity
    });
    
    // Refresh the editor to ensure proper rendering
    setTimeout(() => {
        if (window.codeEditor) {
            window.codeEditor.refresh();
            window.codeEditor.focus();
        }
    }, 150);
    
    // Add the run button event listener
    const runBtn = document.getElementById('runCodeBtn');
    if (runBtn) {
        // Remove existing event listeners
        const newRunBtn = runBtn.cloneNode(true);
        runBtn.parentNode.replaceChild(newRunBtn, runBtn);
        document.getElementById('runCodeBtn').addEventListener('click', runCodePreview);
    }
}

// Full Screen IDE Functions (optional)
function openFullScreenIDE(projectId, projectName) {
    currentProjectId = projectId;
    document.getElementById('ideProjectName').textContent = 'Project: ' + projectName;
    document.getElementById('fullscreenIDE').style.display = 'flex';

    if (!window.fullscreenEditor) {
        window.fullscreenEditor = CodeMirror.fromTextArea(document.getElementById('fullscreenEditor'), {
            lineNumbers: true,
            theme: 'default',
            tabSize: 4,
            lineWrapping: true,
            matchBrackets: true,
            autoCloseBrackets: true,
            mode: 'htmlmixed',
            value: <?php echo json_encode($defaultCode); ?>
        });
    }
}

function closeFullScreenIDE() {
    document.getElementById('fullscreenIDE').style.display = 'none';
}

function runCode() {
    if (window.fullscreenEditor) {
        const code = window.fullscreenEditor.getValue();
        const iframe = document.getElementById('fullscreenPreview');
        iframe.srcdoc = code;
    }
}

// ========== DOM CONTENT LOADED ==========
document.addEventListener('DOMContentLoaded', function() {
    // Handle form submission validation
    const form = document.getElementById('submissionForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const submissionType = document.getElementById('submissionType').value;
            
            if (submissionType === 'code') {
                // Save editor content to textarea
                if (window.codeEditor) {
                    window.codeEditor.save();
                }
                
                const codeTextarea = document.getElementById('codeEditor');
                if (!codeTextarea || codeTextarea.value.trim() === '') {
                    e.preventDefault();
                    alert('Please write some code before submitting');
                    return false;
                }
            } else {
                const fileInput = document.getElementById('submissionFile');
                if (!fileInput.files.length) {
                    e.preventDefault();
                    alert('Please select a file to submit');
                    return false;
                }
            }
            return true;
        });
    }

<?php if (!empty($today_row) && $today_row['verified'] == 1): ?>
    // Check if modal has already been shown today using localStorage
    const today = '<?= date('Y-m-d') ?>';
    const modalShownKey = 'attendance_modal_shown_' + today;

    if (!localStorage.getItem(modalShownKey)) {
        var myModal = new bootstrap.Modal(document.getElementById('verifiedModal'), {});
        myModal.show();
        // Mark modal as shown for today
        localStorage.setItem(modalShownKey, 'true');
    }
<?php endif; ?>
});
</script>

</body>
</html>