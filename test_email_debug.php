<?php
// test_email_debug.php - Debug email functionality
include 'private/config.php';
include 'includes/email.php';

echo "Testing email configuration...\n";
echo "SMTP Host: " . $email_config['smtp_host'] . "\n";
echo "SMTP Port: " . $email_config['smtp_port'] . "\n";
echo "SMTP Username: " . $email_config['smtp_username'] . "\n";
echo "From Email: " . $email_config['from_email'] . "\n";

echo "\nSending test email...\n";
$result = send_test_email('test@example.com');
if ($result === true) {
    echo "Email sent successfully!\n";
} else {
    echo "Email failed: " . $result . "\n";
}
?>
