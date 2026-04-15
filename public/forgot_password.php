<?php
session_start();
require_once __DIR__ . '/../private/config.php';

$error    = '';
$success  = '';
$token    = sanitize_input($_GET['token'] ?? $_POST['token'] ?? '');
$valid    = false;
$username = '';

if ($token !== '') {
    $username = validate_reset_token($pdo, $token);
    $valid    = ($username !== false);
}

if (!$valid && $token !== '') {
    $error = "This reset link is invalid or has expired. Please request a new one from the login page.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!validate_password($new_password)) {
        $error = "Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character (@\$!%*?&).";
    } else {
        $hashed  = password_hash($new_password, PASSWORD_DEFAULT);
        $updated = false;

        $stmt = $pdo->prepare("UPDATE students SET password = ?, password_changed = 1 WHERE username = ?");
        $stmt->execute([$hashed, $username]);
        if ($stmt->rowCount() > 0) $updated = true;

        if (!$updated) {
            $stmt = $pdo->prepare("UPDATE employers SET password = ?, password_changed = 1 WHERE username = ?");
            $stmt->execute([$hashed, $username]);
            if ($stmt->rowCount() > 0) $updated = true;
        }

        if ($updated) {
            consume_reset_token($pdo, $token);
            $success = "Password reset successfully! You can now log in with your new password.";
        } else {
            $error = "Could not update password. Please contact your administrator.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — OJT System</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<style>
    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:radial-gradient(circle at top left,rgba(67,97,238,.16),transparent 30%),linear-gradient(180deg,#eef4ff 0%,#f8fbff 50%,#f3f6fb 100%);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:32px 20px 60px;display:flex;align-items:center;justify-content:center;}
    .card{background:var(--surface);border:1px solid rgba(226,232,240,.8);border-radius:24px;box-shadow:0 20px 42px rgba(15,23,42,.08);width:100%;max-width:480px;overflow:hidden;}
    .card-header{padding:28px 32px 20px;border-bottom:1px solid rgba(226,232,240,.9);text-align:center;background:linear-gradient(135deg,rgba(67,97,238,.08),rgba(99,170,229,.05));}
    .card-header h1{font-size:22px;font-weight:700;margin:0 0 4px;letter-spacing:-.4px;}
    .card-header p{font-size:14px;color:var(--text-muted);margin:0;}
    .card-body{padding:32px 32px 40px;}
    .form-label{font-size:14px;font-weight:700;color:var(--text);margin-bottom:8px;display:block;letter-spacing:-.2px;}
    input[type=password]{border-radius:14px;border:1.5px solid rgba(226,232,240,.9);padding:12px 16px;font-size:14px;font-family:inherit;color:var(--text);background:#f8fbff;transition:border-color .2s,box-shadow .2s,background .2s;width:100%;margin-bottom:14px;}
    input:focus{border-color:var(--accent);background:var(--surface);box-shadow:0 0 0 4px rgba(67,97,238,.1);outline:none;}
    .btn{font-family:inherit;font-size:14px;font-weight:700;border-radius:14px;padding:12px;transition:all .18s;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;border:none;text-decoration:none;width:100%;margin-top:4px;box-shadow:0 12px 24px rgba(15,23,42,.08);}
    .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);box-shadow:0 16px 32px rgba(67,97,238,.2);}
    .btn-secondary{background:transparent;color:var(--text);border:1.5px solid rgba(15,23,42,.1);margin-top:10px;}.btn-secondary:hover{background:rgba(71,85,105,.05);border-color:rgba(15,23,42,.15);}
    .msg{padding:16px 18px;border-radius:14px;font-size:14px;font-weight:600;margin-bottom:14px;}
    .msg-error{background:rgba(239,68,68,.1);color:#991b1b;border:1.5px solid rgba(239,68,68,.25);}
    .msg-success{background:rgba(5,150,105,.1);color:#047857;border:1.5px solid rgba(16,185,129,.25);}
    .hint{font-size:12px;color:var(--text-muted);margin:-10px 0 14px;}
    .back-link{display:block;text-align:center;margin-top:14px;font-size:13px;}
    .back-link a{color:var(--accent);text-decoration:none;font-weight:600;}
    .back-link a:hover{text-decoration:underline;}
</style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h1>Reset Password</h1>
        <p>OJT Management System</p>
    </div>
    <div class="card-body">

        <?php if (!empty($error)): ?>
            <div class="msg msg-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="msg msg-success"><?= htmlspecialchars($success) ?></div>
            <a href="login.php" class="btn btn-primary">← Back to Login</a>

        <?php elseif ($valid): ?>
            <p style="font-size:14px;color:var(--text-muted);margin:0 0 18px;">
                Setting a new password for <strong><?= htmlspecialchars($username) ?></strong>.
            </p>
            <form method="post">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <label class="form-label" for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                <p class="hint">Min. 8 chars — uppercase, lowercase, number, and special character.</p>
                <label class="form-label" for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                <button type="submit" class="btn btn-primary">Set New Password</button>
            </form>
            <p class="back-link"><a href="login.php">← Back to Login</a></p>

        <?php else: ?>
            <?php if (empty($error)): ?>
                <div class="msg msg-error">No reset token found. Please use the link from your email.</div>
            <?php endif; ?>
            <a href="login.php" class="btn btn-secondary">← Back to Login</a>
        <?php endif; ?>

    </div>
</div>
</body>
</html>