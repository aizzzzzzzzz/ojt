<?php
session_start();

if ((!isset($_SESSION['employer_id']) && !isset($_SESSION['uploader_type'])) || $_SESSION['role'] !== "employer") {
    header("Location: employer_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/middleware.php';

// Determine uploader type and id
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

// -------- Upload File --------
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

        // Check if file type is allowed
        if (!in_array($ext, $allowedTypes)) {
            $uploadError = "Invalid file type. Allowed types: " . implode(', ', $allowedTypes);
        } elseif ($_FILES['uploaded_file']['size'] > 10*1024*1024) {
            $uploadError = "File too large (max 10MB).";
        }

        // Check for duplicate file (same filename for same employer)
        if (!$uploadError) {
            try {
                // Check if table exists first
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
                // If table doesn't exist yet, no duplicates to check
            }
        }

        // Proceed with upload if no errors
        if (!$uploadError && move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $destPath)) {
            try {
                // Create table if it doesn't exist
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
                
                // Insert file record with uploader_type and uploader_id
                $stmt = $pdo->prepare("INSERT INTO uploaded_files (uploader_type, uploader_id, filename, filepath, description) VALUES (?, ?, ?, ?, ?)");
                $description = $_POST['description'] ?? '';
                $stmt->execute([$uploader_type, $uploader_id, $fileName, $destPath, $description]);
                
                // FIXED: Use manual audit log with correct user info
                write_audit_log_manual($uploader_type, $uploader_id, 'File Upload', $fileName);
                $uploadSuccess = true;
                $_SESSION['success_message'] = "File '$fileName' uploaded successfully!";
            } catch (PDOException $e) {
                // Check if error is due to duplicate entry
                if ($e->getCode() == 23000) { // SQLSTATE for duplicate entry
                    $uploadError = "A file with the name '$fileName' already exists. Please rename your file or upload a different one.";
                    
                    // Delete the uploaded file since it's a duplicate
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

// Get uploaded files for this employer
try {
    // Check if table exists, create if not
    $tableExists = $pdo->query("SHOW TABLES LIKE 'uploaded_files'")->fetch();
    if (!$tableExists) {
        $createTableSQL = "
            CREATE TABLE uploaded_files (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employer_id INT NOT NULL,
                filename VARCHAR(255) NOT NULL,
                filepath VARCHAR(500) NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                description TEXT
            )
        ";
        $pdo->exec($createTableSQL);
        $uploaded_files = [];
    } else {
        // Get files for this employer
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
    <title>Upload Documents - OJT Supervisor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
        }

        .dashboard-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 1200px;
            margin: 20px auto;
        }

        .dashboard-container h2 {
            margin-bottom: 10px;
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
            text-align: center;
        }

        .dashboard-container h3 {
            margin-top: 30px;
            margin-bottom: 20px;
            font-size: 22px;
            color: #2c3e50;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }

        .welcome-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .welcome-section p {
            font-size: 16px;
            color: #666;
            margin: 5px 0;
        }

        .success-msg {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            text-align: center;
            font-weight: 500;
        }

        .error-msg {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            text-align: center;
            font-weight: 500;
        }

        .upload-form {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .upload-form h4 {
            margin-bottom: 20px;
            color: #2c3e50;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(90deg, #007bff, #00c6ff);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(90deg, #0056b3, #0099cc);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: linear-gradient(90deg, #6c757d, #8e9ba7);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: linear-gradient(90deg, #545b62, #727b84);
            transform: translateY(-2px);
            color: white;
        }

        .table-section {
            overflow-x: auto;
            margin-top: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }

        th, td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }

        th {
            background: linear-gradient(90deg, #f8f9fa, #e9ecef);
            font-weight: 600;
            color: #2c3e50;
            position: sticky;
            top: 0;
        }

        tr:nth-child(even) {
            background: #f8f9fa;
        }

        tr:hover {
            background: #e3f2fd;
            transition: background 0.3s ease;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-danger {
            background: linear-gradient(90deg, #dc3545, #ff6b7a);
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background: linear-gradient(90deg, #bd2130, #e04b59);
        }

        .file-icon {
            font-size: 1.5rem;
            margin-right: 10px;
            vertical-align: middle;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .description-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 20px;
                margin: 10px;
            }

            .dashboard-container h2 {
                font-size: 24px;
            }

            .dashboard-container h3 {
                font-size: 18px;
            }

            .upload-form {
                padding: 20px;
            }

            table, thead, tbody, th, td, tr {
                display: block;
                width: 100%;
            }

            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }

            tr {
                border: 1px solid #ddd;
                margin-bottom: 15px;
                border-radius: 8px;
                padding: 10px;
                background: white;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: right;
                margin-bottom: 10px;
            }

            td::before {
                content: attr(data-label) ": ";
                position: absolute;
                left: 10px;
                width: 45%;
                font-weight: bold;
                text-align: left;
                color: #2c3e50;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <a href="supervisor_dashboard.php
" class="back-link">← Back</a>
        
        <div class="welcome-section">
            <h2>Upload Documents</h2>
            <p>Welcome, <?= htmlspecialchars($employer['username']) ?>! Upload and manage your OJT documents here.</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="success-msg"><?= $success_message ?></div>
        <?php endif; ?>

        <?php if (!empty($uploadError)): ?>
            <div class="error-msg"><?= htmlspecialchars($uploadError) ?></div>
        <?php endif; ?>

        <div class="upload-form">
            <h4>Upload New Document</h4>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <div class="mb-3">
                    <label for="uploaded_file" class="form-label">Select Document</label>
                    <input type="file" name="uploaded_file" id="uploaded_file" required class="form-control">
                    <div class="form-text">
                        Allowed file types: PDF, DOCX, JPG, PNG, JPEG, TXT, XLSX, PPTX<br>
                        Maximum file size: 10MB
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description (Optional)</label>
                    <input type="text" name="description" id="description" class="form-control" placeholder="Brief description of the document">
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <button type="submit" name="upload_file" class="btn btn-primary">
                        📁 Upload Document
                    </button>
                    <a href="supervisor_dashboard.php
" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <h3>My Uploaded Documents</h3>
        
        <?php if (empty($uploaded_files)): ?>
            <div class="alert alert-info text-center">
                <p>No documents uploaded yet. Upload your first document using the form above.</p>
            </div>
        <?php else: ?>
            <div class="table-section">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Description</th>
                            <th>File Type</th>
                            <th>Upload Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uploaded_files as $file): 
                            $ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
                            $icon = '📄'; // default
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $icon = '🖼️';
                            elseif ($ext === 'pdf') $icon = '📕';
                            elseif (in_array($ext, ['docx', 'doc'])) $icon = '📝';
                            elseif (in_array($ext, ['xlsx', 'xls'])) $icon = '📊';
                            elseif ($ext === 'pptx') $icon = '📊';
                        ?>
                        <tr>
                            <td data-label="Filename">
                                <span class="file-icon"><?= $icon ?></span>
                                <?= htmlspecialchars($file['filename']) ?>
                            </td>
                            <td data-label="Description" class="description-cell">
                                <?= !empty($file['description']) ? htmlspecialchars($file['description']) : '<em>No description</em>' ?>
                            </td>
                            <td data-label="File Type"><?= strtoupper($ext) ?></td>
                            <td data-label="Upload Date"><?= date('Y-m-d H:i', strtotime($file['uploaded_at'])) ?></td>
                            <td data-label="Actions">
                                <a href="download.php?file_id=<?= $file['id'] ?>" class="btn btn-sm btn-primary">Download</a>
                                <a href="delete_document.php?file_id=<?= $file['id'] ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this document?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-center">
                <p>Total documents: <strong><?= count($uploaded_files) ?></strong></p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File input preview
        document.getElementById('uploaded_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                console.log('Selected file:', fileName);
            }
        });
    </script>
</body>
</html>