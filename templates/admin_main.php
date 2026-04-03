<?php if (!empty($_SESSION['backup_error'])): ?>
    <div class="error-msg mb-3"><?= htmlspecialchars($_SESSION['backup_error']) ?></div>
    <?php unset($_SESSION['backup_error']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['monthly_backup_status'])): ?>
    <?php $status = $_SESSION['monthly_backup_status']; ?>
    <div class="alert alert-<?= htmlspecialchars($status['type']) ?> mb-3"><?= htmlspecialchars($status['message']) ?></div>
    <?php unset($_SESSION['monthly_backup_status']); ?>
<?php endif; ?>

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

<h3>&#x1F4CA; Attendance Records</h3>
<div class="mb-4">
    <div class="card p-3">
        <div class="card-body d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h5 style="margin-top:0; margin-bottom:6px;">View Attendance Records</h5>
                <p class="text-muted mb-0" style="font-size:13px;">
                    View and filter attendance records for all students. See detailed logs including time in/out,
                    shift status, verification status, and daily tasks.
                </p>
            </div>
            <a href="admin_attendance_records.php" class="btn btn-primary" style="white-space:nowrap; flex-shrink:0;">
                &#x1F4CB; Open Attendance Records
            </a>
        </div>
    </div>
</div>

<h3>Add New Supervisor</h3>
<div class="card p-3 mb-4" id="add-employer">
    <div class="card-body">
        <?php if (!empty($addEmployerError)): ?>
            <div class="error-msg mb-3"><?= htmlspecialchars($addEmployerError) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="admin_username">Username</label>
                    <input id="admin_username" type="text" name="username" required class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="admin_full_name">Full Name</label>
                    <input id="admin_full_name" type="text" name="name" required class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="admin_company">Company</label>
                    <input id="admin_company" type="text" name="company" required class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="admin_email">Email</label>
                    <input id="admin_email" type="email" name="email" required class="form-control">
                </div>
            </div>
            <div class="row align-items-end">
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="admin_password">Password</label>
                    <input id="admin_password" type="password" name="password" required class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="admin_work_start">Work Start Time</label>
                    <input id="admin_work_start" type="time" name="work_start" value="08:00" required class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="admin_work_end">Work End Time</label>
                    <input id="admin_work_end" type="time" name="work_end" value="17:00" required class="form-control">
                </div>
                <div class="col-md-6 mb-3 d-flex align-items-center">
                    <p class="text-muted mb-0" style="font-size:13px;">&#x23F0; Students will be blocked from accessing the dashboard outside these hours.</p>
                </div>
            </div>
            <button type="submit" name="add_employer" class="btn btn-primary">Add Supervisor</button>
        </form>
    </div>
</div>

<h3>OJT Supervisors</h3>
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
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="employer_id" value="<?= $emp['employer_id'] ?>">
                        <button type="submit" name="delete_employer" onclick="return confirm('Delete this employer?')" class="btn btn-outline-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    <?php if (isset($_SESSION['employer_added_success']) && $_SESSION['employer_added_success']): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var successModal = new bootstrap.Modal(document.getElementById('employerSuccessModal'));
            successModal.show();
        });
        <?php unset($_SESSION['employer_added_success']); ?>
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
