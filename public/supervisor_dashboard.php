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
$late_cutoff = '10:00';
$eod_cutoff = '17:00';
$late_cutoff_dt = new DateTime($today . ' ' . $late_cutoff, $timezone);
$eod_cutoff_dt = new DateTime($today . ' ' . $eod_cutoff, $timezone);

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
            if (row) {
                row.classList.toggle('show');
                console.log('Toggled details' + index, row.classList);
            } else {
                console.error('Row not found: details' + index);
            }
        }
    </script>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #eef5ff, #d8e9ff);
            color: #333;
            line-height: 1.6;
        }

        .dashboard-container {
            background: #ffffff;
            padding: 28px;
            border-radius: 16px;
            border: 1px solid #e5edf7;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            width: 100%;
            max-width: 1150px;
            margin: 20px auto;
            text-align: center;
        }

        .dashboard-container h2 {
            margin-bottom: 10px;
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
        }

        .dashboard-container h3 {
            margin-top: 26px;
            margin-bottom: 16px;
            font-size: 20px;
            color: #2c3e50;
            text-align: left;
            border-bottom: 1px solid #e6edf5;
            padding-bottom: 8px;
        }

        .welcome-section {
            margin-bottom: 30px;
            padding: 18px 22px;
            background: #f7fafc;
            border-radius: 12px;
            border: 1px solid #e6edf5;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
        }

        .welcome-section p {
            font-size: 16px;
            color: #666;
            margin: 5px 0;
        }

        .success-msg {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            text-align: left;
            font-weight: 500;
        }
        .error-msg {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            text-align: left;
            font-weight: 500;
        }

        .actions-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .action-card {
            background: #ffffff;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #e6edf5;
            text-align: center;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.12);
        }

        .action-card .icon {
            font-size: 2.2rem;
            margin-bottom: 12px;
            color: #007bff;
        }

        .action-card a {
            display: block;
            padding: 10px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            background: linear-gradient(90deg, #1d4ed8, #3b82f6);
            color: white;
            transition: all 0.3s ease;
        }

        .action-card a:hover {
            background: linear-gradient(90deg, #1e40af, #2563eb);
            transform: translateY(-2px);
        }

        .attendance-actions {
            background: #ffffff;
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid #e6edf5;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
        }

        .attendance-actions h4 {
            margin-bottom: 6px;
            color: #2c3e50;
        }
        .section-subtitle {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 14px;
        }
        .branding-grid {
            display: grid;
            grid-template-columns: minmax(160px, 220px) 1fr;
            gap: 18px;
            align-items: center;
        }
        .branding-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .branding-preview img {
            max-width: 180px;
            max-height: 120px;
            border: 1px solid #e6edf5;
            padding: 10px;
            background: #ffffff;
            border-radius: 10px;
        }
        .branding-form .form-label {
            font-weight: 600;
            color: #334155;
        }
        .branding-form .btn {
            padding: 8px 16px;
            border-radius: 8px;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 6px;
        }
        .status-late {
            background: #fff4e5;
            color: #b45309;
            border: 1px solid #f5d0a9;
        }
        .status-pending {
            background: #eef2ff;
            color: #4338ca;
            border: 1px solid #c7d2fe;
        }

        .table-section {
            overflow-x: auto;
            margin-top: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }

        th, td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }

        th {
            background: #f1f5f9;
            font-weight: 600;
            color: #2c3e50;
            position: sticky;
            top: 0;
        }

        tr:nth-child(even) {
            background: #f8f9fa;
        }

        tr:hover {
            background: #e3f2fd;
            transition: background 0.3s ease;
        }

        table a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        table a:hover {
            background: #e3f2fd;
        }

        .btn-success {
            background: linear-gradient(90deg, #16a34a, #22c55e);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            background: linear-gradient(90deg, #15803d, #16a34a);
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
            color: #212529;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-warning:hover {
            background: linear-gradient(90deg, #d97706, #f59e0b);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(90deg, #dc2626, #ef4444);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            background: linear-gradient(90deg, #b91c1c, #dc2626);
            transform: translateY(-2px);
        }

        .btn-outline-secondary {
            background: transparent;
            color: #6c757d;
            border: 1px solid #6c757d;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-2px);
        }

        .details-row {
            display: none !important;
        }

        .details-row.show {
            display: table-row !important;
        }

        .details-content {
            background: #f5f7fa;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            text-align: left;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 20px;
                margin: 10px;
            }

            .dashboard-container h2 {
                font-size: 24px;
            }

            .dashboard-container h3 {
                font-size: 18px;
            }

            .actions-section {
                grid-template-columns: 1fr;
            }

            .action-card {
                min-width: unset;
            }

            .branding-grid {
                grid-template-columns: 1fr;
            }
            .branding-preview {
                align-items: center;
            }
            table, thead, tbody, th, td, tr {
                display: block;
                width: 100%;
            }

            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }

            tr {
                border: 1px solid #ddd;
                margin-bottom: 15px;
                border-radius: 8px;
                padding: 10px;
                background: white;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: right;
                margin-bottom: 10px;
            }

            td::before {
                content: attr(data-label) ": ";
                position: absolute;
                left: 10px;
                width: 45%;
                font-weight: bold;
                text-align: left;
                color: #2c3e50;
            }

            .welcome-section, .action-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php if (!empty($success_message)): ?>
            <div class="success-msg"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="error-msg"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="welcome-section">
            <h2>Welcome, <?= htmlspecialchars($employer['name']) ?>!</h2>
            <p>You are logged in as <strong>OJT Supervisor</strong>.</p>
        </div>

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
            <div class="action-card">
                <div class="icon">🚪</div>
                <a href="logout.php">Logout</a>
            </div>
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
        
        <div class="attendance-actions">
            <h4>Attendance Signals</h4>
            <div class="section-subtitle">
                Late risk: no time-in by <?= htmlspecialchars($late_cutoff) ?>. Pending verification: time-out recorded but not verified after <?= htmlspecialchars($eod_cutoff) ?>.
            </div>
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
    <script>
        let lastAttendanceCheck = null;
        let lastUpdatesCheck = null;
        const POLL_INTERVAL = 10000;
        
        function openCertificateLink(url) {
            if (window.top !== window.self) {
                try {
                    window.top.location.href = url;
                } catch (err) {
                    window.open(url, '_blank', 'noopener');
                }
                return false;
            }
            return true;
        }

        async function checkAttendanceUpdates() {
            try {
                const url = 'api/check_attendance.php?since=' + encodeURIComponent(lastAttendanceCheck || '');
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success && data.latest_timestamp) {
                    if (lastAttendanceCheck && data.latest_timestamp > lastAttendanceCheck) {
                        showNotification('New attendance data detected! Refreshing...', 'info');
                        setTimeout(() => location.reload(), 1500);
                    }
                    lastAttendanceCheck = data.latest_timestamp;
                }
            } catch (err) {
                console.error('Error checking attendance updates:', err);
            }
        }
        
        async function checkDataUpdates() {
            try {
                const url = 'api/check_updates.php?since=' + encodeURIComponent(lastUpdatesCheck || '');
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    let hasUpdates = false;
                    let updateMessage = '';

                    if (data.projects && data.projects.has_updates) {
                        hasUpdates = true;
                        updateMessage = 'New project submission!';
                    }
                    
                    if (hasUpdates) {
                        showNotification(updateMessage + ' Refreshing...', 'info');
                        setTimeout(() => location.reload(), 1500);
                    }
                    
                    const projTime = data.projects?.latest_timestamp;

                    if (projTime) {
                        lastUpdatesCheck = projTime;
                    }
                }
            } catch (err) {
                console.error('Error checking data updates:', err);
            }
        }
        
        function showNotification(message, type) {
            const existing = document.querySelector('.polling-notification');
            if (existing) existing.remove();
            
            const notification = document.createElement('div');
            notification.className = 'alert alert-' + type + ' alert-dismissible fade show polling-notification';
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '300px';
            notification.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            
            document.body.appendChild(notification);
            
            setTimeout(function() {
                notification.remove();
            }, 5000);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            checkAttendanceUpdates();
            checkDataUpdates();
            
            setInterval(checkAttendanceUpdates, POLL_INTERVAL);
            setInterval(checkDataUpdates, POLL_INTERVAL);
        });
    </script>
</body>
</html>