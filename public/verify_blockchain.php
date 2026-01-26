<?php
include __DIR__ . '/../private/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hash'])) {
    $hash = sanitize_input($_GET['hash']);

    // Check if hash exists in database (assuming we store hashes)
    $stmt = $pdo->prepare("SELECT * FROM certificate_hashes WHERE hash = ? LIMIT 1");
    $stmt->execute([$hash]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        echo "<h2>✅ Certificate Verified</h2>";
        echo "<p>Hash: " . htmlspecialchars($hash) . "</p>";
        echo "<p>Student ID: " . htmlspecialchars($record['student_id']) . "</p>";
        echo "<p>Generated At: " . htmlspecialchars($record['generated_at']) . "</p>";
    } else {
        echo "<h2>❌ Certificate Not Verified</h2>";
        echo "<p>The provided hash does not match any verified certificate.</p>";
    }
} else {
    echo "<h2>Blockchain Certificate Verification</h2>";
    echo "<form method='GET'>";
    echo "<input type='text' name='hash' placeholder='Enter Certificate Hash' required>";
    echo "<button type='submit'>Verify</button>";
    echo "</form>";
}
?>
