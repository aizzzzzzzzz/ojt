<?php
require 'private/config.php';
$stmt = $pdo->prepare('SELECT created_by FROM projects WHERE project_id = 2');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo 'Created by: ' . $row['created_by'] . PHP_EOL;
} else {
    echo 'Not found' . PHP_EOL;
}
?>
