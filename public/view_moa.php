<?php
session_start();
require_once __DIR__ . '/../private/config.php';

// Check if user is logged in (any role)
if (empty($_SESSION['student_id']) && empty($_SESSION['employer_id']) && empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Determine current user
$current_user_type = null;
$current_user_id = null;
$current_student_id = null;

if (!empty($_SESSION['student_id'])) {
    $current_user_type = 'student';
    $current_user_id = $_SESSION['student_id'];
    $current_student_id = $_SESSION['student_id'];
} elseif (!empty($_SESSION['employer_id'])) {
    $current_user_type = 'supervisor';
    $current_user_id = $_SESSION['employer_id'];
} elseif (!empty($_SESSION['admin_id'])) {
    $current_user_type = 'admin';
    $current_user_id = $_SESSION['admin_id'];
}

// Get student info (for supervisors)
$student_id = (int) ($_GET['student_id'] ?? 0);
if ($current_user_type === 'student') {
    $student_id = $current_student_id;
}

if ($student_id <= 0) {
    die('Invalid student ID.');
}

// Get student info
$stmt = $pdo->prepare("SELECT student_id, username, CONCAT(first_name, ' ', last_name) as full_name FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    die('Student not found.');
}

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

// Get MOA documents
$stmt = $pdo->prepare("
    SELECT id, student_id, document_type, filename, filepath, supervisor_signature_path, uploaded_at
    FROM moa_documents
    WHERE student_id = ?
    ORDER BY document_type
");
$stmt->execute([$student_id]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle download
if (isset($_GET['download'])) {
    $doc_id = (int) $_GET['download'];
    $stmt = $pdo->prepare("SELECT filepath, filename, document_type FROM moa_documents WHERE id = ? AND student_id = ?");
    $stmt->execute([$doc_id, $student_id]);
    $doc = $stmt->fetch();
    
    if ($doc && file_exists($doc['filepath'])) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . htmlspecialchars($doc['filename']) . '"');
        header('Content-Length: ' . filesize($doc['filepath']));
        readfile($doc['filepath']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MOA & Endorsement Letter</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/portal-ui.css">
<style>
    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--radius:14px;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:32px 20px 60px;}
    .page-card{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);width:100%;margin:0 auto;overflow:hidden;}
    .page-topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 28px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;}
    .page-topbar h2{font-size:18px;font-weight:700;margin:0;letter-spacing:-.3px;}
    .page-topbar p{font-size:13px;color:var(--text-muted);margin:2px 0 0;}
    .page-inner{padding:24px 28px 32px;}
    .doc-card{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:18px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
    .doc-info{flex:1;min-width:200px;}
    .doc-info h4{margin:0 0 4px;font-size:15px;font-weight:700;color:var(--text);}
    .doc-info p{margin:0;font-size:12px;color:var(--text-muted);}
    .doc-actions{display:flex;gap:8px;flex-wrap:wrap;}
    .btn{font-family:inherit;font-size:13px;font-weight:600;border-radius:9px;padding:9px 18px;transition:all .18s;cursor:pointer;display:inline-flex;align-items:center;gap:6px;border:none;text-decoration:none;}
    .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);}
    .btn-secondary{background:var(--surface);color:var(--text);border:1.5px solid var(--border);}.btn-secondary:hover{background:var(--surface2);}
    .btn-outline-secondary{background:transparent;color:var(--text-muted);border:1.5px solid var(--border);}.btn-outline-secondary:hover{background:var(--surface2);color:var(--text);}
    .empty-state{text-align:center;padding:40px 20px;color:var(--text-muted);}
    .empty-state-icon{font-size:3rem;margin-bottom:12px;}
    @media(max-width:640px){.doc-card{flex-direction:column;align-items:flex-start;}.doc-actions{width:100%;}}

    .page-card { max-width: 800px; }
</style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2><?= htmlspecialchars($student['full_name']) ?></h2>
            <p>MOA & Endorsement Letter Documents</p>
        </div>
        <a href="<?= $current_user_type === 'student' ? 'student_dashboard.php' : ($current_user_type === 'supervisor' ? 'supervisor_dashboard.php' : 'admin_dashboard.php') ?>" class="btn btn-outline-secondary">⬅ Back</a>
    </div>
    <div class="page-inner">
        <?php if (empty($documents)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📄</div>
                <p>No MOA or Endorsement Letter has been uploaded yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($documents as $doc): ?>
            <div class="doc-card">
                <div class="doc-info">
                    <h4><?= htmlspecialchars($doc['document_type']) ?></h4>
                    <p><?= htmlspecialchars($doc['filename']) ?></p>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Uploaded: <?= date('M d, Y g:i A', strtotime($doc['uploaded_at'])) ?></p>
                    <?php if (!empty($doc['supervisor_signature_path'])): ?>
                        <p style="font-size:11px;color:var(--green);margin-top:4px;">✓ Signed by supervisor</p>
                    <?php endif; ?>
                </div>
                <div class="doc-actions">
                    <a href="?student_id=<?= $student_id ?>&download=<?= $doc['id'] ?>" class="btn btn-primary">⬇ Download</a>
                    <?php if ($current_user_type === 'supervisor'): ?>
                        <a href="edit_moa.php?id=<?= $doc['id'] ?>" class="btn btn-secondary">✏️ Sign</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
