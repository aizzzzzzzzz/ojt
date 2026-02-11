<?php
// test_project_notifications.php - Test the new project notification functions
include 'private/config.php';
require_once 'includes/email.php';

echo "Testing new project notification functions...\n";

// Get a student and supervisor for testing
$student_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM students LIMIT 1");
$student_stmt->execute();
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

$supervisor_stmt = $pdo->prepare("SELECT name FROM employers LIMIT 1");
$supervisor_stmt->execute();
$supervisor = $supervisor_stmt->fetch(PDO::FETCH_ASSOC);

if (!$student || !$supervisor) {
    echo "No student or supervisor found for testing.\n";
    exit;
}

$student_name = ucwords(strtolower($student['first_name'] . ' ' . $student['last_name']));
$supervisor_name = $supervisor['name'];
$remarks = "Great work! Well done.";

echo "Testing with:\n";
echo "- Student: $student_name\n";
echo "- Email: " . $student['email'] . "\n";
echo "- Supervisor: $supervisor_name\n";
echo "- Remarks: $remarks\n\n";

// Test approval notification
echo "Testing send_project_approval_notification()...\n";
$result1 = send_project_approval_notification($student['email'], $student_name, $supervisor_name, $remarks);
if ($result1 === true) {
    echo "✓ Approval notification sent successfully!\n";
} else {
    echo "✗ Approval notification failed: $result1\n";
}

// Test rejection notification
echo "\nTesting send_project_rejection_notification()...\n";
$result2 = send_project_rejection_notification($student['email'], $student_name, $supervisor_name);
if ($result2 === true) {
    echo "✓ Rejection notification sent successfully!\n";
} else {
    echo "✗ Rejection notification failed: $result2\n";
}

echo "\nTesting completed.\n";
echo "Note: Check the email content to verify proper capitalization and supervisor name inclusion.\n";
?>
