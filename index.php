<?php
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    if (!in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])) {
        $https_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: ' . $https_url, true, 301);
        exit;
    }
}

session_start();
require_once __DIR__ . '/private/config.php';


$stmt = $pdo->prepare("SELECT COUNT(*) FROM admins");
$stmt->execute();
$admin_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OJT Management System</title>
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

    body { flex-direction:column; gap:40px; padding:40px 20px; }
    .site-title { text-align:center; }
    .site-title h1 { font-size:32px; font-weight:700; color:var(--text); margin:0 0 6px; letter-spacing:-.5px; }
    .site-title p  { font-size:15px; color:var(--text-muted); margin:0; }
    .login-card { max-width:380px; }
    .login-header { padding:28px 28px 18px; border-bottom:1px solid var(--border); text-align:center; }
    .login-header h2 { font-size:20px; font-weight:700; margin:0 0 4px; }
    .login-header p  { font-size:13px; color:var(--text-muted); margin:0; }
    .login-body { padding:22px 28px 28px; }
    .login-body input { margin-bottom:12px; }
    .login-btn { width:100%; justify-content:center; font-size:14px; padding:11px; margin-top:4px; }
    .setup-link { display:block; text-align:center; margin-top:12px; font-size:13px; color:var(--text-muted); }
    .setup-link a { color:var(--accent); text-decoration:none; font-weight:600; }
</style>
</head>
<body>
  <div class="site-title">
    <h1>OJT Management System</h1>
    <p>Sign in to access your dashboard</p>
  </div>
  <div class="page-card login-card">
    <div class="login-header">
      <h2>Welcome back</h2>
      <p>Enter your credentials to continue</p>
    </div>
    <div class="login-body">
      <form method="post" action="public/login.php">
        <label class="form-label" for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Enter your username" required>
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" required>
        <button type="submit" class="btn btn-primary login-btn">Login</button>
      </form>
      <?php if ($admin_count == 0): ?>
        <p class="setup-link"><a href="public/add_first_admin.php">First-time Setup →</a></p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>