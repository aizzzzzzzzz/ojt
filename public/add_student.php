<?php
session_start();
require '../private/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employer')) {
    header('Location: ../index.php');
    exit;
}

$success = "";
$error = "";
$form_data = [
    'username' => '',
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'email' => '',
    'required_hours' => '',
    'course' => '',
    'school' => '',
];

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

    $form_data = [
        'username' => $username,
        'first_name' => $first_name,
        'middle_name' => $middle_name,
        'last_name' => $last_name,
        'email' => $email,
        'required_hours' => $required_hours,
        'course' => $course,
        'school' => $school,
    ];

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
            padding: 32px;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(10, 36, 99, 0.14);
            width: 100%;
            max-width: 820px;
            border: 1px solid #e5e7eb;
        }

        .add-student-container h2 {
            margin-bottom: 8px;
            font-size: 30px;
            font-weight: 700;
            color: #2c3e50;
            text-align: left;
        }

        .subtitle {
            margin: 0 0 22px;
            color: #64748b;
            font-size: 14px;
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .form-group-full {
            grid-column: span 2;
        }

        .form-group {
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }

        .form-group label .icon {
            margin-right: 5px;
        }

        .add-student-container input[type="text"],
        .add-student-container input[type="email"],
        .add-student-container input[type="password"],
        .add-student-container input[type="number"] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            outline: none;
            background: #fff;
        }

        .add-student-container input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .action-btn {
            min-width: 150px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .action-btn-primary {
            background: linear-gradient(90deg, #007bff, #00c6ff);
            color: white;
        }

        .action-btn-secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .action-btn:active {
            transform: translateY(0);
        }

        @media (max-width: 768px) {
            .add-student-container {
                padding: 20px;
                max-width: 96%;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group-full {
                grid-column: span 1;
            }

            .form-actions {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="add-student-container">
        <h2>Add Student</h2>
        <p class="subtitle">Create a student account and set their internship profile details.</p>
        <?php if (!empty($success)): ?>
            <div class="success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label for="username"><span class="icon">👤</span> Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter username" value="<?= htmlspecialchars($form_data['username']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email"><span class="icon">📧</span> Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter email address" value="<?= htmlspecialchars($form_data['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="first_name"><span class="icon">📝</span> First Name</label>
                    <input type="text" id="first_name" name="first_name" placeholder="Enter first name" value="<?= htmlspecialchars($form_data['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="middle_name"><span class="icon">📝</span> Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" placeholder="Enter middle name (optional)" value="<?= htmlspecialchars($form_data['middle_name']) ?>">
                </div>
                <div class="form-group">
                    <label for="last_name"><span class="icon">📝</span> Last Name</label>
                    <input type="text" id="last_name" name="last_name" placeholder="Enter last name" value="<?= htmlspecialchars($form_data['last_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="required_hours"><span class="icon">⏰</span> Required Hours</label>
                    <input type="number" id="required_hours" name="required_hours" min="1" step="1" placeholder="e.g. 200" value="<?= htmlspecialchars($form_data['required_hours']) ?>" required>
                </div>
                <div class="form-group form-group-full">
                    <label for="course"><span class="icon">🎓</span> Course</label>
                    <input type="text" id="course" name="course" placeholder="Enter course (e.g. BSIT, BSEd)" value="<?= htmlspecialchars($form_data['course']) ?>" required>
                </div>
                <div class="form-group form-group-full">
                    <label for="school"><span class="icon">🏫</span> School</label>
                    <input type="text" id="school" name="school" placeholder="Enter school name" value="<?= htmlspecialchars($form_data['school']) ?>" required>
                </div>
                <div class="form-group form-group-full">
                    <label for="password"><span class="icon">🔒</span> Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter password" required>
                </div>
            </div>
            <div class="form-actions">
                <a href="supervisor_dashboard.php" class="action-btn action-btn-secondary">Back</a>
                <button type="submit" class="action-btn action-btn-primary">Add Student</button>
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
