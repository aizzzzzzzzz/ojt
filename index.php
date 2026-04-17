<?php
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    if (!in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])) {
        $https_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: ' . $https_url, true, 301);
        exit;
    }
}

session_start();

if (!empty($_SESSION['admin_id'])) {
    header('Location: public/admin_dashboard.php');
    exit;
}

if (!empty($_SESSION['employer_id'])) {
    header('Location: public/supervisor_dashboard.php');
    exit;
}

if (!empty($_SESSION['student_id'])) {
    header('Location: public/student_dashboard.php');
    exit;
}

require_once __DIR__ . '/private/config.php';


$stmt = $pdo->prepare("SELECT COUNT(*) FROM admins");
$stmt->execute();
$admin_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CRT OJT Management System</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/portal-ui.css">
</head>
<body class="portal-home">
  <div class="portal-home-shell">
    <header class="portal-site-header">
      <a class="portal-brand" href="index.php">
        <img src="assets/school_logo.png" alt="CRT logo">
        <span>
          <span class="portal-brand-title">College for Research and Technology</span>
          <span class="portal-brand-subtitle">OJT Monitoring Evaluation System</span>
        </span>
      </a>
    </header>

    <section class="portal-home-hero">
      <div class="portal-story-card">
        <span class="portal-kicker">CRT OJT Portal</span>
        <p class="portal-subtitle">
          This system gives students, supervisors, and administrators a shared space for daily attendance,
          hour monitoring, evaluation, and certificate preparation with a cleaner workflow from start to finish.
        </p>
        <div class="portal-actions">
          <a class="portal-primary-btn" href="public/login.php">Open Login Portal</a>
          <a class="portal-secondary-btn" href="#portal-features">Explore Features</a>
        </div>
        <div class="portal-role-grid">
          <div class="portal-role-card">
            <strong>Students</strong>
            <span>Log attendance, check verified hours, review evaluations, and download issued certificates.</span>
          </div>
          <div class="portal-role-card">
            <strong>Supervisors</strong>
            <span>Verify daily attendance, manage student records, upload documents, and complete evaluations.</span>
          </div>
          <div class="portal-role-card">
            <strong>Administrators</strong>
            <span>Manage supervisors, review attendance activity, and maintain backup-ready system records.</span>
          </div>
        </div>
      </div>

      <div class="portal-image-card">
        <div class="portal-image-overlay">
          <strong>Built for CRT internship operations</strong>
          <span>A more welcoming front door for the system, aligned with the school identity and current dashboard workflow.</span>
        </div>
        <img src="assets/crtbldg.jpg" alt="College for Research and Technology building">
      </div>
    </section>

    <section class="portal-stat-strip">
      <div class="portal-stat-block">
        <strong>3</strong>
        <span>Dedicated portals for students, supervisors, and administrators.</span>
      </div>
      <div class="portal-stat-block">
        <strong>200h</strong>
        <span>Student hour target displayed clearly inside the dashboard experience.</span>
      </div>
      <div class="portal-stat-block">
        <strong>Daily</strong>
        <span>Attendance verification flow designed to keep monitoring timely and organized.</span>
      </div>
      <div class="portal-stat-block">
        <strong>Ready</strong>
        <span>Evaluation and certificate steps are surfaced once internship progress is complete.</span>
      </div>
    </section>

    <section class="portal-feature-grid" id="portal-features">
      <div class="portal-feature-card">
        <strong>Attendance Monitoring</strong>
        <span>Students can clock in, clock out, and submit daily tasks while supervisors verify records in one place.</span>
      </div>
      <div class="portal-feature-card">
        <strong>Progress Visibility</strong>
        <span>Dashboards show verified hours, remaining target time, current status, and role-based action queues.</span>
      </div>
      <div class="portal-feature-card">
        <strong>Evaluation Workflow</strong>
        <span>Supervisors can evaluate students when requirements are met, with certificate actions shown at the right time.</span>
      </div>
      <div class="portal-feature-card">
        <strong>Administrative Control</strong>
        <span>Admins can manage supervisors, view attendance records, and keep backups and maintenance tasks accessible.</span>
      </div>
    </section>

    <section class="portal-cta-card">
      <div class="portal-cta-copy">
        <strong>Access the portal and continue your workflow.</strong>
        <span>Use the login page to enter the system. The landing page now acts as a public-facing introduction instead of a plain sign-in screen.</span>
      </div>
    </section>

    <?php if ($admin_count == 0): ?>
      <p class="portal-setup-link">First-time setup is still available here: <a href="public/add_first_admin.php">Create the first administrator</a></p>
    <?php endif; ?>
  </div>
</body>
</html>