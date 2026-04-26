<div id="attendance-tab" class="tab-content active">
    <?php if (!empty($has_approved_shift) && $approved_shift): ?>
    <div class="section-card" style="background: var(--green-lt); border-color: var(--green); margin-bottom: 16px;">
        <h4 style="margin-top:0; color: var(--green); font-size: 14px;">✅ Approved Shift Change Active Today</h4>
        <p style="margin: 8px 0 0; color: #15803d; font-size: 13px;">
            <strong>Your shift today:</strong> 
            <?= date('g:i A', strtotime($approved_shift['requested_shift_start'])) ?> - 
            <?= date('g:i A', strtotime($approved_shift['requested_shift_end'])) ?>
            <?php if ($approved_shift['requested_shift_start'] > $approved_shift['requested_shift_end']): ?>
                <span style="color: #a16207;">(Overnight shift - ends next day)</span>
            <?php endif; ?>
        </p>
        <p style="margin: 4px 0 0; color: #15803d; font-size: 12px;">
            <em>Normal schedule: <?= date('g:i A', strtotime($original_work_start_str)) ?> - <?= date('g:i A', strtotime($original_work_end_str)) ?></em>
        </p>
    </div>
    <?php endif; ?>

    <div class="section-card">
        <h3 style="margin-top:0;">End of Day Task</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <textarea
                name="daily_task"
                rows="4"
                class="form-control"
                placeholder="Write what you did today..."
                <?= empty($today_row['time_in']) ? 'disabled' : '' ?>
            ><?= htmlspecialchars($today_row['daily_task'] ?? '') ?></textarea>

            <button type="submit" name="save_task"
                class="action-btn btn-primary"
                style="margin-top:10px;"
                <?= empty($today_row['time_in']) ? 'disabled' : '' ?>>
                 Save Task
            </button>
        </form>

        <p style="color:var(--text-muted);margin-top:8px;font-size:13px;">
            <?php if (empty($today_row['time_in'])): ?>
                You must record a Time In before you can save a task.
            <?php else: ?>
                You can only write/edit your task for <strong><?= $today ?></strong>.
            <?php endif; ?>
        </p>
    </div>

    <div class="section-card">
        <h3 style="margin-top:0;">📸 DTR Verification Photo</h3>
        <form method="POST" enctype="multipart/form-data" id="dtr_upload_form">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            
            <div style="margin-bottom:12px;">
                <label for="dtr_picture" style="display:block; margin-bottom:8px; font-weight:500; font-size:14px;">
                    Upload DTR Picture for <?= $today ?>
                </label>
                <input 
                    type="file" 
                    id="dtr_picture" 
                    name="dtr_picture" 
                    accept="image/jpeg,image/png,image/jpg,image/webp"
                    class="form-control"
                    style="max-width:300px;"
                    <?= empty($today_row['time_in']) ? 'disabled' : '' ?>
                >
                <p style="margin:8px 0 0 0; color:var(--text-muted); font-size:12px;">
                    ✓ Supported formats: JPEG, PNG, WebP<br>
                    ✓ Maximum file size: 5MB<br>
                    ✓ Keep picture clear and visible
                </p>
            </div>

            <?php if (!empty($today_row['dtr_picture'])): ?>
                <div style="margin-bottom:12px; padding:10px; background:#e8f5e9; border-radius:6px; border-left:4px solid #4caf50;">
                    <p style="margin:0; color:#2e7d32; font-size:13px; font-weight:500;">
                        ✓ DTR photo uploaded for <?= $today ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <button 
                type="submit" 
                class="action-btn btn-primary"
                style="padding:8px 16px; font-size:14px;"
                <?= empty($today_row['time_in']) ? 'disabled' : '' ?>>
                <?= !empty($today_row['dtr_picture']) ? '🔄 Update Photo' : '📤 Upload Photo' ?>
            </button>
        </form>

        <p style="color:var(--text-muted); margin-top:10px; font-size:13px;">
            Upload a clear photo of your Daily Time Record (DTR) or attendance sheet for verification purposes.
            <?php if (empty($today_row['time_in'])): ?>
                <br>You must record a Time In before you can upload a DTR photo.
            <?php endif; ?>
        </p>
    </div>

    <div class="section-card">
        <h3 style="margin-top:0;">Attendance Actions</h3>
        <div class="attendance-actions">

        <!-- MOA Documents Button -->
        <a href="view_moa.php" class="action-btn btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 10px;">
            📄 Open MOA Documents
        </a>

        <!-- Shift Change Request Button -->
        <a href="request_shift_change.php" class="action-btn btn-outline-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 10px;">
            📅 Request Shift Change
        </a>
    
    <?php
    $time_in_done    = !empty($today_row['time_in']);
    $time_out_done   = !empty($today_row['time_out']);

    $actions = [
        'time_in'   => '🟢 Time In',
        'time_out'  => '🔴 Time Out',
    ];

    foreach ($actions as $key => $label):
        $done = ${$key . '_done'};

        $seq_disabled = false;
        if ($key === 'time_out' && (!$time_in_done || $done)) $seq_disabled = true;

        $window_disabled = false;
        $window_hint     = '';
        if (!$attendance_time_limits_disabled) {
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
        <?php if ($attendance_time_limits_disabled): ?>
            Demo mode: attendance time limits are temporarily disabled.
        <?php else: ?>
            Time In: within <?= htmlspecialchars((string)$late_grace_minutes) ?> minutes of work start
            <?php if (!empty($is_afternoon_shift) && $is_afternoon_shift): ?>
                (Afternoon shift detected - work starts at <?= htmlspecialchars($work_start_str) ?>)
            <?php endif; ?>
            &nbsp;·&nbsp; Time Out: until <?= htmlspecialchars((string)$eod_grace_hours) ?> hours after work end.
            <br>💡 1 hour lunch is automatically deducted for shifts 4 hours or longer.
        <?php endif; ?>
    </p>
    </div>

    <h3>Attendance History</h3>

    <div class="table-section desktop-view">
    <table>
    <thead>
    <tr>
    <th>Date</th>
    <th>Time In</th>
    <th>Time Out</th>
    <th>Shift Status</th>
    <th>Verified</th>
    <th>Hours (Daily)</th>
    <th>Task</th>
    <th>DTR Photo</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach($attendance as $row): ?>
    <tr>
    <td data-label="Date"><?= htmlspecialchars($row['log_date']) ?></td>
    <td data-label="Time In"><?= (strpos($row['time_in'], '0000') === false && !empty($row['time_in'])) ? date('H:i:s', strtotime($row['time_in'])) : '-' ?></td>
    <td data-label="Time Out"><?= (strpos($row['time_out'], '0000') === false && !empty($row['time_out'])) ? date('H:i:s', strtotime($row['time_out'])) : '-' ?></td>
    <td data-label="Shift Status">
        <?php
        $shift_status = $row['shift_status'] ?? 'on_time';
        $late_minutes = (int)($row['late_minutes'] ?? 0);
        
        $shift_badge = '';
        
        if (empty($row['time_in']) || strpos($row['time_in'], '0000') !== false) {
            $shift_badge = '<span class="shift-status-badge" style="color: #dc3545; font-weight: bold;">❌ Absent</span>';
        } elseif ($shift_status === 'on_time') {
            $shift_badge = '<span class="shift-badge shift-on-time">🟢 On Time</span>';
        } elseif ($shift_status === 'late_grace') {
            $shift_badge = '<span class="shift-badge shift-late-grace">🟡 Late (Grace)</span>';
            if ($late_minutes > 0) {
                $shift_badge .= '<br><small style="color:var(--text-muted)">+' . $late_minutes . ' min late</small>';
            }
        } elseif ($shift_status === 'adjusted_shift') {
            $effective = !empty($row['effective_start_time']) ? date('H:i', strtotime($row['effective_start_time'])) : '-';
            $shift_badge = '<span class="shift-badge shift-adjusted">🟠 Adjusted</span><br><small style="color:var(--text-muted)">Start: ' . $effective . '</small>';
        }
        ?>
        <?= $shift_badge ?>
    </td>
    <td data-label="Verified">
        <?php if ($row['verified'] == 1): ?>
            <span class="verified-badge">✓ Verified</span>
        <?php elseif (!empty($row['time_in']) && strpos($row['time_in'], '0000') === false): ?>
            <span style="color: var(--text-muted);">-</span>
        <?php endif; ?>
    </td>
    <td data-label="Hours (Daily)">
    <?php
    $minutesWorked = 0;
    $startTime = !empty($row['effective_start_time']) ? $row['effective_start_time'] : $row['time_in'];

    if (!empty($startTime) && !empty($row['time_out']) &&
        strpos($startTime, '0000') === false && strpos($row['time_out'], '0000') === false) {

        $minutesWorked = max(0, (strtotime($row['time_out']) - strtotime($startTime)) / 60);

        if ($minutesWorked > 240) {
            $minutesWorked -= 60;
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
    <td data-label="DTR Photo">
        <?php if (!empty($row['dtr_picture'])): ?>
            <a href="view_dtr.php?id=<?= htmlspecialchars((string)$row['id']) ?>" target="_blank" style="color:#1976d2; text-decoration:none; font-weight:500;" title="View DTR photo">
                📸 View
            </a>
        <?php else: ?>
            <span style="color:var(--text-muted);">-</span>
        <?php endif; ?>
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
                $shift_status = $row['shift_status'] ?? 'on_time';
                $late_minutes = (int)($row['late_minutes'] ?? 0);

                $shift_badge = '';
                
                if (empty($row['time_in']) || strpos($row['time_in'], '0000') !== false) {
                    $shift_badge = '<span style="color: #dc3545; font-weight: bold;">❌ Absent</span>';
                } elseif ($shift_status === 'on_time') {
                    $shift_badge = '<span class="shift-badge shift-on-time">🟢 On Time</span>';
                } elseif ($shift_status === 'late_grace') {
                    $shift_badge = '<span class="shift-badge shift-late-grace">🟡 Late</span>';
                    if ($late_minutes > 0) {
                        $shift_badge .= ' <small style="color:var(--text-muted)">+' . $late_minutes . 'm</small>';
                    }
                } elseif ($shift_status === 'adjusted_shift') {
                    $effective = !empty($row['effective_start_time']) ? date('H:i', strtotime($row['effective_start_time'])) : '-';
                    $shift_badge = '<span class="shift-badge shift-adjusted">🟠 Adj</span>';
                }
                ?>
                <?= $shift_badge ?>
                <?php if ($row['verified'] == 1): ?>
                    <span class="verified-badge">✓</span>
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
                    <span class="label">Time Out:</span>
                    <span class="value"><?= (strpos($row['time_out'], '0000') === false && !empty($row['time_out'])) ? date('H:i:s', strtotime($row['time_out'])) : '-' ?></span>
                </div>
                <div class="time-row">
                    <span class="label">Hours Worked:</span>
                    <span class="value">
                    <?php
                    $minutesWorked = 0;
                    $startTime = !empty($row['effective_start_time']) ? $row['effective_start_time'] : $row['time_in'];

                    if (!empty($startTime) && !empty($row['time_out']) &&
                        strpos($startTime, '0000') === false && strpos($row['time_out'], '0000') === false) {
                        $minutesWorked = max(0, (strtotime($row['time_out']) - strtotime($startTime)) / 60);
                        if ($minutesWorked > 240) {
                            $minutesWorked -= 60;
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
            <div class="dtr-info" style="margin-top:12px; padding-top:12px; border-top:1px solid #ddd;">
                <strong>DTR Photo:</strong>
                <?php if (!empty($row['dtr_picture'])): ?>
                    <a href="view_dtr.php?id=<?= htmlspecialchars((string)$row['id']) ?>" target="_blank" style="display:inline-block; margin-top:8px; padding:6px 12px; background:#1976d2; color:white; text-decoration:none; border-radius:4px; font-size:13px; font-weight:500;">
                        📸 View Photo
                    </a>
                <?php else: ?>
                    <p style="margin:8px 0 0 0; color:var(--text-muted);">No photo uploaded</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
