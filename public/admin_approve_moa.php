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
$admin_id = $_SESSION['admin_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    check_csrf($_POST['csrf_token'] ?? '');
    
    $doc_id = (int)($_POST['doc_id'] ?? 0);
    $action = sanitize_input($_POST['action'] ?? '');
    $rejection_reason = sanitize_input($_POST['rejection_reason'] ?? '');
    
    if ($doc_id <= 0 || !in_array($action, ['approve', 'reject'])) {
        $error_message = "Invalid request parameters.";
    } else {
        try {
            if ($action === 'approve') {
                $update_stmt = $pdo->prepare("
                    UPDATE moa_documents 
                    SET admin_approval_status = 'approved', 
                        admin_approved_at = NOW(),
                        admin_rejection_reason = NULL
                    WHERE id = ?
                ");
                $update_stmt->execute([$doc_id]);
                $success_message = "Document approved successfully!";
                write_audit_log('MOA Student Approval', "Admin approved MOA document ID: $doc_id");
            } else {
                if (empty($rejection_reason)) {
                    $error_message = "Please provide a rejection reason.";
                } else {
                    $update_stmt = $pdo->prepare("
                        UPDATE moa_documents 
                        SET admin_approval_status = 'rejected', 
                            admin_rejection_reason = ?
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$rejection_reason, $doc_id]);
                    $success_message = "Document rejected. Supervisor and student will be notified.";
                    write_audit_log('MOA Student Rejection', "Admin rejected MOA document ID: $doc_id - Reason: $rejection_reason");
                }
            }
        } catch (PDOException $e) {
            error_log("Error updating MOA approval: " . $e->getMessage());
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

$createTableSQL = "CREATE TABLE IF NOT EXISTS moa_documents (
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
)";
try {
    $pdo->exec($createTableSQL);
    
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
        }
    }
} catch (PDOException $e) {
    error_log("Error creating MOA table: " . $e->getMessage());
}

$pending_stmt = $pdo->prepare("
    SELECT
        m.id,
        m.student_id,
        m.document_type,
        m.filename,
        m.supervisor_approval_status,
        m.supervisor_approved_at,
        m.admin_approval_status,
        m.uploaded_at,
        s.username,
        CONCAT(s.first_name, ' ', s.last_name) as student_name
    FROM moa_documents m
    JOIN students s ON m.student_id = s.student_id
    WHERE m.supervisor_approval_status = 'approved' AND m.admin_approval_status = 'pending'
    ORDER BY m.supervisor_approved_at DESC
");
$pending_stmt->execute();
$pending_documents = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

$history_stmt = $pdo->prepare("
    SELECT
        m.id,
        m.student_id,
        m.document_type,
        m.filename,
        m.supervisor_approval_status,
        m.admin_approval_status,
        m.admin_approved_at,
        m.admin_rejection_reason,
        m.uploaded_at,
        s.username,
        CONCAT(s.first_name, ' ', s.last_name) as student_name
    FROM moa_documents m
    JOIN students s ON m.student_id = s.student_id
    WHERE m.admin_approval_status IN ('approved', 'rejected')
    ORDER BY m.admin_approved_at DESC
    LIMIT 20
");
$history_stmt->execute();
$history_documents = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve MOA Documents - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/portal-ui.css">
    <style>
        :root {
            --bg: #f1f4f9;
            --surface: #fff;
            --surface2: #f8fafc;
            --border: #e3e8f0;
            --text: #111827;
            --text-muted: #6b7280;
            --accent: #4361ee;
            --accent-dk: #3451d1;
            --accent-lt: #eef1fd;
            --green: #16a34a;
            --green-lt: #dcfce7;
            --red: #dc2626;
            --red-lt: #fee2e2;
            --radius: 14px;
            --shadow-md: 0 2px 8px rgba(0,0,0,.07), 0 8px 28px rgba(0,0,0,.07);
        }
        
        * { box-sizing: border-box; }
        body {
            font-family: 'DM Sans', 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .page-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        .page-header p {
            color: var(--text-muted);
            margin: 0;
            font-size: 14px;
        }

        .back-btn {
            color: var(--text-muted);
            text-decoration: none;
            padding: 8px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            transition: all .18s;
        }

        .back-btn:hover {
            background: var(--surface2);
            color: var(--text);
        }

        .success-msg {
            background: var(--green-lt);
            color: #15803d;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #bbf7d0;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .error-msg {
            background: var(--red-lt);
            color: #b91c1c;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #fecaca;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-md);
        }

        .section h2 {
            margin: 0 0 20px;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .no-items {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .no-items p {
            margin: 0;
            font-size: 14px;
        }

        .document-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: start;
        }

        .document-info h3 {
            margin: 0 0 8px;
            font-size: 16px;
            font-weight: 600;
        }

        .document-info p {
            margin: 4px 0;
            font-size: 13px;
            color: var(--text-muted);
        }

        .doc-type-badge {
            display: inline-block;
            background: var(--accent-lt);
            color: var(--accent-dk);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-direction: column;
        }

        .btn {
            padding: 9px 14px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all .18s;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .btn-approve {
            background: var(--green);
            color: #fff;
        }

        .btn-approve:hover {
            background: #15a34a;
        }

        .btn-reject {
            background: var(--red);
            color: #fff;
        }

        .btn-reject:hover {
            background: #b91c1c;
        }

        .btn-secondary {
            background: var(--surface2);
            color: var(--text-muted);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
        }

        .rejection-form {
            background: var(--red-lt);
            border: 1px solid #fecaca;
            padding: 16px;
            border-radius: 10px;
            margin-top: 12px;
            display: none;
        }

        .rejection-form.show {
            display: block;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text);
        }

        textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            resize: vertical;
            min-height: 80px;
            box-sizing: border-box;
        }

        .status-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 4px;
            margin-left: 8px;
        }

        .status-approved {
            background: var(--green-lt);
            color: #15803d;
        }

        .status-rejected {
            background: var(--red-lt);
            color: #b91c1c;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        @media(max-width: 640px) {
            .document-card {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: row;
                width: 100%;
            }
            .btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>
<div class="page-container">
    <div class="page-header">
        <div>
            <h1>👑 Final MOA Approval</h1>
            <p>School administration - approve supervisor-reviewed documents</p>
        </div>
        <a href="admin_dashboard.php" class="back-btn">⬅ Back</a>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="success-msg"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <div class="error-msg"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Pending Documents Section (Supervisor Approved, Waiting for Admin) -->
    <div class="section">
        <h2>✓ Ready for Final Approval (<?= count($pending_documents) ?>)</h2>
        <p style="color: var(--text-muted); font-size: 13px; margin: -16px 0 16px 0;">These documents have been approved by the supervisor and are ready for your final approval</p>
        
        <?php if (empty($pending_documents)): ?>
            <div class="no-items">
                <p>No documents waiting for your approval. Great job! ✓</p>
            </div>
        <?php else: ?>
            <?php foreach ($pending_documents as $doc): ?>
                <div class="document-card">
                    <div class="document-info">
                        <h3>
                            <?= htmlspecialchars($doc['student_name']) ?>
                            <span class="doc-type-badge"><?= htmlspecialchars($doc['document_type']) ?></span>
                        </h3>
                        <p>
                            <strong>Student ID:</strong> <?= htmlspecialchars($doc['username']) ?>
                        </p>
                        <p>
                            <strong>Uploaded:</strong> <?= date('M d, Y \a\t H:i', strtotime($doc['uploaded_at'])) ?>
                        </p>
                        <p>
                            <strong>Supervisor Approved:</strong> <?= date('M d, Y \a\t H:i', strtotime($doc['supervisor_approved_at'])) ?>
                        </p>
                        <p>
                            <strong>File:</strong> <?= htmlspecialchars($doc['filename']) ?>
                        </p>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="download_document.php?id=<?= (int)$doc['id'] ?>" class="btn btn-secondary" title="Download">
                            📥 Download
                        </a>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
                            <button type="submit" class="btn btn-approve" style="width: 100%; cursor: pointer;">
                                ✅ Approve
                            </button>
                        </form>
                        <button type="button" onclick="toggleRejectForm(this)" class="btn btn-reject" style="width: 100%; cursor: pointer;">
                            ❌ Reject
                        </button>
                        <div class="rejection-form">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
                                <label class="form-label">Rejection Reason</label>
                                <textarea name="rejection_reason" required placeholder="Explain why this document is being rejected..."></textarea>
                                <div style="margin-top: 8px; display: flex; gap: 8px;">
                                    <button type="submit" class="btn btn-reject" style="flex: 1; cursor: pointer;">Submit Rejection</button>
                                    <button type="button" onclick="toggleRejectForm(null)" class="btn btn-secondary" style="flex: 1; cursor: pointer;">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Approval History Section -->
    <div class="section">
        <h2>📊 Approval History (Last 20)</h2>
        
        <?php if (empty($history_documents)): ?>
            <div class="no-items">
                <p>No approval history yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($history_documents as $doc): ?>
                <div class="document-card" style="opacity: 0.8;">
                    <div class="document-info">
                        <h3>
                            <?= htmlspecialchars($doc['student_name']) ?>
                            <span class="doc-type-badge"><?= htmlspecialchars($doc['document_type']) ?></span>
                            <span class="status-badge status-<?= strtolower($doc['admin_approval_status']) ?>">
                                <?= ucfirst($doc['admin_approval_status']) ?>
                            </span>
                        </h3>
                        <p>
                            <strong>Student ID:</strong> <?= htmlspecialchars($doc['username']) ?>
                        </p>
                        <p>
                            <strong>Supervisor:</strong>
                            <span class="status-badge status-<?= strtolower($doc['supervisor_approval_status']) ?>">
                                <?= ucfirst($doc['supervisor_approval_status']) ?>
                            </span>
                        </p>
                        <?php if ($doc['admin_approval_status'] === 'approved'): ?>
                            <p>
                                <strong>Approved on:</strong> <?= date('M d, Y \a\t H:i', strtotime($doc['admin_approved_at'])) ?>
                            </p>
                        <?php elseif ($doc['admin_approval_status'] === 'rejected'): ?>
                            <p style="color: #b91c1c;">
                                <strong>Rejected</strong>
                                <?php if ($doc['admin_rejection_reason']): ?>
                                    <br><em><?= htmlspecialchars($doc['admin_rejection_reason']) ?></em>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleRejectForm(btn) {
    if (btn === null) {
        document.querySelectorAll('.rejection-form').forEach(f => f.classList.remove('show'));
    } else if (btn) {
        const form = btn.nextElementSibling;
        if (form && form.classList.contains('rejection-form')) {
            form.classList.toggle('show');
        }
    }
}
</script>
</body>
</html>
