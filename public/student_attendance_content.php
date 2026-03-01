
<div class="student-sidebar-buttons">
    <button onclick="showSection('attendance')" class="sidebar-btn active">📅 Attendance</button>
    <button onclick="showSection('projects')" class="sidebar-btn">📁 Projects</button>
    <button onclick="showSection('code')" class="sidebar-btn">💻 Code Editor</button>
</div>

<div class="summary">
    <p><strong>Total Hours:</strong> <?= $hours ?> hr <?= $minutes ?> min / 200h</p>
    <p><strong>Status:</strong> <span class="status <?= $statusClass ?>"><?= $statusText ?></span></p>
    <p><strong>Today:</strong> <?= $today ?></p>
</div>


<div style="margin-bottom:20px; text-align:left; padding:16px; border:1px solid #e0e0e0; background:#fff; border-radius:10px;">
    <h3 style="margin-top:0;">Daily Task / Activity</h3>

    <form method="POST">
        <textarea
            name="daily_task"
            rows="4"
            style="width:100%; padding:10px; border-radius:8px; border:1px solid #ccc; font-size:14px;"
            placeholder="Write what you did today..."
        ><?= htmlspecialchars($today_row['daily_task'] ?? '') ?></textarea>

        <button type="submit" name="save_task"
            class="action-btn btn-primary"
            style="margin-top:10px;">
             Save Task
        </button>
    </form>

    <p style="color:#777; margin-top:8px; font-size:13px;">
        You can only write/edit your task for <strong><?= $today ?></strong>.
    </p>
</div>

<div style="text-align:left; margin-bottom:20px; border-radius:10px; padding:16px; border:1px solid #e0e0e0; background:#f8f9fa;">
<h3 style="margin-top:0;">Attendance Actions</h3>
<div class="attendance-actions">
<?php
$time_in_done    = !empty($today_row['time_in']);
$lunch_out_done  = !empty($today_row['lunch_out']);
$lunch_in_done   = !empty($today_row['lunch_in']);
$time_out_done   = !empty($today_row['time_out']);
$actions = [
    'time_in' => '🟢 Time In',
    'lunch_out' => '🍽️ Lunch Out',
    'lunch_in' => '🍽️ Lunch In',
    'time_out' => '🔴 Time Out'
];
foreach($actions as $key=>$label):
    $done = ${$key.'_done'};
    $disabled = '';
    if ($key=='lunch_out' && (!$time_in_done || $done)) $disabled=true;
    if ($key=='lunch_in' && (!$lunch_out_done || $done)) $disabled=true;
    if ($key=='time_out' && (!$time_in_done || $done)) $disabled=true;
    $text = $done ? "({$today_row[$key]})" : '';
?>
<form method="post" style="margin:0;">
    <input type="hidden" name="attendance_action" value="<?= $key ?>">
    <button type="submit" class="action-btn <?= $done||$disabled?'btn-disabled':'btn-primary' ?>" <?= $done||$disabled?'disabled':'' ?>>
        <?= $label ?> <?= $text ?>
    </button>
</form>
<?php endforeach; ?>
</div>
<p style="margin-top:10px; color:#666; font-size:14px;">Note: Actions are recorded for <strong><?= $today ?></strong>. Buttons disable after recording.</p>
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
<td data-label="Time In"><?= ($row['time_in'] && $row['time_in'] != '0000-00-00 00:00:00') ? date('H:i:s', strtotime($row['time_in'])) : '-' ?></td>
<td data-label="Lunch Out"><?= ($row['lunch_out'] && $row['lunch_out'] != '0000-00-00 00:00:00') ? date('H:i:s', strtotime($row['lunch_out'])) : '-' ?></td>
<td data-label="Lunch In"><?= ($row['lunch_in'] && $row['lunch_in'] != '0000-00-00 00:00:00') ? date('H:i:s', strtotime($row['lunch_in'])) : '-' ?></td>
<td data-label="Time Out"><?= ($row['time_out'] && $row['time_out'] != '0000-00-00 00:00:00') ? date('H:i:s', strtotime($row['time_out'])) : '-' ?></td>
<td data-label="Status">
    <?php
    $status = $row['status'] ?: '---';
    $status_class = '';
    if (strtolower($status) === 'present') $status_class = "style='color: green; font-weight: bold;'";
    if (strtolower($status) === 'absent')  $status_class = "style='color: red; font-weight: bold;'";
    if (strtolower($status) === 'excused') $status_class = "style='color: orange; font-weight: bold;'";
    ?>
    <span <?= $status_class ?>><?= htmlspecialchars($status) ?></span>
<td data-label="Verified">
    <?php if ($row['verified'] == 1): ?>
        <span style="color:green; font-weight:bold;">Yes</span>
    <?php else: ?>
        <span style="color:red; font-weight:bold;">No</span>
    <?php endif; ?>
</td>
<td data-label="Hours (Daily)">
<?php
$minutesWorked = 0;

if (!empty($row['time_in']) && !empty($row['time_out'])) {
    $minutesWorked = max(0, (strtotime($row['time_out']) - strtotime($row['time_in'])) / 60);

    if (!empty($row['lunch_in']) && !empty($row['lunch_out'])) {
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
                <span class="value"><?= ($row['time_in'] && $row['time_in'] != '0000-00-00 00:00:00') ? date('H:i:s', strtotime($row['time_in'])) : '-' ?></span>
            </div>
            <div class="time-row">
                <span class="label">Lunch Out:</span>
                <span class="value"><?= ($row['lunch_out'] && $row['lunch_out'] != '0000-00-00 00:00:00') ? date('H:i:s', strtotime($row['lunch_out'])) : '-' ?></span>
            </div>
            <div class="time-row">
                <span class="label">Lunch In:</span>
                <span class="value"><?= ($row['lunch_in'] && $row['lunch_in'] != '0000-00-00 00:00:00') ? date('H:i:s', strtotime($row['lunch_in'])) : '-' ?></span>
            </div>
            <div class="time-row">
                <span class="label">Time Out:</span>
                <span class="value"><?= ($row['time_out'] && $row['time_out'] != '0000-00-00 00:00:00') ? date('H:i:s', strtotime($row['time_out'])) : '-' ?></span>
            </div>
            <div class="time-row">
                <span class="label">Hours Worked:</span>
                <span class="value">
                <?php
                $minutesWorked = 0;
                if (!empty($row['time_in']) && !empty($row['time_out'])) {
                    $minutesWorked = max(0, (strtotime($row['time_out']) - strtotime($row['time_in'])) / 60);
                    if (!empty($row['lunch_in']) && !empty($row['lunch_out'])) {
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
