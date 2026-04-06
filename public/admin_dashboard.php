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

$employerStatus = $_SESSION['employerStatus'] ?? null;
unset($_SESSION['employerStatus']);

$editEmployerId = isset($_SESSION['editEmployerId']) ? (int) $_SESSION['editEmployerId'] : 0;
unset($_SESSION['editEmployerId']);

$editEmployerForm = $_SESSION['editEmployerForm'] ?? [];
unset($_SESSION['editEmployerForm']);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employer'])) {
    check_csrf($_POST['csrf_token'] ?? '');

    $employer_id = (int) ($_POST['employer_id'] ?? 0);
    $editData = [
        'username' => trim((string) ($_POST['username'] ?? '')),
        'name' => trim((string) ($_POST['name'] ?? '')),
        'company' => trim((string) ($_POST['company'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'work_start' => trim((string) ($_POST['work_start'] ?? '')),
        'work_end' => trim((string) ($_POST['work_end'] ?? '')),
    ];
    $new_password = (string) ($_POST['new_password'] ?? '');
    $updateError = '';

    if ($employer_id <= 0) {
        $updateError = 'Invalid supervisor account selected.';
    } elseif ($editData['username'] === '' || $editData['name'] === '' || $editData['company'] === '' || $editData['email'] === '') {
        $updateError = 'Username, name, company, and email are required.';
    } elseif (!filter_var($editData['email'], FILTER_VALIDATE_EMAIL)) {
        $updateError = 'Please enter a valid supervisor email address.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $editData['username'])) {
        $updateError = 'Username must be 3-50 characters and may only contain letters, numbers, dots, underscores, or dashes.';
    } elseif (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $editData['work_start']) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $editData['work_end'])) {
        $updateError = 'Please enter valid work start and end times.';
    } else {
        $stmt = $pdo->prepare("SELECT employer_id FROM employers WHERE LOWER(username) = LOWER(?) AND employer_id <> ? LIMIT 1");
        $stmt->execute([$editData['username'], $employer_id]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $updateError = 'Username already exists.';
        }
    }

    if ($updateError === '' && $new_password !== '' && !validate_password($new_password)) {
        $updateError = 'New password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
    }

    if ($updateError !== '') {
        $_SESSION['employerStatus'] = ['type' => 'danger', 'message' => $updateError];
        $_SESSION['editEmployerId'] = $employer_id;
        $_SESSION['editEmployerForm'] = $editData;
        header("Location: admin_dashboard.php#supervisor-list");
        exit;
    }

    if ($new_password !== '') {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE employers
            SET username = ?, email = ?, name = ?, company = ?, work_start = ?, work_end = ?, password = ?, password_changed = 1
            WHERE employer_id = ?
        ");
        $stmt->execute([
            $editData['username'],
            $editData['email'],
            $editData['name'],
            $editData['company'],
            $editData['work_start'],
            $editData['work_end'],
            $password_hash,
            $employer_id,
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE employers
            SET username = ?, email = ?, name = ?, company = ?, work_start = ?, work_end = ?
            WHERE employer_id = ?
        ");
        $stmt->execute([
            $editData['username'],
            $editData['email'],
            $editData['name'],
            $editData['company'],
            $editData['work_start'],
            $editData['work_end'],
            $employer_id,
        ]);
    }

    write_audit_log('Update Employer', $editData['username']);
    $_SESSION['employerStatus'] = ['type' => 'success', 'message' => 'Supervisor account updated successfully.'];
    header("Location: admin_dashboard.php#supervisor-list");
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
