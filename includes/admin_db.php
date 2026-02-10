<?php
// Database operations module for admin dashboard

function get_students_count($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM students");
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function get_evaluations_count($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM evaluations");
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function get_employers($pdo) {
    $stmt = $pdo->query("SELECT employer_id, username, name, created_at FROM employers ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function add_employer($pdo, $username, $name, $password) {
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT 1 FROM employers WHERE LOWER(username)=LOWER(?)");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return "Username already exists.";
    }

    $stmt = $pdo->prepare("INSERT INTO employers (username,name,password,created_at) VALUES (?,?,?,NOW())");
    $stmt->execute([$username, $name, $password]);
    write_audit_log('Add Employer', $username);
    return true;
}

function delete_employer($pdo, $employer_id) {
    $stmt = $pdo->prepare("SELECT username FROM employers WHERE employer_id=?");
    $stmt->execute([$employer_id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("DELETE FROM employers WHERE employer_id=?");
    $stmt->execute([$employer_id]);
    write_audit_log('Delete Employer', $emp['username'] ?? 'Unknown');
    return true;
}

function handle_file_upload($pdo, $uploaded_file) {
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (!empty($uploaded_file['tmp_name'])) {
        $fileName = basename($uploaded_file['name']);
        $destPath = $uploadDir . uniqid() . '_' . $fileName;
        $allowedTypes = ['pdf','docx','jpg','png'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedTypes)) {
            return "Invalid file type.";
        }
        if ($uploaded_file['size'] > 5*1024*1024) {
            return "File too large (max 5MB).";
        }

        if (move_uploaded_file($uploaded_file['tmp_name'], $destPath)) {
            $stmt = $pdo->prepare("INSERT INTO uploaded_files (uploader_type, uploader_id, filename, filepath) VALUES (?, ?, ?, ?)");
            $stmt->execute(['admin', $_SESSION['admin_id'], $fileName, $destPath]);
            write_audit_log('File Upload', $fileName);
            return true;
        }
    }
    return "No file selected.";
}
?>
