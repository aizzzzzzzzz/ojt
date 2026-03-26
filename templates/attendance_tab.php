<div id="attendance-tab" class="tab-content active">
    <div class="section-card">
        <h3 style="margin-top:0;">End of Day Task</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <textarea
                name="daily_task"
                rows="4"
                class="form-control"
                placeholder="Write what you did today..."
            ><?= htmlspecialchars($today_row['daily_task'] ?? '') ?></textarea>

            <button type="submit" name="save_task"
                class="action-btn btn-primary"
                style="margin-top:10px;">
                 Save Task
            </button>
        </form>

        <p style="color:var(--text-muted);margin-top:8px;font-size:13px;">
            You can only write/edit your task for <strong><?= $today ?></strong>.
        </p>
    </div>

    <div class="section-card">
    <h3 style="margin-top:0;">Attendance Actions</h3>
    <div class="attendance-actions">
    <?php
    $time_in_done    = !empty($today_row['time_in']);
    $lunch_out_done  = !empty($today_row['lunch_out']);
    $lunch_in_done   = !empty($today_row['lunch_in']);
    $time_out_done   = !empty($today_row['time_out']);

    // $before_work_start, $time_in_window_open, $time_in_window_closed,
    // $eod_window_open, $time_in_cutoff, $eod_cutoff_dt are set in student_dashboard.php

    $actions = [
        'time_in'   => '🟢 Time In',
        'lunch_out' => '🍽️ Lunch Out',
        'lunch_in'  => '🍽️ Lunch In',
        'time_out'  => '🔴 Time Out',
    ];

    foreach ($actions as $key => $label):
        $done = ${$key . '_done'};

        // Sequence checks (unchanged)
        $seq_disabled = false;
        if ($key === 'lunch_out' && (!$time_in_done || $done)) $seq_disabled = true;
        if ($key === 'lunch_in'  && (!$lunch_out_done || $done)) $seq_disabled = true;
        if ($key === 'time_out'  && (!$time_in_done || $done))   $seq_disabled = true;

        // Time window checks
        $window_disabled = false;
        $window_hint     = '';
        if ($key === 'time_in' && !$done) {
            if ($before_work_start) {
                $window_disabled = true;
                $window_hint = 'Opens at ' . $work_start_dt->format('H:i');
            } elseif ($time_in_window_closed) {
                $window_disabled = true;
                $window_hint = 'Grace period ended at ' . $time_in_cutoff->format('H:i');
            }
        } elseif ($key !== 'time_in' && !$done && !$seq_disabled) {
            if (!$eod_window_open) {
                $window_disabled = true;
                $window_hint = 'Closed at ' . $eod_cutoff_dt->format('H:i');
            }
        }

        $is_disabled = $done || $seq_disabled || $window_disabled;
    ?>
    <form method="post" style="margin:0;">
        <input type="hidden" name="attendance_action" value="<?= $key ?>">
        <button
            type="submit"
            class="action-btn <?= $is_disabled ? 'btn-disabled' : 'btn-primary' ?>"
            <?= $is_disabled ? 'disabled' : '' ?>
            <?= $window_hint ? 'title="' . htmlspecialchars($window_hint) . '"' : '' ?>
        >
            <?= $label ?>
            <?php if ($window_hint && !$done): ?>
                <span style="font-size:11px;font-weight:400;display:block;margin-top:2px;opacity:0.8;"><?= htmlspecialchars($window_hint) ?></span>
            <?php endif; ?>
        </button>
    </form>
    <?php endforeach; ?>
    </div>
    <p style="margin-top:10px;color:var(--text-muted);font-size:13px;">
        Time In: within <?= htmlspecialchars((string)$late_grace_minutes) ?> minutes of work start &nbsp;·&nbsp; Other actions: until <?= htmlspecialchars((string)$eod_grace_hours) ?> hours after work end.
    </p>
    </div>

    <h3>Attendance History</h3>

    <div class="table-section desktop-view">
    <table>
    <thead>
    <tr>
    <th>Date</th>
    <th>Time In</th>
    <th>Lunch Out</th>
    <th>Lunch In</th>
    <th>Time Out</th>
    <th>Status</th>
    <th>Verified</th>
    <th>Hours (Daily)</th>
    <th>Task</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach($attendance as $row): ?>
    <tr>
    <td data-label="Date"><?= htmlspecialchars($row['log_date']) ?></td>
    <td data-label="Time In"><?= (strpos($row['time_in'], '0000') === false && !empty($row['time_in'])) ? date('H:i:s', strtotime($row['time_in'])) : '-' ?></td>
    <td data-label="Lunch Out"><?= (strpos($row['lunch_out'], '0000') === false && !empty($row['lunch_out'])) ? date('H:i:s', strtotime($row['lunch_out'])) : '-' ?></td>
    <td data-label="Lunch In"><?= (strpos($row['lunch_in'], '0000') === false && !empty($row['lunch_in'])) ? date('H:i:s', strtotime($row['lunch_in'])) : '-' ?></td>
    <td data-label="Time Out"><?= (strpos($row['time_out'], '0000') === false && !empty($row['time_out'])) ? date('H:i:s', strtotime($row['time_out'])) : '-' ?></td>
    <td data-label="Status">
        <?php
        $status = $row['status'] ?: '---';
        $status_class = '';
        if (strtolower($status) === 'present') $status_class = "style='color:var(--green);font-weight:700;'";
        if (strtolower($status) === 'absent')  $status_class = "style='color:var(--red);font-weight:700;'";
        if (strtolower($status) === 'excused') $status_class = "style='color:var(--amber);font-weight:700;'";
        ?>
        <span <?= $status_class ?>><?= htmlspecialchars($status) ?></span>
    <td data-label="Verified">
        <?php if ($row['verified'] == 1): ?>
            <span class="verified-badge">✓ Verified</span>
        <?php else: ?>
            <span class="unverified-badge">⏳ Pending</span>
        <?php endif; ?>
    </td>
    <td data-label="Hours (Daily)">
    <?php
    $minutesWorked = 0;

    if (!empty($row['time_in']) && !empty($row['time_out']) && strpos($row['time_in'], '0000') === false && strpos($row['time_out'], '0000') === false) {
        $minutesWorked = max(0, (strtotime($row['time_out']) - strtotime($row['time_in'])) / 60);

        if (!empty($row['lunch_in']) && !empty($row['lunch_out']) && strpos($row['lunch_in'], '0000') === false && strpos($row['lunch_out'], '0000') === false) {
            $minutesWorked -= max(0, (strtotime($row['lunch_in']) - strtotime($row['lunch_out'])) / 60);
        }

        echo floor($minutesWorked / 60) . " hr " . ($minutesWorked % 60) . " min";
    } else {
        echo "-";
    }
    ?>
    </td>

    <td data-label="Task">
        <?= !empty($row['daily_task']) ? htmlspecialchars($row['daily_task']) : '-' ?>
    </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>

    <div class="mobile-view">
    <?php foreach($attendance as $row): ?>
    <div class="attendance-card">
        <div class="card-header">
            <strong>Date: <?= htmlspecialchars($row['log_date']) ?></strong>
            <span class="status-badge">
                <?php
                $status = $row['status'] ?: '---';
                $status_class = '';
                if (strtolower($status) === 'present') $status_class = "style='background: #d4edda; color: #155724;'";
                if (strtolower($status) === 'absent')  $status_class = "style='background: #f8d7da; color: #721c24;'";
                if (strtolower($status) === 'excused') $status_class = "style='background: #fff3cd; color: #856404;'";
                ?>
                <span class="status-text" <?= $status_class ?>><?= htmlspecialchars($status) ?></span>
                <?php if ($row['verified'] == 1): ?>
                    <span class="verified-badge">✓ Verified</span>
                <?php else: ?>
                    <span class="unverified-badge">✗ Not Verified</span>
                <?php endif; ?>
                </span>
        </div>
        <div class="card-body">
            <div class="time-info">
                <div class="time-row">
                    <span class="label">Time In:</span>
                    <span class="value"><?= (strpos($row['time_in'], '0000') === false && !empty($row['time_in'])) ? date('H:i:s', strtotime($row['time_in'])) : '-' ?></span>
                </div>
                <div class="time-row">
                    <span class="label">Lunch Out:</span>
                    <span class="value"><?= (strpos($row['lunch_out'], '0000') === false && !empty($row['lunch_out'])) ? date('H:i:s', strtotime($row['lunch_out'])) : '-' ?></span>
                </div>
                <div class="time-row">
                    <span class="label">Lunch In:</span>
                    <span class="value"><?= (strpos($row['lunch_in'], '0000') === false && !empty($row['lunch_in'])) ? date('H:i:s', strtotime($row['lunch_in'])) : '-' ?></span>
                </div>
                <div class="time-row">
                    <span class="label">Time Out:</span>
                    <span class="value"><?= (strpos($row['time_out'], '0000') === false && !empty($row['time_out'])) ? date('H:i:s', strtotime($row['time_out'])) : '-' ?></span>
                </div>
                <div class="time-row">
                    <span class="label">Hours Worked:</span>
                    <span class="value">
                    <?php
                    $minutesWorked = 0;
                    if (!empty($row['time_in']) && !empty($row['time_out']) && strpos($row['time_in'], '0000') === false && strpos($row['time_out'], '0000') === false) {
                        $minutesWorked = max(0, (strtotime($row['time_out']) - strtotime($row['time_in'])) / 60);
                        if (!empty($row['lunch_in']) && !empty($row['lunch_out']) && strpos($row['lunch_in'], '0000') === false && strpos($row['lunch_out'], '0000') === false) {
                            $minutesWorked -= max(0, (strtotime($row['lunch_in']) - strtotime($row['lunch_out'])) / 60);
                        }
                        echo floor($minutesWorked / 60) . " hr " . ($minutesWorked % 60) . " min";
                    } else {
                        echo "-";
                    }
                    ?>
                    </span>
                </div>
            </div>
            <div class="task-info">
                <strong>Task:</strong>
                <p><?= !empty($row['daily_task']) ? htmlspecialchars($row['daily_task']) : 'No task recorded' ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
