<?php
session_start();
require_once __DIR__ . '/../private/config.php';

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== "employer") {
    header("Location: employer_login.php");
    exit;
}

// Get all students
$stmt = $pdo->prepare("SELECT student_id, username FROM students");
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $msg = "Invalid request.";
    } else {
        $student_id = sanitize_input($_POST['student_id']);
        $reason = sanitize_input($_POST['reason']);
        $date = date("Y-m-d");

        $check = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND log_date = ?");
        $check->execute([$student_id, $date]);
        $result = $check->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            $stmt = $pdo->prepare("INSERT INTO attendance (student_id, log_date, status, reason) VALUES (?, ?, 'Absent', ?)");
            $stmt->execute([$student_id, $date, $reason]);
            $msg = "Absent recorded!";
        } else {
            $msg = "Attendance already exists for today!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Student Absent</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-gradient-admin">
    <div class="form-container">
        <h2>Mark Student Absent</h2>
        <?php if (!empty($msg)): ?>
            <div class="success-msg"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <div class="form-group">
                <label for="student_id">Select Student:</label>
                <select id="student_id" name="student_id" required>
                    <option value="">-- Select Student --</option>
                    <?php foreach($students as $row): ?>
                        <option value="<?= htmlspecialchars($row['student_id']) ?>"><?= htmlspecialchars($row['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="reason">Reason:</label>
                <input type="text" id="reason" name="reason" required>
            </div>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>
</body>
</html>
