<div class="attendance-actions">
            <h4>Attendance Management</h4>
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="col-md-4">
                    <label class="form-label">Select Student</label>
                    <select name="student_id" class="form-select" required>
                        <option value="">Choose student...</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= htmlspecialchars($student['student_id']) ?>">
                                <?= htmlspecialchars($student['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Reason for Absence</label>
                    <input type="text" name="reason" class="form-control" placeholder="Enter reason..." required>
                </div>
                <div class="col-12">
                    <button type="submit" name="mark_absent" class="btn btn-danger">Mark Student Absent</button>
                </div>
            </form>
        </div>

        <div class="table-section">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Latest Date</th>
                        <th>Status</th>
                        <th>Total Hours (Daily)</th>
                        <th>Verify Attendance</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $student_attendance = [];
                    foreach ($attendance as $row) {
                        $student_attendance[$row['student_id']][] = $row;
                    }

                    $index = 0;

                    foreach ($student_attendance as $student_id => $records):
                        usort($records, function($a, $b) {
                            return strtotime($b['log_date']) <=> strtotime($a['log_date']);
                        });

                        $latest = $records[0];

                        $acc_minutes = $acc_map[$student_id] ?? 0;
                        $required_minutes = 0;
                        $eligible_for_eval = $acc_minutes >= $required_minutes;
                        $already_evaluated = isset($evaluated_students[$student_id]);
                        $acc_display = floor($acc_minutes / 60) . "h " . ($acc_minutes % 60) . "m";

                        $status = $latest['status'] ?: '---';

                        $status_class = '';
                        if (strtolower($status) === 'present') $status_class = "style='color: green; font-weight: bold;'";
                        if (strtolower($status) === 'absent')  $status_class = "style='color: red; font-weight: bold;'";
                        if (strtolower($status) === 'excused') $status_class = "style='color: orange; font-weight: bold;'";
                    ?>
                    <tr>
                        <td data-label="Student">
                            <strong>
                                <?= htmlspecialchars(
                                    $latest['last_name'] . ', ' .
                                    $latest['first_name'] .
                                    ($latest['middle_name'] ? ' ' . $latest['middle_name'] : '')
                                ) ?>
                            </strong><br>
                            <small style="color:#555;">School: <?= htmlspecialchars($latest['school'] ?? 'N/A') ?></small>
                        </td>

                        <td data-label="Latest Date"><?= $latest['log_date'] ? date('Y-m-d', strtotime($latest['log_date'])) : '---' ?></td>

                        <td data-label="Status" <?= $status_class ?>>
                            <?= htmlspecialchars($status) ?>
                            <?php if ($latest['verified'] == 1): ?>
                                <br><small style="color:green;">(Verified)</small>
                            <?php endif; ?>
                        </td>

                        <td data-label="Total Hours (Daily)">
                            <?php if (strtolower($status) === 'present'): ?>
                                <?= htmlspecialchars($latest['daily_hours']) ?>
                            <?php else: ?>
                                ---
                            <?php endif; ?>
                        </td>

                        <td data-label="Verify Attendance">
                            <?php if (!empty($latest['time_out']) && empty($latest['verified'])): ?>
                                <form method="POST" action="verify_attendance.php" style="margin:0;">
                                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">
                                    <input type="hidden" name="log_date" value="<?= htmlspecialchars($latest['log_date']) ?>">
                                    <button type="submit" class="btn btn-success btn-sm">✅ Verify Attendance</button>
                                </form>
                            <?php elseif ($eligible_for_eval && !$already_evaluated): ?>
                                <a href="evaluate_student.php?student_id=<?= $student_id ?>" class="btn btn-warning btn-sm">
                                    📝 Evaluate Student
                                </a>
                            <?php elseif ($already_evaluated): ?>
                                <span style="color:green; font-weight:bold;">✔ Evaluation Completed</span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>

                        <td data-label="Details">
                            <button class="btn btn-outline-secondary btn-sm" onclick="toggleDetails(<?= $index ?>)">
                                ▼ Show Details
                            </button>
                        </td>
                    </tr>

                    <tr class="details-row" id="details<?= $index ?>">
                        <td colspan="6" style="padding: 0; border: none; background: transparent;">
                            <div class="details-content">
                                <h5 style="margin-bottom:15px; color:#2c3e50;">
                                    Attendance Details — <?= htmlspecialchars(
                                        $latest['last_name'] . ', ' .
                                        $latest['first_name'] .
                                        ($latest['middle_name'] ? ' ' . $latest['middle_name'] : ''))
                                    ?>
                                </h5>

                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Date:</strong> <?= htmlspecialchars($latest['log_date'] ?? '---') ?></p>
                                        <p><strong>Time In:</strong> <?= htmlspecialchars($latest['time_in'] ?? '---') ?></p>
                                        <p><strong>Lunch Out:</strong> <?= htmlspecialchars($latest['lunch_out'] ?? '---') ?></p>
                                        <p><strong>Daily Task:</strong><br>
                                            <span style="white-space:pre-line; color:#333;">
                                                <?= !empty($latest['daily_task']) ? htmlspecialchars($latest['daily_task']) : '-' ?>
                                            </span>
                                        </p>
                                    </div>

                                    <div class="col-md-6">
                                        <p><strong>Lunch In:</strong> <?= htmlspecialchars($latest['lunch_in'] ?? '---') ?></p>
                                        <p><strong>Time Out:</strong> <?= htmlspecialchars($latest['time_out'] ?? '---') ?></p>
                                        <p><strong>Verified:</strong>
                                            <?php if ($latest['verified'] == 1): ?>
                                                <span style="color:green; font-weight:bold;">Yes</span>
                                            <?php else: ?>
                                                <span style="color:red; font-weight:bold;">No</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <p style="margin-top:15px;">
                                    <strong>Total Hours (Accumulated):</strong> <?= $acc_display ?>
                                </p>

                                <?php if ($already_evaluated): ?>
                                    <p style="margin-top:15px;">
                                        <?php
                                        $signaturePath = 'assets/signature_' . $employer_id . '_' . $student_id . '.png';
                                        if (file_exists($signaturePath)): ?>
                                            <a href="generate_certificate.php?student_id=<?= $student_id ?>" class="btn btn-success btn-sm">📄 Generate Certificate</a>
                                        <?php else: ?>
                                            <a href="add_signature.php?student_id=<?= $student_id ?>" class="btn btn-warning btn-sm">✍️ Add Signature</a>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <?php $index++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
