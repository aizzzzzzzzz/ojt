<?php
session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/audit.php';

// Only students can access this
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];
$csrf_token = generate_csrf_token();
$success_message = '';
$error_message = '';

// Get document type from URL parameter
$document_type = $_GET['type'] ?? '';
if (!in_array($document_type, ['MOA', 'Endorsement Letter', 'Resume'])) {
    header("Location: approval_status.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf($_POST['csrf_token'] ?? '');

    if (empty($_FILES['document_file']['tmp_name'])) {
        $error_message = "Please select a file to upload.";
    } else {
        $uploadDir = __DIR__ . '/../uploads/moa/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = basename($_FILES['document_file']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedTypes = ['pdf', 'docx', 'doc'];

        if (!in_array($fileExt, $allowedTypes)) {
            $error_message = "Only PDF, DOCX, and DOC files are allowed.";
        } elseif ($_FILES['document_file']['size'] > 20 * 1024 * 1024) {
            $error_message = "File size must not exceed 20MB.";
        } else {
            // Create table if it doesn't exist
            $createTableSQL = "
                CREATE TABLE IF NOT EXISTS moa_documents (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    student_id INT NOT NULL,
                    document_type ENUM('MOA', 'Endorsement Letter', 'Resume') NOT NULL,
                    filename VARCHAR(255) NOT NULL,
                    filepath VARCHAR(500) NOT NULL,
                    supervisor_signature_path VARCHAR(500),
                    supervisor_approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                    supervisor_approved_at TIMESTAMP NULL,
                    supervisor_rejection_reason TEXT,
                    admin_approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                    admin_approved_at TIMESTAMP NULL,
                    admin_rejection_reason TEXT,
                    is_new_student TINYINT(1) NOT NULL DEFAULT 1,
                    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_student_doc (student_id, document_type),
                    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
                )
            ";
            try {
                $pdo->exec($createTableSQL);

                // Add missing columns if they don't exist
                $alterSQL = [
                    "ALTER TABLE moa_documents ADD COLUMN supervisor_approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'",
                    "ALTER TABLE moa_documents ADD COLUMN supervisor_approved_at TIMESTAMP NULL",
                    "ALTER TABLE moa_documents ADD COLUMN supervisor_rejection_reason TEXT",
                    "ALTER TABLE moa_documents ADD COLUMN admin_approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'",
                    "ALTER TABLE moa_documents ADD COLUMN admin_approved_at TIMESTAMP NULL",
                    "ALTER TABLE moa_documents ADD COLUMN admin_rejection_reason TEXT",
                    "ALTER TABLE moa_documents ADD COLUMN is_new_student TINYINT(1) NOT NULL DEFAULT 1",
                    "ALTER TABLE moa_documents MODIFY COLUMN document_type ENUM('MOA','Endorsement Letter','Resume') NOT NULL"
                ];

                foreach ($alterSQL as $sql) {
                    try {
                        $pdo->exec($sql);
                    } catch (PDOException $ae) {
                        // Column might already exist, that's fine
                    }
                }
            } catch (PDOException $e) {
                error_log("Error creating MOA table: " . $e->getMessage());
            }

            $uniqueFileName = uniqid() . '_' . $fileName;
            $destPath = $uploadDir . $uniqueFileName;

            if (move_uploaded_file($_FILES['document_file']['tmp_name'], $destPath)) {
                try {
                    // Delete any row with empty document_type for this student (cleanup bad data)
                    $pdo->prepare("DELETE FROM moa_documents WHERE student_id = ? AND (document_type = '' OR document_type IS NULL)")->execute([$student_id]);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO moa_documents (student_id, document_type, filename, filepath, is_new_student, supervisor_approval_status, admin_approval_status)
                        VALUES (?, ?, ?, ?, 1, 'pending', 'pending')
                        ON DUPLICATE KEY UPDATE filename = VALUES(filename), filepath = VALUES(filepath),
                        document_type = VALUES(document_type),
                        supervisor_approval_status = 'pending', admin_approval_status = 'pending',
                        supervisor_approved_at = NULL, admin_approved_at = NULL, updated_at = NOW()
                    ");
                    $stmt->execute([$student_id, $document_type, $fileName, $destPath]);

                    write_audit_log('Student Upload Document', "$document_type uploaded by student ID $student_id");

                    $success_message = "$document_type uploaded successfully! Your document is now pending approval by your supervisor and admin.";
                } catch (PDOException $e) {
                    $error_message = "Database error: " . $e->getMessage();
                    if (file_exists($destPath)) unlink($destPath);
                }
            } else {
                $error_message = "Failed to upload file. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload <?= htmlspecialchars($document_type) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/portal-ui.css">
<style>
    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--radius:14px;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:32px 20px 60px;}
    .page-card{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);width:100%;max-width:640px;margin:0 auto;overflow:hidden;}
    .page-topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;}
    .page-topbar h2{font-size:18px;font-weight:700;margin:0;letter-spacing:-.3px;}
        .page-topbar p{font-size:14px;color:var(--text-muted);margin:4px 0 0;line-height:1.5;}
        .page-inner{padding:32px 32px 40px;}
        .success-msg{background:rgba(5,150,105,.1);color:#047857;padding:16px 18px;border-radius:14px;border:1.5px solid rgba(16,185,129,.25);font-size:14px;font-weight:600;margin-bottom:20px;}
        .error-msg{background:rgba(239,68,68,.1);color:#991b1b;padding:16px 18px;border-radius:14px;border:1.5px solid rgba(239,68,68,.25);font-size:14px;font-weight:600;margin-bottom:20px;}
    .form-label{font-size:14px;font-weight:700;color:var(--text);margin-bottom:8px;display:block;letter-spacing:-.2px;}
    input[type=file],.form-control{border-radius:14px;border:1.5px solid rgba(226,232,240,.9);padding:12px 16px;font-size:14px;font-family:inherit;color:var(--text);background:#f8fbff;transition:border-color .2s,box-shadow .2s,background .2s;width:100%;}
    input:focus,.form-control:focus{border-color:var(--accent);background:var(--surface);box-shadow:0 0 0 4px rgba(67,97,238,.1);outline:none;}
    .mb-3{margin-bottom:16px;}
    .btn{font-family:inherit;font-size:14px;font-weight:700;border-radius:14px;padding:12px 20px;transition:all .18s;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;border:none;text-decoration:none;box-shadow:0 12px 24px rgba(15,23,42,.08);}
    .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);color:#fff;box-shadow:0 16px 32px rgba(67,97,238,.2);}
    .btn-outline-secondary{background:transparent;color:var(--text);border:1.5px solid rgba(15,23,42,.1);border-radius:12px;padding:8px 14px;font-size:13px;font-weight:600;text-decoration:none;transition:all .18s;}.btn-outline-secondary:hover{background:rgba(71,85,105,.05);color:var(--text);border-color:rgba(15,23,42,.15);}
    
    .upload-zone { background:var(--surface2); border:2px dashed var(--border); border-radius:var(--radius); padding:28px; text-align:center; transition:border-color .2s; }
    .upload-zone:hover { border-color:var(--accent); }
    
    .info-box { background:#fef3c7; color:#92400e; padding:12px 16px; border-radius:9px; border:1px solid #fcd34d; font-size:13px; margin-bottom:20px; }
    
    @media(max-width:640px){body{padding:10px 10px 40px;}.page-card{border-radius:14px;}.page-topbar,.page-inner{padding:14px 16px;}}
</style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2>📤 Upload <?= htmlspecialchars($document_type) ?></h2>
            <p>Submit your document for approval</p>
        </div>
        <a href="approval_status.php" class="btn-outline-secondary">⬅ Back</a>
    </div>
    <div class="page-inner">
        <?php if (!empty($success_message)): ?>
            <div class="success-msg"><?= htmlspecialchars($success_message) ?></div>
            <div style="text-align:center;margin-top:20px;">
                <a href="approval_status.php" class="btn btn-primary">View Status</a>
            </div>
        <?php else: ?>
            <?php if (!empty($error_message)): ?>
                <div class="error-msg"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="document_type" value="<?= htmlspecialchars($document_type) ?>">

                <div class="mb-3">
                    <label class="form-label" for="document_file">Upload <?= htmlspecialchars($document_type) ?></label>
                    <div class="upload-zone">
                        <div style="font-size:2rem;margin-bottom:10px;">📄</div>
                        <p style="color:var(--text-muted);font-size:14px;margin:0 0 14px;">Select a PDF, DOCX, or DOC file (max 20MB)</p>
                        <input type="file" id="document_file" name="document_file" class="form-control" accept=".pdf,.docx,.doc" required>
                    </div>
                </div>

                <div class="info-box">
                    <strong>ℹ️ Approval Workflow:</strong> Once uploaded, your document will be reviewed by your supervisor first, then by the admin. You will be notified once both approvals are complete.
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">📤 Upload Document</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
