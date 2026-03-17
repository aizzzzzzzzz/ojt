<?php

$phpmailerBase = __DIR__ . '/../lib/phpmailer/PHPMailer-master/src';
$phpmailerFiles = [
    $phpmailerBase . '/PHPMailer.php',
    $phpmailerBase . '/SMTP.php',
    $phpmailerBase . '/Exception.php'
];
$phpmailerAvailable = true;
foreach ($phpmailerFiles as $file) {
    if (!is_file($file)) {
        $phpmailerAvailable = false;
        error_log("PHPMailer file missing: " . $file);
    }
}

if ($phpmailerAvailable) {
    require_once $phpmailerFiles[0];
    require_once $phpmailerFiles[1];
    require_once $phpmailerFiles[2];
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function send_email($to, $subject, $body, $altBody = '', $attachments = []) {
    global $email_config;

    if (!isset($email_config) || empty($email_config['smtp_host'])) {
        return "Email configuration not found. Please configure email settings in config.php";
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return "Email library not available on this server.";
    }

    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host       = $email_config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $email_config['smtp_username'];
        $mail->Password   = $email_config['smtp_password'];
        $mail->SMTPSecure = $email_config['smtp_encryption'];
        $mail->Port       = $email_config['smtp_port'];

        $mail->setFrom($email_config['from_email'], $email_config['from_name']);
        $mail->addReplyTo($email_config['reply_to_email'], $email_config['reply_to_name']);
        $mail->addAddress($to);

        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        if (!empty($altBody)) {
            $mail->AltBody = $altBody;
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return "Email could not be sent. Error: " . $mail->ErrorInfo;
    }
}

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

function send_evaluation_notification($student_email, $student_name, $supervisor_name, $evaluation_passed = null, $average_rating = null) {
    $subject = "OJT Evaluation Result - " . $student_name;
    $header_title = "Evaluation Completed";
    $header_color = "#007bff";
    $result_message = "<p>Your OJT evaluation has been completed by your supervisor, <strong>$supervisor_name</strong>.</p><p>Please log in to your account to view your evaluation details.</p>";
    $result_alt = "Your OJT evaluation has been completed by your supervisor, $supervisor_name.\n\nPlease log in to your account to view your evaluation details.";

    if ($evaluation_passed === true) {
        $header_title = "Evaluation Result: Passed";
        $header_color = "#28a745";
        $average_line = $average_rating !== null
            ? "<p>Your average rating is <strong>" . number_format((float) $average_rating, 2) . "</strong>.</p>"
            : "";
        $average_alt = $average_rating !== null
            ? "\nAverage Rating: " . number_format((float) $average_rating, 2)
            : "";
        $result_message = "<p>Your OJT evaluation has been completed by your supervisor, <strong>$supervisor_name</strong>.</p><p><strong>Result:</strong> Passed</p>$average_line<p>If all requirements are complete, your certificate can now be processed in the OJT system.</p>";
        $result_alt = "Your OJT evaluation has been completed by your supervisor, $supervisor_name.\n\nResult: Passed$average_alt\n\nIf all requirements are complete, your certificate can now be processed in the OJT system.";
    } elseif ($evaluation_passed === false) {
        $header_title = "Evaluation Result: Not Passed";
        $header_color = "#dc3545";
        $average_line = $average_rating !== null
            ? "<p>Your average rating is <strong>" . number_format((float) $average_rating, 2) . "</strong>.</p>"
            : "";
        $average_alt = $average_rating !== null
            ? "\nAverage Rating: " . number_format((float) $average_rating, 2)
            : "";
        $result_message = "<p>Your OJT evaluation has been completed by your supervisor, <strong>$supervisor_name</strong>.</p><p><strong>Result:</strong> Not Passed</p>$average_line<p>Please review your supervisor's feedback and coordinate your next steps.</p>";
        $result_alt = "Your OJT evaluation has been completed by your supervisor, $supervisor_name.\n\nResult: Not Passed$average_alt\n\nPlease review your supervisor's feedback and coordinate your next steps.";
    }

    $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: $header_color; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .footer { text-align: center; padding: 10px; color: #666; }
                .button { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>$header_title</h2>
                </div>
                <div class='content'>
                    <p>Dear <strong>$student_name</strong>,</p>
                    $result_message
                </div>
                <div class='footer'>
                    <p>This is an automated message from the OJT System.</p>
                </div>
            </div>
        </body>
        </html>
    ";

    $altBody = "Dear $student_name,\n\n$result_alt\n\nThis is an automated message from the OJT System.";

    return send_email($student_email, $subject, $body, $altBody);
}

function send_certificate_notification($student_email, $student_name, $supervisor_name, $certificate_no = null) {
    $subject = "OJT Certificate Generated - " . $student_name;
    $header_title = "Certificate Generated";
    $header_color = "#28a745";
    $cert_line = $certificate_no ? "<p><strong>Certificate No:</strong> " . htmlspecialchars($certificate_no) . "</p>" : "";
    $cert_alt = $certificate_no ? "\nCertificate No: " . $certificate_no : "";

    $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: $header_color; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .footer { text-align: center; padding: 10px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>$header_title</h2>
                </div>
                <div class='content'>
                    <p>Dear <strong>$student_name</strong>,</p>
                    <p>Your OJT certificate has been generated by your supervisor, <strong>$supervisor_name</strong>.</p>
                    $cert_line
                    <p>You can now log in to your account to download your certificate.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from the OJT System.</p>
                </div>
            </div>
        </body>
        </html>
    ";

    $altBody = "Dear $student_name,\n\nYour OJT certificate has been generated by your supervisor, $supervisor_name.$cert_alt\n\nYou can now log in to your account to download your certificate.\n\nThis is an automated message from the OJT System.";

    return send_email($student_email, $subject, $body, $altBody);
}

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
