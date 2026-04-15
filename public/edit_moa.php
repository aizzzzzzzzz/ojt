<?php
session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/audit.php';

// Only supervisors can edit
if (empty($_SESSION['employer_id']) || $_SESSION['role'] !== 'employer') {
    header('Location: supervisor_login.php');
    exit;
}

$supervisor_id = (int) $_SESSION['employer_id'];
$doc_id = (int) ($_GET['id'] ?? 0);
$error_message = '';
$success_message = '';

if ($doc_id <= 0) {
    die('Invalid document ID.');
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
    // Table might already exist, that's fine
}

// Get document
$stmt = $pdo->prepare("
    SELECT m.id, m.student_id, m.document_type, m.filename, m.filepath,
           s.username, CONCAT(s.first_name, ' ', s.last_name) as student_name
    FROM moa_documents m
    JOIN students s ON m.student_id = s.student_id
    WHERE m.id = ?
");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) {
    die('Document not found.');
}

// Handle signature upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_signature'])) {
    if (!empty($_POST['signature_data'])) {
        $signaturePath = 'assets/signature_moa_' . $doc['student_id'] . '_' . $doc_id . '.png';
        $data = $_POST['signature_data'];
        
        if (preg_match('/^data:image\/(\w+);base64,/', $data)) {
            $data = substr($data, strpos($data, ',') + 1);
            $decodedData = base64_decode($data, true);
            
            if ($decodedData !== false && !empty($decodedData)) {
                if (!is_dir('assets')) mkdir('assets', 0777, true);
                
                if (file_put_contents($signaturePath, $decodedData)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE moa_documents SET supervisor_signature_path = ? WHERE id = ?");
                        $stmt->execute([$signaturePath, $doc_id]);
                        
                        audit_log($pdo, 'Sign MOA', "Supervisor signed {$doc['document_type']} for {$doc['student_name']}");
                        $success_message = "Document signed successfully!";
                    } catch (PDOException $e) {
                        $error_message = "Database error: " . $e->getMessage();
                    }
                } else {
                    $error_message = "Failed to save signature.";
                }
            } else {
                $error_message = "Invalid signature data.";
            }
        } else {
            $error_message = "Invalid signature format.";
        }
    } else {
        $error_message = "Please draw a signature on the canvas.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign MOA Document</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/portal-ui.css">
<style>
    :root{--bg:#f1f4f9;--surface:#fff;--surface2:#f8fafc;--border:#e3e8f0;--text:#111827;--text-muted:#6b7280;--accent:#4361ee;--accent-dk:#3451d1;--green:#16a34a;--green-lt:#dcfce7;--red:#dc2626;--red-lt:#fee2e2;--radius:14px;--shadow-md:0 2px 8px rgba(0,0,0,.07),0 8px 28px rgba(0,0,0,.07);}
    *,*::before,*::after{box-sizing:border-box;}
    body{font-family:'DM Sans','Segoe UI',sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;margin:0;padding:32px 20px 60px;}
    .page-card{background:var(--surface);border-radius:20px;border:1px solid var(--border);box-shadow:var(--shadow-md);width:100%;margin:0 auto;overflow:hidden;}
    .page-topbar{display:flex;align-items:center;justify-content:space-between;padding:28px 32px;border-bottom:1px solid rgba(226,232,240,.9);flex-wrap:wrap;gap:16px;background:linear-gradient(135deg,rgba(67,97,238,.08),rgba(99,170,229,.05));}
    .page-topbar h2{font-size:22px;font-weight:700;margin:0;letter-spacing:-.4px;}
    .page-topbar p{font-size:14px;color:var(--text-muted);margin:4px 0 0;line-height:1.5;}
    .page-inner{padding:32px 32px 40px;}
    .info-box{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin-bottom:16px;}
    .info-box p{margin:0;font-size:13px;}
    .info-box strong{color:var(--text);}
    .success-msg{background:rgba(5,150,105,.1);color:#047857;padding:16px 18px;border-radius:14px;border:1.5px solid rgba(16,185,129,.25);font-size:14px;font-weight:600;margin-bottom:20px;}
    .error-msg{background:rgba(239,68,68,.1);color:#991b1b;padding:16px 18px;border-radius:14px;border:1.5px solid rgba(239,68,68,.25);font-size:14px;font-weight:600;margin-bottom:20px;}
    .form-label{font-size:13px;font-weight:600;color:var(--text);margin-bottom:5px;display:block;}
    .signature-pad{border:2px dashed var(--border);border-radius:var(--radius);background:#fafbfd;width:100%;height:200px;cursor:crosshair;display:block;margin-bottom:12px;}
    .signature-actions{display:flex;gap:8px;flex-wrap:wrap;}
    .btn{font-family:inherit;font-size:13px;font-weight:600;border-radius:9px;padding:9px 18px;transition:all .18s;cursor:pointer;display:inline-flex;align-items:center;gap:6px;border:none;text-decoration:none;}
    .btn-primary{background:var(--accent);color:#fff;}.btn-primary:hover{background:var(--accent-dk);transform:translateY(-1px);}
    .btn-secondary{background:var(--surface2);color:var(--text);border:1.5px solid var(--border);}.btn-secondary:hover{background:var(--border);}
    .btn-outline-secondary{background:transparent;color:var(--text-muted);border:1.5px solid var(--border);border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-family:inherit;transition:all .18s;}
    .btn-outline-secondary:hover{background:var(--surface2);color:var(--text);}
    .mb-3{margin-bottom:16px;}
    @media(max-width:640px){body{padding:10px 10px 40px;}.page-card{border-radius:14px;}.page-topbar,.page-inner{padding:14px 16px;}.signature-pad{height:150px;}}

    .page-card { max-width: 720px; }
</style>
</head>
<body>
<div class="page-card">
    <div class="page-topbar">
        <div>
            <h2>Sign Document</h2>
            <p><?= htmlspecialchars($doc['document_type']) ?></p>
        </div>
        <a href="view_moa.php?student_id=<?= $doc['student_id'] ?>" class="btn btn-outline-secondary">⬅ Back</a>
    </div>
    <div class="page-inner">
        <?php if (!empty($success_message)): ?>
            <div class="success-msg"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="error-msg"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="info-box mb-3">
            <p><strong>Student:</strong> <?= htmlspecialchars($doc['student_name']) ?></p>
            <p><strong>Document:</strong> <?= htmlspecialchars($doc['filename']) ?></p>
        </div>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Your Signature</label>
                <canvas id="signaturePad" class="signature-pad"></canvas>
                <div class="signature-actions">
                    <button type="button" class="btn btn-secondary" onclick="clearSignature()">🗑️ Clear</button>
                </div>
            </div>

            <input type="hidden" id="signature_data" name="signature_data" value="">
            <button type="submit" name="save_signature" class="btn btn-primary" style="width:100%;justify-content:center;">💾 Sign & Save</button>
        </form>
    </div>
</div>

<script>
const canvas = document.getElementById('signaturePad');
const ctx = canvas.getContext('2d');
let isDrawing = false;

canvas.width = canvas.offsetWidth;
canvas.height = canvas.offsetHeight;

ctx.lineCap = 'round';
ctx.lineJoin = 'round';
ctx.lineWidth = 3;
ctx.strokeStyle = '#111827';

canvas.addEventListener('mousedown', (e) => {
    isDrawing = true;
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    ctx.beginPath();
    ctx.moveTo(x, y);
});

canvas.addEventListener('mousemove', (e) => {
    if (!isDrawing) return;
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    ctx.lineTo(x, y);
    ctx.stroke();
});

canvas.addEventListener('mouseup', () => {
    isDrawing = false;
});

canvas.addEventListener('touchstart', (e) => {
    e.preventDefault();
    isDrawing = true;
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches[0];
    const x = touch.clientX - rect.left;
    const y = touch.clientY - rect.top;
    ctx.beginPath();
    ctx.moveTo(x, y);
});

canvas.addEventListener('touchmove', (e) => {
    if (!isDrawing) return;
    e.preventDefault();
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches[0];
    const x = touch.clientX - rect.left;
    const y = touch.clientY - rect.top;
    ctx.lineTo(x, y);
    ctx.stroke();
});

canvas.addEventListener('touchend', () => {
    isDrawing = false;
});

function clearSignature() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById('signature_data').value = '';
}

document.querySelector('form').addEventListener('submit', (e) => {
    const signatureData = canvas.toDataURL('image/png');
    document.getElementById('signature_data').value = signatureData;
});
</script>
</body>
</html>
