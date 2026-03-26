<?php
include __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/Blockchain.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hash'])) {
    $certificateNo = sanitize_input($_GET['hash']);

    $blockchain = new Blockchain();
    $result = $blockchain->verifyCertificate($certificateNo);
    
    $stmt = $pdo->prepare("SELECT * FROM certificate_hashes WHERE certificate_hash = ? LIMIT 1");
    $stmt->execute([$certificateNo]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    $dbVerified = (bool)$record;
    $chainVerified = !empty($result['verified']);
    $chainStatus = $result['chain_status'] ?? ($chainVerified ? 'confirmed' : 'pending');
    $mode = $result['mode'] ?? 'local_blockchain';

    if ($dbVerified) {
        echo "<h2>Certificate Found</h2>";
        echo "<p><strong>Certificate Number:</strong> " . htmlspecialchars($certificateNo) . "</p>";
        echo "<p><strong>Database Status:</strong> Yes</p>";
        echo "<p><strong>Blockchain Mode:</strong> " . htmlspecialchars($mode) . "</p>";

        if ($chainVerified) {
            echo "<p><strong>Blockchain Status:</strong> Confirmed</p>";
        } else {
            echo "<p><strong>Blockchain Status:</strong> " . htmlspecialchars(ucfirst($chainStatus)) . "</p>";
        }

        if (isset($record['student_id'])) {
            echo "<p><strong>Student ID:</strong> " . htmlspecialchars((string)$record['student_id']) . "</p>";
        }
        if (isset($record['generated_at'])) {
            echo "<p><strong>Generated At:</strong> " . htmlspecialchars((string)$record['generated_at']) . "</p>";
        }
        if (isset($record['data_hash']) && !empty($record['data_hash'])) {
            echo "<p><strong>Data Hash:</strong> " . htmlspecialchars((string)$record['data_hash']) . "</p>";
        }
        if (isset($record['tx_hash']) && !empty($record['tx_hash'])) {
            echo "<p><strong>Transaction Hash:</strong> " . htmlspecialchars((string)$record['tx_hash']) . "</p>";
        }

        if (isset($result['note']) && !empty($result['note'])) {
            echo "<p><em>" . htmlspecialchars($result['note']) . "</em></p>";
        }
    } else {
        echo "<h2>Certificate Not Found</h2>";
        echo "<p>The provided certificate number does not exist in the local records.</p>";
        echo "<p><strong>Blockchain Mode:</strong> " . htmlspecialchars($mode) . "</p>";
    }
} else {
    $blockchain = new Blockchain();
    $info = $blockchain->getBlockchainInfo();
    
    echo "<h2>🔗 Blockchain Certificate Verification</h2>";
    echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
    echo "<h3>Blockchain Status</h3>";
    echo "<p><strong>Mode:</strong> " . htmlspecialchars($info['mode']) . "</p>";
    if (isset($info['network'])) {
        echo "<p><strong>Network:</strong> " . htmlspecialchars($info['network']) . "</p>";
    }
    echo "<p><strong>Total Blocks:</strong> " . htmlspecialchars($info['total_blocks']) . "</p>";
    echo "<p><strong>Consensus Mechanism:</strong> " . htmlspecialchars($info['consensus']) . "</p>";
    echo "<p><strong>Difficulty:</strong> " . htmlspecialchars($info['difficulty']) . " (leading zeros required)</p>";
    echo "<p><strong>Chain Valid:</strong> " . ($info['is_valid'] ? '✅ Yes' : '❌ No') . "</p>";
    echo "</div>";
    
    echo "<form method='GET'>";
    echo "<label for='verify_cert_hash'>Enter Certificate Number:</label><br>";
    echo "<input id='verify_cert_hash' type='text' name='hash' placeholder='e.g., CERT-2026-1-001' required style='width: 100%; padding: 10px; margin: 10px 0;'>";
    echo "<button type='submit' style='padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;'>Verify on Blockchain</button>";
    echo "</form>";
}
?>
