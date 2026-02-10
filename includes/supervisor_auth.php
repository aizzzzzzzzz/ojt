<?php
// Authentication module for supervisor dashboard
function authenticate_supervisor() {
    if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== "employer") {
        header("Location: employer_login.php");
        exit;
    }
    return $_SESSION['employer_id'];
}

function get_supervisor_info($pdo, $employer_id) {
    $stmt = $pdo->prepare("SELECT * FROM employers WHERE employer_id = ?");
    $stmt->execute([$employer_id]);
    $employer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employer) {
        session_destroy();
        header("Location: employer_login.php");
        exit;
    }
    return $employer;
}
?>
