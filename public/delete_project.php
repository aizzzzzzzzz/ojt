<?php
session_start();

if (!isset($_SESSION['employer_id'])) {
    header("Location: employer_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../includes/audit.php';

if (isset($_GET['id'])) {
    $project_id = (int) $_GET['id'];

    try {
        // Get project name for logging
        $stmt = $pdo->prepare("SELECT project_name FROM projects WHERE project_id = ?");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("DELETE FROM project_submissions WHERE project_id = ?");
        $stmt->execute([$project_id]);

        $stmt = $pdo->prepare("DELETE FROM projects WHERE project_id = ?");
        $stmt->execute([$project_id]);

        // Log supervisor delete action
        audit_log($pdo, 'Delete Project', "Deleted project: " . ($project['project_name'] ?? "ID: $project_id"));

        $_SESSION['success'] = "Project deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting project: " . $e->getMessage();
    }
}

header("Location: manage_projects.php");
exit;
?>
