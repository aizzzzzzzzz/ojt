<?php
session_start();
include_once __DIR__ . '/private/config.php';
include_once __DIR__ . '/includes/email.php';

if (!isset($_SESSION['user_type'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$test_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = $_POST['test_email'] ?? '';

    if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $result = send_test_email($test_email);
        if ($result === true) {
            $message = '<div class="alert alert-success">Test email sent successfully to ' . htmlspecialchars($test_email) . '</div>';
        } else {
            $message = '<div class="alert alert-danger">Failed to send test email: ' . htmlspecialchars($result) . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning">Please enter a valid email address.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email Functionality</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Test Email Configuration</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <?php echo $message; ?>
                        <?php endif; ?>

                        <p>This tool allows you to test the email configuration by sending a test email.</p>

                        <form method="post">
                            <div class="mb-3">
                                <label for="test_email" class="form-label">Test Email Address</label>
                                <input type="email" class="form-control" id="test_email" name="test_email"
                                       value="<?php echo htmlspecialchars($test_email); ?>" required>
                                <div class="form-text">Enter an email address to send the test message to.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">Send Test Email</button>
                            <a href="index.php" class="btn btn-secondary">Back to Home</a>
                        </form>

                        <hr>
                        <h5>Current Email Configuration:</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><strong>SMTP Host:</strong> <?php echo htmlspecialchars($email_config['smtp_host']); ?></li>
                            <li class="list-group-item"><strong>SMTP Port:</strong> <?php echo htmlspecialchars($email_config['smtp_port']); ?></li>
                            <li class="list-group-item"><strong>Encryption:</strong> <?php echo htmlspecialchars($email_config['smtp_encryption']); ?></li>
                            <li class="list-group-item"><strong>From Email:</strong> <?php echo htmlspecialchars($email_config['from_email']); ?></li>
                            <li class="list-group-item"><strong>From Name:</strong> <?php echo htmlspecialchars($email_config['from_name']); ?></li>
                        </ul>

                        <div class="alert alert-info mt-3">
                            <strong>Note:</strong> To configure email settings, set environment variables or modify the $email_config array in private/config.php.
                            For Gmail, you may need to generate an App Password if 2FA is enabled.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
