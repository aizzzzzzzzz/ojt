<?php
session_start();

if(!isset($_SESSION['employer_id']) || $_SESSION['role'] !== "employer"){
    header("Location: employer_login.php");
    exit;
}

require_once __DIR__ . '/../private/config.php';

if (!isset($_GET['student_id']) || !is_numeric($_GET['student_id'])) {
    die("Student ID is required and must be numeric.");
}

$student_id = intval($_GET['student_id']);

// Check if student belongs to current employer (IDOR prevention)
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ? AND employer_id = ?");
$stmt->execute([$student_id, $_SESSION['employer_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found or access denied.");
}

$stmt = $pdo->prepare("
    SELECT e.*, emp.username AS employer_name
    FROM evaluations e
    JOIN employers emp ON e.employer_id = emp.employer_id
    WHERE e.student_id = ?
");
$stmt->execute([$student_id]);
$evaluation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evaluation) {
    die("No evaluation found for this student.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Report</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .container { max-width: 800px; margin: 30px auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        .comments { margin-top: 20px; padding: 15px; background: #f7f7f7; border-left: 5px solid #007BFF; }
        .back { display: inline-block; margin-top: 20px; text-decoration: none; color: white; background: #007BFF; padding: 10px 15px; border-radius: 5px; }
        .back:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Evaluation Report</h2>

        <p><strong>Student:</strong> <?= htmlspecialchars($student['username']) ?></p>
        <p><strong>Evaluated by:</strong> <?= htmlspecialchars($evaluation['employer_name']) ?></p>
        <p><strong>Date:</strong> <?= htmlspecialchars($evaluation['evaluation_date']) ?></p>

        <table>
    <tr>
        <th>Criteria</th>
        <th>Score (1-5)</th>
    </tr>
    <tr>
        <td>Attendance</td>
        <td><?= $evaluation['attendance_score'] ?></td>
    </tr>
    <tr>
        <td>Performance</td>
        <td><?= $evaluation['performance_score'] ?></td>
    </tr>
    <tr>
        <td>Communication</td>
        <td><?= $evaluation['communication_score'] ?></td>
    </tr>
    <tr>
        <td>Attitude</td>
        <td><?= $evaluation['attitude_score'] ?></td>
    </tr>
    <tr>
        <th>Overall Score</th>
        <th><?= $evaluation['overall_score'] ?></th>
    </tr>
</table>


        <div class="comments">
            <h3>Comments</h3>
            <p><?= nl2br(htmlspecialchars($evaluation['comments'])) ?></p>
        </div>

        <a href="supervisor_dashboard.php
" class="back">⬅ Back</a>
    </div>
</body>
</html>
