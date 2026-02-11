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
<style>
body { font-family:"Segoe UI", sans-serif; background:#e3f2fd; padding:20px; }
.dashboard-container { background: rgba(255,255,255,0.95); padding:30px; border-radius:15px; max-width:1000px; margin:auto; }
.table-section { overflow-x:auto; margin-top:20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.1);}
th, td { text-align:center; padding:12px; }
th { background:#f8f9fa; font-weight:600; }
tr:nth-child(even) { background:#f8f9fa; }
tr:hover { background:#e3f2fd; }
</style>
</head>
<body>
<div class="dashboard-container">
<h2>Manage Projects</h2>
<a href="supervisor_dashboard.php
" class="btn btn-outline-secondary mb-3">⬅ Back</a>

<div class="table-section">
<table class="table table-bordered">
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
            <td><?= htmlspecialchars($p['project_name']) ?></td>
            <td><?= htmlspecialchars($p['description']) ?></td>
            <td><?= htmlspecialchars($p['start_date']) ?></td>
            <td><?= htmlspecialchars($p['due_date']) ?></td>
            <td><?= htmlspecialchars($p['status']) ?></td>
            <td>
                <a href="delete_project.php?id=<?= $p['project_id'] ?>" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Delete this project?')">🗑 Delete</a>
                <a href="project_submissions.php?project_id=<?= $p['project_id'] ?>" class="btn btn-success btn-sm">📂 Submissions</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>
</body>
</html>
