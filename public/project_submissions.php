<?php
session_start();
if (!isset($_SESSION['employer_id'])) {
    header("Location: supervisor_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';

$project_id = $_GET['project_id'] ?? 0;
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
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<style>
    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--amber:#d97706;--amber-lt:#fef3c7;--radius:14px;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:28px 20px 60px;}
    .page-card{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);width:100%;max-width:1200px;margin:0 auto;overflow:hidden;}
    .page-topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;}
    .page-topbar h2{font-size:18px;font-weight:700;margin:0;}
    .page-topbar p{font-size:13px;color:var(--text-muted);margin:2px 0 0;}
    .page-inner{padding:24px 28px 32px;}
    .success-msg{background:var(--green-lt);color:#15803d;padding:12px 16px;border-radius:10px;border:1px solid #bbf7d0;font-size:14px;font-weight:500;margin-bottom:16px;}
    .error-msg{background:var(--red-lt);color:#b91c1c;padding:12px 16px;border-radius:10px;border:1px solid #fecaca;font-size:14px;font-weight:500;margin-bottom:16px;}
    .btn{font-family:inherit;font-size:13px;font-weight:600;border-radius:9px;padding:9px 18px;transition:all .18s;cursor:pointer;display:inline-flex;align-items:center;gap:6px;border:none;text-decoration:none;}
    .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);color:#fff;}
    .btn-success{background:var(--green);color:#fff;}.btn-success:hover{background:#15803d;transform:translateY(-1px);color:#fff;}
    .btn-danger{background:var(--red);color:#fff;}.btn-danger:hover{background:#b91c1c;transform:translateY(-1px);color:#fff;}
    .btn-secondary{background:var(--surface2);color:var(--text);border:1.5px solid var(--border);}.btn-secondary:hover{background:var(--border);}
    .btn-outline-secondary{background:transparent;color:var(--text-muted);border:1.5px solid var(--border);border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-family:inherit;transition:all .18s;}
    .btn-outline-secondary:hover{background:var(--surface2);color:var(--text);}
    .btn-sm{padding:6px 12px!important;font-size:12px!important;}
    .form-control{border-radius:9px;border:1px solid var(--border);padding:9px 12px;font-size:14px;font-family:inherit;color:var(--text);background:var(--surface);width:100%;}
    .form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.12);outline:none;}
    .table-section{overflow-x:auto;border-radius:var(--radius);border:1px solid var(--border);margin-top:0;}
    .table{width:100%;border-collapse:collapse;font-size:14px;}
    .table thead th{background:var(--surface2);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);border-bottom:1px solid var(--border);padding:11px 14px;white-space:nowrap;text-align:center;}
    .table tbody td{padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--text);text-align:center;}
    .table tbody tr:last-child td{border-bottom:none;}
    .table tbody tr:hover td{background:var(--accent-lt);}
    .badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;}
    .bg-success{background:var(--green-lt)!important;color:var(--green)!important;}
    .bg-danger{background:var(--red-lt)!important;color:var(--red)!important;}
    .bg-warning{background:var(--amber-lt)!important;color:var(--amber)!important;}
    .bg-info{background:var(--accent-lt)!important;color:var(--accent)!important;}
    .grade-form{display:flex;flex-direction:column;gap:8px;margin-top:6px;min-width:160px;}
    .grade-form input{padding:7px 10px;font-size:13px;}
    .grade-form .btn-row{display:flex;gap:6px;}
    @media(max-width:768px){body{padding:10px 10px 40px;}.page-card{border-radius:14px;}.page-topbar,.page-inner{padding:14px 16px;}}
</style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2>Project Submissions</h2>
            <?php if (!empty($submissions)): ?>
            <p><?= htmlspecialchars($submissions[0]['project_name']) ?></p>
            <?php endif; ?>
        </div>
        <a href="manage_projects.php" class="btn-outline-secondary">⬅ Back</a>
    </div>
    <div class="page-inner">
        <?php if (!empty($_SESSION['success_message'])): ?>
            <div class="success-msg"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="error-msg"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (empty($submissions)): ?>
            <p style="text-align:center;color:var(--text-muted);padding:32px 0;">No submissions yet for this project.</p>
        <?php else: ?>
        <div class="table-section">
        <table class="table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>File</th>
                    <th>Submitted</th>
                    <th>Timing</th>
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
                        <strong><?= htmlspecialchars($s['username']) ?></strong>
                        <?php if ($s['attempt_number'] > 1): ?>
                            <br><span class="badge bg-info" style="margin-top:4px;">Attempt #<?= $s['attempt_number'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (strpos($s['file_path'], 'storage/uploads/') === 0): ?>
                            <a href="view_submission.php?submission_id=<?= $s['submission_id'] ?>" target="_blank" class="btn btn-primary btn-sm">👁 View</a>
                        <?php else: ?>
                            <a href="view_pdf.php?file=<?= urlencode($s['file_path']) ?>" target="_blank" class="btn btn-primary btn-sm">👁 View</a>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;"><?= htmlspecialchars($s['submission_date']) ?></td>
                    <td>
                        <span class="badge <?= $s['calculated_status'] === 'On Time' ? 'bg-success' : 'bg-danger' ?>">
                            <?= htmlspecialchars($s['calculated_status']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= $s['status'] === 'Approved' ? 'bg-success' : ($s['status'] === 'Rejected' ? 'bg-danger' : 'bg-warning') ?>">
                            <?= htmlspecialchars($s['status']) ?>
                        </span>
                    </td>
                    <td style="font-size:13px;"><?= htmlspecialchars($s['remarks'] ?? '—') ?></td>
                    <td style="font-size:13px;"><?= htmlspecialchars($s['graded_at'] ?? '—') ?></td>
                    <td>
                        <?php if ($s['status'] === 'Rejected'): ?>
                            <span style="color:var(--red);font-size:13px;font-weight:600;">Rejected</span>
                        <?php elseif ($s['graded_at']): ?>
                            <span style="color:var(--text-muted);font-size:13px;">Graded</span>
                        <?php else: ?>
                            <div id="actions-<?= $s['submission_id'] ?>">
                                <div style="display:flex;gap:6px;justify-content:center;">
                                    <button type="button" class="btn btn-success btn-sm" onclick="showGradeForm(<?= $s['submission_id'] ?>)">✓ Approve</button>
                                    <form method="POST" action="grade_submission.php" style="display:inline;">
                                        <input type="hidden" name="submission_id" value="<?= $s['submission_id'] ?>">
                                        <input type="hidden" name="status" value="Rejected">
                                        <button type="submit" class="btn btn-danger btn-sm">✕ Reject</button>
                                    </form>
                                </div>
                            </div>
                            <div id="grade-form-<?= $s['submission_id'] ?>" style="display:none;">
                                <form method="POST" action="grade_submission.php" class="grade-form">
                                    <input type="hidden" name="submission_id" value="<?= $s['submission_id'] ?>">
                                    <input type="hidden" name="status" value="Approved">
                                    <input type="text" name="remarks" placeholder="Grade / feedback" class="form-control" required>
                                    <div class="btn-row">
                                        <button type="submit" class="btn btn-success btn-sm">Submit</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="hideGradeForm(<?= $s['submission_id'] ?>)">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
function showGradeForm(id) {
    document.getElementById('actions-' + id).style.display = 'none';
    document.getElementById('grade-form-' + id).style.display = 'block';
}
function hideGradeForm(id) {
    document.getElementById('actions-' + id).style.display = 'block';
    document.getElementById('grade-form-' + id).style.display = 'none';
}
</script>
</body>
</html>