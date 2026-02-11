<?php
include 'private/config.php';

$stmt = $pdo->prepare('SELECT ps.submission_id, ps.student_id, ps.status, s.first_name, s.last_name, s.email FROM project_submissions ps JOIN students s ON ps.student_id = s.student_id ORDER BY ps.submission_id DESC LIMIT 10');
$stmt->execute();
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo 'Recent project submissions:' . PHP_EOL;
foreach ($submissions as $sub) {
    echo 'ID: ' . $sub['submission_id'] . ', Student: ' . $sub['first_name'] . ' ' . $sub['last_name'] . ', Email: ' . $sub['email'] . ', Status: ' . $sub['status'] . PHP_EOL;
}
?>
