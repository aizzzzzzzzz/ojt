<?php if (!empty($_SESSION['backup_error'])): ?>
    <div class="error-msg mb-3"><?= htmlspecialchars($_SESSION['backup_error']) ?></div>
    <?php unset($_SESSION['backup_error']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['monthly_backup_status'])): ?>
    <?php $status = $_SESSION['monthly_backup_status']; ?>
    <div class="alert alert-<?= htmlspecialchars($status['type']) ?> mb-3"><?= htmlspecialchars($status['message']) ?></div>
    <?php unset($_SESSION['monthly_backup_status']); ?>
<?php endif; ?>

<h3>Quick Actions</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:24px;">
    <a href="admin_approve_moa.php" style="display:flex;align-items:center;gap:12px;padding:16px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);text-decoration:none;color:var(--text);transition:all .18s;font-weight:600;font-size:14px;">
        <span style="font-size:1.8rem;">✅</span>
        <span>Approve Student Documents</span>
    </a>
</div>

<details class="maintenance-card mb-4">
    <summary>Maintenance &amp; Backups</summary>
    <div class="maintenance-body">
        <p class="mb-2">Download a full SQL backup of the database.</p>
        <form method="post" action="admin_backup.php" target="_top">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <button type="submit" class="btn btn-success">Backup Now</button>
        </form>
        <p class="mb-2 mt-3">Run the monthly backup scheduler (creates a backup once per month).</p>
        <form method="post" action="admin_monthly_backup.php" target="_top">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <button type="submit" class="btn btn-outline-success">Run Monthly Backup</button>
        </form>
    </div>
</details>

<h3 id="supervisor-list">OJT Supervisors</h3>
<?php if (!empty($employerStatus) && is_array($employerStatus)): ?>
    <div class="alert alert-<?= htmlspecialchars($employerStatus['type'] ?? 'info') ?> mb-3">
        <?= htmlspecialchars($employerStatus['message'] ?? '') ?>
    </div>
<?php endif; ?>
<div class="card mb-4" style="overflow:hidden;">
    <table class="table table-striped mb-0">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Name</th>
                <th>Company</th>
                <th>Working Hours</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employers as $emp): ?>
            <tr>
                <td><?= htmlspecialchars($emp['username']) ?></td>
                <td><?= htmlspecialchars($emp['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($emp['name']) ?></td>
                <td><?= htmlspecialchars($emp['company']) ?></td>
                <td>
                    <?php
                        $ws = !empty($emp['work_start']) ? date('h:i A', strtotime($emp['work_start'])) : '08:00 AM';
                        $we = !empty($emp['work_end'])   ? date('h:i A', strtotime($emp['work_end']))   : '05:00 PM';
                        echo htmlspecialchars($ws) . ' - ' . htmlspecialchars($we);
                    ?>
                </td>
                <td>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editEmployerModal<?= (int) $emp['employer_id'] ?>">
                            Update
                        </button>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="employer_id" value="<?= $emp['employer_id'] ?>">
                            <button type="submit" name="delete_employer" onclick="return confirm('Delete this employer?')" class="btn btn-outline-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php foreach ($employers as $emp): ?>
    <?php
        $modalEmp = $emp;
        if ($editEmployerId === (int) $emp['employer_id'] && !empty($editEmployerForm) && is_array($editEmployerForm)) {
            $modalEmp = array_merge($modalEmp, $editEmployerForm);
        }
        $modalWorkStart = !empty($modalEmp['work_start']) ? substr((string) $modalEmp['work_start'], 0, 5) : '08:00';
        $modalWorkEnd = !empty($modalEmp['work_end']) ? substr((string) $modalEmp['work_end'], 0, 5) : '17:00';
    ?>
    <div class="modal fade" id="editEmployerModal<?= (int) $emp['employer_id'] ?>" tabindex="-1" aria-labelledby="editEmployerModalLabel<?= (int) $emp['employer_id'] ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEmployerModalLabel<?= (int) $emp['employer_id'] ?>">Update Supervisor Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="employer_id" value="<?= (int) $emp['employer_id'] ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="edit_username_<?= (int) $emp['employer_id'] ?>">Username</label>
                                <input id="edit_username_<?= (int) $emp['employer_id'] ?>" type="text" name="username" value="<?= htmlspecialchars($modalEmp['username'] ?? '') ?>" required class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="edit_name_<?= (int) $emp['employer_id'] ?>">Full Name</label>
                                <input id="edit_name_<?= (int) $emp['employer_id'] ?>" type="text" name="name" value="<?= htmlspecialchars($modalEmp['name'] ?? '') ?>" required class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="edit_company_<?= (int) $emp['employer_id'] ?>">Company</label>
                                <input id="edit_company_<?= (int) $emp['employer_id'] ?>" type="text" name="company" value="<?= htmlspecialchars($modalEmp['company'] ?? '') ?>" required class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="edit_email_<?= (int) $emp['employer_id'] ?>">Email</label>
                                <input id="edit_email_<?= (int) $emp['employer_id'] ?>" type="email" name="email" value="<?= htmlspecialchars($modalEmp['email'] ?? '') ?>" required class="form-control">
                            </div>
                        </div>
                        <div class="row align-items-end">
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="edit_work_start_<?= (int) $emp['employer_id'] ?>">Work Start Time</label>
                                <input id="edit_work_start_<?= (int) $emp['employer_id'] ?>" type="time" name="work_start" value="<?= htmlspecialchars($modalWorkStart) ?>" required class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="edit_work_end_<?= (int) $emp['employer_id'] ?>">Work End Time</label>
                                <input id="edit_work_end_<?= (int) $emp['employer_id'] ?>" type="time" name="work_end" value="<?= htmlspecialchars($modalWorkEnd) ?>" required class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="edit_password_<?= (int) $emp['employer_id'] ?>">New Password</label>
                                <input id="edit_password_<?= (int) $emp['employer_id'] ?>" type="password" name="new_password" class="form-control">
                                <div class="form-text">Leave blank to keep the current password.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_employer" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
    <?php if (isset($_SESSION['employer_added_success']) && $_SESSION['employer_added_success']): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var successModal = new bootstrap.Modal(document.getElementById('employerSuccessModal'));
            successModal.show();
        });
        <?php unset($_SESSION['employer_added_success']); ?>
    <?php endif; ?>

    <?php if (!empty($editEmployerId)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var editModal = document.getElementById('editEmployerModal<?= (int) $editEmployerId ?>');
            if (editModal) {
                var modalInstance = new bootstrap.Modal(editModal);
                modalInstance.show();
            }
        });
    <?php endif; ?>
</script>

<div class="modal fade" id="employerSuccessModal" tabindex="-1" aria-labelledby="employerSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="employerSuccessModalLabel">Success</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Supervisor added successfully!
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
