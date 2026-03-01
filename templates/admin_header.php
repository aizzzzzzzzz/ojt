<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
<style>
    body {
        margin: 0;
        padding: 0;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #74ebd5, #ACB6E5);
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
        box-sizing: border-box;
        color: #333;
        line-height: 1.6;
    }

    .dashboard-container {
        background: rgba(255, 255, 255, 0.95);
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        width: 100%;
        max-width: 1200px;
        text-align: center;
    }

    .dashboard-container h2 {
        margin-bottom: 20px;
        font-size: 28px;
        font-weight: 600;
        color: #2c3e50;
    }

    .dashboard-container h3 {
        margin-top: 30px;
        margin-bottom: 15px;
        font-size: 22px;
        color: #2c3e50;
        text-align: left;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 10px;
    }

    .welcome-section {
        margin-bottom: 30px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
    }

    .welcome-section p {
        font-size: 16px;
        color: #666;
        margin: 5px 0;
    }

    .welcome-content {
        text-align: left;
    }

    .logout-btn {
        display: inline-block;
        background: #dc3545;
        color: #fff;
        padding: 10px 16px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
        transition: background 0.2s ease, transform 0.2s ease;
    }

    .logout-btn:hover {
        background: #bb2d3b;
        color: #fff;
        transform: translateY(-1px);
    }

    .logout-btn:focus-visible {
        outline: 3px solid rgba(220, 53, 69, 0.35);
        outline-offset: 2px;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .summary-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        font-size: 16px;
        text-align: center;
        transition: transform 0.3s ease;
    }


    .summary-card strong {
        display: block;
        font-size: 24px;
        color: #2c3e50;
        margin-top: 10px;
    }

    .section-header {
        width: 100%;
        text-align: left;
        background: #ffffff;
        border: 1px solid #dee2e6;
        padding: 12px 16px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 6px;
        margin-bottom: 10px;
    }

    .collapse-section {
        margin-bottom: 20px;
        border-radius: 10px;
        overflow: hidden;
    }

    .collapse-section .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .collapse-section .card-body {
        padding: 20px;
    }

    .collapse {
        display: none !important;
    }

    .collapse.show {
        display: block !important;
    }

    .form-control {
        border-radius: 8px;
        border: 1px solid #ddd;
        padding: 10px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }

    .form-control:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .btn {
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background: linear-gradient(90deg, #28a745, #85e085);
    }

    .btn-primary:hover {
        background: linear-gradient(90deg, #218838, #6c9e6c);
        transform: translateY(-2px);
    }

    .alert {
        border-radius: 8px;
        border: none;
        font-weight: 500;
    }

    .success-msg {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid #c3e6cb;
        text-align: left;
        font-weight: 500;
    }

    .error-msg {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid #f5c6cb;
        text-align: left;
        font-weight: 500;
    }

    .table-section {
        overflow-x: auto;
        margin-top: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 10px;
        overflow: hidden;
    }

    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
    }

    th {
        background: #f8f9fa;
        font-weight: 600;
    }

    .delete-btn {
        color: #dc3545;
        font-weight: 500;
        text-decoration: none;
        padding: 6px 12px;
        border-radius: 5px;
        transition: all 0.3s ease;
    }

    .delete-btn:hover {
        background: #f8d7da;
        text-decoration: none;
        transform: translateY(-2px);
    }

    @media (max-width: 768px) {
        body {
            padding: 10px;
            align-items: center;
        }

        .dashboard-container {
            width: 95%;
            padding: 20px;
            margin: 10px;
        }

        .dashboard-container h2 {
            font-size: 24px;
        }

        .welcome-section {
            flex-direction: column;
            align-items: stretch;
        }

        .logout-btn {
            width: 100%;
            text-align: center;
        }

        .dashboard-container h3 {
            font-size: 18px;
        }

        .summary-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }

        .summary-card strong {
            font-size: 20px;
        }

        .collapse-section .card-body {
            padding: 15px;
        }

        .dashboard-container input,
        .dashboard-container input[type="file"] {
            width: 100%;
            max-width: none;
        }

        .btn {
            width: 100%;
            max-width: none;
            margin-bottom: 10px;
        }

        table, thead, tbody, th, td, tr {
            display: block;
            width: 100%;
        }

        thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }

        tr {
            border: 1px solid #ddd;
            margin-bottom: 15px;
            border-radius: 8px;
            padding: 10px;
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        td {
            border: none;
            position: relative;
            padding-left: 50%;
            text-align: right;
            margin-bottom: 10px;
        }

        td::before {
            content: attr(data-label) ": ";
            position: absolute;
            left: 10px;
            width: 45%;
            padding-right: 10px;
            white-space: nowrap;
            font-weight: bold;
            text-align: left;
            color: #2c3e50;
        }

        .summary-card {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            font-size: 14px;
        }
}
</style>
</head>
<body>
<div class="dashboard-container">
    <div class="welcome-section">
        <div class="welcome-content">
            <h2>Welcome, <?= htmlspecialchars($admin['full_name'] ?? $admin['username']) ?>!</h2>
            <p>You are logged in as an administrator.</p>
        </div>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="text-muted small">Students</div>
            <div class="fs-3 fw-bold"><?= $students_count ?></div>
        </div>
        <div class="summary-card">
            <div class="text-muted small">Employers</div>
            <div class="fs-3 fw-bold"><?= count($employers) ?></div>
        </div>
        <div class="summary-card">
            <div class="text-muted small">Evaluations</div>
            <div class="fs-3 fw-bold"><?= $evaluations_count ?></div>
        </div>
    </div>
