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
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:32px 20px 60px;display:flex;align-items:center;justify-content:center;}
    .card{background:var(--surface);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow-md);width:100%;max-width:420px;overflow:hidden;}
    .card-header{padding:28px 28px 20px;border-bottom:1px solid var(--border);text-align:center;}
    .card-header h1{font-size:22px;font-weight:700;margin:0 0 4px;letter-spacing:-.3px;}
    .card-header p{font-size:13px;color:var(--text-muted);margin:0;}
    .card-body{padding:24px 28px 32px;}
    .form-label{font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px;display:block;}
    input[type=password]{border-radius:9px;border:1px solid var(--border);padding:9px 12px;font-size:14px;font-family:inherit;color:var(--text);background:var(--surface);transition:border-color .2s,box-shadow .2s;width:100%;margin-bottom:14px;}
    input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.12);outline:none;}
    .btn{font-family:inherit;font-size:14px;font-weight:600;border-radius:9px;padding:11px;transition:all .18s;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;border:none;text-decoration:none;width:100%;margin-top:4px;}
    .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);box-shadow:0 4px 12px rgba(67,97,238,.28);}
    .btn-secondary{background:var(--surface2);color:var(--text);border:1.5px solid var(--border);margin-top:10px;}.btn-secondary:hover{background:var(--border);}
    .msg{padding:12px 16px;border-radius:10px;font-size:14px;font-weight:500;margin-bottom:14px;}
    .msg-error  {background:var(--red-lt);  color:#b91c1c;border:1px solid #fecaca;}
    .msg-success{background:var(--green-lt);color:#15803d;border:1px solid #bbf7d0;}
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