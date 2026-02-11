<?php
// Get ALL submissions for this student+project
$historyStmt = $pdo->prepare("SELECT status, submission_date, submission_status, remarks, graded_at FROM project_submissions WHERE project_id = ? AND student_id = ? ORDER BY submission_date ASC");
$historyStmt->execute([$project_id, $student_id]);
$allSubmissions = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
$hasSubmissions = !empty($allSubmissions);

// Check if already approved
$isApproved = false;
foreach ($allSubmissions as $sub) {
    if ($sub['status'] === 'Approved') {
        $isApproved = true;
        break;
    }
}
?>

<!-- Current Submission Status -->
<?php if($hasSubmissions): ?>
    <div class="submission-status mb-4">
        <h5>Submission History</h5>
        <?php foreach($allSubmissions as $index => $sub): ?>
            <div class="mb-3 p-3 border rounded">
                <div class="d-flex justify-content-between">
                    <strong>Attempt #<?= $index + 1 ?></strong>
                    <span class="badge bg-<?= $sub['status'] === 'Approved' ? 'success' : ($sub['status'] === 'Rejected' ? 'danger' : 'warning') ?>">
                        <?= htmlspecialchars($sub['status']) ?>
                    </span>
                </div>
                <p class="mb-1"><small>Submitted: <?= date('F j, Y H:i', strtotime($sub['submission_date'])) ?></small></p>
                <p class="mb-1">
                    <span class="badge bg-<?= $sub['submission_status'] === 'On Time' ? 'success' : 'danger' ?>">
                        <?= htmlspecialchars($sub['submission_status']) ?>
                    </span>
                </p>
                <?php if($sub['remarks']): ?>
                    <p class="mb-1"><strong>Remarks:</strong> <?= htmlspecialchars($sub['remarks']) ?></p>
                <?php endif; ?>
                <?php if($sub['graded_at']): ?>
                    <p class="mb-0"><small>Graded: <?= date('F j, Y H:i', strtotime($sub['graded_at'])) ?></small></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($isApproved): ?>
    <div class="alert alert-success">
        <h5>Project Completed Successfully! 🎉</h5>
        <p>You have already passed this project. You cannot submit further attempts.</p>
    </div>
<?php else: ?>
    <!-- Show submission form only if not approved -->
    <form method="post" action="submit_project.php" enctype="multipart/form-data">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">

        <div class="mb-3">
            <label for="project_file" class="form-label">Upload Project File</label>
            <input type="file" class="form-control" id="project_file" name="project_file" required>
            <div class="form-text">
                Allowed file types: PDF, DOC, DOCX, HTML, TXT, ZIP<br>
                Maximum file size: 10MB
            </div>
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
            <a href="student_dashboard.php" class="btn btn-secondary me-md-2">Back to Dashboard</a>
            <button type="submit" class="btn btn-primary">
                <?= $hasSubmissions ? 'Submit New Attempt' : 'Submit Project' ?>
            </button>
        </div>
    </form>
<?php endif; ?>
