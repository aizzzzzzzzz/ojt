-- Migration script to add companies table and link supervisors/students with company_id
-- Run this script after backing up your database

-- Step 1: Create companies table
CREATE TABLE IF NOT EXISTS companies (
    company_id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Step 2: Insert distinct companies from employers table
INSERT INTO companies (company_name)
SELECT DISTINCT company
FROM employers
WHERE company IS NOT NULL AND company != ''
ORDER BY company;

-- Step 3: Add company_id column to employers table
ALTER TABLE employers ADD COLUMN company_id INT NULL;

-- Step 4: Update employers table to set company_id based on company name
UPDATE employers e
JOIN companies c ON e.company = c.company_name
SET e.company_id = c.company_id;

-- Step 5: Add company_id column to students table
ALTER TABLE students ADD COLUMN company_id INT NULL;

-- Step 6: Update students table to set company_id based on their supervisor's company
UPDATE students s
JOIN employers e ON s.created_by = e.employer_id
SET s.company_id = e.company_id
WHERE s.created_by IS NOT NULL;

-- Step 7: Add foreign key constraints (optional, but recommended)
-- Drop existing constraints if they exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'employers' AND CONSTRAINT_NAME = 'fk_employers_company_id') > 0,
    'ALTER TABLE employers DROP FOREIGN KEY fk_employers_company_id;',
    'SELECT "Constraint does not exist";'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND CONSTRAINT_NAME = 'fk_students_company_id') > 0,
    'ALTER TABLE students DROP FOREIGN KEY fk_students_company_id;',
    'SELECT "Constraint does not exist";'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Now add the constraints
ALTER TABLE employers ADD CONSTRAINT fk_employers_company_id
FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE SET NULL;

ALTER TABLE students ADD CONSTRAINT fk_students_company_id
FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE SET NULL;

-- Step 8: Create indexes for better performance
CREATE INDEX idx_employers_company_id ON employers(company_id);
CREATE INDEX idx_students_company_id ON students(company_id);

-- Optional: Drop the old company column from employers if migration is successful
-- ALTER TABLE employers DROP COLUMN company;

-- Verification queries (run these after migration to verify)
-- SELECT 'Companies created:', COUNT(*) FROM companies;
-- SELECT 'Employers with company_id:', COUNT(*) FROM employers WHERE company_id IS NOT NULL;
-- SELECT 'Students with company_id:', COUNT(*) FROM students WHERE company_id IS NOT NULL;
