<?php
$editorPreview = '';
if (isset($_POST['code_editor'])) {
    $code = $_POST['code_editor'];
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
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<style>
    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--amber:#d97706;--amber-lt:#fef3c7;--radius:14px;--shadow-sm:0 1px 2px rgba(0,0,0,.05);--shadow:0 1px 4px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.06);--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:28px 20px 60px;}
    .dashboard-container{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);width:100%;max-width:1440px;margin:0 auto;overflow:hidden;}
    .topbar{display:flex;align-items:center;justify-content:space-between;padding:20px 32px;border-bottom:1px solid var(--border);gap:16px;flex-wrap:wrap;}
    .topbar-left h2{font-size:20px;font-weight:700;color:var(--text);margin:0;letter-spacing:-.3px;}
    .topbar-left p{font-size:13px;color:var(--text-muted);margin:2px 0 0;}
    .topbar-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
    .badge-role{background:var(--accent-lt);color:var(--accent);font-size:12px;font-weight:600;padding:4px 12px;border-radius:20px;}
    .btn-logout{display:inline-flex;align-items:center;background:var(--red);color:#fff!important;padding:8px 16px;border-radius:9px;text-decoration:none!important;font-weight:600;font-size:13px;transition:background .2s,transform .15s;border:none;cursor:pointer;}
    .btn-logout:hover{background:#b91c1c;transform:translateY(-1px);}
    .dashboard-inner{padding:24px 32px 36px;}
    .success-msg{background:var(--green-lt);color:#15803d;padding:12px 16px;border-radius:10px;border:1px solid #bbf7d0;font-size:14px;font-weight:500;margin-bottom:16px;}
    .error-msg{background:var(--red-lt);color:#b91c1c;padding:12px 16px;border-radius:10px;border:1px solid #fecaca;font-size:14px;font-weight:500;margin-bottom:16px;}
    .summary{display:flex;gap:24px;flex-wrap:wrap;align-items:center;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:14px 20px;margin-bottom:20px;font-size:14px;}
    .summary p{margin:0;}
    .status.completed{color:var(--green);font-weight:700;}
    .status.in-progress{color:var(--amber);font-weight:700;}
    .tab-switcher{display:flex;gap:4px;margin-bottom:20px;background:var(--surface2);border:1px solid var(--border);border-radius:11px;padding:4px;width:fit-content;}
    .tab-button{padding:9px 20px;border:none;background:transparent;font-size:13px;font-weight:600;cursor:pointer;border-radius:8px;color:var(--text-muted);transition:all .18s;font-family:inherit;}
    .tab-button.active{background:var(--surface);color:var(--accent);box-shadow:var(--shadow-sm);border:1px solid var(--border);}
    .tab-button:hover:not(.active){color:var(--text);background:var(--surface);}
    .tab-content{display:none;}
    .tab-content.active{display:block;}
    .action-btn,.btn-primary,.btn-export{padding:9px 16px;border-radius:9px;border:none;font-weight:600;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .18s;font-family:inherit;text-decoration:none;}
    .btn-primary,.action-btn.btn-primary{background:var(--accent);color:#fff!important;}
    .btn-primary:hover,.action-btn.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);}
    .btn-success{background:var(--green);color:#fff;}
    .btn-success:hover{background:#15803d;transform:translateY(-1px);}
    .btn-secondary{background:var(--surface2);color:var(--text);border:1.5px solid var(--border);padding:9px 16px;border-radius:9px;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .18s;text-decoration:none;}
    .btn-secondary:hover{background:var(--border);}
    .btn-outline-secondary{background:transparent;color:var(--text-muted);border:1.5px solid var(--border);border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-family:inherit;transition:all .18s;cursor:pointer;}
    .btn-outline-secondary:hover{background:var(--surface2);color:var(--text);}
    .btn-outline-primary{background:transparent;color:var(--accent);border:1.5px solid var(--accent);border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-family:inherit;transition:all .18s;}
    .btn-outline-primary:hover{background:var(--accent-lt);}
    .btn-sm{padding:6px 12px!important;font-size:12px!important;}
    .btn-disabled{background:#e5e7eb;color:#9ca3af;cursor:not-allowed;padding:9px 16px;border-radius:9px;border:none;font-weight:600;font-size:13px;font-family:inherit;}
    .attendance-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-start;margin-bottom:16px;}
    .section-card{margin-bottom:16px;padding:18px 20px;border-radius:var(--radius);border:1px solid var(--border);background:var(--surface2);}
    .section-card h3{margin:0 0 12px;font-size:15px;font-weight:700;color:var(--text);}
    .export-panel{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:18px 20px;margin:0 0 16px;}
    .export-panel h5{font-size:15px;font-weight:700;color:var(--text);margin:0 0 14px;}
    .export-form{display:flex;flex-direction:column;align-items:flex-start;gap:12px;}
    .export-controls{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;}
    .date-input-group{display:flex;flex-direction:column;gap:4px;}
    .date-input-group label{font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;}
    .export-buttons{display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;}
    .btn-export-excel{background:var(--green);color:#fff;}
    .btn-export-excel:hover{background:#15803d;transform:translateY(-1px);}
    .btn-export-all{background:var(--accent);color:#fff;}
    .btn-export-all:hover{background:var(--accent-dk);transform:translateY(-1px);}
    .form-control,textarea{border-radius:9px;border:1px solid var(--border);padding:9px 12px;font-size:14px;font-family:inherit;color:var(--text);background:var(--surface);transition:border-color .2s,box-shadow .2s;width:100%;}
    .form-control:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.12);outline:none;}
    .table-section{overflow-x:auto;border-radius:var(--radius);border:1px solid var(--border);margin-top:16px;}
    .desktop-view table{width:100%;border-collapse:collapse;background:var(--surface);}
    th{background:var(--surface2);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);padding:11px 14px;border-bottom:1px solid var(--border);white-space:nowrap;text-align:center;}
    td{padding:11px 14px;border-bottom:1px solid var(--border);font-size:13px;color:var(--text);vertical-align:middle;text-align:center;}
    tr:last-child td{border-bottom:none;}
    tr:hover td{background:var(--accent-lt);transition:background .15s;}
    .mobile-view{display:none;}
    .attendance-card{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:12px;overflow:hidden;}
    .card-header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:var(--surface);border-bottom:1px solid var(--border);font-size:14px;font-weight:600;flex-wrap:wrap;gap:8px;}
    .status-badge{display:flex;gap:6px;align-items:center;flex-wrap:wrap;}
    .status-text{padding:3px 8px;border-radius:20px;font-size:12px;font-weight:600;}
    .verified-badge{background:var(--green-lt);color:var(--green);font-size:12px;font-weight:600;padding:3px 8px;border-radius:20px;}
    .unverified-badge{background:var(--red-lt);color:var(--red);font-size:12px;font-weight:600;padding:3px 8px;border-radius:20px;}
    .card-body{padding:14px 16px;}
    .time-info{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:10px;}
    .time-row{display:flex;flex-direction:column;gap:1px;}
    .time-row .label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);}
    .time-row .value{font-size:14px;font-weight:500;color:var(--text);}
    .task-info{font-size:13px;color:var(--text-muted);border-top:1px solid var(--border);padding-top:10px;margin-top:8px;}
    .task-info p{margin:4px 0 0;color:var(--text);}
    .projects-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-top:16px;}
    .project-card{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:18px;transition:transform .2s,box-shadow .2s;cursor:pointer;}
    .project-card:hover{transform:translateY(-2px);box-shadow:var(--shadow);}
    .project-card.disabled{opacity:.6;cursor:default;}
    .project-card h5{font-size:14px;font-weight:700;margin:0 0 6px;color:var(--text);}
    .project-card p{font-size:13px;color:var(--text-muted);margin:0 0 10px;}
    .submission-card{background:var(--surface2);border:1px solid var(--border);border-left:4px solid var(--green);border-radius:var(--radius);padding:16px;margin-bottom:12px;}
    .submission-card h6{margin:0 0 8px;font-size:14px;font-weight:700;color:var(--text);}
    .submission-meta{font-size:13px;color:var(--text-muted);}
    #codeTab{display:flex;gap:15px;margin-bottom:15px;height:calc(100vh - 320px);min-height:400px;}
    .editor-half,.preview-half{flex:1;display:flex;flex-direction:column;min-height:0;}
    .editor-half h6,.preview-half h6{margin:0 0 10px;font-size:14px;color:var(--text);font-weight:700;}
    #codeEditorContainer{flex:1;border:1px solid var(--border);border-radius:9px;overflow:hidden;background:#fff;}
    .CodeMirror{height:100%!important;font-size:14px!important;}
    .CodeMirror-scroll{height:100%;}
    .preview-controls{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
    .fullscreen-ide{position:fixed;top:0;left:0;width:100vw;height:100vh;background:white;z-index:9999;display:flex;flex-direction:column;}
    .ide-header{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;background:var(--surface2);border-bottom:1px solid var(--border);flex-shrink:0;}
    .ide-header h4{margin:0;font-size:16px;font-weight:700;color:var(--text);}
    .ide-content{flex:1;display:flex;overflow:hidden;min-height:0;}
    .code-panel{flex:1;display:flex;flex-direction:column;border-right:1px solid var(--border);overflow:hidden;}
    .output-panel{flex:1;display:flex;flex-direction:column;overflow:hidden;}
    .panel-header{padding:10px 16px;background:var(--surface2);border-bottom:1px solid var(--border);font-weight:600;font-size:13px;color:var(--text);flex-shrink:0;}
    .panel-content{flex:1;padding:10px;overflow:hidden;position:relative;}
    .ide-controls{display:flex;gap:10px;padding:14px 20px;background:var(--surface2);border-top:1px solid var(--border);flex-shrink:0;}
    .welcome-header{display:none;}
    @media(max-width:768px){
        body{padding:10px 10px 40px;}
        .dashboard-container{border-radius:14px;}
        .topbar,.dashboard-inner{padding:14px 16px;}
        .tab-switcher{width:100%;}
        .tab-button{flex:1;text-align:center;font-size:12px;padding:8px 10px;}
        .summary{gap:12px;font-size:13px;}
        .desktop-view{display:none;}
        .mobile-view{display:block;}
        .attendance-actions{gap:8px;}
        .action-btn,.btn-primary{width:100%;justify-content:center;}
        .export-controls{flex-direction:column;}
        .time-info{grid-template-columns:1fr;}
        .projects-grid{grid-template-columns:1fr;}
    }
</style>
</head>
<body>
<div class="dashboard-container">

    <div class="topbar">
        <div class="topbar-left">
            <h2>Welcome, <?= htmlspecialchars(isset($student['first_name']) ? trim(explode(' ', $student['first_name'])[0]) : 'Student') ?>!</h2>
            <p>OJT Student Portal</p>
        </div>
        <div class="topbar-right">
            <span class="badge-role">Student</span>
            <?php if (!empty($has_generated_certificate)): ?>
                <a href="download_certificate.php" class="action-btn btn-primary" style="text-decoration:none;">📄 Certificate</a>
            <?php endif; ?>
            <a href="logout.php" class="btn-logout">⎋ Logout</a>
        </div>
    </div>

    <div class="dashboard-inner">

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

<div class="summary" id="summarySection">
    <p><strong>Total Hours:</strong> <?= $hours ?> hr <?= $minutes ?> min / 200h</p>
    <p><strong>Status:</strong> <span class="status <?= $statusClass ?>"><?= $statusText ?></span></p>
    <p><strong>Today:</strong> <?= $today ?></p>
</div>

<div class="tab-switcher">
    <button class="tab-button active" onclick="switchTab('attendance', this)">📅 Attendance</button>
    <button class="tab-button" onclick="switchTab('export', this)">📊 Export</button>
    <button class="tab-button" onclick="switchTab('projects', this)">📁 Projects</button>
</div>