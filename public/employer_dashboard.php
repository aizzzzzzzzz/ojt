<?php
session_start();

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== "employer") {
    header("Location: employer_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';

$employer_id = $_SESSION['employer_id'];

$stmt = $pdo->prepare("SELECT * FROM employers WHERE employer_id = ?");
$stmt->execute([$employer_id]);
$employer = $stmt->fetch(PDO::FETCH_ASSOC);

// Read session success message for verified attendance
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$stmt = $pdo->query("SELECT student_id, username FROM students ORDER BY username");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attendance_stmt = $pdo->prepare("
    SELECT
        s.student_id,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.school,
        a.log_date,
        a.time_in,
        a.lunch_out,
        a.lunch_in,
        a.time_out,
        a.status,
        a.reason,
        a.verified,
        a.daily_task,
        CASE
            WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL THEN
                CONCAT(
                    FLOOR((
                        TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                        - COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)
                    ) / 60), 'h ',
                    MOD((
                        TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                        - COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)
                    ), 60), 'm'
                )
            ELSE '---'
        END AS daily_hours
    FROM students s
    LEFT JOIN attendance a ON s.student_id = a.student_id
    ORDER BY s.last_name, s.first_name, s.middle_name, a.log_date
");

$attendance_stmt->execute();
$attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

$acc_stmt = $pdo->prepare("
    SELECT student_id,
        SUM(
            CASE 
                WHEN time_in IS NOT NULL AND time_out IS NOT NULL AND verified = 1 THEN
                    TIMESTAMPDIFF(MINUTE, time_in, time_out)
                    - COALESCE(TIMESTAMPDIFF(MINUTE, lunch_out, lunch_in), 0)
                ELSE 0
            END
        ) AS total_minutes
    FROM attendance
    GROUP BY student_id
");
$acc_stmt->execute();
$acc_rows = $acc_stmt->fetchAll(PDO::FETCH_ASSOC);

$eval_stmt = $pdo->prepare("
    SELECT DISTINCT student_id 
    FROM evaluations
");
$eval_stmt->execute();
$evaluated_students = array_flip($eval_stmt->fetchAll(PDO::FETCH_COLUMN));

$acc_map = [];
foreach ($acc_rows as $ar) {
    $acc_map[$ar['student_id']] = (int) ($ar['total_minutes'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard</title>
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
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #333;
            line-height: 1.6;
        }

        .dashboard-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 1200px;
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
            margin-top: 30px;
            margin-bottom: 20px;
            font-size: 22px;
            color: #2c3e50;
            text-align: left;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }

        .welcome-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
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

        .actions-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .action-card .icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #007bff;
        }

        .action-card a {
            display: block;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            background: linear-gradient(90deg, #007bff, #00c6ff);
            color: white;
            transition: all 0.3s ease;
        }

        .action-card a:hover {
            background: linear-gradient(90deg, #0056b3, #0099cc);
            transform: translateY(-2px);
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
            background: linear-gradient(90deg, #f8f9fa, #e9ecef);
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
            background: linear-gradient(90deg, #28a745, #85e085);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            background: linear-gradient(90deg, #218838, #6c9e6c);
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(90deg, #ffc107, #ffed4e);
            color: #212529;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-warning:hover {
            background: linear-gradient(90deg, #e0a800, #d39e00);
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
                flex-direction: column;
            }

            .action-card {
                min-width: unset;
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

        <div class="welcome-section">
            <h2>Welcome, <?= htmlspecialchars($employer['username']) ?>!</h2>
            <p>You are logged in as <strong>Employer</strong>.</p>
        </div>

        <h3>Quick Actions</h3>
        <div class="actions-section">
            <div class="action-card">
                <div class="icon">👤</div>
                <a href="add_student.php">Add New Student</a>
            </div>
            <div class="action-card">
                <div class="icon">❌</div>
                <a href="absences.php">Mark Student Absent</a>
            </div>
            <div class="action-card">
                <div class="icon">📋</div>
                <a href="create_project.php">Create Project</a>
            </div>
            <div class="action-card">
                <div class="icon">✅</div>
                <a href="manage_projects.php">Manage Projects</a>
            </div>
            <div class="action-card">
                <div class="icon">🚪</div>
                <a href="logout.php">Logout</a>
            </div>
        </div>

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
                                        ($latest['middle_name'] ? ' ' . $latest['middle_name'] : '')
                                    ) ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
