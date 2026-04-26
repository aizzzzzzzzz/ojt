<?php
session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';

if (empty($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];
$student_username = '';

$stmt = $pdo->prepare("SELECT username, CONCAT(first_name, ' ', last_name) as full_name FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if ($student) {
    $student_username = $student['username'];
    $student_name = $student['full_name'];
}

$moa_stmt = $pdo->prepare("
    SELECT
        id,
        document_type,
        supervisor_approval_status,
        supervisor_approved_at,
        supervisor_rejection_reason,
        admin_approval_status,
        admin_approved_at,
        admin_rejection_reason,
        uploaded_at
    FROM moa_documents
    WHERE student_id = ?
    ORDER BY document_type
");
$moa_stmt->execute([$student_id]);
$documents = $moa_stmt->fetchAll(PDO::FETCH_ASSOC);

$has_moa = false;
$has_endorsement = false;
$has_resume = false;

foreach ($documents as $doc) {
    if ($doc['document_type'] === 'MOA') {
        $has_moa = true;
        $moa_doc = $doc;
    } elseif ($doc['document_type'] === 'Endorsement Letter') {
        $has_endorsement = true;
        $endo_doc = $doc;
    } elseif ($doc['document_type'] === 'Resume') {
        $has_resume = true;
        $resume_doc = $doc;
    }
}

if ($has_moa && $has_endorsement) {
    $moa_approved = ($moa_doc['supervisor_approval_status'] === 'approved' && $moa_doc['admin_approval_status'] === 'approved');
    $endo_approved = ($endo_doc['supervisor_approval_status'] === 'approved' && $endo_doc['admin_approval_status'] === 'approved');
    
    if ($moa_approved && $endo_approved) {
        $update_stmt = $pdo->prepare("UPDATE moa_documents SET is_new_student = 0 WHERE student_id = ? AND is_new_student = 1");
        $update_stmt->execute([$student_id]);
        header("Location: student_dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Approval Status</title>
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
            --yellow: #ca8a04;
            --yellow-lt: #fef3c7;
            --radius: 14px;
            --shadow-md: 0 2px 8px rgba(0,0,0,.07), 0 8px 28px rgba(0,0,0,.07);
        }
        
        * { box-sizing: border-box; }
        body {
            font-family: 'DM Sans', 'Segoe UI', sans-serif;
            background: radial-gradient(circle at top left, rgba(67,97,238,0.16), transparent 30%),
                        linear-gradient(180deg, #eef4ff 0%, #f8fbff 50%, #f3f6fb 100%);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            margin: 0;
            padding: 32px 20px 60px;
        }

        .container {
            max-width: 680px;
            margin: 0 auto;
        }

        .card {
            background: var(--surface);
            border-radius: 20px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .card-header {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.12), rgba(99, 170, 229, 0.08));
            border-bottom: 1px solid var(--border);
            padding: 34px 28px;
            text-align: center;
        }

        .card-header::before,
        .card-header::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            opacity: 0.45;
        }

        .card-header::before {
            width: 140px;
            height: 140px;
            background: rgba(67, 97, 238, 0.15);
            top: -30px;
            right: -30px;
        }

        .card-header::after {
            width: 84px;
            height: 84px;
            background: rgba(67, 97, 238, 0.08);
            bottom: -20px;
            left: -16px;
        }

        .card-header h1 {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
        }

        .card-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
        }

        .card-body {
            padding: 32px 28px;
        }

        .hero-panel {
            display: grid;
            gap: 18px;
            margin-bottom: 30px;
            background: linear-gradient(180deg, rgba(67,97,238,0.08), rgba(99,170,229,0.04));
            border: 1px solid rgba(67,97,238,0.12);
            border-radius: 20px;
            padding: 24px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.8);
        }

        .hero-panel strong {
            color: var(--text);
        }

        .status-message {
            background: var(--surface);
            color: var(--text);
            border: 1px solid rgba(67,97,238,0.16);
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 28px;
            font-size: 15px;
            line-height: 1.7;
            box-shadow: 0 20px 42px rgba(67,97,238,0.05);
        }

        .document-grid {
            display: grid;
            gap: 18px;
        }

        .document-check {
            padding: 22px;
            border-radius: 20px;
            border: 1px solid rgba(226,232,240,0.9);
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 15px 30px rgba(15, 23, 42, 0.05);
        }

        .document-check h3 {
            margin: 0 0 12px;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
            padding: 6px 12px;
            border-radius: 999px;
            margin-left: auto;
            min-width: 120px;
            text-align: center;
        }

        .status-pending {
            background: rgba(250,204,21,0.14);
            color: #92400e;
        }

        .status-approved {
            background: rgba(5,150,105,0.14);
            color: #047857;
        }

        .status-rejected {
            background: rgba(239,68,68,0.14);
            color: #b91c1c;
        }

        .approval-step {
            margin: 16px 0 0;
            padding: 18px 0 0;
            border-top: 1px solid rgba(226,232,240,0.9);
            font-size: 14px;
        }

        .approval-step:first-of-type {
            margin-top: 0;
            border-top: none;
            padding-top: 0;
        }

        .step-title {
            font-weight: 600;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .step-status {
            font-size: 12px;
            color: var(--text-muted);
            margin: 4px 0 0;
        }

        .step-reason {
            background: rgba(254, 205, 211, 0.7);
            color: #991b1b;
            padding: 10px 14px;
            border-radius: 12px;
            margin: 10px 0 0;
            font-size: 13px;
            border: 1px solid rgba(251,146,60,0.2);
        }

        .missing-document {
            padding: 18px;
            background: rgba(254, 226, 226, 0.7);
            border: 1px dashed rgba(239, 68, 68, 0.35);
            border-radius: 16px;
            color: #991b1b;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .missing-document strong {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .upload-link {
            background: var(--accent);
            color: #fff;
            padding: 10px 16px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            transition: all .18s ease;
            box-shadow: 0 12px 24px rgba(67,97,238,0.12);
            white-space: nowrap;
        }

        .upload-link:hover {
            transform: translateY(-1px);
            background: var(--accent-dk);
        }

        .upload-link:hover {
            background: #991b1b;
            transform: translateY(-1px);
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 28px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
            font-family: inherit;
            min-width: 160px;
            box-shadow: 0 14px 24px rgba(15, 23, 42, 0.08);
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--accent-dk);
        }

        .btn-outline {
            background: transparent;
            color: var(--text);
            border: 1.5px solid rgba(15, 23, 42, 0.1);
        }

        .btn-outline:hover {
            background: rgba(71, 85, 105, 0.04);
            color: var(--text);
        }

        .btn-red {
            background: var(--red);
            color: #fff;
            flex: 0;
        }

        .btn-red:hover {
            background: #991b1b;
        }

        @media(max-width: 640px) {
            body { padding: 16px 16px 40px; }
            .card-header { padding: 24px 16px; }
            .card-body { padding: 20px 16px; }
            .button-group { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h1>⏳ Account Activation Pending</h1>
            <p>Your documents are being reviewed</p>
        </div>
        
        <div class="card-body">
            <div class="status-message">
                Welcome, <strong><?= htmlspecialchars($student_name ?? $student_username) ?></strong>! Your account requires document approval before you can proceed. Both your MOA and Endorsement Letter must be approved by your supervisor and admin.
            </div>

            <div class="document-grid">
                <!-- MOA Status -->
                <div class="document-check">
                    <h3>
                        📋 Memorandum of Agreement (MOA)
                        <?php if ($has_moa): ?>
                            <span class="status-badge status-<?= strtolower($moa_doc['supervisor_approval_status']) ?>">
                                <?= ucfirst($moa_doc['supervisor_approval_status']) ?>
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-pending">Not Uploaded</span>
                        <?php endif; ?>
                    </h3>
                    
                    <?php if ($has_moa): ?>
                        <div class="approval-step">
                            <div class="step-title">
                                <?php if ($moa_doc['supervisor_approval_status'] === 'approved'): ?>
                                    ✅ Supervisor Approval
                                <?php elseif ($moa_doc['supervisor_approval_status'] === 'rejected'): ?>
                                    ❌ Supervisor Approval
                                <?php else: ?>
                                    ⏳ Supervisor Approval
                                <?php endif; ?>
                            </div>
                            <?php if ($moa_doc['supervisor_approval_status'] === 'approved'): ?>
                                <div class="step-status">Approved on <?= date('M d, Y', strtotime($moa_doc['supervisor_approved_at'])) ?></div>
                            <?php elseif ($moa_doc['supervisor_approval_status'] === 'rejected'): ?>
                                <div class="step-status">Rejected</div>
                                <?php if ($moa_doc['supervisor_rejection_reason']): ?>
                                    <div class="step-reason"><strong>Reason:</strong> <?= htmlspecialchars($moa_doc['supervisor_rejection_reason']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="step-status">Pending supervisor review...</div>
                            <?php endif; ?>
                        </div>

                        <div class="approval-step">
                            <div class="step-title">
                                <?php if ($moa_doc['admin_approval_status'] === 'approved'): ?>
                                    ✅ Admin Approval
                                <?php elseif ($moa_doc['admin_approval_status'] === 'rejected'): ?>
                                    ❌ Admin Approval
                                <?php else: ?>
                                    ⏳ Admin Approval
                                <?php endif; ?>
                            </div>
                            <?php if ($moa_doc['admin_approval_status'] === 'approved'): ?>
                                <div class="step-status">Approved on <?= date('M d, Y', strtotime($moa_doc['admin_approved_at'])) ?></div>
                            <?php elseif ($moa_doc['admin_approval_status'] === 'rejected'): ?>
                                <div class="step-status">Rejected</div>
                                <?php if ($moa_doc['admin_rejection_reason']): ?>
                                    <div class="step-reason"><strong>Reason:</strong> <?= htmlspecialchars($moa_doc['admin_rejection_reason']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="step-status">Pending admin review...</div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="missing-document">
                            <strong>⚠️ Not yet uploaded</strong>
                            Your MOA document hasn't been uploaded yet. 
                            <a href="upload_student_document.php?type=MOA" class="upload-link">Upload now</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Endorsement Letter Status -->
                <div class="document-check">
                    <h3>
                        📄 Endorsement Letter
                        <?php if ($has_endorsement): ?>
                            <span class="status-badge status-<?= strtolower($endo_doc['supervisor_approval_status']) ?>">
                                <?= ucfirst($endo_doc['supervisor_approval_status']) ?>
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-pending">Not Uploaded</span>
                        <?php endif; ?>
                    </h3>
                    
                    <?php if ($has_endorsement): ?>
                        <div class="approval-step">
                            <div class="step-title">
                                <?php if ($endo_doc['supervisor_approval_status'] === 'approved'): ?>
                                    ✅ Supervisor Approval
                                <?php elseif ($endo_doc['supervisor_approval_status'] === 'rejected'): ?>
                                    ❌ Supervisor Approval
                                <?php else: ?>
                                    ⏳ Supervisor Approval
                                <?php endif; ?>
                            </div>
                            <?php if ($endo_doc['supervisor_approval_status'] === 'approved'): ?>
                                <div class="step-status">Approved on <?= date('M d, Y', strtotime($endo_doc['supervisor_approved_at'])) ?></div>
                            <?php elseif ($endo_doc['supervisor_approval_status'] === 'rejected'): ?>
                                <div class="step-status">Rejected</div>
                                <?php if ($endo_doc['supervisor_rejection_reason']): ?>
                                    <div class="step-reason"><strong>Reason:</strong> <?= htmlspecialchars($endo_doc['supervisor_rejection_reason']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="step-status">Pending supervisor review...</div>
                            <?php endif; ?>
                        </div>

                        <div class="approval-step">
                            <div class="step-title">
                                <?php if ($endo_doc['admin_approval_status'] === 'approved'): ?>
                                    ✅ Admin Approval
                                <?php elseif ($endo_doc['admin_approval_status'] === 'rejected'): ?>
                                    ❌ Admin Approval
                                <?php else: ?>
                                    ⏳ Admin Approval
                                <?php endif; ?>
                            </div>
                            <?php if ($endo_doc['admin_approval_status'] === 'approved'): ?>
                                <div class="step-status">Approved on <?= date('M d, Y', strtotime($endo_doc['admin_approved_at'])) ?></div>
                            <?php elseif ($endo_doc['admin_approval_status'] === 'rejected'): ?>
                                <div class="step-status">Rejected</div>
                                <?php if ($endo_doc['admin_rejection_reason']): ?>
                                    <div class="step-reason"><strong>Reason:</strong> <?= htmlspecialchars($endo_doc['admin_rejection_reason']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="step-status">Pending admin review...</div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="missing-document">
                            <strong>⚠️ Not yet uploaded</strong>
                            Your Endorsement Letter hasn't been uploaded yet.
                            <a href="upload_student_document.php?type=Endorsement%20Letter" class="upload-link">Upload now</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Resume Status -->
                <div class="document-check">
                    <h3>
                        📋 Resume
                        <?php if ($has_resume): ?>
                            <span class="status-badge status-<?= strtolower($resume_doc['supervisor_approval_status']) ?>">
                                <?= ucfirst($resume_doc['supervisor_approval_status']) ?>
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-pending">Not Uploaded</span>
                        <?php endif; ?>
                    </h3>

                    <?php if ($has_resume): ?>
                        <div class="approval-step">
                            <div class="step-title">
                                <?php if ($resume_doc['supervisor_approval_status'] === 'approved'): ?>
                                    ✅ Supervisor Approval
                                <?php elseif ($resume_doc['supervisor_approval_status'] === 'rejected'): ?>
                                    ❌ Supervisor Approval
                                <?php else: ?>
                                    ⏳ Supervisor Approval
                                <?php endif; ?>
                            </div>
                            <?php if ($resume_doc['supervisor_approval_status'] === 'approved'): ?>
                                <div class="step-status">Approved on <?= date('M d, Y', strtotime($resume_doc['supervisor_approved_at'])) ?></div>
                            <?php elseif ($resume_doc['supervisor_approval_status'] === 'rejected'): ?>
                                <div class="step-status">Rejected</div>
                                <?php if ($resume_doc['supervisor_rejection_reason']): ?>
                                    <div class="step-reason"><strong>Reason:</strong> <?= htmlspecialchars($resume_doc['supervisor_rejection_reason']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="step-status">Pending supervisor review...</div>
                            <?php endif; ?>
                        </div>

                        <div class="approval-step">
                            <div class="step-title">
                                <?php if ($resume_doc['admin_approval_status'] === 'approved'): ?>
                                    ✅ Admin Approval
                                <?php elseif ($resume_doc['admin_approval_status'] === 'rejected'): ?>
                                    ❌ Admin Approval
                                <?php else: ?>
                                    ⏳ Admin Approval
                                <?php endif; ?>
                            </div>
                            <?php if ($resume_doc['admin_approval_status'] === 'approved'): ?>
                                <div class="step-status">Approved on <?= date('M d, Y', strtotime($resume_doc['admin_approved_at'])) ?></div>
                            <?php elseif ($resume_doc['admin_approval_status'] === 'rejected'): ?>
                                <div class="step-status">Rejected</div>
                                <?php if ($resume_doc['admin_rejection_reason']): ?>
                                    <div class="step-reason"><strong>Reason:</strong> <?= htmlspecialchars($resume_doc['admin_rejection_reason']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="step-status">Pending admin review...</div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="missing-document">
                            <strong>⚠️ Not yet uploaded</strong>
                            Your Resume hasn't been uploaded yet.
                            <a href="upload_student_document.php?type=Resume" class="upload-link">Upload now</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="button-group">
                <button onclick="location.reload();" class="btn btn-primary">🔄 Refresh Status</button>
                <a href="logout.php" class="btn btn-outline">Logout</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
