<?php
session_start();
if (!isset($_SESSION['employer_id'])) {
    header("Location: supervisor_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';

$project_id = $_GET['project_id'] ?? 0;
// Update the SQL query to properly calculate submission_status
// Change ROW_NUMBER to count from oldest to newest:
$stmt = $pdo->prepare("
    SELECT
        ps.*,
        s.username,
        p.due_date,
        p.project_name,
        ROW_NUMBER() OVER (PARTITION BY ps.student_id ORDER BY ps.submission_date ASC) as attempt_number,
        CASE
            WHEN ps.submission_date <= DATE_ADD(p.due_date, INTERVAL 1 DAY) THEN 'On Time'
            ELSE 'Late'
        END as calculated_status
    FROM project_submissions ps
    JOIN students s ON ps.student_id = s.student_id
    JOIN projects p ON ps.project_id = p.project_id
    WHERE ps.project_id = ?
    ORDER BY ps.student_id, ps.submission_date DESC
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
<a href="manage_projects.php" class="btn btn-outline-secondary mb-3">⬅ Back</a>

<?php if (!empty($submissions)): ?>
<h3 class="mb-3"> <?= htmlspecialchars($submissions[0]['project_name']) ?></h3>
<?php endif; ?>
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
            <td>
                <?= htmlspecialchars($s['username']) ?>
                <?php if($s['attempt_number'] > 1): ?>
                    <br>
                    <small class="text-muted">
                        <span class="badge bg-info">Attempt #<?= $s['attempt_number'] ?></span>
                    </small>
                <?php endif; ?>
            </td>
            <td>
                <?php if (strpos($s['file_path'], 'storage/uploads/') === 0): ?>
                    <a href="view_submission.php?submission_id=<?= $s['submission_id'] ?>" target="_blank">View Submission</a>
                <?php else: ?>
                    <a href="view_pdf.php?file=<?= urlencode($s['file_path']) ?>" target="_blank">
                        View Submission
                    </a>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($s['submission_date']) ?></td>
            <td>
                <?php 
                // Use calculated_status instead of submission_status
                $statusClass = ($s['calculated_status'] === 'On Time') ? 'success' : 'danger';
                $statusText = $s['calculated_status'];
                ?>
                <span class="badge bg-<?= $statusClass ?>">
                    <?= htmlspecialchars($statusText) ?>
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
                <?php if ($s['status'] === 'Rejected'): ?>
                    <span class="text-danger">Rejected</span>
                <?php elseif ($s['graded_at']): ?>
                    <span class="text-muted">Graded</span>
                <?php else: ?>
                    <div id="actions-<?= $s['submission_id'] ?>">
                        <button type="button" class="btn btn-sm btn-success" onclick="showGradeForm(<?= $s['submission_id'] ?>)">Approve</button>
                        <form method="POST" action="grade_submission.php" style="display: inline;">
                            <input type="hidden" name="submission_id" value="<?= $s['submission_id'] ?>">
                            <input type="hidden" name="status" value="Rejected">
                            <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                        </form>
                    </div>
                    <div id="grade-form-<?= $s['submission_id'] ?>" style="display: none;">
                        <form method="POST" action="grade_submission.php">
                            <input type="hidden" name="submission_id" value="<?= $s['submission_id'] ?>">
                            <input type="hidden" name="status" value="Approved">
                            <input type="text" name="remarks" placeholder="Enter grade" class="form-control mb-2" required>
                            <button type="submit" class="btn btn-sm btn-success">Submit Grade</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="hideGradeForm(<?= $s['submission_id'] ?>)">Cancel</button>
                        </form>
                    </div>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>
<script>
function showGradeForm(submissionId) {
    document.getElementById('actions-' + submissionId).style.display = 'none';
    document.getElementById('grade-form-' + submissionId).style.display = 'block';
}

function hideGradeForm(submissionId) {
    document.getElementById('actions-' + submissionId).style.display = 'block';
    document.getElementById('grade-form-' + submissionId).style.display = 'none';
}
</script>
</body>
</html>
