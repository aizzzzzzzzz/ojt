<?php
session_start();
if (!isset($_SESSION['employer_id'])) {
    header("Location: employer_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';

$stmt = $pdo->query("SELECT * FROM projects ORDER BY due_date DESC");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Projects</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<style>
    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--amber:#d97706;--radius:14px;--shadow-sm:0 1px 2px rgba(0,0,0,.05);--shadow:0 1px 4px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.06);--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:28px 20px 60px;}
    .page-card{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);width:100%;margin:0 auto;overflow:hidden;}
    .page-topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;}
    .page-topbar h2{font-size:18px;font-weight:700;margin:0;}
    .page-topbar p{font-size:13px;color:var(--text-muted);margin:2px 0 0;}
    .page-inner{padding:24px 28px 32px;}
    .page-inner h3{font-size:15px;font-weight:700;margin:24px 0 12px;padding-bottom:9px;border-bottom:1px solid var(--border);}
    .page-inner h3:first-child{margin-top:0;}
    .success-msg{background:var(--green-lt);color:#15803d;padding:12px 16px;border-radius:10px;border:1px solid #bbf7d0;font-size:14px;font-weight:500;margin-bottom:16px;}
    .error-msg{background:var(--red-lt);color:#b91c1c;padding:12px 16px;border-radius:10px;border:1px solid #fecaca;font-size:14px;font-weight:500;margin-bottom:16px;}
    .form-label{font-size:13px;font-weight:600;color:var(--text);margin-bottom:5px;display:block;}
    .form-control,.form-select,input[type=text],input[type=password],input[type=date],input[type=file],textarea,select{border-radius:9px;border:1px solid var(--border);padding:9px 12px;font-size:14px;font-family:inherit;color:var(--text);background:var(--surface);transition:border-color .2s,box-shadow .2s;width:100%;}
    input:focus,textarea:focus,select:focus,.form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.12);outline:none;}
    .form-text{font-size:12px;color:var(--text-muted);margin-top:4px;}
    .mb-3{margin-bottom:16px;}.mb-4{margin-bottom:24px;}
    .btn{font-family:inherit;font-size:13px;font-weight:600;border-radius:9px;padding:9px 18px;transition:all .18s;cursor:pointer;display:inline-flex;align-items:center;gap:6px;border:none;text-decoration:none;}
    .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);color:#fff;}
    .btn-success{background:var(--green);color:#fff;}.btn-success:hover{background:#15803d;transform:translateY(-1px);color:#fff;}
    .btn-danger{background:var(--red);color:#fff;}.btn-danger:hover{background:#b91c1c;transform:translateY(-1px);color:#fff;}
    .btn-secondary{background:var(--surface2);color:var(--text);border:1.5px solid var(--border);}.btn-secondary:hover{background:var(--border);}
    .btn-outline-secondary{background:transparent;color:var(--text-muted);border:1.5px solid var(--border);border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-family:inherit;transition:all .18s;}
    .btn-outline-secondary:hover{background:var(--surface2);color:var(--text);}
    .btn-outline-success{background:transparent;color:var(--green);border:1.5px solid var(--green);border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-family:inherit;transition:all .18s;}
    .btn-outline-success:hover{background:var(--green-lt);}
    .btn-sm{padding:6px 12px!important;font-size:12px!important;}
    .d-flex{display:flex;}.gap-2{gap:8px;}.align-items-center{align-items:center;}.flex-wrap{flex-wrap:wrap;}
    .table{width:100%;border-collapse:collapse;font-size:14px;}
    .table thead th{background:var(--surface2);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);border-bottom:1px solid var(--border);padding:11px 14px;white-space:nowrap;text-align:center;}
    .table tbody td{padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--text);text-align:center;}
    .table tbody tr:last-child td{border-bottom:none;}
    .table tbody tr:hover td{background:var(--accent-lt);}
    .table-section{overflow-x:auto;border-radius:var(--radius);border:1px solid var(--border);margin-top:16px;}
    @media(max-width:768px){body{padding:10px 10px 40px;}.page-card{border-radius:14px;}.page-topbar,.page-inner{padding:14px 16px;}}

    .page-card { max-width: 1100px; }
    .status-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
    .status-pending  { background:var(--amber-lt); color:var(--amber); }
    .status-ongoing  { background:var(--accent-lt); color:var(--accent); }
    .status-completed{ background:var(--green-lt);  color:var(--green);  }
</style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2>Manage Projects</h2>
            <p>View and manage all OJT projects</p>
        </div>
        <div class="d-flex gap-2">
            <a href="supervisor_dashboard.php" class="btn-outline-secondary">⬅ Back</a>
            <a href="create_project.php" class="btn btn-primary">➕ Create Project</a>
        </div>
    </div>
    <div class="page-inner">
        <?php if (empty($projects)): ?>
            <p style="color:var(--text-muted);text-align:center;padding:32px 0;">No projects yet. Create one to get started.</p>
        <?php else: ?>
        <div class="table-section">
        <table class="table">
            <thead>
                <tr>
                    <th>Project Name</th>
                    <th>Description</th>
                    <th>Start Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($projects as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['project_name']) ?></strong></td>
                    <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($p['description']) ?></td>
                    <td><?= htmlspecialchars($p['start_date']) ?></td>
                    <td><?= htmlspecialchars($p['due_date']) ?></td>
                    <td>
                        <?php
                            $s = strtolower($p['status']);
                            $cls = $s === 'ongoing' ? 'status-ongoing' : ($s === 'completed' ? 'status-completed' : 'status-pending');
                        ?>
                        <span class="status-badge <?= $cls ?>"><?= htmlspecialchars($p['status']) ?></span>
                    </td>
                    <td>
                        <div class="d-flex gap-2" style="justify-content:center;">
                            <a href="project_submissions.php?project_id=<?= $p['project_id'] ?>" class="btn btn-success btn-sm">📂 Submissions</a>
                            <a href="delete_project.php?id=<?= $p['project_id'] ?>" class="btn btn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #fecaca;" onclick="return confirm('Delete this project?')">🗑 Delete</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>