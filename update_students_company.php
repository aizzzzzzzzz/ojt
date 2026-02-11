<?php
require 'private/config.php';

echo "Updating students' company_id based on created_by...\n";

try {
    // Update students table to set company_id based on their supervisor's company
    $updateStmt = $pdo->prepare("
        UPDATE students s
        JOIN employers e ON s.created_by = e.employer_id
        SET s.company_id = e.company_id
        WHERE s.created_by IS NOT NULL AND s.company_id IS NULL
    ");
    $updateStmt->execute();

    $affectedRows = $updateStmt->rowCount();
    echo "Updated $affectedRows students with company_id.\n";

    // Verify
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE company_id IS NOT NULL");
    $result = $stmt->fetch();
    echo "Total students with company_id: " . $result['count'] . "\n";

} catch (Exception $e) {
    echo 'Exception: ' . $e->getMessage() . "\n";
}
?>
