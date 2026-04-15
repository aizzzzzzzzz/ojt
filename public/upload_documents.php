<?php
session_start();

if ((!isset($_SESSION['employer_id']) && !isset($_SESSION['uploader_type'])) || $_SESSION['role'] !== "employer") {
    header("Location: employer_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';

if (isset($_SESSION['employer_id'])) {
    $uploader_type = 'employer';
    $uploader_id = $_SESSION['employer_id'];
    $table = 'employers';
    $id_field = 'employer_id';
} else {
    $uploader_type = $_SESSION['uploader_type'];
    $uploader_id = $_SESSION['uploader_id'];
    $table = 'admins';
    $id_field = 'admin_id';
}

$stmt = $pdo->prepare("SELECT * FROM $table WHERE $id_field = ?");
$stmt->execute([$uploader_id]);
$employer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employer) {
    session_destroy();
    header("Location: employer_login.php");
    exit;
}

$csrf_token = generate_csrf_token();

$uploadError = '';
$uploadSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    check_csrf($_POST['csrf_token'] ?? '');
    if (!empty($_FILES['uploaded_file']['tmp_name'])) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = basename($_FILES['uploaded_file']['name']);
        $destPath = $uploadDir . uniqid() . '_' . $fileName;
        $allowedTypes = ['pdf','docx','jpg','png','jpeg','txt','xlsx','pptx'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedTypes)) {
            $uploadError = "Invalid file type. Allowed types: " . implode(', ', $allowedTypes);
        } elseif ($_FILES['uploaded_file']['size'] > 10*1024*1024) {
            $uploadError = "File too large (max 10MB).";
        }

        if (!$uploadError) {
            try {
                $tableExists = $pdo->query("SHOW TABLES LIKE 'uploaded_files'")->fetch();
                if ($tableExists) {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM uploaded_files WHERE uploader_type = ? AND uploader_id = ? AND filename = ?");
                    $checkStmt->execute([$uploader_type, $uploader_id, $fileName]);
                    $duplicateCount = $checkStmt->fetchColumn();
                    
                    if ($duplicateCount > 0) {
                        $uploadError = "A file with the name '$fileName' already exists. Please rename your file or upload a different one.";
                    }
                }
            } catch (PDOException $e) {
            }
        }

        if (!$uploadError && move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $destPath)) {
            try {
                $createTableSQL = "
                    CREATE TABLE IF NOT EXISTS uploaded_files (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        uploader_type ENUM('admin', 'employer') NOT NULL,
                        uploader_id INT NOT NULL,
                        filename VARCHAR(255) NOT NULL,
                        filepath VARCHAR(500) NOT NULL,
                        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        description TEXT,
                        UNIQUE KEY unique_uploader_filename (uploader_type, uploader_id, filename)
                    )
                ";
                $pdo->exec($createTableSQL);
                
                $stmt = $pdo->prepare("INSERT INTO uploaded_files (uploader_type, uploader_id, filename, filepath, description) VALUES (?, ?, ?, ?, ?)");
                $description = $_POST['description'] ?? '';
                $stmt->execute([$uploader_type, $uploader_id, $fileName, $destPath, $description]);
                
                write_audit_log_manual($uploader_type, $uploader_id, 'File Upload', $fileName);
                $uploadSuccess = true;
                $_SESSION['success_message'] = "File '$fileName' uploaded successfully!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $uploadError = "A file with the name '$fileName' already exists. Please rename your file or upload a different one.";
                    
                    if (file_exists($destPath)) {
                        unlink($destPath);
                    }
                } else {
                    $uploadError = "Database error: " . $e->getMessage();
                }
            }
        }
    } else {
        $uploadError = "No file selected.";
    }
}

try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'uploaded_files'")->fetch();
    if (!$tableExists) {
        $createTableSQL = "
            CREATE TABLE uploaded_files (
                id INT AUTO_INCREMENT PRIMARY KEY,
                uploader_type ENUM('admin', 'employer') NOT NULL,
                uploader_id INT NOT NULL,
                filename VARCHAR(255) NOT NULL,
                filepath VARCHAR(500) NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                description TEXT,
                UNIQUE KEY unique_uploader_filename (uploader_type, uploader_id, filename)
            )
        ";
        $pdo->exec($createTableSQL);
        $uploaded_files = [];
    } else {
        $columns = $pdo->query("DESCRIBE uploaded_files")->fetchAll(PDO::FETCH_ASSOC);
        $hasUploaderType = false;
        $hasEmployerId = false;
        foreach ($columns as $col) {
            if ($col['Field'] === 'uploader_type') $hasUploaderType = true;
            if ($col['Field'] === 'employer_id') $hasEmployerId = true;
        }

        if (!$hasUploaderType && $hasEmployerId) {
            $pdo->exec("ALTER TABLE uploaded_files ADD COLUMN uploader_type ENUM('admin', 'employer') NOT NULL DEFAULT 'employer' AFTER id");
            $pdo->exec("ALTER TABLE uploaded_files ADD COLUMN uploader_id INT NOT NULL DEFAULT 0 AFTER uploader_type");
            $pdo->exec("UPDATE uploaded_files SET uploader_id = employer_id WHERE uploader_id = 0");
            $pdo->exec("ALTER TABLE uploaded_files DROP COLUMN employer_id");
            $pdo->exec("ALTER TABLE uploaded_files ADD UNIQUE KEY unique_uploader_filename (uploader_type, uploader_id, filename)");
        }

        $files_stmt = $pdo->prepare("
            SELECT id, filename, uploaded_at, description
            FROM uploaded_files
            WHERE uploader_type = ? AND uploader_id = ?
            ORDER BY uploaded_at DESC
        ");
        $files_stmt->execute([$uploader_type, $uploader_id]);
        $uploaded_files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $uploaded_files = [];
    $uploadError = "Error accessing database: " . $e->getMessage();
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Documents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<style>
    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--accent-lt:#eef1fd;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--amber:#d97706;--amber-lt:#fef3c7;--radius:14px;--shadow-sm:0 1px 2px rgba(0,0,0,.05);--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:28px 20px 60px;}
    .page-card{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);width:100%;margin:0 auto;overflow:hidden;}
    .page-topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;}
    .page-topbar h2{font-size:18px;font-weight:700;margin:0;}
    .page-topbar p{font-size:13px;color:var(--text-muted);margin:2px 0 0;}
    .page-inner{padding:24px 28px 32px;}
    .success-msg{background:var(--green-lt);color:#15803d;padding:12px 16px;border-radius:10px;border:1px solid #bbf7d0;font-size:14px;font-weight:500;margin-bottom:16px;}
    .error-msg{background:var(--red-lt);color:#b91c1c;padding:12px 16px;border-radius:10px;border:1px solid #fecaca;font-size:14px;font-weight:500;margin-bottom:16px;}
    .form-label{font-size:14px;font-weight:700;color:var(--text);margin-bottom:8px;display:block;letter-spacing:-.2px;}
    .form-control,.form-select,input,textarea,select{border-radius:14px;border:1.5px solid rgba(226,232,240,.9);padding:12px 16px;font-size:14px;font-family:inherit;color:var(--text);background:#f8fbff;transition:border-color .2s,box-shadow .2s,background .2s;width:100%;}
    input:focus,textarea:focus,select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.12);outline:none;}
    .mb-3{margin-bottom:16px;}
    .btn{font-family:inherit;font-size:14px;font-weight:700;border-radius:14px;padding:12px 20px;transition:all .18s;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;border:none;text-decoration:none;box-shadow:0 12px 24px rgba(15,23,42,.08);}
    .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);color:#fff;box-shadow:0 16px 32px rgba(67,97,238,.2);}
    .btn-success{background:var(--green);color:#fff;}.btn-success:hover{background:#15803d;transform:translateY(-1px);color:#fff;box-shadow:0 16px 32px rgba(22,163,74,.2);}
    .btn-secondary{background:transparent;color:var(--text);border:1.5px solid rgba(15,23,42,.1);}.btn-secondary:hover{background:rgba(71,85,105,.05);color:var(--text);border-color:rgba(15,23,42,.15);}
    .btn-outline-secondary{background:transparent;color:var(--text);border:1.5px solid rgba(15,23,42,.1);border-radius:12px;padding:8px 14px;font-size:13px;font-weight:600;text-decoration:none;transition:all .18s;}.btn-outline-secondary:hover{background:rgba(71,85,105,.05);color:var(--text);border-color:rgba(15,23,42,.15);}
    .table{width:100%;border-collapse:collapse;font-size:14px;}
    .table thead th{background:var(--surface2);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);border-bottom:1px solid var(--border);padding:11px 14px;}
    .table tbody td{padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--text);}
    .table tbody tr:last-child td{border-bottom:none;}
    .table tbody tr:hover td{background:var(--accent-lt);}
    .table-section{overflow-x:auto;border-radius:var(--radius);border:1px solid var(--border);margin-top:16px;}
    @media(max-width:768px){body{padding:10px 10px 40px;}.page-card{border-radius:14px;}.page-topbar,.page-inner{padding:14px 16px;}}

    .page-card { max-width: 1100px; }
    .upload-zone { background:var(--surface2); border:2px dashed var(--border); border-radius:var(--radius); padding:28px; text-align:center; transition:border-color .2s; }
    .upload-zone:hover { border-color:var(--accent); }
    .file-item { display:flex; justify-content:space-between; align-items:center; padding:11px 0; border-bottom:1px solid var(--border); font-size:14px; gap:12px; }
    .file-item:last-child { border-bottom:none; }
    .file-item a { color:var(--accent); text-decoration:none; font-weight:500; }
    .file-item a:hover { text-decoration:underline; }
    .file-icon { font-size:20px; flex-shrink:0; }
    .file-meta { font-size:12px; color:var(--text-muted); }
</style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2>Upload Documents</h2>
            <p>Share documents and resources with your students</p>
        </div>
        <a href="supervisor_dashboard.php" class="btn-outline-secondary">⬅ Back</a>
    </div>
    <div class="page-inner">

        <?php if (!empty($success_message)): ?>
            <div class="success-msg"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="error-msg"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <h3>Upload New Document</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <div class="upload-zone mb-3">
                <div style="font-size:2rem;margin-bottom:10px;">📁</div>
                <p style="color:var(--text-muted);font-size:14px;margin:0 0 14px;">Select a file to upload (PDF, DOCX, JPG, PNG — max 5MB)</p>
                <input type="file" name="document" class="form-control" style="max-width:360px;margin:0 auto;" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Description (optional)</label>
                <input type="text" name="description" class="form-control" placeholder="Add a short description...">
            </div>
            <button type="submit" name="upload_document" class="btn btn-primary">📤 Upload Document</button>
        </form>

        <h3 style="margin-top:28px;">Uploaded Documents</h3>
        <?php if (!empty($documents)): ?>
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:0 16px;">
            <?php foreach ($documents as $doc): ?>
                <div class="file-item">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span class="file-icon">📄</span>
                        <div>
                            <div style="font-weight:600;color:var(--text);"><?= htmlspecialchars($doc['original_name'] ?? $doc['filename'] ?? 'Document') ?></div>
                            <?php if (!empty($doc['description'])): ?>
                                <div class="file-meta"><?= htmlspecialchars($doc['description']) ?></div>
                            <?php endif; ?>
                            <div class="file-meta"><?= htmlspecialchars($doc['uploaded_at'] ?? $doc['created_at'] ?? '') ?></div>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;flex-shrink:0;">
                        <a href="<?= htmlspecialchars($doc['filepath'] ?? $doc['file_path'] ?? '#') ?>" target="_blank" class="btn btn-primary btn-sm">View</a>
                        <a href="delete_document.php?id=<?= $doc['document_id'] ?? $doc['id'] ?>" class="btn btn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #fecaca;" onclick="return confirm('Delete this document?')">Delete</a>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color:var(--text-muted);text-align:center;padding:24px 0;">No documents uploaded yet.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>