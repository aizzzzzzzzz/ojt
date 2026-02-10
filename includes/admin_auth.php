<?php
// Authentication module for admin dashboard
function authenticate_admin() {
    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== "admin") {
        header("Location: admin_login.php");
        exit;
    }
    return $_SESSION['admin_id'];
}

function get_admin_info($pdo, $admin_id) {
    $stmt = $pdo->prepare("SELECT username, full_name FROM admins WHERE admin_id=?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        session_destroy();
        header("Location: admin_login.php");
        exit;
    }
    return $admin;
}
?>
