



<h3>Add New Supervisor</h3>
    <div id="add-employer">
        <div class="card p-3"><div class="card-body">
            <?php if (!empty($addEmployerError)): ?>
                <div class="error-msg"><?= htmlspecialchars($addEmployerError) ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" required class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" required class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Company</label>
                        <input type="text" name="company" required class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" required class="form-control">
                    </div>
                </div>
                <button type="submit" name="add_employer" class="btn btn-primary">Add Supervisor</button>
            </form>
        </div></div>
    </div>



    <h3 class="mb-4">OJT Supervisors</h3>
    <div>
        <table class="table table-striped mt-3">
            <thead><tr>
                <th>Username</th>
                <th>Name</th>
                <th>Company</th>
                <th>Action</th>
            </tr></thead>
            <tbody>
                <?php foreach ($employers as $emp): ?>
                <tr>
                    <td><?= htmlspecialchars($emp['username']) ?></td>
                    <td><?= htmlspecialchars($emp['name']) ?></td>
                    <td><?= htmlspecialchars($emp['company']) ?></td>
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
            // Show success modal when page loads
            document.addEventListener('DOMContentLoaded', function() {
                var successModal = new bootstrap.Modal(document.getElementById('employerSuccessModal'));
                successModal.show();
            });
            <?php unset($_SESSION['employer_added_success']); ?>
        <?php endif; ?>
    </script>

    <!-- Success Modal for Adding Supervisor -->
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
