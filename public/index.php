<?php
session_start();
require_once __DIR__ . '/../private/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OJT Management System - Home</title>
  <meta name="description" content="Welcome to the OJT Management System. Choose your role to login.">
  <meta name="keywords" content="OJT, Management, System, Students, Employers, Admins">
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #e3f2fd, #bbdefb);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .header {
      text-align: center;
      margin-bottom: 40px;
    }

    .header h1 {
      font-size: 48px;
      font-weight: bold;
      color: #333;
      margin: 0;
    }

    .header p {
      font-size: 20px;
      color: #666;
      margin: 10px 0 0 0;
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
      border-radius: 15px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
      width: 300px;
      text-align: center;
      transition: transform 0.3s, box-shadow 0.3s;
    }

    .card:hover {
      transform: translateY(-10px);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
    }

    .card-icon {
      font-size: 60px;
      margin-bottom: 20px;
    }

    .card h3 {
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 15px;
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
      border-radius: 8px;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      transition: 0.3s;
      text-decoration: none;
      color: white;
    }

    .btn-admin {
      background: #007bff;
    }
    .btn-admin:hover {
      background: #0056b3;
    }

    .btn-employer {
      background: #20c997;
    }
    .btn-employer:hover {
      background: #17a2b8;
    }

    .btn-student {
      background: #fd7e14;
    }
    .btn-student:hover {
      background: #e8590c;
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
      <p>Manage the system, employers, and students</p>
      <a href="admin_login.php" class="btn btn-admin">Login as Admin</a>
      <a href="add_first_admin.php" class="btn btn-admin" style="margin-top: 10px; font-size: 14px; padding: 8px 16px;">First-time Setup</a>
    </div>

    <div class="card">
      <div class="card-icon">🏢</div>
      <h3>Employer</h3>
      <p>Track attendance and evaluate students</p>
      <a href="supervisor_login.php" class="btn btn-employer">Login as Supervisor</a>
    </div>

    <div class="card">
      <div class="card-icon">🎓</div>
      <h3>Student</h3>
      <p>Check your attendance and evaluations</p>
      <a href="student_login.php" class="btn btn-student">Login as Student</a>
    </div>
  </div>
</body>
</html>
