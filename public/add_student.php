<?php
session_start();
require '../private/config.php';

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username       = trim($_POST['username']);
    $first_name     = trim($_POST['first_name']);
    $middle_name    = trim($_POST['middle_name']);
    $last_name      = trim($_POST['last_name']);
    $password       = $_POST['password'];
    $required_hours = trim($_POST['required_hours']);
    $course         = trim($_POST['course']);
    $school         = trim($_POST['school']);

    if (!empty($username) && !empty($first_name) && !empty($last_name) && !empty($password) && !empty($required_hours) && !empty($course) && !empty($school)) {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->rowCount() > 0) {
            $error = "Username already taken!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO students (username, password, first_name, middle_name, last_name, required_hours, course, school) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$username, $hashed_password, $first_name, $middle_name, $last_name, $required_hours, $course, $school])) {
                $success = "Student added successfully!";
            } else {
                $error = "Error adding student. Please try again.";
            }
        }
    } else {
        $error = "All fields are required!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }

        .add-student-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .add-student-container h2 {
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
        }

        .success-msg {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            font-size: 16px;
            text-align: left;
            line-height: 1.4;
        }

        .error-msg {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
            font-size: 16px;
            text-align: left;
            line-height: 1.4;
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

        .form-group label .icon {
            margin-right: 5px;
        }

        .add-student-container input[type="text"],
        .add-student-container input[type="password"],
        .add-student-container input[type="number"] {
            width: 100%;
            padding: 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            outline: none;
        }

        .add-student-container input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .add-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(90deg, #007bff, #00c6ff);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.3s ease, transform 0.2s ease;
            margin-top: 10px;
        }

        .add-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .add-btn:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="add-student-container">
        <h2>Add Student</h2>
        <?php if (!empty($success)): ?>
            <div class="success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="username"><span class="icon">👤</span> Username</label>
                <input type="text" id="username" name="username" placeholder="Enter username" required>
            </div>
            <div class="form-group">
                <label for="first_name"><span class="icon">📝</span> First Name</label>
                <input type="text" id="first_name" name="first_name" placeholder="Enter First name" required>
            </div>
            <div class="form-group">
                <label for="middle_name"><span class="icon">📝</span> Middle Name</label>
                <input type="text" id="middle_name" name="middle_name" placeholder="Enter Middle name (optional)">
            </div>
            <div class="form-group">
                <label for="last_name"><span class="icon">📝</span> Last Name</label>
                <input type="text" id="last_name" name="last_name" placeholder="Enter Last name" required>
            </div>
            <div class="form-group">
                <label for="course"><span class="icon">🎓</span> Course</label>
                <input type="text" id="course" name="course" placeholder="Enter Course (e.g. BSIT, BSEd)" required>
            <div class="form-group">
                <label for="school"><span class="icon">🏫</span> School</label>
                <input type="text" id="school" name="school" placeholder="Enter school name" required>
            </div>
            <div class="form-group">
                <label for="required_hours"><span class="icon">⏰</span> Required Hours</label>
                <input type="text" id="required_hours" name="required_hours" placeholder="e.g. 200" required>
            </div>
            <div class="form-group">
                <label for="password"><span class="icon">🔒</span> Password</label>
                <input type="password" id="password" name="password" placeholder="Enter password" required>
            </div>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="submit" class="add-btn" style="flex: 1; padding: 12px; font-size: 14px;">Add Student</button>
                <a href="employer_dashboard.php" class="add-btn" style="flex: 1; padding: 12px; font-size: 14px; text-decoration: none; display: inline-block; text-align: center;">Back to Dashboard</a>
            </div>
        </form>
    </div>
</body>
</html>
