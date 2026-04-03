<?php
session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/email.php';

$error             = '';
$info              = '';
$lockout_remaining = 0;
$show_reset_btn    = false;
$reset_sent        = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reset'])) {
    $username = sanitize_input($_POST['username'] ?? '');

    if ($username === '') {
        $error = "Please enter your username so we can find your account.";
    } else {
        $stmt = $pdo->prepare("SELECT email FROM students WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $student = $stmt->fetch();

        if ($student && !empty($student['email'])) {
            $token = generate_reset_token($pdo, $username);

            if ($token) {
                $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                    . '://' . $_SERVER['HTTP_HOST']
                    . dirname($_SERVER['REQUEST_URI']) . '/forgot_password.php?token=' . urlencode($token);

                $body = "
                    <div style='font-family:DM Sans,sans-serif;max-width:480px;margin:auto;padding:32px 24px;background:#f1f4f9;border-radius:16px;'>
                      <h2 style='color:#111827;margin:0 0 8px;'>Password Reset</h2>
                      <p style='color:#6b7280;font-size:14px;'>A password reset was requested for <strong>{$username}</strong>.</p>
                      <p style='color:#6b7280;font-size:14px;'>Click the button below. This link expires in <strong>1 hour</strong>.</p>
                      <a href='{$reset_link}'
                         style='display:inline-block;margin:16px 0;padding:12px 24px;background:#4361ee;color:#fff;border-radius:9px;text-decoration:none;font-weight:600;font-size:14px;'>
                        Reset My Password
                      </a>
                      <p style='color:#9ca3af;font-size:12px;margin-top:24px;'>If you didn't request this, ignore this email — your password won't change.</p>
                    </div>";
                $altBody = "Reset your OJT System password: {$reset_link}\n\nExpires in 1 hour.";

                $result = send_email($student['email'], 'OJT System – Password Reset Request', $body, $altBody);

                if ($result === true) {
                    $reset_sent = true;
                } else {
                    error_log("Reset email failed: " . $result);
                    $error = "Could not send reset email. Please contact your administrator.";
                }
            } else {
                $error = "Could not generate a reset token. Please try again or contact your administrator.";
            }
        } else {
            $info = "If your account has an email on file, a reset link has been sent. Otherwise, please contact your administrator.";
        }
    }
}

elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Please fill in all fields.";
    } else {
        $status = check_login_attempts($pdo, $username);

        if ($status === 'locked') {
            $show_reset_btn = true;
            $error = "Your account is locked after too many failed attempts. Please reset your password via email.";
        } elseif ($status === 'cooldown') {
            $lockout_remaining = get_lockout_remaining($pdo, $username);
            $error = "Too many failed attempts. Please wait {$lockout_remaining} second(s) before trying again.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            if ($admin && password_verify($password, $admin['password'])) {
                clear_login_attempts($pdo, $username);
                $_SESSION['admin_id']        = $admin['admin_id'];
                $_SESSION['admin_username']  = $admin['username'];
                $_SESSION['admin_full_name'] = $admin['full_name'] ?? $admin['username'];
                $_SESSION['role']            = 'admin';
                $_SESSION['is_admin']        = true;
                write_audit_log('Admin Login', "Admin {$admin['username']} logged in.");
                header("Location: admin_dashboard.php"); exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM employers WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $employer = $stmt->fetch();
            if ($employer && password_verify($password, $employer['password'])) {
                clear_login_attempts($pdo, $username);
                $_SESSION['employer_id']   = $employer['employer_id'];
                $_SESSION['employer_name'] = $employer['name'];
                $_SESSION['role']          = 'employer';
                $_SESSION['is_admin']      = false;
                if ((int)($employer['password_changed'] ?? 0) === 0) {
                    $_SESSION['change_password']  = true;
                    $_SESSION['first_time_login'] = true;
                    header("Location: change_password.php"); exit;
                }
                header("Location: supervisor_dashboard.php"); exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM students WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $student = $stmt->fetch();
            if ($student && password_verify($password, $student['password'])) {
                clear_login_attempts($pdo, $username);
                $_SESSION['student_id'] = $student['student_id'];
                $_SESSION['role']       = 'student';
                $_SESSION['success']    = "Login successful!";
                
                require_once __DIR__ . '/../includes/audit.php';
                log_activity('Student Login', "Student {$student['username']} logged in");
                
                if ((int)($student['password_changed'] ?? 0) === 0) {
                    $_SESSION['change_password']  = true;
                    $_SESSION['first_time_login'] = true;
                    header("Location: change_password.php"); exit;
                }
                header("Location: student_dashboard.php"); exit;
            }

            record_login_attempt($pdo, $username);
            $status_after = check_login_attempts($pdo, $username);

            if ($status_after === 'locked') {
                $show_reset_btn = true;
                $error = "Your account is now locked. Please reset your password via email.";
            } elseif ($status_after === 'cooldown') {
                $lockout_remaining = get_lockout_remaining($pdo, $username);
                $error = "Too many failed attempts. Please wait {$lockout_remaining} second(s) before trying again.";
            } else {
                $row           = get_login_attempt_row($pdo, $username, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
                $count         = $row ? (int)$row['attempt_count'] : 1;
                $attempts_left = max(0, 3 - $count);
                $error = $attempts_left > 0
                    ? "Invalid username or password. {$attempts_left} attempt(s) remaining before cooldown."
                    : "Invalid username or password.";
            }
        }
    }
}

$prefill_username = sanitize_input($_POST['username'] ?? '');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM admins");
$stmt->execute();
$admin_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — OJT System</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<style>
    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--amber:#d97706;--amber-lt:#fef3c7;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:linear-gradient(rgba(0,0,0,0.65),rgba(0,0,0,0.65)),url('../assets/crtbldg.jpg') center bottom/cover no-repeat fixed;color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:32px 20px 60px;display:flex;align-items:center;justify-content:center;flex-direction:column;}
    .login-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow-md);width:100%;max-width:400px;overflow:hidden;}
    .login-header{padding:28px 28px 20px;border-bottom:1px solid var(--border);text-align:center;}
    .login-header h1{font-size:22px;font-weight:700;margin:0 0 4px;letter-spacing:-.3px;}
    .login-header p{font-size:13px;color:var(--text-muted);margin:0;}
    .page-title{text-align:center;color:#fff;margin-bottom:40px;text-shadow:0 2px 8px rgba(0,0,0,0.7), 0 0 20px rgba(0,0,0,0.5);}
    .page-title h2{font-size:28px;font-weight:700;margin:0 0 6px;letter-spacing:-.5px;}
    .page-title p{font-size:14px;color:#e0e0e0;margin:0;}
    .login-body{padding:24px 28px 28px;}
    .form-label{font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px;display:block;}
    input[type=text],input[type=password]{border-radius:9px;border:1px solid var(--border);padding:9px 12px;font-size:14px;font-family:inherit;color:var(--text);background:var(--surface);transition:border-color .2s,box-shadow .2s;width:100%;margin-bottom:14px;}
    input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.12);outline:none;}
    input:disabled,input[readonly]{background:var(--surface2);color:var(--text-muted);}
    .btn{font-family:inherit;font-size:14px;font-weight:600;border-radius:9px;padding:11px;transition:all .18s;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;border:none;text-decoration:none;width:100%;margin-top:4px;}
    .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover:not(:disabled){background:var(--accent-dk);transform:translateY(-1px);box-shadow:0 4px 12px rgba(67,97,238,.28);}
    .btn-primary:disabled{opacity:.5;cursor:not-allowed;}
    .btn-warning{background:#f59e0b;color:#fff;margin-top:10px;}.btn-warning:hover{background:#d97706;transform:translateY(-1px);}
    .msg{padding:12px 16px;border-radius:10px;font-size:14px;font-weight:500;margin-bottom:14px;}
    .msg-error  {background:var(--red-lt);  color:#b91c1c;border:1px solid #fecaca;}
    .msg-info   {background:var(--amber-lt);color:#92400e;border:1px solid #fde68a;}
    .msg-success{background:var(--green-lt);color:#15803d;border:1px solid #bbf7d0;}
    .divider{border:none;border-top:1px solid var(--border);margin:16px 0;}
    .setup-link{display:block;text-align:center;margin-top:14px;font-size:13px;color:var(--text-muted);}
    .setup-link a{color:var(--accent);text-decoration:none;font-weight:600;}
    .setup-link a:hover{text-decoration:underline;}
</style>
</head>
<body>
<div class="page-title">
    <h2>OJT Management System</h2>
    <p>Sign in to access your dashboard</p>
</div>
<div class="login-card">
    <div class="login-header">
        <h1>Internship Monitoring System</h1>
        <p>Sign in to your account to continue</p>
    </div>
    <div class="login-body">

        <?php if ($reset_sent): ?>
            <div class="msg msg-success">
                ✅ Reset link sent! Check your email and follow the instructions. The link expires in 1 hour.
            </div>

        <?php else: ?>

            <?php if (!empty($error)): ?>
                <div class="msg msg-error" id="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($info)): ?>
                <div class="msg msg-info"><?= htmlspecialchars($info) ?></div>
            <?php endif; ?>

            <form method="post" id="login-form">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username"
                       placeholder="Enter your username"
                       value="<?= htmlspecialchars($prefill_username) ?>"
                       <?= $show_reset_btn ? 'readonly' : '' ?> required>

                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="Enter your password"
                       <?= ($show_reset_btn || $lockout_remaining > 0) ? 'disabled' : '' ?> required>

                <button type="submit" class="btn btn-primary" id="login-btn"
                        <?= ($show_reset_btn || $lockout_remaining > 0) ? 'disabled' : '' ?>>
                    Login
                </button>
            </form>

            <?php if ($show_reset_btn): ?>
                <hr class="divider">
                <p style="font-size:13px;color:var(--text-muted);margin:0 0 8px;text-align:center;">
                    Account locked. Reset your password via email.
                </p>
                <form method="post">
                    <input type="hidden" name="username" value="<?= htmlspecialchars($prefill_username) ?>">
                    <button type="submit" name="send_reset" class="btn btn-warning">
                        📧 Send Password Reset Email
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($lockout_remaining > 0): ?>
            <script>
            (function() {
                var remaining = <?= (int)$lockout_remaining ?>;
                var msg      = document.getElementById('error-msg');
                var btn      = document.getElementById('login-btn');
                var pass     = document.getElementById('password');
                var timer    = setInterval(function() {
                    remaining--;
                    if (remaining <= 0) {
                        clearInterval(timer);
                        msg.className   = 'msg msg-success';
                        msg.textContent = '✅ Cooldown expired. You may try again.';
                        btn.disabled    = false;
                        pass.disabled   = false;
                        pass.focus();
                    } else {
                        msg.textContent = 'Too many failed attempts. Please wait ' + remaining + ' second(s) before trying again.';
                    }
                }, 1000);
            })();
            </script>
            <?php endif; ?>

        <?php endif; ?>

        <?php if ((int)$admin_count === 0): ?>
            <p class="setup-link"><a href="add_first_admin.php">First-time Setup →</a></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>