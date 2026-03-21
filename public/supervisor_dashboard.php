<?php
session_start();
include_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';

include_once __DIR__ . '/../includes/supervisor_auth.php';
include_once __DIR__ . '/../includes/supervisor_db.php';
include_once __DIR__ . '/../includes/supervisor_attendance.php';

$employer_id = authenticate_supervisor();
$employer = get_supervisor_info($pdo, $employer_id);

$csrf_token = generate_csrf_token();

$timezone = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $timezone);
$today = $now->format('Y-m-d');

// Dynamic cutoffs based on employer's work schedule
$work_start_raw = $employer['work_start'] ?? '08:00:00';
$work_end_raw   = $employer['work_end']   ?? '17:00:00';

$late_grace_minutes = (int)($employer['late_grace_minutes'] ?? 10);
$eod_grace_hours    = (int)($employer['eod_grace_hours']    ?? 3);
// Clamp: late grace 1–30 min, eod grace 1–6 hours
$late_grace_minutes = max(1, min(30, $late_grace_minutes));
$eod_grace_hours    = max(1, min(6,  $eod_grace_hours));

$work_start_dt  = new DateTime($today . ' ' . $work_start_raw, $timezone);
$late_cutoff_dt = clone $work_start_dt;
$late_cutoff_dt->modify("+{$late_grace_minutes} minutes");
$eod_cutoff_dt  = new DateTime($today . ' ' . $work_end_raw, $timezone);
$eod_cutoff_dt->modify("+{$eod_grace_hours} hours");

// Human-readable versions for display
$late_cutoff = $late_cutoff_dt->format('H:i');
$eod_cutoff  = $eod_cutoff_dt->format('H:i');

$company_logo_root = !empty($employer['company_id'])
    ? 'company_logo_company_' . $employer['company_id']
    : 'company_logo_employer_' . $employer_id;
$company_logo_exts = ['png', 'jpg', 'jpeg'];
$company_logo_relative = '';
$company_logo_full_path = '';
foreach ($company_logo_exts as $ext) {
    $candidate = 'assets/' . $company_logo_root . '.' . $ext;
    $candidate_full = __DIR__ . '/' . $candidate;
    if (file_exists($candidate_full)) {
        $company_logo_relative = $candidate;
        $company_logo_full_path = $candidate_full;
        break;
    }
}
$company_logo_exists = !empty($company_logo_full_path);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grace_periods'])) {
    check_csrf($_POST['csrf_token'] ?? '');
    $lg = max(1, min(30, (int)($_POST['late_grace_minutes'] ?? 10)));
    $eg = max(1, min(6,  (int)($_POST['eod_grace_hours']   ?? 3)));
    $pdo->prepare("UPDATE employers SET late_grace_minutes = ?, eod_grace_hours = ? WHERE employer_id = ?")
        ->execute([$lg, $eg, $employer_id]);
    $_SESSION['success_message'] = 'Grace period settings saved.';
    header("Location: supervisor_dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_company_logo'])) {
    check_csrf($_POST['csrf_token'] ?? '');

    $upload_error = '';
    if (empty($_FILES['company_logo']['tmp_name'])) {
        $upload_error = 'Please choose a logo image to upload.';
    } elseif ($_FILES['company_logo']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = 'File upload error: ' . $_FILES['company_logo']['error'];
    } elseif ($_FILES['company_logo']['size'] > (2 * 1024 * 1024)) {
        $upload_error = 'Logo is too large. Maximum size is 2 MB.';
    } else {
        $imgInfo = getimagesize($_FILES['company_logo']['tmp_name']);
        if (!$imgInfo) {
            $upload_error = 'Invalid image file.';
        } else {
            if (!is_dir(__DIR__ . '/assets')) {
                mkdir(__DIR__ . '/assets', 0777, true);
            }
            $ext = '';
            if ($imgInfo['mime'] === 'image/png') {
                $ext = 'png';
            } elseif ($imgInfo['mime'] === 'image/jpeg') {
                $ext = 'jpg';
            } else {
                $upload_error = 'Unsupported image format. Supported formats: PNG, JPG.';
            }

            if (empty($upload_error)) {
                $company_logo_relative = 'assets/' . $company_logo_root . '.' . $ext;
                $company_logo_full_path = __DIR__ . '/' . $company_logo_relative;
                if (!move_uploaded_file($_FILES['company_logo']['tmp_name'], $company_logo_full_path)) {
                    $upload_error = 'Failed to save logo image.';
                } else {
                    foreach ($company_logo_exts as $cleanup_ext) {
                        if ($cleanup_ext === $ext) {
                            continue;
                        }
                        $cleanup_path = __DIR__ . '/assets/' . $company_logo_root . '.' . $cleanup_ext;
                        if (file_exists($cleanup_path)) {
                            unlink($cleanup_path);
                        }
                    }
                }
            }
        }
    }

    if (!empty($upload_error)) {
        $_SESSION['error_message'] = $upload_error;
    } else {
        $_SESSION['success_message'] = 'Company logo updated successfully.';
    }
    header("Location: supervisor_dashboard.php");
    exit;
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$students = get_students_list($pdo, $_SESSION['employer_id'] ?? null);
$attendance = get_attendance_records($pdo);
$acc_map = get_total_minutes($pdo);
$evaluated_students = get_evaluated_students($pdo);
$evaluation_pass_map = [];
$certificate_map = [];

$student_ids = [];
foreach ($students as $student_row) {
    $student_ids[] = (int) $student_row['student_id'];
}

if (!empty($student_ids)) {
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    $eval_result_stmt = $pdo->prepare("
        SELECT
            student_id,
            (
                COALESCE(attendance_rating, 0) +
                COALESCE(work_quality_rating, 0) +
                COALESCE(initiative_rating, 0) +
                COALESCE(communication_rating, 0) +
                COALESCE(teamwork_rating, 0) +
                COALESCE(adaptability_rating, 0) +
                COALESCE(professionalism_rating, 0) +
                COALESCE(problem_solving_rating, 0) +
                COALESCE(technical_skills_rating, 0)
            ) / 9.0 AS avg_rating
        FROM evaluations
        WHERE student_id IN ($placeholders)
    ");
    $eval_result_stmt->execute($student_ids);

    while ($eval_row = $eval_result_stmt->fetch(PDO::FETCH_ASSOC)) {
        $evaluation_pass_map[(int) $eval_row['student_id']] = ((float) $eval_row['avg_rating']) >= 3.0;
    }

    $certificate_stmt = $pdo->prepare("
        SELECT student_id, certificate_no, generated_at
        FROM certificates
        WHERE student_id IN ($placeholders)
        ORDER BY generated_at DESC
    ");
    $certificate_stmt->execute($student_ids);

    while ($certificate_row = $certificate_stmt->fetch(PDO::FETCH_ASSOC)) {
        $certificate_student_id = (int) $certificate_row['student_id'];
        if (!isset($certificate_map[$certificate_student_id])) {
            $certificate_map[$certificate_student_id] = $certificate_row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OJT Supervisor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function toggleDetails(index) {
            const row = document.getElementById('details' + index);
            if (!row) return;
            const isShowing = row.classList.contains('show');
            document.querySelectorAll('.details-row.show').forEach(r => r.classList.remove('show'));
            if (!isShowing) row.classList.add('show');
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:         #f1f4f9;
            --surface:    #ffffff;
            --surface2:   #f8fafc;
            --border:     #e3e8f0;
            --text:       #111827;
            --text-muted: #6b7280;
            --accent:     #4361ee;
            --accent-dk:  #3451d1;
            --accent-lt:  #eef1fd;
            --green:      #16a34a;
            --green-lt:   #dcfce7;
            --red:        #dc2626;
            --red-lt:     #fee2e2;
            --amber:      #d97706;
            --amber-lt:   #fef3c7;
            --radius:     14px;
            --shadow-sm:  0 1px 2px rgba(0,0,0,0.05);
            --shadow:     0 1px 4px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.06);
            --shadow-md:  0 2px 8px rgba(0,0,0,0.07), 0 8px 28px rgba(0,0,0,0.07);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            padding: 28px 20px 60px;
            margin: 0;
        }

        .dashboard-container {
            background: var(--surface);
            border-radius: 20px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-md);
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            padding: 0;
            overflow: hidden;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 32px;
            border-bottom: 1px solid var(--border);
            gap: 16px;
            flex-wrap: wrap;
        }

        .topbar-left h2 { font-size: 20px; font-weight: 700; color: var(--text); margin: 0; letter-spacing: -0.3px; }
        .topbar-left p  { font-size: 13px; color: var(--text-muted); margin: 2px 0 0; }

        .topbar-right { display: flex; align-items: center; gap: 10px; }

        .badge-role {
            background: var(--accent-lt);
            color: var(--accent);
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--red);
            color: #fff;
            padding: 8px 16px;
            border-radius: 9px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: background 0.2s, transform 0.15s;
        }

        .logout-btn:hover { background: #b91c1c; color: #fff; transform: translateY(-1px); }

        .dashboard-inner { padding: 28px 32px 36px; }

        .success-msg { background: var(--green-lt); color: #15803d; padding: 12px 16px; border-radius: 10px; border: 1px solid #bbf7d0; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
        .error-msg   { background: var(--red-lt);   color: #b91c1c; padding: 12px 16px; border-radius: 10px; border: 1px solid #fecaca; font-size: 14px; font-weight: 500; margin-bottom: 16px; }

        .dashboard-inner h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            margin: 28px 0 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            text-align: left;
        }

        .dashboard-inner h3:first-child { margin-top: 0; }

        /* Quick Actions */
        .actions-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 28px;
        }

        .action-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            padding: 18px 14px;
            border-radius: var(--radius);
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .action-card:hover { transform: translateY(-3px); box-shadow: var(--shadow); }

        .action-card .icon { font-size: 1.8rem; margin-bottom: 10px; display: block; }

        .action-card a {
            display: block;
            padding: 9px 12px;
            border-radius: 9px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            background: var(--accent);
            color: white;
            transition: background 0.2s, transform 0.15s;
        }

        .action-card a:hover { background: var(--accent-dk); transform: translateY(-1px); }

        /* Attendance / branding card */
        .attendance-actions {
            background: var(--surface2);
            border: 1px solid var(--border);
            padding: 18px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
        }

        .attendance-actions h4 { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 4px; }

        .section-subtitle { color: var(--text-muted); font-size: 13px; margin-bottom: 14px; }

        .branding-grid { display: grid; grid-template-columns: minmax(160px, 200px) 1fr; gap: 18px; align-items: center; }
        .branding-preview { display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .branding-preview img { max-width: 180px; max-height: 120px; border: 1px solid var(--border); padding: 10px; background: var(--surface); border-radius: 10px; }

        .status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 4px; }
        .status-late    { background: var(--amber-lt); color: var(--amber); border: 1px solid #fde68a; }
        .status-pending { background: var(--accent-lt); color: var(--accent); border: 1px solid #c7d2fe; }

        /* Table */
        .table-section { overflow-x: auto; margin-top: 16px; border-radius: var(--radius); border: 1px solid var(--border); }

        table { width: 100%; border-collapse: collapse; background: var(--surface); }

        th {
            background: var(--surface2);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            padding: 11px 14px;
            text-align: center;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
        }

        td { padding: 12px 14px; text-align: center; border-bottom: 1px solid var(--border); font-size: 14px; color: var(--text); }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--accent-lt); transition: background 0.15s; }

        /* Buttons */
        .btn, button.btn { font-family: inherit; font-size: 13px; font-weight: 600; border-radius: 9px; padding: 7px 14px; transition: all 0.18s; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; border: none; }

        .btn-primary    { background: var(--accent) !important; color: #fff !important; border: none !important; }
        .btn-primary:hover  { background: var(--accent-dk) !important; transform: translateY(-1px) !important; }

        .btn-success    { background: var(--green) !important; color: #fff !important; border: none !important; }
        .btn-success:hover  { background: #15803d !important; transform: translateY(-1px) !important; }

        .btn-warning    { background: #f59e0b !important; color: #fff !important; border: none !important; }
        .btn-warning:hover  { background: var(--amber) !important; transform: translateY(-1px) !important; }

        .btn-danger     { background: var(--red) !important; color: #fff !important; border: none !important; }
        .btn-danger:hover   { background: #b91c1c !important; transform: translateY(-1px) !important; }

        .btn-outline-secondary { background: transparent !important; color: var(--text-muted) !important; border: 1.5px solid var(--border) !important; }
        .btn-outline-secondary:hover { background: var(--surface2) !important; color: var(--text) !important; transform: translateY(-1px) !important; }

        /* Detail rows */
        .details-row { display: none !important; }
        .details-row.show { display: table-row !important; }

        .details-content {
            background: var(--surface2);
            padding: 20px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            text-align: left;
        }

        /* Form controls */
        .form-control, .form-select {
            border-radius: 9px !important;
            border: 1px solid var(--border) !important;
            padding: 9px 12px !important;
            font-size: 14px !important;
            font-family: inherit !important;
            color: var(--text) !important;
            transition: border-color 0.2s, box-shadow 0.2s !important;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent) !important;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.12) !important;
            outline: none !important;
        }

        @media (max-width: 768px) {
            body { padding: 12px 12px 40px; }
            .dashboard-container { border-radius: 14px; }
            .topbar, .dashboard-inner { padding: 16px 18px; }
            .actions-section { grid-template-columns: 1fr 1fr; }
            .branding-grid { grid-template-columns: 1fr; }
            table, thead, tbody, th, td, tr { display: block; width: 100%; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { border: 1px solid var(--border); margin-bottom: 10px; border-radius: 10px; padding: 10px; }
            td { border: none; position: relative; padding-left: 50%; text-align: right; margin-bottom: 8px; }
            td::before { content: attr(data-label); position: absolute; left: 10px; width: 45%; font-weight: 600; text-align: left; color: var(--text-muted); font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="topbar">
            <div class="topbar-left">
                <h2>Welcome, <?= htmlspecialchars($employer['name']) ?>!</h2>
                <p>OJT Supervisor Dashboard</p>
            </div>
            <div class="topbar-right">
                <span class="badge-role">Supervisor</span>
                <a href="logout.php" class="logout-btn">⎋ Logout</a>
            </div>
        </div>

        <div class="dashboard-inner">

        <?php if (!empty($success_message)): ?>
            <div class="success-msg"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="error-msg"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <h3>Quick Actions</h3>
        <div class="actions-section">
            <div class="action-card">
                <div class="icon">👤</div>
                <a href="add_student.php">Add New Student</a>
            </div>
            <div class="action-card">
                <div class="icon">✅</div>
                <a href="manage_projects.php">Manage Projects</a>
            </div>
            <div class="action-card">
                <div class="icon">📁</div>
                <a href="upload_documents.php">Upload Documents</a>
            </div>
        </div>

        <h3>Attendance Settings</h3>
        <div class="attendance-actions" style="margin-bottom:20px;">
            <h4>Grace Period Configuration</h4>
            <div class="section-subtitle">Controls when students are flagged as late or pending verification.</div>
            <form method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;max-width:480px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--text);display:block;margin-bottom:5px;">
                        Late Grace Period
                        <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(1–30 min)</span>
                    </label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="number" name="late_grace_minutes"
                            value="<?= htmlspecialchars($late_grace_minutes) ?>"
                            min="1" max="30" step="1"
                            style="width:80px;border-radius:9px;border:1px solid var(--border);padding:8px 10px;font-size:14px;font-family:inherit;">
                        <span style="font-size:13px;color:var(--text-muted);">minutes</span>
                    </div>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--text);display:block;margin-bottom:5px;">
                        EOD Verification Window
                        <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(1–6 hrs)</span>
                    </label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="number" name="eod_grace_hours"
                            value="<?= htmlspecialchars($eod_grace_hours) ?>"
                            min="1" max="6" step="1"
                            style="width:80px;border-radius:9px;border:1px solid var(--border);padding:8px 10px;font-size:14px;font-family:inherit;">
                        <span style="font-size:13px;color:var(--text-muted);">hours after work end</span>
                    </div>
                </div>
                <div style="grid-column:span 2;margin-top:4px;">
                    <button type="submit" name="save_grace_periods" class="btn btn-primary btn-sm">Save Settings</button>
                </div>
            </form>
        </div>

        <?php if (!$company_logo_exists): ?>
            <h3>Company Branding</h3>
            <div class="attendance-actions">
                <h4>Certificate Logo</h4>
                <div class="section-subtitle">Shown on the top-right of certificates.</div>
                <div class="branding-grid">
                    <div class="branding-preview">
                        <div class="text-muted">No company logo uploaded yet.</div>
                    </div>
                    <div class="branding-form">
                        <form method="post" enctype="multipart/form-data" class="row g-2">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <div class="col-12">
                                <label for="company_logo" class="form-label">Upload Logo (PNG/JPG, max 2MB)</label>
                                <input type="file" class="form-control" id="company_logo" name="company_logo" accept="image/png,image/jpeg" required>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="upload_company_logo" class="btn btn-primary">Upload Company Logo</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <h3>Attendance Records</h3>

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
                        $required_hours = $latest['required_hours'] ?? 0;
                        $required_minutes = 0;
                        $eligible_for_eval = $acc_minutes >= $required_minutes;
                        $completed_hours = $acc_minutes >= $required_minutes;
                        $already_evaluated = isset($evaluated_students[$student_id]);
                        $evaluation_passed = $evaluation_pass_map[$student_id] ?? false;
                        $existing_cert = $certificate_map[$student_id] ?? null;
                        $has_certificate = $existing_cert !== null;
                        $acc_display = floor($acc_minutes / 60) . "h " . ($acc_minutes % 60) . "m";

                        $status = $latest['status'] ?: '---';
                        $latest_date = $latest['log_date'] ? date('Y-m-d', strtotime($latest['log_date'])) : null;
                        $is_today = $latest_date === $today;
                        $has_time_in = !empty($latest['time_in']);
                        $has_time_out = !empty($latest['time_out']);
                        $is_verified = (int)($latest['verified'] ?? 0) === 1;
                        $late_risk = $is_today && !$has_time_in && $now >= $late_cutoff_dt;
                        $pending_verification = !$is_verified && $has_time_out && (
                            ($latest_date && $latest_date < $today) || ($is_today && $now >= $eod_cutoff_dt)
                        );

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
                            <?php if ($late_risk): ?>
                                <div class="status-pill status-late">Late risk</div>
                            <?php endif; ?>
                            <?php if ($pending_verification): ?>
                                <div class="status-pill status-pending">Pending verification</div>
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
                            <?php elseif ($already_evaluated && $completed_hours): ?>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <span style="color:green; font-weight:bold;">✔ Evaluation Completed</span>
                                    <?php if ($evaluation_passed && !$has_certificate): ?>
                                        <a href="generate_certificate.php?student_id=<?= $student_id ?>" class="btn btn-success btn-sm" target="_top" rel="noopener" onclick="return openCertificateLink(this.href);">
                                            📄 Generate Certificate
                                        </a>
                                    <?php elseif ($evaluation_passed && $has_certificate): ?>
                                        <span style="color:green; font-weight:bold;">✓ Certificate Generated</span>
                                    <?php else: ?>
                                        <span style="color:#dc3545; font-weight:bold;">Evaluation Failed</span>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($already_evaluated): ?>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <span style="color:green; font-weight:bold;">✔ Evaluation Completed</span>
                                </div>
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
                                        <?php if ($evaluation_passed && !$existing_cert): ?>
                                            <a href="generate_certificate.php?student_id=<?= $student_id ?>" class="btn btn-success btn-sm" target="_top" rel="noopener" onclick="return openCertificateLink(this.href);">📄 Generate Certificate</a>
                                        <?php elseif (!$evaluation_passed): ?>
                                            <span style="color:#dc3545; font-weight:bold;">Evaluation Failed</span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($completed_hours): ?>
                                        <form method="POST" action="delete_student.php" style="margin-top: 10px;" onsubmit="return confirm('Are you sure you want to delete this student? This action cannot be undone.');">
                                            <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">🗑️ Delete Student</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <?php $index++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
        </div><!-- /.dashboard-inner -->
    </div><!-- /.dashboard-container -->
</body>
</html>