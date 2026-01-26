<?php
session_start();
if (!isset($_SESSION['employer_id'])) {
    header("Location: employer_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';

$project_id = $_GET['project_id'] ?? 0;



$stmt = $pdo->prepare("
    SELECT ps.*, s.username
    FROM project_submissions ps
    JOIN students s ON ps.student_id = s.student_id
    WHERE ps.project_id = ?
    ORDER BY ps.submission_date DESC
");
$stmt->execute([$project_id]);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Project Submissions</title>
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
<h2>Project Submissions</h2>
<a href="employer_dashboard.php" class="btn btn-outline-secondary mb-3">⬅ Back to Dashboard</a>

<div class="table-section">
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Student</th>
            <th>File</th>
            <th>Submission Date</th>
            <th>On Time / Late</th>
            <th>Status</th>
            <th>Remarks</th>
            <th>Graded At</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($submissions as $s): ?>
        <tr>
            <td><?= htmlspecialchars($s['username']) ?></td>
            <td>
                <?php if (strpos($s['file_path'], 'storage/uploads/') === 0): ?>
                    <a href="view_submission.php?submission_id=<?= $s['submission_id'] ?>" target="_blank">View Submission</a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($s['file_path']) ?>" target="_blank">View File</a>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($s['submission_date']) ?></td>
            <td>
                <span class="badge bg-<?= ($s['submission_status'] ?? 'Unknown') === 'On Time' ? 'success' : 'danger' ?>">
                    <?= htmlspecialchars($s['submission_status'] ?? 'Unknown') ?>
                </span>
            </td>
            <td>
                <span class="badge bg-<?= $s['status'] === 'Approved' ? 'success' : ($s['status'] === 'Rejected' ? 'danger' : 'warning') ?>">
                    <?= htmlspecialchars($s['status']) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($s['remarks'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($s['graded_at'] ?? 'N/A') ?></td>
            <td>
                <?php if ($s['graded_at']): ?>
                    <span class="text-muted">Graded</span>
                <?php else: ?>
                    <form method="POST" action="grade_submission.php">
                        <input type="hidden" name="submission_id" value="<?= $s['submission_id'] ?>">
                        <input type="text" name="remarks" placeholder="Enter remarks" value="<?= htmlspecialchars($s['remarks'] ?? '') ?>" class="form-control mb-2">
                        <select name="status" class="form-select mb-2">
                            <option value="Pending" <?= ($s['status']=='Pending')?'selected':'' ?>>Pending</option>
                            <option value="Approved" <?= ($s['status']=='Approved')?'selected':'' ?>>Approved</option>
                            <option value="Rejected" <?= ($s['status']=='Rejected')?'selected':'' ?>>Rejected</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-success">Submit Grade</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>
</body>
</html>
