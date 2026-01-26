<?php
session_start();
if (!isset($_SESSION['employer_id'])) {
    header("Location: employer_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';

$employer_id = $_SESSION['employer_id'];
$success_message = '';

if (isset($_POST['submit'])) {
    $project_name = $_POST['project_name'];
    $description = $_POST['description'];
    $start_date = $_POST['start_date'];
    $due_date = $_POST['due_date'];
    $status = $_POST['status'];

    $stmt = $pdo->prepare("INSERT INTO projects (project_name, description, start_date, due_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$project_name, $description, $start_date, $due_date, $status, $employer_id])) {
        $success_message = "Project created successfully!";
    } else {
        $success_message = "Error creating project.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Project</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* Keep your dashboard form style consistent */
body { font-family: "Segoe UI", sans-serif; background: #e3f2fd; padding:20px; }
.dashboard-container { background: rgba(255,255,255,0.95); padding:30px; border-radius:15px; max-width:600px; margin:auto; }
.success-msg { background:#d4edda;color:#155724;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #c3e6cb; }
</style>
</head>
<body>
<div class="dashboard-container">
    <h2>Create New Project</h2>
    <a href="employer_dashboard.php" class="btn btn-outline-secondary mb-3">⬅ Back to Dashboard</a>
    <?php if ($success_message): ?>
        <div class="success-msg"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Project Name:</label><br>
        <input type="text" name="project_name" class="form-control" required><br>

        <label>Description:</label><br>
        <textarea name="description" class="form-control" rows="4"></textarea><br>

        <label>Start Date:</label><br>
        <input type="date" name="start_date" class="form-control" required><br>

        <label>Due Date:</label><br>
        <input type="date" name="due_date" class="form-control" required><br>

        <label>Status:</label><br>
        <select name="status" class="form-control">
            <option value="Pending">Pending</option>
            <option value="Ongoing">Ongoing</option>
            <option value="Completed">Completed</option>
        </select><br>

        <button type="submit" name="submit" class="btn btn-success mt-2">Create Project</button>
    </form>
</div>
</body>
</html>
