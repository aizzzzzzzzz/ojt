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

                // Ensure MOA table has all required columns
                $alterSQL = [
                    "ALTER TABLE moa_documents ADD COLUMN supervisor_approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'",
                    "ALTER TABLE moa_documents ADD COLUMN supervisor_approved_at TIMESTAMP NULL",
                    "ALTER TABLE moa_documents ADD COLUMN supervisor_rejection_reason TEXT",
                    "ALTER TABLE moa_documents ADD COLUMN admin_approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'",
                    "ALTER TABLE moa_documents ADD COLUMN admin_approved_at TIMESTAMP NULL",
                    "ALTER TABLE moa_documents ADD COLUMN admin_rejection_reason TEXT",
                    "ALTER TABLE moa_documents ADD COLUMN is_new_student TINYINT(1) NOT NULL DEFAULT 1"
                ];
                
                foreach ($alterSQL as $sql) {
                    try {
                        $pdo->exec($sql);
                    } catch (PDOException $ae) {
                        // Column might already exist, that's fine
                    }
                }

                // Check MOA approval status for new students
                $moa_stmt = $pdo->prepare("
                    SELECT 
                        document_type,
                        supervisor_approval_status,
                        admin_approval_status,
                        supervisor_rejection_reason,
                        admin_rejection_reason
                    FROM moa_documents 
                    WHERE student_id = ? AND is_new_student = 1
                ");
                $moa_stmt->execute([$student['student_id']]);
                $moa_docs = $moa_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($moa_docs)) {
                    // Check if both MOA and Endorsement Letter exist
                    $has_moa = false;
                    $has_endorsement = false;
                    $all_approved = true;
                    
                    foreach ($moa_docs as $doc) {
                        if ($doc['document_type'] === 'MOA') {
                            $has_moa = true;
                        } elseif ($doc['document_type'] === 'Endorsement Letter') {
                            $has_endorsement = true;
                        }
                        
                        // Check if this document is fully approved
                        if ($doc['supervisor_approval_status'] !== 'approved' || $doc['admin_approval_status'] !== 'approved') {
                            $all_approved = false;
                        }
                    }
                    
                    // If both documents exist and both are approved, proceed to dashboard
                    if ($has_moa && $has_endorsement && $all_approved) {
                        // Mark documents as no longer new
                        $update_stmt = $pdo->prepare("UPDATE moa_documents SET is_new_student = 0 WHERE student_id = ? AND is_new_student = 1");
                        $update_stmt->execute([$student['student_id']]);
                        header("Location: student_dashboard.php"); exit;
                    } else {
                        // Redirect to approval status page
                        header("Location: approval_status.php"); exit;
                    }
                } else {
                    // No MOA documents uploaded yet - redirect to approval status
                    header("Location: approval_status.php"); exit;
                }
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
    <link rel="stylesheet" href="../assets/portal-ui.css">
<style>
    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#dbe6f0;--text:#10223a;--text-muted:#5f7188;--accent:#4361ee;--accent-dk:#3451d1;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--amber:#d97706;--amber-lt:#fef3c7;--shadow-md:0 20px 50px rgba(18,57,91,.18);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:24px 20px 40px;}
    .portal-login-wrap{width:100%;max-width:1240px;margin:0 auto;}
    .portal-login-top{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:26px;}
    .portal-login-panel{display:grid;grid-template-columns:minmax(0,1.06fr) minmax(380px,.94fr);gap:22px;align-items:stretch;}
    .portal-login-story,.login-card-shell{border-radius:30px;overflow:hidden;box-shadow:var(--shadow-md);}
    .portal-login-story{position:relative;min-height:680px;background:linear-gradient(145deg,rgba(255,255,255,.13),rgba(255,255,255,.08));border:1px solid rgba(255,255,255,.14);backdrop-filter:blur(14px);}
    .portal-login-image{position:absolute;inset:0;background:linear-gradient(rgba(10,34,58,.28),rgba(10,34,58,.34)),url('../assets/crtbldg.jpg') center center/cover no-repeat;}
    .portal-login-copy{position:relative;z-index:1;display:flex;flex-direction:column;justify-content:space-between;height:100%;padding:34px;}
    .portal-login-kicker{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:rgba(255,255,255,.92);color:var(--portal-navy);font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;}
    .portal-login-headline{max-width:580px;margin:18px 0 12px;font-size:clamp(2.4rem,4vw,4.6rem);line-height:1.02;letter-spacing:-.06em;color:#fff;}
    .portal-login-subtitle{max-width:620px;margin:0 0 24px;font-size:16px;line-height:1.85;color:rgba(255,255,255,.84);}
    .portal-login-points{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;}
    .portal-login-point{padding:16px 16px 18px;border-radius:22px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);backdrop-filter:blur(12px);}
    .portal-login-point strong{display:block;margin-bottom:6px;font-size:15px;color:#fff;}
    .portal-login-point span{display:block;font-size:13px;line-height:1.7;color:rgba(255,255,255,.78);}
    .login-card-shell{background:rgba(255,255,255,.96);border:1px solid rgba(255,255,255,.55);display:flex;flex-direction:column;}
    .login-card-header{padding:30px 30px 20px;border-bottom:1px solid var(--border);}
    .login-card-header h1{font-size:28px;font-weight:800;margin:0 0 8px;letter-spacing:-.04em;color:var(--text);}
    .login-card-header p{font-size:14px;color:var(--text-muted);margin:0;}
    .login-card-body{padding:26px 30px 30px;}
    .login-back-link{display:inline-flex;align-items:center;gap:8px;color:rgba(255,255,255,.88);text-decoration:none;font-weight:700;}
    .login-meta{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;}
    .login-chip{display:inline-flex;align-items:center;padding:8px 12px;border-radius:999px;background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.16);color:#fff;font-size:12px;font-weight:700;}
    .form-label{font-size:13px;font-weight:700;color:var(--text);margin-bottom:6px;display:block;}
    input[type=text],input[type=password]{border-radius:14px;border:1px solid var(--border);padding:13px 14px;font-size:15px;font-family:inherit;color:var(--text);background:#fff;transition:border-color .2s,box-shadow .2s;width:100%;margin-bottom:16px;}
    input:focus{border-color:var(--portal-blue);box-shadow:0 0 0 4px rgba(31,119,200,.12);outline:none;}
    input:disabled,input[readonly]{background:var(--surface2);color:var(--text-muted);}
    .btn{font-family:inherit;font-size:14px;font-weight:700;border-radius:16px;padding:13px 16px;transition:all .18s;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;border:none;text-decoration:none;width:100%;margin-top:6px;}
    .btn-primary{background:linear-gradient(135deg,var(--portal-navy),var(--portal-blue) 60%,var(--portal-sky));color:#fff;}
    .btn-primary:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 14px 28px rgba(18,57,91,.20);}
    .btn-primary:disabled{opacity:.5;cursor:not-allowed;}
    .btn-warning{background:linear-gradient(135deg,#f59e0b,#f97316);color:#fff;margin-top:10px;}
    .btn-warning:hover{transform:translateY(-1px);box-shadow:0 12px 22px rgba(217,119,6,.18);}
    .msg{padding:13px 16px;border-radius:16px;font-size:14px;font-weight:600;margin-bottom:16px;}
    .msg-error{background:var(--red-lt);color:#b91c1c;border:1px solid #fecaca;}
    .msg-info{background:var(--amber-lt);color:#92400e;border:1px solid #fde68a;}
    .msg-success{background:var(--green-lt);color:#15803d;border:1px solid #bbf7d0;}
    .divider{border:none;border-top:1px solid var(--border);margin:18px 0;}
    .setup-link{display:block;text-align:center;margin-top:16px;font-size:13px;color:var(--text-muted);}
    .setup-link a{color:var(--portal-blue);text-decoration:none;font-weight:700;}
    .setup-link a:hover{text-decoration:underline;}
    .login-utility-links{display:flex;justify-content:flex-end;margin:-4px 0 16px;}
    .login-utility-links a{color:var(--portal-blue);text-decoration:none;font-size:13px;font-weight:700;}
    .login-utility-links a:hover{text-decoration:underline;}
    .login-side-note{margin-top:18px;padding:16px 18px;border-radius:18px;background:linear-gradient(135deg,#eef7ff,#f9fcff);border:1px solid var(--border);}
    .login-side-note strong{display:block;margin-bottom:5px;font-size:14px;color:var(--text);}
    .login-side-note span{display:block;font-size:13px;color:var(--text-muted);line-height:1.7;}
    @media (max-width: 1100px){
        .portal-login-panel{grid-template-columns:1fr;}
        .portal-login-story{min-height:520px;}
    }
    @media (max-width: 720px){
        body{padding:16px 14px 28px;}
        .portal-login-copy,.login-card-header,.login-card-body{padding:22px 20px;}
        .portal-login-points{grid-template-columns:1fr;}
        .portal-login-headline{font-size:clamp(2rem,10vw,3rem);}
        .portal-login-story{min-height:unset;}
    }
</style>
</head>
<body class="portal-home portal-login">
<div class="portal-login-wrap">
    <div class="portal-login-top">
        <a class="portal-brand" href="../index.php">
            <img src="../assets/school_logo.png" alt="CRT logo">
            <span>
                <span class="portal-brand-title">College for Research and Technology</span>
                <span class="portal-brand-subtitle">OJT Management and Internship Monitoring System</span>
            </span>
        </a>
        <a class="login-back-link" href="../index.php">← Back to Home</a>
    </div>

    <div class="portal-login-panel">
        <section class="portal-login-story">
            <div class="portal-login-image"></div>
            <div class="portal-login-copy">
                <div>
                    <span class="portal-login-kicker">CRT Secure Login</span>
                    <p class="portal-login-subtitle">
                        Sign in to continue with attendance monitoring, evaluation workflows, supervisor management, and certificate-ready internship tracking.
                    </p>
                    <div class="login-meta">
                        <span class="login-chip">Students</span>
                        <span class="login-chip">Supervisors</span>
                        <span class="login-chip">Administrators</span>
                    </div>
                </div>
                <div class="portal-login-points">
                    <div class="portal-login-point">
                        <strong>One Portal</strong>
                        <span>All roles sign in through the same secure access point and land in the right workspace.</span>
                    </div>
                    <div class="portal-login-point">
                        <strong>Protected Access</strong>
                        <span>Cooldown and account lock behavior stay in place to reduce repeated login abuse.</span>
                    </div>
                    <div class="portal-login-point">
                        <strong>Password Recovery</strong>
                        <span>Locked student accounts can still request a reset email from the same screen.</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="login-card-shell">
            <div class="login-card-header">
                <h1>Sign in to the portal</h1>
                <p>Use your CRT account credentials to continue to your dashboard.</p>
            </div>
            <div class="login-card-body">

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

                <div class="login-utility-links">
                    <a href="forgot_password.php">Reset Password</a>
                </div>

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

         <div class="login-side-note">
             <strong>Need help signing in?</strong>
             <span>Use your assigned username and password. If your account is locked, the reset option will appear automatically below.</span>
         </div>

         <?php if ((int)$admin_count === 0): ?>
             <p class="setup-link"><a href="add_first_admin.php">First-time Setup →</a></p>
         <?php endif; ?>
            </div>
        </section>
    </div>
</div>
</body>
</html>
