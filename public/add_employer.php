<?php
session_start();
require "../private/config.php";

// Debug: Start output buffering to see all messages
ob_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("=== DEBUG START: add_employer.php POST ===");
    error_log("POST data: " . print_r($_POST, true));
    
    $name = trim($_POST["name"]);
    $company_input = trim($_POST["company"]);
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (!empty($name) && !empty($company_input) && !empty($username) && !empty($password)) {
        // Check for duplicate username (case-insensitive)
        $stmt = $pdo->prepare("SELECT 1 FROM employers WHERE LOWER(username) = LOWER(?)");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Username already exists.";
            header("Location: add_employer.php");
            exit;
        } else {
            error_log("DEBUG: Checking company: '{$company_input}'");
            
            // Check if company exists first (case-insensitive)
            $companyStmt = $pdo->prepare("SELECT company_id FROM companies WHERE LOWER(company_name) = LOWER(?)");
            $companyStmt->execute([$company_input]);
            $existingCompany = $companyStmt->fetch();
            
            error_log("DEBUG: Company check result: " . print_r($existingCompany, true));

            if ($existingCompany) {
                $company_id = $existingCompany['company_id'];
                error_log("DEBUG: Company exists with ID: {$company_id}");
            } else {
                error_log("DEBUG: Creating new company: '{$company_input}'");
                
                // Create new company
                try {
                    $insertCompanyStmt = $pdo->prepare("INSERT INTO companies (company_name) VALUES (?)");
                    
                    if ($insertCompanyStmt->execute([$company_input])) {
                        $company_id = $pdo->lastInsertId();
                        error_log("DEBUG: Company created successfully! ID: {$company_id}");
                        
                        // Verify the company was actually inserted
                        $verifyStmt = $pdo->prepare("SELECT * FROM companies WHERE company_id = ?");
                        $verifyStmt->execute([$company_id]);
                        $verified = $verifyStmt->fetch();
                        error_log("DEBUG: Verification: " . print_r($verified, true));
                    } else {
                        $errorInfo = $insertCompanyStmt->errorInfo();
                        error_log("DEBUG: Failed to insert company. Error: " . print_r($errorInfo, true));
                        $_SESSION['error'] = "Failed to create company. Database error.";
                        header("Location: add_employer.php");
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log("DEBUG: PDO Exception: " . $e->getMessage());
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                    header("Location: add_employer.php");
                    exit;
                }
            }

            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            error_log("DEBUG: Hashed password created");
            
            // Insert employer
            try {
                $stmt = $pdo->prepare("INSERT INTO employers (name, company_id, username, password) VALUES (?, ?, ?, ?)");
                error_log("DEBUG: Inserting employer with values: name={$name}, company_id={$company_id}, username={$username}");
                
                if ($stmt->execute([$name, $company_id, $username, $hashed_password])) {
                    $employer_id = $pdo->lastInsertId();
                    error_log("DEBUG: Employer created successfully! ID: {$employer_id}");
                    
                    // Verify employer was created
                    $verifyEmpStmt = $pdo->prepare("SELECT * FROM employers WHERE employer_id = ?");
                    $verifyEmpStmt->execute([$employer_id]);
                    $empData = $verifyEmpStmt->fetch();
                    error_log("DEBUG: Employer verification: " . print_r($empData, true));
                    
                    $_SESSION['success'] = "Employer account created successfully! Company ID: {$company_id}, Employer ID: {$employer_id}";
                } else {
                    $errorInfo = $stmt->errorInfo();
                    error_log("DEBUG: Failed to insert employer. Error: " . print_r($errorInfo, true));
                    $_SESSION['error'] = "Failed to add employer. Database error.";
                }
            } catch (PDOException $e) {
                error_log("DEBUG: PDO Exception inserting employer: " . $e->getMessage());
                $_SESSION['error'] = "Database error: " . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error'] = "All fields are required!";
    }
    
    error_log("=== DEBUG END: add_employer.php POST ===");
    header("Location: add_employer.php");
    exit;
}

// Get all companies for debugging display
$companies = [];
try {
    $companiesStmt = $pdo->query("SELECT * FROM companies ORDER BY company_name");
    $companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DEBUG: Error fetching companies: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Employer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .success-msg {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
        }
        .error-msg {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
        }
        .debug-info {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add Employer/Supervisor</h2>
        
        <!-- Debug Info -->
        <div class="debug-info">
            <h5>Current Companies in Database:</h5>
            <?php if (empty($companies)): ?>
                <p class="text-danger">No companies found in database.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($companies as $company): ?>
                        <li>ID: <?= $company['company_id'] ?> - <?= htmlspecialchars($company['company_name']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if (!empty($_SESSION['error'])): ?>
            <div class='error-msg'><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (!empty($_SESSION['success'])): ?>
            <div class='success-msg'><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">Supervisor Name:</label>
                <input type="text" class="form-control" name="name" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Company:</label>
                <input type="text" class="form-control" name="company" required>
                <div class="form-text">If the company doesn't exist, it will be created automatically.</div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Username:</label>
                <input type="text" class="form-control" name="username" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Password:</label>
                <input type="password" class="form-control" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary">Add Employer</button>
        </form>

        <hr>
        <a href="index.php" class="btn btn-secondary">Back to Home</a>
        
        <!-- Test Links -->
        <div class="mt-4">
            <h5>Test Links:</h5>
            <a href="test_companies.php" class="btn btn-sm btn-info">Test Companies System</a>
            <a href="view_companies.php" class="btn btn-sm btn-info">View All Companies</a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>