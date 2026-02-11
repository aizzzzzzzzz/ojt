<?php
require 'private/config.php';
require 'includes/db.php';

try {
    // Test data
    $project_id = 1;
    $student_id = 26; // From sample data
    $file_path = 'test_file.txt';
    $remarks = 'Test submission';

    echo "=== TESTING REJECTED PROJECT RESUBMISSION ===\n";

    // First, set the existing submission to Rejected
    $update_stmt = $pdo->prepare("UPDATE project_submissions SET status = 'Rejected', graded_at = NOW() WHERE submission_id = 6");
    $update_stmt->execute();
    echo "Set submission ID 6 to Rejected and graded.\n";

    // Check current state
    $stmt = $pdo->prepare("SELECT * FROM project_submissions WHERE submission_id = 6");
    $stmt->execute();
    $before = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Before resubmission: Status=" . $before['status'] . ", Graded_at=" . ($before['graded_at'] ?? 'NULL') . "\n";

    // Now call the function to submit again (simulate resubmission)
    submit_project($pdo, $project_id, $student_id, $file_path, $remarks);

    echo "Resubmission successful!\n";

    // Check after resubmission
    $stmt = $pdo->prepare("SELECT * FROM project_submissions WHERE submission_id = 6");
    $stmt->execute();
    $after = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "After resubmission: Status=" . $after['status'] . ", Graded_at=" . ($after['graded_at'] ?? 'NULL') . "\n";

    // Now simulate supervisor grading again
    $grade_stmt = $pdo->prepare("UPDATE project_submissions SET status = 'Approved', remarks = 'Good work after revision', graded_at = NOW() WHERE submission_id = 6");
    $grade_stmt->execute();
    echo "Supervisor graded the resubmitted project.\n";

    // Final check
    $stmt = $pdo->prepare("SELECT * FROM project_submissions WHERE submission_id = 6");
    $stmt->execute();
    $final = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Final state: Status=" . $final['status'] . ", Remarks=" . $final['remarks'] . ", Graded_at=" . ($final['graded_at'] ?? 'NULL') . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
