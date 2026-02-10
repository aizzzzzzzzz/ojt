<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OJT Supervisor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function toggleDetails(index) {
            const row = document.getElementById('details' + index);
            if (row) {
                row.classList.toggle('show');
                console.log('Toggled details' + index, row.classList);
            } else {
                console.error('Row not found: details' + index);
            }
        }
    </script>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #333;
            line-height: 1.6;
        }

        .dashboard-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 1200px;
            margin: 20px auto;
            text-align: center;
        }

        .dashboard-container h2 {
            margin-bottom: 10px;
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
        }

        .dashboard-container h3 {
            margin-top: 30px;
            margin-bottom: 20px;
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
        }

        .welcome-section p {
            font-size: 16px;
            color: #666;
            margin: 5px 0;
        }

        .success-msg {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            text-align: left;
            font-weight: 500;
        }

        .actions-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .action-card .icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #007bff;
        }

        .action-card a {
            display: block;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            background: linear-gradient(90deg, #007bff, #00c6ff);
            color: white;
            transition: all 0.3s ease;
        }

        .action-card a:hover {
            background: linear-gradient(90deg, #0056b3, #0099cc);
            transform: translateY(-2px);
        }

        .attendance-actions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .attendance-actions h4 {
            margin-bottom: 15px;
            color: #2c3e50;
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
            text-align: center;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }

        th {
            background: linear-gradient(90deg, #f8f9fa, #e9ecef);
            font-weight: 600;
            color: #2c3e50;
            position: sticky;
            top: 0;
        }

        tr:nth-child(even) {
            background: #f8f9fa;
        }

        tr:hover {
            background: #e3f2fd;
            transition: background 0.3s ease;
        }

        table a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        table a:hover {
            background: #e3f2fd;
        }

        .btn-success {
            background: linear-gradient(90deg, #28a745, #85e085);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            background: linear-gradient(90deg, #218838, #6c9e6c);
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(90deg, #ffc107, #ffed4e);
            color: #212529;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-warning:hover {
            background: linear-gradient(90deg, #e0a800, #d39e00);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(90deg, #dc3545, #ff6b7a);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            background: linear-gradient(90deg, #bd2130, #e04b59);
            transform: translateY(-2px);
        }

        .btn-outline-secondary {
            background: transparent;
            color: #6c757d;
            border: 1px solid #6c757d;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-2px);
        }

        .details-row {
            display: none !important;
        }

        .details-row.show {
            display: table-row !important;
        }

        .details-content {
            background: #f5f7fa;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            text-align: left;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 20px;
                margin: 10px;
            }

            .dashboard-container h2 {
                font-size: 24px;
            }

            .dashboard-container h3 {
                font-size: 18px;
            }

            .actions-section {
                flex-direction: column;
            }

            .action-card {
                min-width: unset;
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
                font-weight: bold;
                text-align: left;
                color: #2c3e50;
            }

            .welcome-section, .action-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php if (!empty($success_message)): ?>
            <div class="success-msg"><?= $success_message ?></div>
        <?php endif; ?>

        <div class="welcome-section">
            <h2>Welcome, <?= htmlspecialchars($employer['name']) ?>!</h2>
            <p>You are logged in as <strong>OJT Supervisor</strong>.</p>
        </div>

        <h3>Quick Actions</h3>
        <div class="actions-section">
            <div class="action-card">
                <div class="icon">👤</div>
                <a href="add_student.php">Add New Student</a>
            </div>
            <div class="action-card">
                <div class="icon">📋</div>
                <a href="create_project.php">Create Project</a>
            </div>
            <div class="action-card">
                <div class="icon">✅</div>
                <a href="manage_projects.php">Manage Projects</a>
            </div>
            <div class="action-card">
                <div class="icon">📁</div>
                <a href="upload_documents.php">Upload Documents</a>
            </div>
            <div class="action-card">
                <div class="icon">🚪</div>
                <a href="logout.php">Logout</a>
            </div>
        </div>

        <h3>Attendance Records</h3>
