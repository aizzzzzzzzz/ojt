<?php
// Safe code preview - store code for iframe display instead of executing
$editorPreview = '';
if (isset($_POST['code_editor'])) {
    $code = $_POST['code_editor'];
    // Sanitize and store for safe iframe preview
    $editorPreview = htmlspecialchars($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
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

    /* Export Panel Styles */
    .export-panel {
        background: #f8fff8;
        border: 1px solid #c3e6cb;
        border-radius: 10px;
        padding: 20px;
        margin: 20px auto;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        max-width: 800px;
        width: 100%;
        text-align: center;
    }

    .export-panel h5 {
        margin-top: 0;
        color: #2c3e50;
        margin-bottom: 20px;
        font-size: 1.25rem;
    }

    .export-form {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
        width: 100%;
    }

    .export-form label {
        font-weight: 600;
        color: #2c3e50;
        text-align: left;
        width: 100%;
        max-width: 600px;
    }

    .export-form .form-control {
        border-radius: 6px;
        border: 1px solid #ddd;
        padding: 8px 12px;
        font-size: 14px;
        transition: border-color 0.3s ease;
        height: 38px;
    }

    .export-form .form-control:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }

    .export-controls {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: flex-end;
        gap: 15px;
        width: 100%;
        max-width: 600px;
    }

    .date-input-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
        flex: 1;
        min-width: 150px;
    }

    .date-input-group label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 14px;
        text-align: left;
    }

    .date-input-group .form-control {
        width: 100%;
        padding: 6px 8px;
        font-size: 14px;
        height: 38px;
    }

    .export-buttons {
        display: flex;
        gap: 10px;
        justify-content: center;
        width: 100%;
        margin-top: 10px;
    }

    .export-buttons .btn-export {
        height: 38px;
        padding: 8px 15px;
        font-size: 14px;
        min-width: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-export {
        padding: 8px 15px;
        border-radius: 6px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        height: 38px;
    }

    .btn-export-excel {
        background: linear-gradient(90deg, #2E7D32, #4CAF50);
        color: white;
    }

    .btn-export-excel:hover {
        background: linear-gradient(90deg, #1B5E20, #388E3C);
        transform: translateY(-2px);
    }

    .btn-export-all {
        background: linear-gradient(90deg, #1565C0, #2196F3);
        color: white;
    }

    .btn-export-all:hover {
        background: linear-gradient(90deg, #0D47A1, #1976D2);
        transform: translateY(-2px);
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

    /* Code Editor Split Screen Styles */
    #codeTab {
        display: flex;
        gap: 15px;
        margin-bottom: 15px;
        height: calc(100vh - 320px);
        min-height: 400px;
    }

    .editor-half,
    .preview-half {
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
        overflow: hidden;
        min-height: 0;
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
        flex-shrink: 0;
    }

    .panel-content {
        flex: 1;
        padding: 10px;
        overflow: hidden;
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

        .export-panel {
            margin: 20px 0;
            padding: 15px;
        }

        .export-form {
            align-items: stretch;
        }

        .export-controls {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }

        .date-input-group {
            width: 100%;
        }

        .date-input-group .form-control {
            width: 100%;
        }

        .export-buttons {
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
        }

        .export-buttons .btn-export {
            width: 100%;
            min-width: unset;
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
<?php if (!empty($_SESSION['error'])): ?>
    <div class="error-msg"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
<?php if (!empty($messages)) foreach ($messages as $m): ?>
    <div class="error-msg"><?= htmlspecialchars($m) ?></div>
<?php endforeach; ?>

<div class="welcome-header" id="welcomeHeader">
    <h2>Welcome, <?= 
        htmlspecialchars(
            isset($student['first_name']) 
                ? trim(explode(' ', $student['first_name'])[0]) 
                : 'Student'
        ) 
    ?></h2>
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
    <button class="tab-button active" onclick="switchTab('attendance', this)">Attendance</button>
    <button class="tab-button" onclick="switchTab('export', this)">Export History</button>
    <button class="tab-button" onclick="switchTab('projects', this)">Projects</button>
</div>
