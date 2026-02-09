<?php
require_once '../private/config.php';
require_once '../includes/audit.php';

// Test log_activity function
try {
    // Dummy data for testing
    $user_id = 1;
    $role = 'student';
    $action = 'Test activity log';

    // Call the function
    log_activity($pdo, $user_id, $role, $action);

    echo "log_activity function executed successfully.\n";

    // Optional: Verify insertion by querying the table
    $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? AND role = ? AND action = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id, $role, $action]);
    $result = $stmt->fetch();

    if ($result) {
        echo "Record inserted successfully: ID " . $result['id'] . ", Created at: " . $result['created_at'] . "\n";
    } else {
        echo "Record not found in database.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
