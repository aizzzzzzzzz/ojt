<?php
session_start();
include_once __DIR__ . '/../private/config.php';

if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== "student") {
    header("Location: student_login.php");
    exit;
}

$student_id = (int)$_SESSION['student_id'];

if (!isset($_GET['file'])) {
    die("Invalid request.");
}

$fileName = basename($_GET['file']); // Prevent directory traversal
$filePath = __DIR__ . '/../storage/uploads/' . $fileName;

// Check if file exists and belongs to the student
$stmt = $pdo->prepare("SELECT * FROM project_submissions WHERE file_path = ? AND student_id = ? LIMIT 1");
$stmt->execute([$fileName, $student_id]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission || !file_exists($filePath)) {
    die("File not found or access denied.");
}

$content = file_get_contents($filePath);

if (!$content) {
    die("Unable to read file.");
}

// Determine content type
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if ($fileExt === 'php') {
    // For PHP files, try to execute them
    ob_start();
    try {
        eval('?>' . $content);
        $output = ob_get_clean();
    } catch (Exception $e) {
        $output = "Error executing PHP code: " . $e->getMessage();
    }
} elseif (in_array($fileExt, ['html', 'htm'])) {
    // For HTML files, output directly
    $output = $content;
} elseif ($fileExt === 'css') {
    // For CSS, wrap in a basic HTML structure
    $output = "<html><head><style>$content</style></head><body><h1>CSS Output</h1><p>This is how the CSS would look when applied to HTML.</p></body></html>";
} elseif ($fileExt === 'js') {
    // For JS, wrap in HTML with script tag
    $output = "<html><head><script>$content</script></head><body><h1>JavaScript Output</h1><p>Check the console for JavaScript execution.</p></body></html>";
} else {
    // For other files, just display as text
    $output = "<pre>" . htmlspecialchars($content) . "</pre>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Output - <?= htmlspecialchars($submission['project_name'] ?? 'Project') ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .output-container { max-width: 100%; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="output-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Output for: <?= htmlspecialchars($submission['project_name'] ?? 'Project') ?></h2>
            <a href="student_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
        <div class="border rounded p-3 bg-light">
            <?php echo $output; ?>
        </div>
    </div>
</body>
</html>
