<?php
// includes/email.php - Email utility functions using PHPMailer

require_once __DIR__ . '/../lib/phpmailer/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../lib/phpmailer/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/../lib/phpmailer/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using PHPMailer
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $altBody Plain text alternative (optional)
 * @param array $attachments Array of file paths to attach (optional)
 * @return bool|string True on success, error message on failure
 */
function send_email($to, $subject, $body, $altBody = '', $attachments = []) {
    global $email_config;

    // Check if email config is available
    if (!isset($email_config) || empty($email_config['smtp_host'])) {
        return "Email configuration not found. Please configure email settings in config.php";
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->SMTPDebug = 0; // Disable debug output in production
        $mail->isSMTP();
        $mail->Host       = $email_config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $email_config['smtp_username'];
        $mail->Password   = $email_config['smtp_password'];
        $mail->SMTPSecure = $email_config['smtp_encryption'];
        $mail->Port       = $email_config['smtp_port'];

        // Recipients
        $mail->setFrom($email_config['from_email'], $email_config['from_name']);
        $mail->addReplyTo($email_config['reply_to_email'], $email_config['reply_to_name']);
        $mail->addAddress($to);

        // Attachments
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        if (!empty($altBody)) {
            $mail->AltBody = $altBody;
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        // Log the error
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return "Email could not be sent. Error: " . $mail->ErrorInfo;
    }
}

/**
 * Send a test email to verify configuration
 *
 * @param string $test_email Email address to send test to
 * @return bool|string True on success, error message on failure
 */
function send_test_email($test_email) {
    $subject = "OJT System - Email Test";
    $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .footer { text-align: center; padding: 10px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>OJT System Email Test</h2>
                </div>
                <div class='content'>
                    <p>This is a test email to verify that the email configuration is working correctly.</p>
                    <p>If you received this email, the email functionality is properly configured.</p>
                    <p><strong>Test sent at:</strong> " . date('Y-m-d H:i:s') . "</p>
                </div>
                <div class='footer'>
                    <p>This is an automated test message from the OJT System.</p>
                </div>
            </div>
        </body>
        </html>
    ";

    $altBody = "OJT System Email Test\n\nThis is a test email to verify that the email configuration is working correctly.\n\nIf you received this email, the email functionality is properly configured.\n\nTest sent at: " . date('Y-m-d H:i:s');

    return send_email($test_email, $subject, $body, $altBody);
}

/**
 * Send notification email for student evaluation completion
 *
 * @param string $student_email Student's email address
 * @param string $student_name Student's full name
 * @param string $supervisor_name Supervisor's name
 * @return bool|string True on success, error message on failure
 */
function send_evaluation_notification($student_email, $student_name, $supervisor_name) {
    $subject = "OJT Evaluation Completed - " . $student_name;

    $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .footer { text-align: center; padding: 10px; color: #666; }
                .button { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Evaluation Completed</h2>
                </div>
                <div class='content'>
                    <p>Dear <strong>$student_name</strong>,</p>
                    <p>Your OJT evaluation has been completed by your supervisor, <strong>$supervisor_name</strong>.</p>
                    <p>You can now download your completion certificate from the OJT system.</p>
                    <p>Please log in to your account to access your certificate.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from the OJT System.</p>
                </div>
            </div>
        </body>
        </html>
    ";

    $altBody = "Dear $student_name,\n\nYour OJT evaluation has been completed by your supervisor, $supervisor_name.\n\nYou can now download your completion certificate from the OJT system.\n\nPlease log in to your account to access your certificate.\n\nThis is an automated message from the OJT System.";

    return send_email($student_email, $subject, $body, $altBody);
}

/**
 * Send notification email for project approval
 *
 * @param string $student_email Student's email address
 * @param string $student_name Student's full name
 * @param string $supervisor_name Supervisor's name
 * @param string $remarks Optional remarks from supervisor
 * @return bool|string True on success, error message on failure
 */
function send_project_approval_notification($student_email, $student_name, $supervisor_name, $remarks = '') {
    $subject = "OJT Project Submission Approved - " . $student_name;

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
                    <p>Your project submission has been approved by your supervisor, <strong>$supervisor_name</strong>.</p>
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

    $altBody = "Dear $student_name,\n\nYour project submission has been approved by your supervisor, $supervisor_name.\n\n" . (!empty($remarks) ? "Remarks: $remarks\n\n" : "") . "You can now proceed with the next steps or check your dashboard for updates.\n\nThis is an automated message from the OJT System.";

    return send_email($student_email, $subject, $body, $altBody);
}

/**
 * Send notification email for project rejection
 *
 * @param string $student_email Student's email address
 * @param string $student_name Student's full name
 * @param string $supervisor_name Supervisor's name
 * @return bool|string True on success, error message on failure
 */
function send_project_rejection_notification($student_email, $student_name, $supervisor_name) {
    $subject = "OJT Project Submission Rejected - " . $student_name;

    $body = "
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
                    <p>Your project submission has been rejected by your supervisor, <strong>$supervisor_name</strong>.</p>
                    <p>Please review the submission requirements and resubmit if necessary.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from the OJT System.</p>
                </div>
            </div>
        </body>
        </html>
    ";

    $altBody = "Dear $student_name,\n\nYour project submission has been rejected by your supervisor, $supervisor_name.\n\nPlease review the submission requirements and resubmit if necessary.\n\nThis is an automated message from the OJT System.";

    return send_email($student_email, $subject, $body, $altBody);
}

/**
 * Send notification email for attendance verification
 *
 * @param string $student_email Student's email address
 * @param string $student_name Student's full name
 * @param string $date Date of attendance
 * @param string $status Attendance status
 * @return bool|string True on success, error message on failure
 */
function send_attendance_notification($student_email, $student_name, $date, $status) {
    $subject = "OJT Attendance Update - " . date('M d, Y', strtotime($date));

    $status_color = ($status === 'present') ? '#28a745' : (($status === 'absent') ? '#dc3545' : '#ffc107');

    $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: $status_color; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .footer { text-align: center; padding: 10px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Attendance " . ucfirst($status) . "</h2>
                </div>
                <div class='content'>
                    <p>Dear <strong>$student_name</strong>,</p>
                    <p>Your attendance for <strong>" . date('F d, Y', strtotime($date)) . "</strong> has been marked as <strong>" . ucfirst($status) . "</strong>.</p>
                    <p>Please log in to the OJT system to view your attendance records.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from the OJT System.</p>
                </div>
            </div>
        </body>
        </html>
    ";

    $altBody = "Dear $student_name,\n\nYour attendance for " . date('F d, Y', strtotime($date)) . " has been marked as " . ucfirst($status) . ".\n\nPlease log in to the OJT system to view your attendance records.\n\nThis is an automated message from the OJT System.";

    return send_email($student_email, $subject, $body, $altBody);
}
?>
