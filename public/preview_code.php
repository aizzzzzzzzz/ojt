<?php

session_start();
include_once __DIR__ . '/../private/config.php';

if (!isset($_SESSION['student_id'])) {
    http_response_code(403);
    echo "<p style='color: red;'>Access denied. Please login.</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "<p style='color: red;'>Method not allowed.</p>";
    exit;
}

$code = isset($_POST['code']) ? $_POST['code'] : '';

if (empty($code)) {
    echo "<p style='color: orange;'>No code to preview.</p>";
    exit;
}

$hasPHP = (strpos($code, '<?php') !== false || strpos($code, '<?') !== false || strpos($code, '<?=') !== false);

if ($hasPHP) {
    executePHPCode($code);
} else {
    outputHTMLCode($code);
}

function executePHPCode($code) {
    $tempFile = createTempPhpFile($code);
    if ($tempFile === null) {
        showPHPCodeWithoutExecution($code);
        return;
    }

    ob_start();
    
    $previousErrorReporting = error_reporting(E_ALL);
    $previousDisplayErrors = ini_get('display_errors');
    ini_set('display_errors', '1');
    
    try {
        include $tempFile;
    } catch (Throwable $e) {
        echo "<div style=\"background: #ffebee; border: 1px solid #f44336; padding: 10px; margin: 10px 0; border-radius: 4px;\">";
        echo "<strong style=\"color: #c62828;\">Error:</strong> " . htmlspecialchars($e->getMessage());
        echo "</div>";
    } finally {
        if (is_file($tempFile)) {
            @unlink($tempFile);
        }
    }
    
    error_reporting($previousErrorReporting);
    ini_set('display_errors', $previousDisplayErrors);
    
    $output = ob_get_clean();
    
    echo $output;
}

function createTempPhpFile($code) {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ojt_preview';
    if (!is_dir($tempDir) && !@mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
        return null;
    }

    $tempFile = tempnam($tempDir, 'preview_');
    if ($tempFile === false) {
        return null;
    }

    $phpFile = $tempFile . '.php';
    if (!@rename($tempFile, $phpFile)) {
        $phpFile = $tempFile;
    }

    $bytes = @file_put_contents($phpFile, $code);
    if ($bytes === false) {
        @unlink($phpFile);
        return null;
    }

    return $phpFile;
}

function showPHPCodeWithoutExecution($code) {
    echo "<div style=\"background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 10px 0; border-radius: 4px;\">";
    echo "<strong style=\"color: #856404;\">⚠️ PHP Preview Not Available</strong><br>";
    echo "<span style=\"color: #856404;\">PHP preview is unavailable on this server due to execution restrictions.</span>";
    echo "</div>";
    
    echo "<div style=\"margin-top: 15px;\">";
    echo "<h4 style=\"margin: 0 0 10px 0; color: #333;\">Your PHP Code:</h4>";
    echo "<pre style=\"background: #f5f5f5; border: 1px solid #ddd; padding: 15px; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 14px; line-height: 1.5;\">";
    echo htmlspecialchars($code);
    echo "</pre>";
    echo "</div>";
    
    echo "<div style=\"margin-top: 15px; padding: 10px; background: #e3f2fd; border-radius: 4px;\">";
    echo "<strong style=\"color: #1565c0;\">💡 Tip:</strong> ";
    echo "<span style=\"color: #1565c0;\">You can still submit your PHP code. The preview only shows the code, but it will be saved and graded correctly.</span>";
    echo "</div>";
}

function outputHTMLCode($code) {
    echo $code;
}
?>
