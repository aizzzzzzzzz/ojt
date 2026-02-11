<?php
require 'private/config.php';

try {
    echo "=== EMPLOYERS TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE employers');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . ' - ' . $col['Null'] . ' - ' . $col['Key'] . ' - ' . $col['Default'] . ' - ' . $col['Extra'] . "\n";
    }

    echo "\n=== SAMPLE EMPLOYERS DATA ===\n";
    $stmt = $pdo->query('SELECT employer_id, name, company, company_id FROM employers LIMIT 5');
    $employers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($employers as $emp) {
        echo 'ID: ' . $emp['employer_id'] . ', Name: ' . ($emp['name'] ?? 'NULL') . ', Company: ' . ($emp['company'] ?? 'NULL') . ', Company_ID: ' . ($emp['company_id'] ?? 'NULL') . "\n";
    }

    echo "\n=== COMPANIES TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE companies');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . ' - ' . $col['Null'] . ' - ' . $col['Key'] . ' - ' . $col['Default'] . ' - ' . $col['Extra'] . "\n";
    }

    echo "\n=== COMPANIES DATA ===\n";
    $stmt = $pdo->query('SELECT * FROM companies');
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($companies as $comp) {
        echo 'ID: ' . $comp['company_id'] . ', Name: ' . $comp['company_name'] . "\n";
    }

    echo "\n=== STUDENTS TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE students');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . ' - ' . $col['Null'] . ' - ' . $col['Key'] . ' - ' . $col['Default'] . ' - ' . $col['Extra'] . "\n";
    }

    echo "\n=== SAMPLE STUDENTS DATA ===\n";
    $stmt = $pdo->query('SELECT student_id, first_name, last_name, created_by, company_id FROM students LIMIT 5');
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($students as $stu) {
        echo 'ID: ' . $stu['student_id'] . ', Name: ' . ($stu['first_name'] ?? '') . ' ' . ($stu['last_name'] ?? '') . ', Created_by: ' . ($stu['created_by'] ?? 'NULL') . ', Company_ID: ' . ($stu['company_id'] ?? 'NULL') . "\n";
    }

} catch (Exception $e) {
    echo 'Exception: ' . $e->getMessage() . "\n";
}
?>
