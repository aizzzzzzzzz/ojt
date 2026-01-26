<?php
/**
 * One-time setup script to create projects and project_submissions tables
 * Access this file once at: http://localhost/ojt/setup_projects.php
 * Then delete or rename it after successful execution
 */

require_once __DIR__ . '/private/config.php';

$setup_messages = [];
$setup_errors = [];

try {
    // Create projects table
    $sql1 = "CREATE TABLE IF NOT EXISTS projects (
        project_id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql1);
    $setup_messages[] = "✓ Projects table created successfully";

    // Create project_submissions table
    $sql2 = "CREATE TABLE IF NOT EXISTS project_submissions (
        submission_id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        project_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        status VARCHAR(50) DEFAULT 'submitted',
        submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        remarks LONGTEXT,
        submission_status VARCHAR(50) DEFAULT 'pending',
        grade INT,
        feedback LONGTEXT,
        graded_at TIMESTAMP NULL,
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
        INDEX idx_student (student_id),
        INDEX idx_project (project_id),
        INDEX idx_submitted_at (submission_date)
    )";
    $pdo->exec($sql2);
    $setup_messages[] = "✓ Project submissions table created successfully";

    // Check if projects already exist
    $check = $pdo->query("SELECT COUNT(*) as count FROM projects")->fetch();
    if ($check['count'] == 0) {
        // Add sample projects
        $sql3 = "INSERT INTO projects (title, description) VALUES 
        ('Web Form Validation', 'Create an HTML form with CSS styling and PHP/JavaScript validation. Validate email, password strength, and form submission.'),
        ('Todo Application', 'Build a simple Todo application using HTML, CSS, and your choice of backend language. Features: add, delete, mark complete.'),
        ('Simple Calculator', 'Create a calculator application with basic operations (add, subtract, multiply, divide). Can use any supported language.'),
        ('Login System', 'Develop a secure login system with HTML forms, CSS styling, and backend authentication using PHP or Java.')";
        $pdo->exec($sql3);
        $setup_messages[] = "✓ Sample projects added successfully (4 projects)";
    } else {
        $setup_messages[] = "ℹ Projects already exist, skipping sample data insertion";
    }

    $success = true;
} catch (PDOException $e) {
    $setup_errors[] = "Database Error: " . $e->getMessage();
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OJT Setup - Projects Module</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #74ebd5, #ACB6E5);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="setup-container">
    <h1>🛠️ OJT Projects Module Setup</h1>

    <?php if (!empty($setup_errors)): ?>
        <?php foreach ($setup_errors as $error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-box">
            <h5>✓ Setup Completed Successfully!</h5>
            <ul style="margin: 15px 0 0 0; padding-left: 20px;">
                <?php foreach ($setup_messages as $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="info-box">
            <strong>⚠️ Next Steps:</strong>
            <ol style="margin: 10px 0 0 0; padding-left: 20px;">
                <li>Go to <a href="student_dashboard.php">Student Dashboard</a> and start using the projects feature</li>
                <li><strong>Security:</strong> Delete this file (setup_projects.php) from your server as it's no longer needed</li>
                <li>You can now create new projects in the database as needed</li>
            </ol>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">
            <strong>Setup Failed!</strong> Please check the errors above and try again.
        </div>
    <?php endif; ?>
</div>
</body>
</html>
