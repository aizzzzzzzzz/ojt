<?php
session_start();
require '../private/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employer')) {
    header('Location: ../index.php');
    exit;
}

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username       = trim($_POST['username']);
    $first_name     = trim($_POST['first_name']);
    $middle_name    = trim($_POST['middle_name']);
    $last_name      = trim($_POST['last_name']);
    $email          = trim($_POST['email']);
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

            $created_by = null;
            $company_id = null;
            
            error_log("DEBUG add_student.php - Session role: " . $_SESSION['role']);
            error_log("DEBUG add_student.php - Session employer_id: " . ($_SESSION['employer_id'] ?? 'NOT SET'));
            
            if ($_SESSION['role'] === 'employer') {
                $created_by = $_SESSION['employer_id'];
                
                if ($created_by) {
                    error_log("DEBUG add_student.php - Looking for employer_id: " . $created_by);
                    $companyStmt = $pdo->prepare("SELECT company_id FROM employers WHERE employer_id = ?");
                    $companyStmt->execute([$created_by]);
                    $companyData = $companyStmt->fetch();
                    
                    if ($companyData) {
                        $company_id = $companyData['company_id'];
                        error_log("DEBUG add_student.php - Found company_id: " . $company_id);
                    } else {
                        error_log("DEBUG add_student.php - No employer found with ID: " . $created_by);
                    }
                }
            }
            
            if ($_SESSION['role'] === 'admin') {
                error_log("DEBUG add_student.php - Admin adding student, company_id will be NULL");
            }

            error_log("DEBUG add_student.php - Final values - created_by: " . $created_by . ", company_id: " . $company_id);
            
            $stmt = $pdo->prepare("INSERT INTO students (username, password, first_name, middle_name, last_name, email, required_hours, course, school, created_by, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$username, $hashed_password, $first_name, $middle_name, $last_name, $email, $required_hours, $course, $school, $created_by, $company_id])) {
                $success = "Student added successfully!";
                error_log("DEBUG add_student.php - Student added successfully with company_id: " . $company_id);
            } else {
                $error = "Error adding student. Please try again.";
                $errorInfo = $stmt->errorInfo();
                error_log("DEBUG add_student.php - SQL Error: " . print_r($errorInfo, true));
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .add-student-container input[type="email"],
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
                <label for="email"><span class="icon">📧</span> Email</label>
                <input type="email" id="email" name="email" placeholder="Enter email address" required>
            </div>
            <div class="form-group">
                <label for="course"><span class="icon">🎓</span> Course</label>
                <input type="text" id="course" name="course" placeholder="Enter Course (e.g. BSIT, BSEd)" required>
            </div>
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
                <a href="supervisor_dashboard.php" class="add-btn" style="flex: 1; padding: 12px; font-size: 14px; text-decoration: none; display: inline-block; text-align: center;">Back</a>
            </div>
        </form>
    </div>

    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="successModalLabel">Success</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Student added successfully!
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        <?php if (!empty($success)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
            });
        <?php endif; ?>
    </script>
</body>
</html>