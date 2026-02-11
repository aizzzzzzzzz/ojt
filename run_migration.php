<?php
// Migration runner script
require 'private/config.php';

try {
    echo "Starting migration...\n";

    // Step 1: Create companies table
    echo "Creating companies table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS companies (
            company_id INT AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Step 2: Insert distinct companies from employers table
    echo "Migrating existing companies...\n";
    $pdo->exec("
        INSERT IGNORE INTO companies (company_name)
        SELECT DISTINCT company
        FROM employers
        WHERE company IS NOT NULL AND company != ''
        ORDER BY company
    ");

    // Step 3: Add company_id column to employers table (if not exists)
    echo "Checking/adding company_id to employers table...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM employers LIKE 'company_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE employers ADD COLUMN company_id INT NULL");
        echo "Added company_id column to employers table.\n";
    } else {
        echo "company_id column already exists in employers table.\n";
    }

    // Step 4: Update employers table to set company_id based on company name
    echo "Updating employers with company_id...\n";
    $pdo->exec("
        UPDATE employers e
        JOIN companies c ON e.company = c.company_name
        SET e.company_id = c.company_id
        WHERE e.company_id IS NULL
    ");

    // Step 5: Add company_id column to students table (if not exists)
    echo "Checking/adding company_id to students table...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'company_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN company_id INT NULL");
        echo "Added company_id column to students table.\n";
    } else {
        echo "company_id column already exists in students table.\n";
    }

    // Step 6: Update students table to set company_id based on their supervisor's company
    echo "Updating students with company_id...\n";
    $pdo->exec("
        UPDATE students s
        JOIN employers e ON s.created_by = e.employer_id
        SET s.company_id = e.company_id
        WHERE s.created_by IS NOT NULL AND s.company_id IS NULL
    ");

    // Step 7: Add foreign key constraints (if not exist)
    echo "Checking/adding foreign key constraints...\n";
    try {
        $pdo->exec("ALTER TABLE employers ADD CONSTRAINT fk_employers_company_id FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE SET NULL");
        echo "Added foreign key constraint to employers table.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            throw $e;
        }
        echo "Foreign key constraint already exists on employers table.\n";
    }

    try {
        $pdo->exec("ALTER TABLE students ADD CONSTRAINT fk_students_company_id FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE SET NULL");
        echo "Added foreign key constraint to students table.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            throw $e;
        }
        echo "Foreign key constraint already exists on students table.\n";
    }

    // Step 8: Create indexes (if not exist)
    echo "Checking/creating indexes...\n";
    try {
        $pdo->exec("CREATE INDEX idx_employers_company_id ON employers(company_id)");
        echo "Created index on employers.company_id.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            throw $e;
        }
        echo "Index already exists on employers.company_id.\n";
    }

    try {
        $pdo->exec("CREATE INDEX idx_students_company_id ON students(company_id)");
        echo "Created index on students.company_id.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            throw $e;
        }
        echo "Index already exists on students.company_id.\n";
    }

    // Verification queries
    echo "\nMigration completed successfully!\n";

    $companiesCount = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
    echo "Companies created: $companiesCount\n";

    $employersWithCompanyId = $pdo->query("SELECT COUNT(*) FROM employers WHERE company_id IS NOT NULL")->fetchColumn();
    echo "Employers with company_id: $employersWithCompanyId\n";

    $studentsWithCompanyId = $pdo->query("SELECT COUNT(*) FROM students WHERE company_id IS NOT NULL")->fetchColumn();
    echo "Students with company_id: $studentsWithCompanyId\n";

    echo "\nMigration script completed. You can now safely drop the 'company' column from employers table if desired.\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
