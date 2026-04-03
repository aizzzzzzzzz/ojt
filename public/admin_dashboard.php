<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/evaluation_security.php';

require_admin();
$csrf_token = generate_csrf_token();
ensure_supervisor_email_support($pdo);

$stmt = $pdo->prepare("SELECT username, full_name FROM admins WHERE admin_id=?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    session_destroy();
    header("Location: admin_login.php");
    exit;
}

$uploadError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    check_csrf($_POST['csrf_token'] ?? '');
    if (!empty($_FILES['uploaded_file']['tmp_name'])) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = basename($_FILES['uploaded_file']['name']);
        $destPath = $uploadDir . uniqid() . '_' . $fileName;
        $allowedTypes = ['pdf','docx','jpg','png'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedTypes)) $uploadError = "Invalid file type.";
        elseif ($_FILES['uploaded_file']['size'] > 5*1024*1024) $uploadError = "File too large (max 5MB).";

        if (!$uploadError && move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $destPath)) {
            $stmt = $pdo->prepare("INSERT INTO uploaded_files (uploader_type, uploader_id, filename, filepath) VALUES (?, ?, ?, ?)");
            $stmt->execute(['admin', $_SESSION['admin_id'], $fileName, $destPath]);
            write_audit_log('File Upload', $fileName);
            $_SESSION['upload_success'] = true;
            header("Location: admin_dashboard.php");
            exit;
        }
    } else {
        $uploadError = "No file selected.";
    }
}

$addEmployerError = $_SESSION['addEmployerError'] ?? '';
unset($_SESSION['addEmployerError']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employer'])) {
    check_csrf($_POST['csrf_token'] ?? '');

    $username = sanitize_input($_POST['username']);
    $name = sanitize_input($_POST['name']);
    $email = trim($_POST['email'] ?? '');
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['addEmployerError'] = "A valid supervisor email is required.";
        header("Location: admin_dashboard.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT 1 FROM employers WHERE LOWER(username)=LOWER(?)");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $_SESSION['addEmployerError'] = "Username already exists.";
        write_audit_log('Add Employer Failed', $username);
        header("Location: admin_dashboard.php");
        exit;
    }

    $company = sanitize_input($_POST['company']);
    $work_start = sanitize_input($_POST['work_start'] ?? '08:00');
    $work_end   = sanitize_input($_POST['work_end']   ?? '17:00');
    $stmt = $pdo->prepare("INSERT INTO employers (username,email,name,company,password,work_start,work_end,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
    $stmt->execute([$username,$email,$name,$company,$password,$work_start,$work_end]);
    write_audit_log('Add Employer', $username);
    $_SESSION['employer_added_success'] = true;
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
$employers = $pdo->query("SELECT employer_id, username, email, name, company, work_start, work_end FROM employers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

include_once __DIR__ . '/../templates/admin_header.php';
include_once __DIR__ . '/../templates/admin_main.php';
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
