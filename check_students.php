<?php
include 'private/config.php';

$stmt = $pdo->prepare('SELECT first_name, last_name, email FROM students WHERE email IS NOT NULL AND email != "" LIMIT 5');
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo 'Students with emails:' . PHP_EOL;
foreach ($students as $student) {
    echo $student['first_name'] . ' ' . $student['last_name'] . ': ' . $student['email'] . PHP_EOL;
}
?>
