<?php
session_start();
require_once('../private/config.php');

if (!isset($_SESSION['change_password']) || $_SESSION['change_password'] !== true) {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'student') {
            header("Location: student_dashboard.php");
        } elseif ($_SESSION['role'] === 'employer') {
            header("Location: supervisor_dashboard.php");
        }
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $isFirstTimeStudentOrSupervisor = (
        !empty($_SESSION['first_time_login']) &&
        in_array($_SESSION['role'] ?? '', ['student', 'employer'], true)
    );

    if (empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif ($isFirstTimeStudentOrSupervisor && !validate_password($new_password)) {
        $error = "Password must be at least 8 characters with uppercase, lowercase, number, and special character.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        if ($_SESSION['role'] === 'student') {
            $stmt = $pdo->prepare("UPDATE students SET password = ?, password_changed = 1 WHERE student_id = ?");
            $stmt->execute([$hashed_password, $_SESSION['student_id']]);
        } elseif ($_SESSION['role'] === 'employer') {
            $stmt = $pdo->prepare("UPDATE employers SET password = ?, password_changed = 1 WHERE employer_id = ?");
            $stmt->execute([$hashed_password, $_SESSION['employer_id']]);
        }

        unset($_SESSION['change_password']);
        unset($_SESSION['first_time_login']);
        $success = "Password changed successfully! Redirecting to dashboard...";

        echo "<script>
            setTimeout(function() {
                window.location.href = '" . ($_SESSION['role'] === 'student' ? 'student_dashboard.php' : 'supervisor_dashboard.php') . "';
            }, 2000);
        </script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }

        .change-password-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .change-password-container h2 {
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #555;
        }

        .change-password-container input[type="password"] {
            width: 100%;
            padding: 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            outline: none;
        }

        .change-password-container input[type="password"]:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }

        .change-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(90deg, #28a745, #85e085);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.3s ease, transform 0.2s ease;
            margin-top: 10px;
        }

        .change-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .change-btn:active {
            transform: translateY(0);
        }

        .error-msg {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid #fecaca;
            font-size: 14px;
            text-align: left;
        }

        .success-msg {
            background: #d1fae5;
            color: #065f46;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid #a7f3d0;
            font-size: 14px;
            text-align: left;
        }

        @media (max-width: 480px) {
            .change-password-container {
                margin: 20px;
                padding: 30px 20px;
                width: auto;
            }

            .change-password-container h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="change-password-container">
        <h2>Change Password</h2>
        <p>This is your first time logging in. Please change your password to continue.</p>
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
            </div>
            <button type="submit" class="change-btn">Change Password</button>
        </form>
    </div>
</body>
</html>
