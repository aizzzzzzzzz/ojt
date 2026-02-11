<?php
// test_approval_email.php - Test approval email functionality
include 'private/config.php';
require_once 'includes/email.php';

echo "Testing approval email functionality...\n";

// Get a recent submission
$stmt = $pdo->prepare("SELECT ps.submission_id, s.first_name, s.last_name, s.email FROM project_submissions ps JOIN students s ON ps.student_id = s.student_id WHERE ps.status != 'Approved' ORDER BY ps.submission_id DESC LIMIT 1");
$stmt->execute();
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    echo "No suitable submission found for testing.\n";
    exit;
}

echo "Testing with submission ID: " . $submission['submission_id'] . ", Student: " . $submission['first_name'] . ' ' . $submission['last_name'] . ", Email: " . $submission['email'] . "\n";

// Simulate approval
$remarks = "Test approval remarks";
$student_name = $submission['first_name'] . ' ' . $submission['last_name'];
$subject = "OJT Project Submission Approved";
$body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .footer { text-align: center; padding: 10px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Submission Approved</h2>
            </div>
            <div class='content'>
                <p>Dear <strong>$student_name</strong>,</p>
                <p>Your project submission has been approved by your supervisor.</p>
                " . (!empty($remarks) ? "<p><strong>Remarks:</strong> $remarks</p>" : "") . "
                <p>You can now proceed with the next steps or check your dashboard for updates.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from the OJT System.</p>
            </div>
        </div>
    </body>
    </html>
";
$altBody = "Dear $student_name,\n\nYour project submission has been approved by your supervisor.\n\n" . (!empty($remarks) ? "Remarks: $remarks\n\n" : "") . "You can now proceed with the next steps or check your dashboard for updates.\n\nThis is an automated message from the OJT System.";

echo "Sending approval email...\n";
$email_result = send_email($submission['email'], $subject, $body, $altBody);
if ($email_result === true) {
    echo "Approval email sent successfully!\n";
} else {
    echo "Approval email failed: " . $email_result . "\n";
}

// Test rejection email
echo "\nTesting rejection email...\n";
$subject2 = "OJT Project Submission Rejected";
$body2 = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .footer { text-align: center; padding: 10px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Submission Rejected</h2>
            </div>
            <div class='content'>
                <p>Dear <strong>$student_name</strong>,</p>
                <p>Your project submission has been rejected by your supervisor.</p>
                <p>Please review the submission requirements and resubmit if necessary.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from the OJT System.</p>
            </div>
        </div>
    </body>
    </html>
";
$altBody2 = "Dear $student_name,\n\nYour project submission has been rejected by your supervisor.\n\nPlease review the submission requirements and resubmit if necessary.\n\nThis is an automated message from the OJT System.";

$email_result2 = send_email($submission['email'], $subject2, $body2, $altBody2);
if ($email_result2 === true) {
    echo "Rejection email sent successfully!\n";
} else {
    echo "Rejection email failed: " . $email_result2 . "\n";
}

echo "\nTesting completed.\n";
?>
