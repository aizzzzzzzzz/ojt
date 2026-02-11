<?php
session_start();

if (!isset($_SESSION['employer_id'])) {
    header("Location: employer_login.php");
    exit;
}

include __DIR__ . '/../private/config.php';

if (isset($_GET['id'])) {
    $project_id = (int) $_GET['id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM project_submissions WHERE project_id = ?");
        $stmt->execute([$project_id]);

        $stmt = $pdo->prepare("DELETE FROM projects WHERE project_id = ?");
        $stmt->execute([$project_id]);

        $_SESSION['success'] = "Project deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting project: " . $e->getMessage();
    }
}

header("Location: manage_projects.php");
exit;
?>
