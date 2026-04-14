<?php
session_start();
require_once('../private/config.php');
require_once __DIR__ . '/../includes/audit.php';

if (!isset($_SESSION['change_password']) || $_SESSION['change_password'] !== true) {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'student') {
            header("Location: approval_status.php");
        } elseif ($_SESSION['role'] === 'employer') {
            header("Location: supervisor_dashboard.php");
        }
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $isFirstTimeStudentOrSupervisor = (
        !empty($_SESSION['first_time_login']) &&
        in_array($_SESSION['role'] ?? '', ['student', 'employer'], true)
    );

    if (empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif ($isFirstTimeStudentOrSupervisor && !validate_password($new_password)) {
        $error = "Password must be at least 8 characters with uppercase, lowercase, number, and special character.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        if ($_SESSION['role'] === 'student') {
            $stmt = $pdo->prepare("UPDATE students SET password = ?, password_changed = 1 WHERE student_id = ?");
            $stmt->execute([$hashed_password, $_SESSION['student_id']]);
        } elseif ($_SESSION['role'] === 'employer') {
            $stmt = $pdo->prepare("UPDATE employers SET password = ?, password_changed = 1 WHERE employer_id = ?");
            $stmt->execute([$hashed_password, $_SESSION['employer_id']]);
        }

        unset($_SESSION['change_password']);
        unset($_SESSION['first_time_login']);
        $success = "Password changed successfully! Redirecting to dashboard...";
        
        if ($_SESSION['role'] === 'student') {
            log_activity('Change Password', "Student changed password after first login");
        } elseif ($_SESSION['role'] === 'employer') {
            audit_log($pdo, 'Change Password', "Supervisor changed password after first login");
        }

        echo "<script>
            setTimeout(function() {
                window.location.href = '" . ($_SESSION['role'] === 'student' ? 'approval_status.php' : 'supervisor_dashboard.php') . "';
            }, 2000);
        </script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<style>
    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--radius:14px;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:32px 20px 60px;display:flex;align-items:center;justify-content:center;}
    .page-card{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);width:100%;overflow:hidden;}
    .page-topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;}
    .page-topbar h2{font-size:18px;font-weight:700;margin:0;letter-spacing:-.3px;}
    .page-inner{padding:24px 28px 32px;}
    .success-msg{background:var(--green-lt);color:#15803d;padding:12px 16px;border-radius:10px;border:1px solid #bbf7d0;font-size:14px;font-weight:500;margin-bottom:16px;}
    .error-msg{background:var(--red-lt);color:#b91c1c;padding:12px 16px;border-radius:10px;border:1px solid #fecaca;font-size:14px;font-weight:500;margin-bottom:16px;}
    .form-label{font-size:13px;font-weight:600;color:var(--text);margin-bottom:5px;display:block;}
    input[type=text],input[type=password],input[type=date],input[type=file],.form-control,textarea,select{border-radius:9px;border:1px solid var(--border);padding:9px 12px;font-size:14px;font-family:inherit;color:var(--text);background:var(--surface);transition:border-color .2s,box-shadow .2s;width:100%;}
    input:focus,textarea:focus,select:focus,.form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.12);outline:none;}
    .btn{font-family:inherit;font-size:13px;font-weight:600;border-radius:9px;padding:9px 18px;transition:all .18s;cursor:pointer;display:inline-flex;align-items:center;gap:6px;border:none;text-decoration:none;}
    .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);color:#fff;}
    .btn-success{background:var(--green);color:#fff;}.btn-success:hover{background:#15803d;transform:translateY(-1px);color:#fff;}
    .btn-secondary{background:var(--surface2);color:var(--text);border:1.5px solid var(--border);}.btn-secondary:hover{background:var(--border);}
    .btn-outline-secondary{background:transparent;color:var(--text-muted);border:1.5px solid var(--border);border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-family:inherit;transition:all .18s;}
    .btn-outline-secondary:hover{background:var(--surface2);color:var(--text);}
    .mb-3{margin-bottom:16px;}

    .page-card { max-width:420px; }
</style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2>Change Password</h2>
            <p style="font-size:13px;color:var(--text-muted);margin:2px 0 0;">Required before continuing</p>
        </div>
    </div>
    <div class="page-inner">
        <p style="font-size:14px;color:var(--text-muted);margin:0 0 18px;">This is your first login. Please set a new password to continue.</p>
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label" for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Update Password</button>
        </form>
    </div>
</div>
</body>
</html>