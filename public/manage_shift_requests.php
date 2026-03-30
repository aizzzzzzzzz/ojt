<?php
session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/audit.php';

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== "employer") {
    header("Location: supervisor_login.php");
    exit;
}

$employer_id = (int)$_SESSION['employer_id'];
$success = "";
$error = "";

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action']; // 'approve' or 'reject'
    $review_notes = trim($_POST['review_notes'] ?? '');
    
    if ($request_id > 0 && in_array($action, ['approve', 'reject'])) {
        try {
            // Verify this request belongs to a student under this supervisor
            $check_stmt = $pdo->prepare("
                SELECT scr.student_id, s.created_by, s.company_id
                FROM shift_change_requests scr
                JOIN students s ON scr.student_id = s.student_id
                WHERE scr.id = ? AND scr.status = 'pending'
            ");
            $check_stmt->execute([$request_id]);
            $request = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request && ($request['created_by'] == $employer_id || $request['company_id'] == $employer_id)) {
                $status = $action === 'approve' ? 'approved' : 'rejected';
                
                $update_stmt = $pdo->prepare("
                    UPDATE shift_change_requests 
                    SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_notes = ?
                    WHERE id = ?
                ");
                $update_stmt->execute([$status, $employer_id, $review_notes, $request_id]);
                
                // Log action
                audit_log($pdo, ucfirst($action) . ' Shift Request', "Shift request #$request_id {$status}d for student ID: {$request['student_id']}");
                
                $success = "Request " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
            } else {
                $error = "Invalid request or already processed.";
            }
        } catch (PDOException $e) {
            error_log("Shift request action error: " . $e->getMessage());
            $error = "Failed to process request.";
        }
    }
}

// Get pending requests for this supervisor's students
$pending_stmt = $pdo->prepare("
    SELECT scr.*, 
           CONCAT(s.first_name, ' ', s.last_name) AS student_name,
           s.username,
           s.created_by,
           s.company_id
    FROM shift_change_requests scr
    JOIN students s ON scr.student_id = s.student_id
    WHERE scr.status = 'pending'
      AND (s.created_by = ? OR s.company_id = ?)
    ORDER BY scr.requested_at DESC
");
$pending_stmt->execute([$employer_id, $employer_id]);
$pending_requests = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent processed requests
$processed_stmt = $pdo->prepare("
    SELECT scr.*, 
           CONCAT(s.first_name, ' ', s.last_name) AS student_name,
           s.username,
           em.name AS reviewer_name
    FROM shift_change_requests scr
    JOIN students s ON scr.student_id = s.student_id
    LEFT JOIN employers em ON scr.reviewed_by = em.employer_id
    WHERE scr.status IN ('approved', 'rejected')
      AND (s.created_by = ? OR s.company_id = ?)
    ORDER BY scr.reviewed_at DESC
    LIMIT 20
");
$processed_stmt->execute([$employer_id, $employer_id]);
$processed_requests = $processed_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Shift Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--amber:#d97706;--amber-lt:#fef3c7;--radius:14px;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
        *,*::before,*::after{box-sizing:border-box;}
        body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:32px 20px 60px;}
        .page-card{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);width:100%;margin:0 auto;overflow:hidden;}
        .page-topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;}
        .page-topbar h2{font-size:18px;font-weight:700;margin:0;}
        .page-topbar p{font-size:13px;color:var(--text-muted);margin:2px 0 0;}
        .page-inner{padding:24px 28px 32px;}
        .success-msg{background:var(--green-lt);color:#15803d;padding:12px 16px;border-radius:10px;border:1px solid #bbf7d0;font-size:14px;font-weight:500;margin-bottom:16px;}
        .error-msg{background:var(--red-lt);color:#b91c1c;padding:12px 16px;border-radius:10px;border:1px solid #fecaca;font-size:14px;font-weight:500;margin-bottom:16px;}
        .section-title{font-size:15px;font-weight:700;margin:24px 0 12px;padding-bottom:9px;border-bottom:1px solid var(--border);}
        .request-card{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:18px;margin-bottom:16px;}
        .request-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;}
        .student-info strong{display:block;font-size:14px;margin-bottom:4px;}
        .student-info small{color:var(--text-muted);font-size:12px;}
        .status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;}
        .status-pending{background:var(--amber-lt);color:var(--amber);}
        .status-approved{background:var(--green-lt);color:var(--green);}
        .status-rejected{background:var(--red-lt);color:var(--red);}
        .shift-times{font-size:13px;color:var(--text-muted);margin:8px 0;}
        .reason-box{background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px;margin:12px 0;font-size:13px;}
        .action-form{display:flex;gap:8px;margin-top:12px;}
        .btn{font-family:inherit;font-size:13px;font-weight:600;border-radius:9px;padding:9px 18px;transition:all .18s;cursor:pointer;display:inline-flex;align-items:center;gap:6px;border:none;text-decoration:none;}
        .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);color:#fff;}
        .btn-success{background:var(--green);color:#fff;}.btn-success:hover{background:#15803d;transform:translateY(-1px);color:#fff;}
        .btn-danger{background:var(--red);color:#fff;}.btn-danger:hover{background:#b91c1c;transform:translateY(-1px);color:#fff;}
        .btn-outline-secondary{background:transparent;color:var(--text-muted);border:1.5px solid var(--border);border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;text-decoration:none;}.btn-outline-secondary:hover{background:var(--surface2);color:var(--text);}
        .form-control,textarea{border-radius:9px;border:1px solid var(--border);padding:9px 12px;font-size:14px;font-family:inherit;color:var(--text);background:var(--surface);width:100%;}
        input:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.12);outline:none;}
        .badge-count{background:var(--accent);color:#fff;font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;margin-left:6px;}
        .page-card{max-width:900px;}
        .empty-state{text-align:center;padding:40px 20px;color:var(--text-muted);}
    </style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2>Shift Change Requests</h2>
            <p>Review and approve student shift change requests</p>
        </div>
        <a href="supervisor_dashboard.php" class="btn btn-outline-secondary">← Back to Dashboard</a>
    </div>
    
    <div class="page-inner">
        <?php if ($success): ?>
            <div class="success-msg">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-msg">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <h3 class="section-title">
            Pending Requests
            <?php if (count($pending_requests) > 0): ?>
                <span class="badge-count"><?= count($pending_requests) ?></span>
            <?php endif; ?>
        </h3>
        
        <?php if (empty($pending_requests)): ?>
            <div class="empty-state">
                <p style="font-size:14px;">✅ No pending shift change requests</p>
            </div>
        <?php else: ?>
            <?php foreach($pending_requests as $req): ?>
                <div class="request-card">
                    <div class="request-header">
                        <div class="student-info">
                            <strong>👤 <?= htmlspecialchars($req['student_name']) ?></strong>
                            <small>Username: <?= htmlspecialchars($req['username']) ?> | Requested: <?= date('M d, Y h:i A', strtotime($req['requested_at'])) ?></small>
                        </div>
                        <span class="status-badge status-pending">⏳ Pending</span>
                    </div>
                    
                    <div class="shift-times">
                        <strong>📅 Date:</strong> <?= date('M d, Y', strtotime($req['request_date'])) ?><br>
                        <strong>🕐 Requested Shift:</strong> <?= date('g:i A', strtotime($req['requested_shift_start'])) ?> - <?= date('g:i A', strtotime($req['requested_shift_end'])) ?>
                    </div>
                    
                    <div class="reason-box">
                        <strong>📝 Reason:</strong><br>
                        <?= nl2br(htmlspecialchars($req['reason'])) ?>
                    </div>
                    
                    <form method="POST" class="action-form">
                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <textarea name="review_notes" placeholder="Add a note (optional)..." rows="2" style="flex:1;"></textarea>
                        <div style="display:flex;flex-direction:column;gap:6px;min-width:200px;">
                            <button type="submit" class="btn btn-success" style="justify-content:center;">✓ Approve</button>
                            <button type="button" class="btn btn-danger" onclick="rejectRequest(<?= $req['id'] ?>)" style="justify-content:center;">✗ Reject</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <h3 class="section-title">Recently Processed</h3>
        
        <?php if (empty($processed_requests)): ?>
            <div class="empty-state">
                <p style="font-size:14px;">No processed requests yet</p>
            </div>
        <?php else: ?>
            <?php foreach($processed_requests as $req): ?>
                <div class="request-card" style="opacity:0.85;">
                    <div class="request-header">
                        <div class="student-info">
                            <strong>👤 <?= htmlspecialchars($req['student_name']) ?></strong>
                            <small>Reviewed by: <?= htmlspecialchars($req['reviewer_name'] ?? 'Unknown') ?> | <?= date('M d, Y h:i A', strtotime($req['reviewed_at'])) ?></small>
                        </div>
                        <span class="status-badge status-<?= $req['status'] ?>">
                            <?= $req['status'] === 'approved' ? '✓' : '✗' ?> <?= ucfirst($req['status']) ?>
                        </span>
                    </div>
                    
                    <div class="shift-times">
                        <strong>📅 Date:</strong> <?= date('M d, Y', strtotime($req['request_date'])) ?><br>
                        <strong>🕐 Shift:</strong> <?= date('g:i A', strtotime($req['requested_shift_start'])) ?> - <?= date('g:i A', strtotime($req['requested_shift_end'])) ?>
                    </div>
                    
                    <?php if (!empty($req['review_notes'])): ?>
                        <div class="reason-box">
                            <strong>💬 Reviewer Note:</strong><br>
                            <?= nl2br(htmlspecialchars($req['review_notes'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function rejectRequest(requestId) {
    const notes = prompt("Enter a reason for rejection (optional):");
    if (notes !== null) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="request_id" value="${requestId}">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="review_notes" value="${notes || ''}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
</body>
</html>
