<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';

require_admin();
$csrf_token = generate_csrf_token();

$stmt = $pdo->prepare("SELECT username, full_name FROM admins WHERE admin_id=?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    session_destroy();
    header("Location: admin_login.php");
    exit;
}

$students_count = $pdo->query("SELECT COUNT(*) AS count FROM students")->fetch(PDO::FETCH_ASSOC)['count'];
$employers = $pdo->query("SELECT employer_id, username, name, company, work_start, work_end FROM employers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$evaluations_count = $pdo->query("SELECT COUNT(*) AS count FROM evaluations")->fetch(PDO::FETCH_ASSOC)['count'];

$filter_student = $_GET['student_id'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$students_stmt = $pdo->query("SELECT student_id, first_name, last_name, course, school FROM students ORDER BY last_name, first_name");
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

$summary_query = "
    SELECT 
        s.student_id,
        s.first_name,
        s.last_name,
        s.course,
        s.school,
        s.email,
        COUNT(a.id) as total_days,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN a.verified = 1 THEN 1 ELSE 0 END) as verified_days,
        COALESCE(SUM(
            CASE 
                WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL 
                AND a.time_in NOT LIKE '%0000%' AND a.time_out NOT LIKE '%0000%'
                THEN CASE 
                    WHEN TIMESTAMPDIFF(MINUTE, 
                        COALESCE(a.effective_start_time, a.time_in), 
                        a.time_out
                    ) > 240 THEN TIMESTAMPDIFF(MINUTE, 
                        COALESCE(a.effective_start_time, a.time_in), 
                        a.time_out
                    ) - 60
                    ELSE TIMESTAMPDIFF(MINUTE, 
                        COALESCE(a.effective_start_time, a.time_in), 
                        a.time_out
                    )
                END
                ELSE 0
            END
        ), 0) as total_minutes
    FROM students s
    LEFT JOIN attendance a ON s.student_id = a.student_id
    WHERE 1=1
";

$params = [];

if ($filter_student) {
    $summary_query .= " AND s.student_id = ?";
    $params[] = $filter_student;
}

if ($filter_date_from) {
    $summary_query .= " AND a.log_date >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $summary_query .= " AND a.log_date <= ?";
    $params[] = $filter_date_to;
}

if ($filter_status) {
    if ($filter_status === 'present') {
        $summary_query .= " AND a.status = 'Present'";
    } elseif ($filter_status === 'absent') {
        $summary_query .= " AND a.status = 'Absent'";
    }
}

if ($search) {
    $summary_query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.course LIKE ? OR s.school LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$summary_query .= "
    GROUP BY s.student_id, s.first_name, s.last_name, s.course, s.school, s.email
    ORDER BY s.last_name, s.first_name
";

$summary_stmt = $pdo->prepare($summary_query);
$summary_stmt->execute($params);
$student_summaries = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);

$attendance_details = [];
$selected_student = null;

if ($filter_student) {
    $selected_student_stmt = $pdo->prepare("
        SELECT student_id, first_name, last_name, course, school, email 
        FROM students 
        WHERE student_id = ?
    ");
    $selected_student_stmt->execute([$filter_student]);
    $selected_student = $selected_student_stmt->fetch(PDO::FETCH_ASSOC);

    $details_query = "
        SELECT 
            a.*,
            e.name as employer_name,
            e.company as employer_company
        FROM attendance a
        LEFT JOIN employers e ON a.employer_id = e.employer_id
        WHERE a.student_id = ?
    ";
    $details_params = [$filter_student];

    if ($filter_date_from) {
        $details_query .= " AND a.log_date >= ?";
        $details_params[] = $filter_date_from;
    }
    if ($filter_date_to) {
        $details_query .= " AND a.log_date <= ?";
        $details_params[] = $filter_date_to;
    }
    if ($filter_status) {
        $details_query .= " AND a.status = ?";
        $details_params[] = $filter_status === 'present' ? 'Present' : 'Absent';
    }

    $details_query .= " ORDER BY a.log_date DESC, a.time_in DESC";

    $details_stmt = $pdo->prepare($details_query);
    $details_stmt->execute($details_params);
    $attendance_details = $details_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$render_admin_overview = false;
include_once __DIR__ . '/../templates/admin_header.php';
?>

<style>
    .filter-section {
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px;
        margin-bottom: 24px;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .filter-group label {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-actions {
        display: flex;
        gap: 10px;
        padding-bottom: 1px;
    }

    .summary-table {
        width: 100%;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        margin-bottom: 24px;
    }

    .summary-table thead th {
        background: var(--surface2) !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.6px !important;
        color: var(--text-muted) !important;
        border-bottom: 1px solid var(--border) !important;
        padding: 11px 14px !important;
        white-space: nowrap;
        text-align: left;
    }

    .summary-table tbody td {
        padding: 12px 14px !important;
        border-bottom: 1px solid var(--border) !important;
        vertical-align: middle !important;
        color: var(--text) !important;
        font-size: 14px;
    }

    .summary-table tbody tr:last-child td { border-bottom: none !important; }
    .summary-table tbody tr:hover td { background: var(--accent-lt) !important; cursor: pointer; }

    .summary-table tbody tr:nth-child(odd) td { background: var(--surface2) !important; }
    .summary-table tbody tr:nth-child(odd):hover td { background: var(--accent-lt) !important; }

    .stats-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        margin-right: 6px;
    }

    .stats-badge.present {
        background: var(--green-lt);
        color: var(--green);
    }

    .stats-badge.absent {
        background: var(--red-lt);
        color: var(--red);
    }

    .stats-badge.verified {
        background: var(--accent-lt);
        color: var(--accent);
    }

    .view-link {
        color: var(--accent);
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .view-link:hover {
        text-decoration: underline;
    }

    .detail-section {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 24px;
        margin-top: 24px;
    }

    .detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border);
    }

    .student-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .student-info h4 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: var(--text);
    }

    .student-info p {
        margin: 0;
        font-size: 13px;
        color: var(--text-muted);
    }

    .attendance-table {
        width: 100%;
        border-collapse: separate !important;
        border-spacing: 0 !important;
    }

    .attendance-table thead th {
        background: var(--surface2) !important;
        font-size: 10px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.6px !important;
        color: var(--text-muted) !important;
        border-bottom: 1px solid var(--border) !important;
        padding: 10px 12px !important;
        white-space: nowrap;
        text-align: left;
    }

    .attendance-table tbody td {
        padding: 10px 12px !important;
        border-bottom: 1px solid var(--border) !important;
        vertical-align: middle !important;
        color: var(--text) !important;
        font-size: 13px;
    }

    .attendance-table tbody tr:last-child td { border-bottom: none !important; }
    .attendance-table tbody tr:hover td { background: var(--accent-lt) !important; }

    .status-badge-small {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 600;
    }

    .status-badge-small.present {
        background: var(--green-lt);
        color: var(--green);
    }

    .status-badge-small.absent {
        background: var(--red-lt);
        color: var(--red);
    }

    .verified-badge-small {
        color: var(--green);
        font-size: 11px;
        font-weight: 600;
    }

    .unverified-badge-small {
        color: var(--text-muted);
        font-size: 11px;
    }

    .shift-status-badge {
        display: inline-block;
        font-size: 11px;
        font-weight: 600;
    }

    .shift-on-time { color: var(--green); }
    .shift-late-grace { color: #ca8a04; }
    .shift-adjusted { color: var(--accent); }

    .no-records {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-muted);
    }

    .back-dashboard-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 18px;
        background: var(--surface2);
        color: var(--text);
        border: 1px solid var(--border);
        border-radius: 9px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.18s ease;
        letter-spacing: 0.1px;
    }

    .back-dashboard-btn:hover {
        background: var(--accent);
        color: #fff;
        border-color: var(--accent);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }

    .back-dashboard-btn svg {
        transition: transform 0.18s ease;
    }

    .back-dashboard-btn:hover svg {
        transform: translateX(-2px);
    }

    .export-btn {
        background: var(--green);
        color: #fff;
        border: none;
        padding: 9px 18px;
        border-radius: 9px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: all 0.18s;
    }

    .export-btn:hover {
        background: #15803d;
        transform: translateY(-1px);
    }

    @media (max-width: 768px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }

        .detail-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

        .summary-table thead, .attendance-table thead {
            display: none;
        }

        .summary-table tbody tr, .attendance-table tbody tr {
            display: block;
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 10px;
            padding: 10px;
        }

        .summary-table tbody td, .attendance-table tbody td {
            display: flex;
            justify-content: space-between;
            border: none !important;
            padding: 6px 8px !important;
        }

        .summary-table tbody td::before, .attendance-table tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            color: var(--text-muted);
            font-size: 11px;
            text-transform: uppercase;
        }
    }
</style>

<div class="dashboard-inner">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border);">
        <div>
            <h3 style="margin: 0 0 4px 0;">📊 Attendance Records</h3>
            <span style="font-size: 13px; color: var(--text-muted);">Overview of student attendance logs and summaries</span>
        </div>
        <div>
            <a href="admin_dashboard.php" class="back-dashboard-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M15 18l-6-6 6-6"/></svg>
                Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" action="admin_attendance_records.php">
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input 
                        type="text" 
                        id="search" 
                        name="search" 
                        class="form-control" 
                        placeholder="Name, course, school..."
                        value="<?= htmlspecialchars($search) ?>"
                    >
                </div>

                <div class="filter-group">
                    <label for="student_id">Student</label>
                    <select id="student_id" name="student_id" class="form-select">
                        <option value="">All Students</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= $student['student_id'] ?>" 
                                <?= $filter_student == $student['student_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date_from">Date From</label>
                    <input 
                        type="date" 
                        id="date_from" 
                        name="date_from" 
                        class="form-control"
                        value="<?= htmlspecialchars($filter_date_from) ?>"
                    >
                </div>

                <div class="filter-group">
                    <label for="date_to">Date To</label>
                    <input 
                        type="date" 
                        id="date_to" 
                        name="date_to" 
                        class="form-control"
                        value="<?= htmlspecialchars($filter_date_to) ?>"
                    >
                </div>

                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="present" <?= $filter_status === 'present' ? 'selected' : '' ?>>Present</option>
                        <option value="absent" <?= $filter_status === 'absent' ? 'selected' : '' ?>>Absent</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        🔍 Filter
                    </button>
                    <a href="admin_attendance_records.php" class="btn btn-secondary">
                        ✕ Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Student Summary Table -->
    <h4 style="margin-bottom: 16px;">Student Attendance Summary</h4>
    
    <?php if (count($student_summaries) > 0): ?>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Student</th>
                <th>Course</th>
                <th>School</th>
                <th>Total Days</th>
                <th>Present</th>
                <th>Absent</th>
                <th>Verified</th>
                <th>Total Hours</th>
                <th>Attendance Rate</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($student_summaries as $summary): ?>
                <?php 
                $total_days = (int)$summary['total_days'];
                $present_days = (int)$summary['present_days'];
                $absent_days = (int)$summary['absent_days'];
                $verified_days = (int)$summary['verified_days'];
                $total_hours = floor((int)$summary['total_minutes'] / 60);
                $total_mins = (int)$summary['total_minutes'] % 60;
                $attendance_rate = $total_days > 0 ? round(($present_days / $total_days) * 100) : 0;
                ?>
                <tr>
                    <td data-label="Student">
                        <strong><?= htmlspecialchars($summary['last_name'] . ', ' . $summary['first_name']) ?></strong>
                        <?php if ($summary['email']): ?>
                            <br><small style="color: var(--text-muted);"><?= htmlspecialchars($summary['email']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td data-label="Course"><?= htmlspecialchars($summary['course']) ?></td>
                    <td data-label="School"><?= htmlspecialchars($summary['school']) ?></td>
                    <td data-label="Total Days"><?= $total_days ?></td>
                    <td data-label="Present">
                        <span class="stats-badge present"><?= $present_days ?></span>
                    </td>
                    <td data-label="Absent">
                        <span class="stats-badge absent"><?= $absent_days ?></span>
                    </td>
                    <td data-label="Verified">
                        <span class="stats-badge verified"><?= $verified_days ?></span>
                    </td>
                    <td data-label="Total Hours"><?= $total_hours ?>h <?= $total_mins ?>m</td>
                    <td data-label="Attendance Rate">
                        <span style="color: <?= $attendance_rate >= 80 ? 'var(--green)' : ($attendance_rate >= 60 ? '#ca8a04' : 'var(--red)') ?>; font-weight: 600;">
                            <?= $attendance_rate ?>%
                        </span>
                    </td>
                    <td data-label="Action">
                        <a href="admin_attendance_records.php?student_id=<?= $summary['student_id'] ?>&<?= http_build_query(array_filter($_GET)) ?>" class="view-link">
                            View Details →
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="no-records">
            <p>No student records found matching your filters.</p>
        </div>
    <?php endif; ?>

    <!-- Detailed Attendance View -->
    <?php if ($selected_student && count($attendance_details) > 0): ?>
    <div class="detail-section">
        <div class="detail-header">
            <div class="student-info">
                <h4>📋 Attendance Details: <?= htmlspecialchars($selected_student['first_name'] . ' ' . $selected_student['last_name']) ?></h4>
                <p><?= htmlspecialchars($selected_student['course']) ?> • <?= htmlspecialchars($selected_student['school']) ?></p>
                <?php if ($selected_student['email']): ?>
                    <p>📧 <?= htmlspecialchars($selected_student['email']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <a href="export_attendance.php?student_id=<?= $selected_student['student_id'] ?>&date_from=<?= $filter_date_from ?>&date_to=<?= $filter_date_to ?>&status=<?= $filter_status ?>" 
                   class="export-btn" 
                   target="_blank">
                    📥 Export to Excel
                </a>
            </div>
        </div>

        <table class="attendance-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Status</th>
                    <th>Shift</th>
                    <th>Verified</th>
                    <th>Hours</th>
                    <th>Task</th>
                    <th>DTR Photo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendance_details as $row): ?>
                    <?php
                    $minutesWorked = 0;
                    $startTime = !empty($row['effective_start_time']) ? $row['effective_start_time'] : $row['time_in'];
                    $hoursWorked = '-';

                    if (!empty($startTime) && !empty($row['time_out']) &&
                        strpos($startTime, '0000') === false && strpos($row['time_out'], '0000') === false) {
                        $minutesWorked = max(0, (strtotime($row['time_out']) - strtotime($startTime)) / 60);
                        if ($minutesWorked > 240) {
                            $minutesWorked -= 60;
                        }
                        $hoursWorked = floor($minutesWorked / 60) . "h " . ($minutesWorked % 60) . "m";
                    }

                    $shift_status = $row['shift_status'] ?? 'on_time';
                    $late_minutes = (int)($row['late_minutes'] ?? 0);
                    $shift_display = '';
                    
                    if ($shift_status === 'on_time') {
                        $shift_display = '<span class="shift-status-badge shift-on-time">🟢 On Time</span>';
                    } elseif ($shift_status === 'late_grace') {
                        $shift_display = '<span class="shift-status-badge shift-late-grace">🟡 Late</span>';
                        if ($late_minutes > 0) {
                            $shift_display .= '<br><small style="color:var(--text-muted)">+' . $late_minutes . 'm</small>';
                        }
                    } elseif ($shift_status === 'adjusted_shift') {
                        $effective = !empty($row['effective_start_time']) ? date('H:i', strtotime($row['effective_start_time'])) : '-';
                        $shift_display = '<span class="shift-status-badge shift-adjusted">🟠 Adj</span><br><small style="color:var(--text-muted)">Start: ' . $effective . '</small>';
                    }
                    ?>
                    <tr>
                        <td data-label="Date"><?= htmlspecialchars($row['log_date']) ?></td>
                        <td data-label="Time In"><?= (strpos($row['time_in'], '0000') === false && !empty($row['time_in'])) ? date('H:i', strtotime($row['time_in'])) : '-' ?></td>
                        <td data-label="Time Out"><?= (strpos($row['time_out'], '0000') === false && !empty($row['time_out'])) ? date('H:i', strtotime($row['time_out'])) : '-' ?></td>
                        <td data-label="Status">
                            <span class="status-badge-small <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span>
                        </td>
                        <td data-label="Shift"><?= $shift_display ?></td>
                        <td data-label="Verified">
                            <?php if ($row['verified'] == 1): ?>
                                <span class="verified-badge-small">✓ Verified</span>
                            <?php else: ?>
                                <span class="unverified-badge-small">⏳ Pending</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Hours"><?= $hoursWorked ?></td>
                        <td data-label="Task">
                            <?php if (!empty($row['daily_task'])): ?>
                                <span title="<?= htmlspecialchars($row['daily_task']) ?>">
                                    <?= htmlspecialchars(substr($row['daily_task'], 0, 30)) ?><?= strlen($row['daily_task']) > 30 ? '...' : '' ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td data-label="DTR Photo">
                            <?php if (!empty($row['dtr_picture'])): ?>
                                <a href="view_dtr.php?id=<?= htmlspecialchars((string)$row['id']) ?>" target="_blank" class="view-link" style="color: #1976d2;">
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
    <?php elseif ($selected_student): ?>
    <div class="detail-section">
        <div class="no-records">
            <p>No attendance records found for this student with the current filters.</p>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
