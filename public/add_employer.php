<?php
session_start();
require "../private/config.php";
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/evaluation_security.php';

ob_start();
ensure_supervisor_email_support($pdo);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("=== DEBUG START: add_employer.php POST ===");
    error_log("POST data: " . print_r($_POST, true));

    $name = trim($_POST["name"]);
    $company_input = trim($_POST["company"]);
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"] ?? '');
    $password = trim($_POST["password"]);

    if (!empty($name) && !empty($company_input) && !empty($username) && !empty($password) && !empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Please enter a valid supervisor email address.";
            header("Location: add_employer.php");
            exit;
        }

        $stmt = $pdo->prepare("SELECT 1 FROM employers WHERE LOWER(username) = LOWER(?)");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $_SESSION['error'] = "Username already exists.";
            header("Location: add_employer.php");
            exit;
        } else {
            error_log("DEBUG: Checking company: '{$company_input}'");

            $companyStmt = $pdo->prepare("SELECT company_id FROM companies WHERE LOWER(company_name) = LOWER(?)");
            $companyStmt->execute([$company_input]);
            $existingCompany = $companyStmt->fetch();

            error_log("DEBUG: Company check result: " . print_r($existingCompany, true));

            if ($existingCompany) {
                $company_id = $existingCompany['company_id'];
                error_log("DEBUG: Company exists with ID: {$company_id}");
            } else {
                error_log("DEBUG: Creating new company: '{$company_input}'");

                try {
                    $insertCompanyStmt = $pdo->prepare("INSERT INTO companies (company_name) VALUES (?)");

                    if ($insertCompanyStmt->execute([$company_input])) {
                        $company_id = $pdo->lastInsertId();
                        error_log("DEBUG: Company created successfully! ID: {$company_id}");

                        $verifyStmt = $pdo->prepare("SELECT * FROM companies WHERE company_id = ?");
                        $verifyStmt->execute([$company_id]);
                        $verified = $verifyStmt->fetch();
                        error_log("DEBUG: Verification: " . print_r($verified, true));
                    } else {
                        $errorInfo = $insertCompanyStmt->errorInfo();
                        error_log("DEBUG: Failed to insert company. Error: " . print_r($errorInfo, true));
                        $_SESSION['error'] = "Failed to create company. Database error.";
                        header("Location: add_employer.php");
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log("DEBUG: PDO Exception: " . $e->getMessage());
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                    header("Location: add_employer.php");
                    exit;
                }
            }

            $work_start = $_POST['work_start'] ?? '08:00';
                $work_end   = $_POST['work_end']   ?? '17:00';
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            error_log("DEBUG: Hashed password created");

            try {
                $stmt = $pdo->prepare("INSERT INTO employers (name, company, company_id, username, email, password, work_start, work_end) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                error_log("DEBUG: Inserting employer with values: name={$name}, company_id={$company_id}, username={$username}, email={$email}");

                if ($stmt->execute([$name, $company_input, $company_id, $username, $email, $hashed_password, $work_start, $work_end])) {
                    $employer_id = $pdo->lastInsertId();
                    error_log("DEBUG: Employer created successfully! ID: {$employer_id}");

                    $verifyEmpStmt = $pdo->prepare("SELECT * FROM employers WHERE employer_id = ?");
                    $verifyEmpStmt->execute([$employer_id]);
                    $empData = $verifyEmpStmt->fetch();
                    error_log("DEBUG: Employer verification: " . print_r($empData, true));

                    // Log admin add employer action
                    audit_log($pdo, 'Add Employer', "Added employer: $username (Company: $company_input)");

                    $_SESSION['success'] = "Employer account created successfully! Company ID: {$company_id}, Employer ID: {$employer_id}";
                } else {
                    $errorInfo = $stmt->errorInfo();
                    error_log("DEBUG: Failed to insert employer. Error: " . print_r($errorInfo, true));
                    $_SESSION['error'] = "Failed to add employer. Database error.";
                }
            } catch (PDOException $e) {
                error_log("DEBUG: PDO Exception inserting employer: " . $e->getMessage());
                $_SESSION['error'] = "Database error: " . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error'] = "All fields are required!";
    }

    error_log("=== DEBUG END: add_employer.php POST ===");
    header("Location: add_employer.php");
    exit;
}

$companies = [];
try {
    $companiesStmt = $pdo->query("SELECT * FROM companies ORDER BY company_name");
    $companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DEBUG: Error fetching companies: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employer</title>
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

    .page-card { max-width: 720px; }
    .companies-list { background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius); padding:14px 16px; }
    .companies-list ul { margin:8px 0 0; padding-left:20px; font-size:14px; color:var(--text-muted); }
    .time-row { display:flex; gap:12px; align-items:flex-end; }
    .time-row > div { flex:1; }
    .time-sep { padding-bottom:10px; color:var(--text-muted); font-weight:600; flex-shrink:0; }
</style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2>Add Supervisor</h2>
            <p>Create a new supervisor account</p>
        </div>
        <a href="index.php" class="btn-outline-secondary">⬅ Back</a>
    </div>
    <div class="page-inner">

        <?php if (!empty($_SESSION['error'])): ?>
            <div class="error-msg"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="success-msg"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <form method="post">
            <div class="form-grid">
                <div class="mb-3">
                    <label class="form-label" for="employer_name">Supervisor Name</label>
                    <input id="employer_name" type="text" name="name" placeholder="Full name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="employer_company">Company</label>
                    <input id="employer_company" type="text" name="company" placeholder="Company name" required>
                    <span class="form-text">Created automatically if it doesn't exist.</span>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="employer_username">Username</label>
                    <input id="employer_username" type="text" name="username" placeholder="Login username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="employer_email">Email</label>
                    <input id="employer_email" type="email" name="email" placeholder="Supervisor email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="employer_password">Password</label>
                    <input id="employer_password" type="password" name="password" placeholder="Login password" required>
                </div>
                <div class="mb-3 span-2">
                    <label class="form-label">Working Hours</label>
                    <div class="time-row">
                        <div>
                            <span class="form-text">Start Time</span>
                            <input id="work_start" type="time" name="work_start" value="08:00" required>
                        </div>
                        <div class="time-sep">—</div>
                        <div>
                            <span class="form-text">End Time</span>
                            <input id="work_end" type="time" name="work_end" value="17:00" required>
                        </div>
                    </div>
                    <span class="form-text">⏰ Students will be blocked from the dashboard outside these hours.</span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Add Supervisor</button>
        </form>

        <?php if (!empty($companies)): ?>
        <div style="margin-top:24px;">
            <h3>Companies in Database</h3>
            <div class="companies-list">
                <ul>
                    <?php foreach ($companies as $company): ?>
                        <li><?= htmlspecialchars($company['company_name']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
