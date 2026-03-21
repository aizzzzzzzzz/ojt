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
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<style>

    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--amber:#d97706;--amber-lt:#fef3c7;--radius:14px;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:32px 20px 60px;}
    .page-card{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);width:100%;margin:0 auto;overflow:hidden;}
    .page-topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;}
    .page-topbar h2{font-size:18px;font-weight:700;margin:0;letter-spacing:-.3px;}
    .page-topbar p{font-size:13px;color:var(--text-muted);margin:2px 0 0;}
    .page-inner{padding:24px 28px 32px;}
    .page-inner h3{font-size:15px;font-weight:700;margin:24px 0 12px;padding-bottom:9px;border-bottom:1px solid var(--border);}
    .page-inner h3:first-child{margin-top:0;}
    .success-msg{background:var(--green-lt);color:#15803d;padding:12px 16px;border-radius:10px;border:1px solid #bbf7d0;font-size:14px;font-weight:500;margin-bottom:16px;}
    .error-msg{background:var(--red-lt);color:#b91c1c;padding:12px 16px;border-radius:10px;border:1px solid #fecaca;font-size:14px;font-weight:500;margin-bottom:16px;}
    .form-label{font-size:13px;font-weight:600;color:var(--text);margin-bottom:5px;display:block;}
    .form-text{font-size:12px;color:var(--text-muted);margin-top:4px;}
    input[type=text],input[type=email],input[type=password],input[type=number],input[type=time],input[type=date],textarea,select,.form-control{border-radius:9px;border:1px solid var(--border);padding:9px 12px;font-size:14px;font-family:inherit;color:var(--text);background:var(--surface);transition:border-color .2s,box-shadow .2s;width:100%;}
    input:focus,textarea:focus,select:focus,.form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.12);outline:none;}
    .mb-3{margin-bottom:16px;}.mb-4{margin-bottom:24px;}
    .btn{font-family:inherit;font-size:13px;font-weight:600;border-radius:9px;padding:9px 18px;transition:all .18s;cursor:pointer;display:inline-flex;align-items:center;gap:6px;border:none;text-decoration:none;}
    .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);color:#fff;}
    .btn-success{background:var(--green);color:#fff;}.btn-success:hover{background:#15803d;transform:translateY(-1px);color:#fff;}
    .btn-secondary{background:var(--surface2);color:var(--text);border:1.5px solid var(--border);}.btn-secondary:hover{background:var(--border);}
    .btn-outline-secondary{background:transparent;color:var(--text-muted);border:1.5px solid var(--border);border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-family:inherit;transition:all .18s;}
    .btn-outline-secondary:hover{background:var(--surface2);color:var(--text);}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .span-2{grid-column:span 2;}
    @media(max-width:640px){body{padding:10px 10px 40px;}.page-card{border-radius:14px;}.page-topbar,.page-inner{padding:14px 16px;}.form-grid{grid-template-columns:1fr;}.span-2{grid-column:span 1;}}

    .page-card { max-width: 860px; }
</style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2>Add Student</h2>
            <p>Create a student account and set their internship profile</p>
        </div>
        <a href="supervisor_dashboard.php" class="btn-outline-secondary">⬅ Back</a>
    </div>
    <div class="page-inner">

        <?php if (!empty($success)): ?>
            <div class="success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-grid">
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Login username" value="<?= htmlspecialchars($form_data['username']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Email address" value="<?= htmlspecialchars($form_data['email']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" placeholder="First name" value="<?= htmlspecialchars($form_data['first_name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" placeholder="Middle name (optional)" value="<?= htmlspecialchars($form_data['middle_name']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" placeholder="Last name" value="<?= htmlspecialchars($form_data['last_name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="required_hours">Required Hours</label>
                    <input type="number" id="required_hours" name="required_hours" min="1" step="1" placeholder="e.g. 200" value="<?= htmlspecialchars($form_data['required_hours']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="course">Course</label>
                    <input type="text" id="course" name="course" placeholder="e.g. BSIT, BSEd" value="<?= htmlspecialchars($form_data['course']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="school">School</label>
                    <input type="text" id="school" name="school" placeholder="School name" value="<?= htmlspecialchars($form_data['school']) ?>" required>
                </div>
                <div class="mb-3 span-2">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Initial password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:4px;">Add Student</button>
        </form>
    </div>
</div>

<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:1px solid var(--border);">
            <div class="modal-header" style="border-bottom:1px solid var(--border);">
                <h5 class="modal-title" style="font-weight:700;">Student Added</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="font-size:14px;">Student account created successfully!</div>
            <div class="modal-footer" style="border-top:1px solid var(--border);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    <?php if (!empty($success)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        new bootstrap.Modal(document.getElementById('successModal')).show();
    });
    <?php endif; ?>
</script>
</body>
</html>