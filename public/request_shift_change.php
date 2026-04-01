<?php
session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/audit.php';

if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== "student") {
    header("Location: student_login.php");
    exit;
}

$student_id = (int)$_SESSION['student_id'];
$success = "";
$error = "";

$schedule_stmt = $pdo->prepare("
    SELECT e.work_start, e.work_end, e.employer_id
    FROM students s
    LEFT JOIN employers e ON s.created_by = e.employer_id
    WHERE s.student_id = ?
    LIMIT 1
");
$schedule_stmt->execute([$student_id]);
$current_schedule = $schedule_stmt->fetch(PDO::FETCH_ASSOC);

// Fallback to company schedule
if (!$current_schedule || empty($current_schedule['work_start'])) {
    $schedule_stmt = $pdo->prepare("
        SELECT e.work_start, e.work_end, e.employer_id
        FROM students s
        LEFT JOIN employers e ON s.company_id = e.company_id
        WHERE s.student_id = ?
        LIMIT 1
    ");
    $schedule_stmt->execute([$student_id]);
    $current_schedule = $schedule_stmt->fetch(PDO::FETCH_ASSOC);
}

$default_start = $current_schedule['work_start'] ?? '08:00';
$default_end = $current_schedule['work_end'] ?? '17:00';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_date = $_POST['request_date'] ?? '';
    $shift_start = $_POST['shift_start'] ?? '';
    $shift_end = $_POST['shift_end'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $request_type = $_POST['request_type'] ?? 'one_time'; // one_time, recurring
    
    if (empty($request_date) || empty($shift_start) || empty($shift_end)) {
        $error = "Please fill in all required fields.";
    } elseif (strtotime($shift_start) >= strtotime($shift_end)) {
        $error = "Shift start time must be before shift end time.";
    } elseif (empty($reason)) {
        $error = "Please provide a reason for the shift change request.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO shift_change_requests 
                (student_id, request_date, requested_shift_start, requested_shift_end, reason, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $student_id,
                $request_date,
                $shift_start,
                $shift_end,
                $reason
            ]);
            
            $request_id = $pdo->lastInsertId();
            
            // Log activity
            log_activity('Shift Change Request', "Requested shift change for $request_date: $shift_start - $shift_end");
            
            $success = "Shift change request submitted successfully! Your supervisor will review it.";
            
            // Clear form
            $_POST = array();
            
        } catch (PDOException $e) {
            error_log("Shift request error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $error = "Failed to submit request. Error: " . $e->getMessage();
        }
    }
}

// Get student's pending requests
$requests_stmt = $pdo->prepare("
    SELECT * FROM shift_change_requests 
    WHERE student_id = ? 
    ORDER BY requested_at DESC
    LIMIT 10
");
$requests_stmt->execute([$student_id]);
$my_requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Shift Change</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--amber:#d97706;--amber-lt:#fef3c7;--radius:14px;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
        *,*::before,*::after{box-sizing:border-box;}
        body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:32px 20px 60px;}
        .page-card{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);width:100%;max-width:800px;margin:0 auto;overflow:hidden;}
        .page-topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;}
        .page-topbar h2{font-size:18px;font-weight:700;margin:0;}
        .page-topbar p{font-size:13px;color:var(--text-muted);margin:2px 0 0;}
        .page-inner{padding:24px 28px 32px;}
        .success-msg{background:var(--green-lt);color:#15803d;padding:12px 16px;border-radius:10px;border:1px solid #bbf7d0;font-size:14px;font-weight:500;margin-bottom:16px;}
        .error-msg{background:var(--red-lt);color:#b91c1c;padding:12px 16px;border-radius:10px;border:1px solid #fecaca;font-size:14px;font-weight:500;margin-bottom:16px;}
        .info-box{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:20px;}
        .form-label{font-size:13px;font-weight:600;color:var(--text);margin-bottom:5px;display:block;}
        .form-control,select,input[type=date],input[type=time],textarea{border-radius:9px;border:1px solid var(--border);padding:9px 12px;font-size:14px;font-family:inherit;color:var(--text);background:var(--surface);transition:border-color .2s,box-shadow .2s;width:100%;}
        input:focus,textarea:focus,select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.12);outline:none;}
        .btn{font-family:inherit;font-size:13px;font-weight:600;border-radius:9px;padding:9px 18px;transition:all .18s;cursor:pointer;display:inline-flex;align-items:center;gap:6px;border:none;text-decoration:none;}
        .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);color:#fff;}
        .btn-outline-secondary{background:transparent;color:var(--text-muted);border:1.5px solid var(--border);border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;text-decoration:none;}.btn-outline-secondary:hover{background:var(--surface2);color:var(--text);}
        .mb-3{margin-bottom:16px;}
        .request-card{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:12px;}
        .status-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
        .status-pending{background:var(--amber-lt);color:var(--amber);}
        .status-approved{background:var(--green-lt);color:var(--green);}
        .status-rejected{background:var(--red-lt);color:var(--red);}
        .page-card{max-width:700px;}
    </style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2>Request Shift Change</h2>
            <p>Submit a request to adjust your work schedule</p>
        </div>
        <a href="student_dashboard.php" class="btn btn-outline-secondary">← Back to Dashboard</a>
    </div>
    
    <div class="page-inner">
        <?php if ($success): ?>
            <div class="success-msg">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-msg">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <strong>📋 Your Current Schedule:</strong><br>
            <span style="color:var(--text-muted);font-size:13px;">
                Work Hours: <?= date('g:i A', strtotime($default_start)) ?> - <?= date('g:i A', strtotime($default_end)) ?>
            </span>
        </div>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Request Type</label>
                <select name="request_type" class="form-control" required>
                    <option value="one_time">One-Time Change (Specific Date)</option>
                    <option value="recurring">Recurring (Weekly)</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Date <span style="color:var(--text-muted);font-weight:400;">(required)</span></label>
                <input type="date" name="request_date" class="form-control" required min="<?= date('Y-m-d') ?>">
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="mb-3">
                    <label class="form-label">New Start Time <span style="color:var(--text-muted);font-weight:400;">(required)</span></label>
                    <input type="time" name="shift_start" class="form-control" required value="<?= htmlspecialchars($default_start) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">New End Time <span style="color:var(--text-muted);font-weight:400;">(required)</span></label>
                    <input type="time" name="shift_end" class="form-control" required value="<?= htmlspecialchars($default_end) ?>">
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Reason <span style="color:var(--text-muted);font-weight:400;">(required)</span></label>
                <textarea name="reason" class="form-control" rows="4" placeholder="Explain why you need this shift change (e.g., 'Have morning classes on Wednesdays', 'Transportation issues', etc.)" required></textarea>
            </div>
            
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">📤 Submit Request</button>
                <a href="student_dashboard.php" class="btn btn-outline-secondary" style="flex:1;justify-content:center;">Cancel</a>
            </div>
        </form>
        
        <?php if (!empty($my_requests)): ?>
            <h3 style="margin-top:32px;margin-bottom:16px;font-size:15px;">Your Recent Requests</h3>
            
            <?php foreach($my_requests as $req): ?>
                <div class="request-card">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
                        <div>
                            <strong>Date: <?= date('M d, Y', strtotime($req['request_date'])) ?></strong><br>
                            <span style="font-size:13px;color:var(--text-muted);">
                                <?= date('g:i A', strtotime($req['requested_shift_start'])) ?> - <?= date('g:i A', strtotime($req['requested_shift_end'])) ?>
                            </span>
                        </div>
                        <span class="status-badge status-<?= $req['status'] ?>">
                            <?= ucfirst($req['status']) ?>
                        </span>
                    </div>
                    <div style="font-size:13px;color:var(--text-muted);">
                        <strong>Reason:</strong> <?= htmlspecialchars($req['reason']) ?>
                    </div>
                    <?php if (!empty($req['review_notes'])): ?>
                        <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border);font-size:12px;color:var(--text-muted);">
                            <strong>Supervisor Note:</strong> <?= htmlspecialchars($req['review_notes']) ?>
                        </div>
                    <?php endif; ?>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:8px;">
                        Requested: <?= date('M d, Y h:i A', strtotime($req['requested_at'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
