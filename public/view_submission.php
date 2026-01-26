<?php
session_start();
include __DIR__ . '/../private/config.php';

// Check if user is logged in (student or employer)
if (!isset($_SESSION['student_id']) && !isset($_SESSION['employer_id'])) {
    header("Location: index.php");
    exit;
}

$submission_id = $_GET['submission_id'] ?? 0;
if (!$submission_id) {
    die("Invalid submission ID.");
}

// Fetch submission details
$stmt = $pdo->prepare("SELECT ps.file_path FROM project_submissions ps WHERE ps.submission_id = ?");
$stmt->execute([$submission_id]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    die("Submission not found.");
}

$file_path = $submission['file_path'];
$full_path = __DIR__ . '/../' . $file_path;

if (!file_exists($full_path)) {
    die("File not found.");
}

// Read and output the content safely
$content = file_get_contents($full_path);
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submission</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container" style="max-width: 1200px; margin: 20px auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
        <h2>Project Submission</h2>
        <pre><code><?= htmlspecialchars($content) ?></code></pre>
        <a href="javascript:history.back()" class="btn btn-primary">Back</a>
    </div>
</body>
</html>
?>
