<?php
/**
 * @function check_csrf(string $token): void
 * @function write_audit_log(string $action, string $details): void
 * @function generate_csrf_token(): string
 * @function require_admin(): void
 * @function sanitize_input(string $input): string
 */
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';

require_admin(); // enforce login
$csrf_token = generate_csrf_token();

// Fetch admin info
$stmt = $pdo->prepare("SELECT username, full_name FROM admins WHERE admin_id=?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Redirect if somehow admin not found
if (!$admin) {
    session_destroy();
    header("Location: admin_login.php");
    exit;
}

// -------- Upload File --------
$uploadError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    check_csrf($_POST['csrf_token'] ?? '');
    if (!empty($_FILES['uploaded_file']['tmp_name'])) {
        $uploadDir = __DIR__ . '/../uploads/'; // move outside web root in production
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = basename($_FILES['uploaded_file']['name']);
        $destPath = $uploadDir . uniqid() . '_' . $fileName;
        $allowedTypes = ['pdf','docx','jpg','png'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedTypes)) $uploadError = "Invalid file type.";
        elseif ($_FILES['uploaded_file']['size'] > 5*1024*1024) $uploadError = "File too large (max 5MB).";

        if (!$uploadError && move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $destPath)) {
            $stmt = $pdo->prepare("INSERT INTO uploaded_files (admin_id, filename, filepath) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['admin_id'], $fileName, $destPath]);
            write_audit_log('File Upload', $fileName);
            $_SESSION['upload_success'] = true;
            header("Location: admin_dashboard.php");
            exit;
        }
    } else {
        $uploadError = "No file selected.";
    }
}

// -------- Employers CRUD --------
$addEmployerError = $_SESSION['addEmployerError'] ?? '';
unset($_SESSION['addEmployerError']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employer'])) {
    check_csrf($_POST['csrf_token'] ?? '');

    $username = sanitize_input($_POST['username']);
    $name = sanitize_input($_POST['name']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT 1 FROM employers WHERE LOWER(username)=LOWER(?)");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $_SESSION['addEmployerError'] = "Username already exists.";
        write_audit_log('Add Employer Failed', $username);
        header("Location: admin_dashboard.php");
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO employers (username,name,password,created_at) VALUES (?,?,?,NOW())");
    $stmt->execute([$username,$name,$password]);
    write_audit_log('Add Employer', $username);
    header("Location: admin_dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_employer'])) {
    check_csrf($_POST['csrf_token'] ?? '');
    $stmt = $pdo->prepare("SELECT username FROM employers WHERE employer_id=?");
    $stmt->execute([(int)$_POST['employer_id']]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("DELETE FROM employers WHERE employer_id=?");
    $stmt->execute([(int)$_POST['employer_id']]);
    write_audit_log('Delete Employer', $emp['username'] ?? 'Unknown');
    header("Location: admin_dashboard.php");
    exit;
}

$students_count = $pdo->query("SELECT COUNT(*) AS count FROM students")->fetch(PDO::FETCH_ASSOC)['count'];
$evaluations_count = $pdo->query("SELECT COUNT(*) AS count FROM evaluations")->fetch(PDO::FETCH_ASSOC)['count'];
$employers = $pdo->query("SELECT employer_id, username, name, created_at FROM employers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
<style>
    body {
        margin: 0;
        padding: 0;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #74ebd5, #ACB6E5);
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
        box-sizing: border-box;
        color: #333;
        line-height: 1.6;
    }

    .dashboard-container {
        background: rgba(255, 255, 255, 0.95);
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        width: 100%;
        max-width: 1200px;
        text-align: center;
    }

    .dashboard-container h2 {
        margin-bottom: 20px;
        font-size: 28px;
        font-weight: 600;
        color: #2c3e50;
    }

    .dashboard-container h3 {
        margin-top: 30px;
        margin-bottom: 15px;
        font-size: 22px;
        color: #2c3e50;
        text-align: left;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 10px;
    }

    .welcome-section {
        margin-bottom: 30px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .welcome-section p {
        font-size: 16px;
        color: #666;
        margin: 5px 0;
    }

    .welcome-section a {
        color: #007bff;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s ease;
    }

    .welcome-section a:hover {
        text-decoration: underline;
        color: #0056b3;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .summary-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        font-size: 16px;
        text-align: center;
        transition: transform 0.3s ease;
    }

    /* Removed hover effect from summary cards for professional look */

    .summary-card strong {
        display: block;
        font-size: 24px;
        color: #2c3e50;
        margin-top: 10px;
    }

    .section-header {
        width: 100%;
        text-align: left;
        background: #ffffff;
        border: 1px solid #dee2e6;
        padding: 12px 16px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 6px;
        margin-bottom: 10px;
    }

    .collapse-section {
        margin-bottom: 20px;
        border-radius: 10px;
        overflow: hidden;
    }

    .collapse-section .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .collapse-section .card-body {
        padding: 20px;
    }

    .collapse {
        display: none !important;
    }

    .collapse.show {
        display: block !important;
    }

    .form-control {
        border-radius: 8px;
        border: 1px solid #ddd;
        padding: 10px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }

    .form-control:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .btn {
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background: linear-gradient(90deg, #28a745, #85e085);
    }

    .btn-primary:hover {
        background: linear-gradient(90deg, #218838, #6c9e6c);
        transform: translateY(-2px);
    }

    .alert {
        border-radius: 8px;
        border: none;
        font-weight: 500;
    }

    .success-msg {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid #c3e6cb;
        text-align: left;
        font-weight: 500;
    }

    .error-msg {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid #f5c6cb;
        text-align: left;
        font-weight: 500;
    }

    .table-section {
        overflow-x: auto;
        margin-top: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 10px;
        overflow: hidden;
    }

    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
    }

    th {
        background: #f8f9fa;
        font-weight: 600;
    }

    .delete-btn {
        color: #dc3545;
        font-weight: 500;
        text-decoration: none;
        padding: 6px 12px;
        border-radius: 5px;
        transition: all 0.3s ease;
    }

    .delete-btn:hover {
        background: #f8d7da;
        text-decoration: none;
        transform: translateY(-2px);
    }

    @media (max-width: 768px) {
        body {
            padding: 10px;
            align-items: center;
        }

        .dashboard-container {
            width: 95%;
            padding: 20px;
            margin: 10px;
        }

        .dashboard-container h2 {
            font-size: 24px;
        }

        .dashboard-container h3 {
            font-size: 18px;
        }

        .summary-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }

        .summary-card strong {
            font-size: 20px;
        }

        .collapse-section .card-body {
            padding: 15px;
        }

        .dashboard-container input,
        .dashboard-container input[type="file"] {
            width: 100%;
            max-width: none;
        }

        .btn {
            width: 100%;
            max-width: none;
            margin-bottom: 10px;
        }

        table, thead, tbody, th, td, tr {
            display: block;
            width: 100%;
        }

        thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }

        tr {
            border: 1px solid #ddd;
            margin-bottom: 15px;
            border-radius: 8px;
            padding: 10px;
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        td {
            border: none;
            position: relative;
            padding-left: 50%;
            text-align: right;
            margin-bottom: 10px;
        }

        td::before {
            content: attr(data-label) ": ";
            position: absolute;
            left: 10px;
            width: 45%;
            padding-right: 10px;
            white-space: nowrap;
            font-weight: bold;
            text-align: left;
            color: #2c3e50;
        }

        .summary-card {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            font-size: 14px;
        }
}
</style>
</head>
<body>
<div class="dashboard-container">
    <div class="welcome-section">
        <h2>Welcome, <?= htmlspecialchars($admin['full_name'] ?? $admin['username']) ?>!</h2>
        <p>You are logged in as an administrator.</p>
        <p><a href="logout.php">Logout</a></p>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="text-muted small">Students</div>
            <div class="fs-3 fw-bold"><?= $students_count ?></div>
        </div>
        <div class="summary-card">
            <div class="text-muted small">Employers</div>
            <div class="fs-3 fw-bold"><?= count($employers) ?></div>
        </div>
        <div class="summary-card">
            <div class="text-muted small">Evaluations</div>
            <div class="fs-3 fw-bold"><?= $evaluations_count ?></div>
        </div>
    </div>
    <h3 data-bs-toggle="collapse" data-bs-target="#add-employer" style="cursor:pointer;">Add New Supervisor ⬇</h3>
    <div id="add-employer" class="collapse">
        <div class="card p-3"><div class="card-body">
            <?php if (!empty($addEmployerError)): ?>
                <div class="error-msg"><?= htmlspecialchars($addEmployerError) ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" required class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" required class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" required class="form-control">
                    </div>
                </div>
                <button type="submit" name="add_employer" class="btn btn-primary">Add Supervisor</button>
            </form>
        </div></div>
    </div>

    <button class="section-header" data-bs-toggle="collapse" data-bs-target="#upload-file">
        OJT Documents
    </button>
        <div id="upload-file" class="collapse">
            <div class="card mb-4">
                <div class="card-body">
                    <?php if (!empty($uploadError)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($uploadError) ?></div>
                    <?php elseif (!empty($_SESSION['upload_success'])): ?>
                        <div class="alert alert-success">File uploaded successfully!</div>
                        <?php unset($_SESSION['upload_success']); ?>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="file" name="uploaded_file" required class="form-control mb-3">
                        <button type="submit" name="upload_file" class="btn btn-primary">Upload</button>
                    </form>
                    <?php
                    $uploaded_files = $pdo->query("
                        SELECT id, filename, admin_id, uploaded_at 
                        FROM uploaded_files 
                        ORDER BY uploaded_at DESC
                    ")->fetchAll(PDO::FETCH_ASSOC);

                    if ($uploaded_files):
                    ?>
                    <h5 class="mt-4">Uploaded Files</h5>
                    <table class="table table-striped mt-3">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Uploaded By (Admin ID)</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($uploaded_files as $file): ?>
                            <tr>
                                <td><?= htmlspecialchars($file['filename']) ?></td>
                                <td><?= htmlspecialchars($file['admin_id']) ?></td>
                                <td><?= $file['uploaded_at'] ?></td>
                                <td>
                                    <a href="download.php?file_id=<?= $file['id'] ?>" class="btn btn-sm btn-primary">Download</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <h3 class="mb-4">OJT Supervisors</h3>
    <div>
        <table class="table table-striped mt-3">
            <thead><tr>
                <th>Username</th>
                <th>Name</th>
                <th>Created</th>
                <th>Action</th>
            </tr></thead>
            <tbody>
                <?php foreach ($employers as $emp): ?>
                <tr>
                    <td><?= htmlspecialchars($emp['username']) ?></td>
                    <td><?= htmlspecialchars($emp['name']) ?></td>
                    <td><?= $emp['created_at'] ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="employer_id" value="<?= $emp['employer_id'] ?>">
                            <button type="submit" name="delete_employer" onclick="return confirm('Delete this employer?')" class="btn btn-outline-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
