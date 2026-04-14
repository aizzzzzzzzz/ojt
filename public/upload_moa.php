<?php
session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';
require_once __DIR__ . '/../includes/audit.php';

ob_start();
require_admin();

$csrf_token = generate_csrf_token();
$success_message = '';
$error_message = '';

// Fetch all students for MOA assignment
$students_stmt = $pdo->prepare("SELECT student_id, username, CONCAT(first_name, ' ', last_name) as full_name FROM students ORDER BY last_name, first_name");
$students_stmt->execute();
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf($_POST['csrf_token'] ?? '');
    
    $student_id = (int) ($_POST['student_id'] ?? 0);
    $document_type = sanitize_input($_POST['document_type'] ?? '');
    
    if ($student_id <= 0 || !$document_type) {
        $error_message = "Please select a student and document type.";
    } elseif (empty($_FILES['moa_file']['tmp_name'])) {
        $error_message = "Please select a file to upload.";
    } else {
        $uploadDir = __DIR__ . '/../uploads/moa/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileName = basename($_FILES['moa_file']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedTypes = ['pdf', 'docx', 'doc'];
        
        if (!in_array($fileExt, $allowedTypes)) {
            $error_message = "Only PDF and DOCX files are allowed.";
        } elseif ($_FILES['moa_file']['size'] > 20 * 1024 * 1024) {
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
                    "ALTER TABLE moa_documents ADD COLUMN is_new_student TINYINT(1) NOT NULL DEFAULT 1"
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
            
            if (move_uploaded_file($_FILES['moa_file']['tmp_name'], $destPath)) {
                try {
                    // Resume doesn't need approval, MOA and Endorsement Letter do
                    $is_new_student = ($document_type === 'Resume') ? 0 : 1;
                    $supervisor_status = ($document_type === 'Resume') ? 'approved' : 'pending';
                    $admin_status = ($document_type === 'Resume') ? 'approved' : 'pending';
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO moa_documents (student_id, document_type, filename, filepath, is_new_student, supervisor_approval_status, admin_approval_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE filename = VALUES(filename), filepath = VALUES(filepath), updated_at = NOW()
                    ");
                    $stmt->execute([$student_id, $document_type, $fileName, $destPath, $is_new_student, $supervisor_status, $admin_status]);
                    
                    $student_name = '';
                    $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM students WHERE student_id = ?");
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch();
                    if ($student) $student_name = $student['full_name'];
                    
                    write_audit_log('Upload MOA/Endorsement/Resume', "$document_type uploaded for {$student_name}");
                    
                    if ($document_type === 'Resume') {
                        $success_message = "Resume uploaded successfully!";
                    } else {
                        $success_message = "$document_type uploaded successfully! The document is pending approval by the supervisor and admin.";
                    }
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
    <title>Upload MOA & Endorsement Letter</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/portal-ui.css">
<style>
    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--radius:14px;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:32px 20px 60px;}
    .page-card{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);width:100%;margin:0 auto;overflow:hidden;}
    .page-topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;}
    .page-topbar h2{font-size:18px;font-weight:700;margin:0;letter-spacing:-.3px;}
    .page-topbar p{font-size:13px;color:var(--text-muted);margin:2px 0 0;}
    .page-inner{padding:24px 28px 32px;}
    .success-msg{background:var(--green-lt);color:#15803d;padding:12px 16px;border-radius:10px;border:1px solid #bbf7d0;font-size:14px;font-weight:500;margin-bottom:16px;}
    .error-msg{background:var(--red-lt);color:#b91c1c;padding:12px 16px;border-radius:10px;border:1px solid #fecaca;font-size:14px;font-weight:500;margin-bottom:16px;}
    .form-label{font-size:13px;font-weight:600;color:var(--text);margin-bottom:5px;display:block;}
    input[type=text],input[type=email],input[type=password],input[type=file],select,.form-control,textarea{border-radius:9px;border:1px solid var(--border);padding:9px 12px;font-size:14px;font-family:inherit;color:var(--text);background:var(--surface);transition:border-color .2s,box-shadow .2s;width:100%;}
    input:focus,textarea:focus,select:focus,.form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.12);outline:none;}
    .mb-3{margin-bottom:16px;}
    .btn{font-family:inherit;font-size:13px;font-weight:600;border-radius:9px;padding:9px 18px;transition:all .18s;cursor:pointer;display:inline-flex;align-items:center;gap:6px;border:none;text-decoration:none;}
    .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);color:#fff;}
    .btn-outline-secondary{background:transparent;color:var(--text-muted);border:1.5px solid var(--border);border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-family:inherit;transition:all .18s;}
    .btn-outline-secondary:hover{background:var(--surface2);color:var(--text);}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .span-2{grid-column:span 2;}
    @media(max-width:640px){body{padding:10px 10px 40px;}.page-card{border-radius:14px;}.page-topbar,.page-inner{padding:14px 16px;}.form-grid{grid-template-columns:1fr;}.span-2{grid-column:span 1;}}

    .page-card { max-width: 720px; }
    .upload-zone { background:var(--surface2); border:2px dashed var(--border); border-radius:var(--radius); padding:28px; text-align:center; transition:border-color .2s; }
    .upload-zone:hover { border-color:var(--accent); }
</style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2>Upload MOA & Endorsement Letter</h2>
            <p>Upload documents for students</p>
        </div>
        <a href="admin_dashboard.php" class="btn-outline-secondary">⬅ Back</a>
    </div>
    <div class="page-inner">
        <?php if (!empty($success_message)): ?>
            <div class="success-msg"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="error-msg"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            
            <div class="form-grid">
                <div class="mb-3">
                    <label class="form-label" for="student_id">Select Student</label>
                    <select id="student_id" name="student_id" class="form-control" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= $student['student_id'] ?>">
                                <?= htmlspecialchars($student['full_name'] . ' (' . $student['username'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="document_type">Document Type</label>
                    <select id="document_type" name="document_type" class="form-control" required>
                        <option value="">-- Choose Type --</option>
                        <option value="MOA">Memorandum of Agreement (MOA) - Requires Approval</option>
                        <option value="Endorsement Letter">Endorsement Letter - Requires Approval</option>
                        <option value="Resume">Resume - No Approval Required</option>
                    </select>
                </div>
            </div>

            <div class="mb-3 span-2">
                <label class="form-label" for="moa_file">Upload File</label>
                <div class="upload-zone">
                    <div style="font-size:2rem;margin-bottom:10px;">📄</div>
                    <p style="color:var(--text-muted);font-size:14px;margin:0 0 14px;">Select a PDF or DOCX file (max 20MB)</p>
                    <input type="file" id="moa_file" name="moa_file" class="form-control" accept=".pdf,.docx,.doc" required>
                </div>
            </div>

            <div style="background:#fef3c7;color:#92400e;padding:12px 16px;border-radius:9px;border:1px solid #fcd34d;font-size:13px;margin-bottom:20px;">
                <strong>ℹ️ Approval Workflow:</strong> Once uploaded, documents require approval from the supervisor first, then the admin. Students cannot access their account until both approvals are received.
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">📤 Upload Document</button>
        </form>
    </div>
</div>
</body>
</html>
