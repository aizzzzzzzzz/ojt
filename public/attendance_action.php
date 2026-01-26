<?php
session_start();
include_once __DIR__ . '/../private/config.php';

date_default_timezone_set('Asia/Manila');

$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

$student_id = (int)($_GET['student_id'] ?? 0);
$employer_id = (int)($_GET['employer_id'] ?? 0);
$action = $_GET['action'] ?? '';
$token = $_GET['token'] ?? '';

if (!$student_id || !$employer_id || !$action || !$token) {
    exit("Invalid request.");
}

$stmt = $pdo->prepare("SELECT * FROM qr_tokens 
    WHERE student_id = ? AND employer_id = ? AND token = ? AND qr_date = ?");
$stmt->execute([$student_id, $employer_id, $token, $today]);
$qr = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$qr) {
    exit("Invalid or expired QR code.");
}

if ($qr['is_used']) {
    exit("This QR code has already been used.");
}

$stmt = $pdo->prepare("UPDATE qr_tokens SET is_used = 1, used_at = ? WHERE id = ?");
$stmt->execute([$now, $qr['id']]);

$stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND log_date = ? AND employer_id = ?");
$stmt->execute([$student_id, $today, $employer_id]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attendance) {
    $stmt = $pdo->prepare("INSERT INTO attendance (student_id, employer_id, log_date, status) VALUES (?, ?, ?, 'Present')");
    $stmt->execute([$student_id, $employer_id, $today]);
}

if ($action === 'time_in') {
    $stmt = $pdo->prepare("UPDATE attendance SET time_in = ?, status = 'Present'
        WHERE student_id = ? AND log_date = ? AND employer_id = ?");
    $stmt->execute([$now, $student_id, $today, $employer_id]);
    echo "✅ Time In recorded successfully.";
} elseif ($action === 'lunch_out') {
    $stmt = $pdo->prepare("UPDATE attendance SET lunch_out = ?
        WHERE student_id = ? AND log_date = ? AND employer_id = ?");
    $stmt->execute([$now, $student_id, $today, $employer_id]);
    echo "✅ Lunch Out recorded successfully.";
} elseif ($action === 'lunch_in') {
    $stmt = $pdo->prepare("UPDATE attendance SET lunch_in = ?
        WHERE student_id = ? AND log_date = ? AND employer_id = ?");
    $stmt->execute([$now, $student_id, $today, $employer_id]);
    echo "✅ Lunch In recorded successfully.";
} elseif ($action === 'time_out') {
    $stmt = $pdo->prepare("UPDATE attendance SET time_out = ?
        WHERE student_id = ? AND log_date = ? AND employer_id = ?");
    $stmt->execute([$now, $student_id, $today, $employer_id]);
    echo "✅ Time Out recorded successfully.";
} else {
    exit("Invalid action.");
}
?>
