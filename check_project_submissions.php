<?php
require 'private/config.php';

try {
    echo "=== PROJECT_SUBMISSIONS TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE project_submissions');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . ' - ' . $col['Null'] . ' - ' . $col['Key'] . ' - ' . $col['Default'] . ' - ' . $col['Extra'] . "\n";
    }

    echo "\n=== SAMPLE PROJECT_SUBMISSIONS DATA ===\n";
    $stmt = $pdo->query('SELECT * FROM project_submissions LIMIT 5');
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($submissions as $sub) {
        echo 'ID: ' . $sub['submission_id'] . ', Project: ' . ($sub['project_id'] ?? 'NULL') . ', Student: ' . ($sub['student_id'] ?? 'NULL') . ', Status: ' . ($sub['status'] ?? 'NULL') . "\n";
    }

} catch (Exception $e) {
    echo 'Exception: ' . $e->getMessage() . "\n";
}
?>
