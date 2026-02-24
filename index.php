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
  <title>OJT Management System - Home</title>
  <meta name="description" content="Welcome to the OJT Management System. Choose your role to login.">
  <meta name="keywords" content="OJT, Management, System, Students, Employers, Admins">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      overflow-x: hidden;
      position: relative;
    }

    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
      animation: float 20s infinite linear;
      z-index: -1;
    }

    @keyframes float {
      0% { transform: translateY(0px); }
      100% { transform: translateY(-100px); }
    }

    .header {
      text-align: center;
      margin-bottom: 40px;
      animation: fadeInUp 1s ease-out;
    }

    .header h1 {
      font-size: 48px;
      font-weight: bold;
      color: #fff;
      margin: 0;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }

    .header p {
      font-size: 20px;
      color: #e0e0e0;
      margin: 10px 0 0 0;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    }

    .role-cards {
      display: flex;
      justify-content: center;
      gap: 30px;
      flex-wrap: wrap;
    }

    .card {
      background: rgba(255, 255, 255, 0.95);
      padding: 30px;
      border-radius: 20px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
      width: 300px;
      text-align: center;
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      animation: fadeInUp 1s ease-out;
      animation-fill-mode: both;
    }

    .card:nth-child(1) { animation-delay: 0.2s; }
    .card:nth-child(2) { animation-delay: 0.4s; }
    .card:nth-child(3) { animation-delay: 0.6s; }

    .card:hover {
      transform: translateY(-15px) scale(1.05);
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
    }

    .card-icon {
      font-size: 60px;
      margin-bottom: 20px;
      transition: transform 0.3s;
    }

    .card:hover .card-icon {
      transform: scale(1.2);
    }

    .card h3 {
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 15px;
      color: #333;
    }

    .card p {
      font-size: 16px;
      color: #666;
      margin-bottom: 25px;
      line-height: 1.5;
    }

    .btn {
      display: inline-block;
      padding: 12px 24px;
      border: none;
      border-radius: 25px;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      color: white;
      position: relative;
      overflow: hidden;
    }

    .btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
      transition: left 0.5s;
      pointer-events: none;
    }

    .btn:hover::before {
      left: 100%;
    }

    .btn-admin {
      background: linear-gradient(45deg, #007bff, #0056b3);
    }
    .btn-admin:hover {
      background: linear-gradient(45deg, #0056b3, #004085);
      transform: translateY(-2px);
    }

    .btn-employer {
      background: linear-gradient(45deg, #20c997, #17a2b8);
    }
    .btn-employer:hover {
      background: linear-gradient(45deg, #17a2b8, #138496);
      transform: translateY(-2px);
    }

    .btn-student {
      background: linear-gradient(45deg, #fd7e14, #e8590c);
    }
    .btn-student:hover {
      background: linear-gradient(45deg, #e8590c, #d9480f);
      transform: translateY(-2px);
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 768px) {
      .role-cards {
        flex-direction: column;
        align-items: center;
      }

      .card {
        width: 90%;
        max-width: 300px;
      }

      .header h1 {
        font-size: 36px;
      }

      .header p {
        font-size: 18px;
      }
    }
  </style>
</head>
<body>
  <div class="header">
    <h1>OJT Management System</h1>
    <p>Welcome to the OJT Management System</p>
  </div>

  <div class="role-cards">
    <div class="card">
      <div class="card-icon">👨‍💼</div>
      <h3>Admin</h3>
      <a href="public/admin_login.php" class="btn btn-admin">Login as Admin</a>
      <?php if ($admin_count == 0): ?>
      <a href="public/add_first_admin.php" class="btn btn-admin" style="margin-top: 10px; font-size: 14px; padding: 8px 16px;">First-time Setup</a>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-icon">🏢</div>
      <h3>Supervisor</h3>
      <a href="public/supervisor_login.php" class="btn btn-employer">Login as Supervisor</a>
    </div>

    <div class="card">
      <div class="card-icon">🎓</div>
      <h3>Student</h3>
      <a href="public/student_login.php" class="btn btn-student">Login as Student</a>
    </div>
  </div>
</body>
</html>
