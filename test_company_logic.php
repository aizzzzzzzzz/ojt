<?php
require 'private/config.php';

echo "Testing company and student logic...\n";

// Step 1: Insert a test company
$companyStmt = $pdo->prepare("INSERT INTO companies (company_name) VALUES (?)");
$companyStmt->execute(['Test Company']);
$company_id = $pdo->lastInsertId();
echo "Inserted company with ID: $company_id\n";

// Step 2: Insert a test employer linked to the company
$employerStmt = $pdo->prepare("INSERT INTO employers (name, company_id, username, password) VALUES (?, ?, ?, ?)");
$employerStmt->execute(['Test Supervisor', $company_id, 'testsupervisor', password_hash('password', PASSWORD_DEFAULT)]);
$employer_id = $pdo->lastInsertId();
echo "Inserted employer with ID: $employer_id\n";

// Step 3: Insert another employer from the same company
$employerStmt2 = $pdo->prepare("INSERT INTO employers (name, company_id, username, password) VALUES (?, ?, ?, ?)");
$employerStmt2->execute(['Test Supervisor 2', $company_id, 'testsupervisor2', password_hash('password', PASSWORD_DEFAULT)]);
$employer_id2 = $pdo->lastInsertId();
echo "Inserted second employer with ID: $employer_id2\n";

// Step 4: Simulate adding a student by first employer
$studentStmt = $pdo->prepare("INSERT INTO students (username, password, first_name, last_name, email, required_hours, course, school, created_by, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$studentStmt->execute(['teststudent', password_hash('password', PASSWORD_DEFAULT), 'Test', 'Student', 'test@example.com', 200, 'BSIT', 'Test School', $employer_id, $company_id]);
$student_id = $pdo->lastInsertId();
echo "Inserted student with ID: $student_id, created_by: $employer_id, company_id: $company_id\n";

// Step 5: Test get_students_list for first employer
require 'includes/supervisor_db.php';
$students = get_students_list($pdo, $employer_id);
echo "Students visible to first employer:\n";
foreach ($students as $student) {
    echo "- {$student['username']}\n";
}

// Step 6: Test get_students_list for second employer (should see the same student)
$students2 = get_students_list($pdo, $employer_id2);
echo "Students visible to second employer:\n";
foreach ($students2 as $student) {
    echo "- {$student['username']}\n";
}

// Step 7: Add a student by second employer
$studentStmt2 = $pdo->prepare("INSERT INTO students (username, password, first_name, last_name, email, required_hours, course, school, created_by, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$studentStmt2->execute(['teststudent2', password_hash('password', PASSWORD_DEFAULT), 'Test2', 'Student2', 'test2@example.com', 200, 'BSIT', 'Test School', $employer_id2, $company_id]);
$student_id2 = $pdo->lastInsertId();
echo "Inserted second student with ID: $student_id2, created_by: $employer_id2\n";

// Step 8: Check again for first employer (should see both students)
$students_updated = get_students_list($pdo, $employer_id);
echo "Updated students visible to first employer:\n";
foreach ($students_updated as $student) {
    echo "- {$student['username']}\n";
}

// Cleanup: Delete test data
$pdo->prepare("DELETE FROM students WHERE username IN ('teststudent', 'teststudent2')")->execute();
$pdo->prepare("DELETE FROM employers WHERE username IN ('testsupervisor', 'testsupervisor2')")->execute();
$pdo->prepare("DELETE FROM companies WHERE company_name = 'Test Company'")->execute();

echo "Test completed and cleaned up.\n";
?>
