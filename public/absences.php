<?php
session_start();
$conn = new mysqli("localhost", "root", "", "student_db");

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== "employer") {
    header("Location: employer_login.php");
    exit;
}

$students = $conn->query("SELECT student_id, username FROM students");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $reason = $_POST['reason'];
    $log_date = date("Y-m-d");

    $check = $conn->prepare("SELECT * FROM attendance WHERE student_id=? AND log_date=?");
    $check->bind_param("is", $student_id, $log_date);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO attendance (student_id, log_date, status, reason) VALUES (?, ?, 'Absent', ?)");
        $stmt->bind_param("iss", $student_id, $log_date, $reason);
        $stmt->execute();
        $msg = "❌ Absent recorded with reason!";
    } else {
        $msg = "⚠️ Attendance already exists for today!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Student Absent</title>
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

        .absent-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .absent-container h2 {
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

        .absent-container select,
        .absent-container input[type="text"] {
            width: 100%;
            padding: 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            outline: none;
        }

        .absent-container select:focus,
        .absent-container input[type="text"]:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .submit-btn {
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

        .submit-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        @media (max-width: 480px) {
            .absent-container {
                margin: 20px;
                padding: 30px 20px;
                width: auto;
            }

            .absent-container h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="absent-container">
        <h2>Mark Student Absent</h2>
        <?php if (isset($msg)): ?>
            <div class="success-msg"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="student_id">Select Student:</label>
                <select name="student_id" id="student_id" required>
                    <option value="">-- Select Student --</option>
                    <?php 
                    $students->data_seek(0);
                    while ($row = $students->fetch_assoc()): 
                    ?>
                        <option value="<?= $row['student_id'] ?>"><?= htmlspecialchars($row['username']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="reason">Reason:</label>
                <input type="text" id="reason" name="reason" required>
            </div>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="submit" class="submit-btn" style="flex: 1; padding: 12px; font-size: 14px;">Submit</button>
                <a href="supervisor_dashboard.php
" class="submit-btn" style="flex: 1; padding: 12px; font-size: 14px; text-decoration: none; display: inline-block; text-align: center;">Back</a>
            </div>
        </form>
    </div>
</body>
</html>
