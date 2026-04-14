<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/portal-ui.css">
<style>
    :root {
        --bg:         #f1f4f9;
        --surface:    #ffffff;
        --surface2:   #f8fafc;
        --border:     #e3e8f0;
        --text:       #111827;
        --text-muted: #6b7280;
        --accent:     #4361ee;
        --accent-dk:  #3451d1;
        --accent-lt:  #eef1fd;
        --green:      #16a34a;
        --green-lt:   #dcfce7;
        --red:        #dc2626;
        --red-lt:     #fee2e2;
        --radius:     14px;
        --shadow-sm:  0 1px 2px rgba(0,0,0,0.05);
        --shadow:     0 1px 4px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.06);
        --shadow-md:  0 2px 8px rgba(0,0,0,0.07), 0 8px 28px rgba(0,0,0,0.07);
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
        font-family: 'DM Sans', 'Segoe UI', sans-serif;
        background: var(--bg);
        color: var(--text);
        line-height: 1.6;
        min-height: 100vh;
        padding: 28px 20px 60px;
        margin: 0;
    }

    .dashboard-container {
        background: var(--surface);
        border-radius: 20px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-md);
        width: 100%;
        max-width: 1440px;
        margin: 0 auto;
        padding: 0;
        overflow: hidden;
    }

    .topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 32px;
        border-bottom: 1px solid var(--border);
        background: var(--surface);
        gap: 16px;
        flex-wrap: wrap;
    }

    .topbar-left h2 {
        font-size: 20px;
        font-weight: 700;
        color: var(--text);
        margin: 0;
        letter-spacing: -0.3px;
    }

    .topbar-left p {
        font-size: 13px;
        color: var(--text-muted);
        margin: 2px 0 0;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .badge-role {
        background: var(--accent-lt);
        color: var(--accent);
        font-size: 12px;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 20px;
    }

    .logout-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--red);
        color: #fff;
        padding: 8px 16px;
        border-radius: 9px;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: background 0.2s, transform 0.15s;
        white-space: nowrap;
    }

    .logout-btn:hover { background: #b91c1c; color: #fff; transform: translateY(-1px); }

    .dashboard-inner { padding: 28px 32px 36px; }

    .dashboard-inner h3 {
        font-size: 15px;
        font-weight: 700;
        color: var(--text);
        letter-spacing: -0.2px;
        margin: 28px 0 14px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border);
        text-align: left;
    }

    .dashboard-inner h3:first-child { margin-top: 0; }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 28px;
    }

    .summary-card {
        background: var(--surface2);
        border: 1px solid var(--border);
        padding: 20px 22px;
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        transition: box-shadow 0.2s, transform 0.2s;
        text-align: left;
    }

    .summary-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }

    .summary-card .label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        color: var(--text-muted);
        margin-bottom: 6px;
    }

    .summary-card .value {
        font-size: 34px;
        font-weight: 700;
        color: var(--text);
        line-height: 1;
    }

    .maintenance-card {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--surface2);
        overflow: hidden;
        margin-bottom: 24px;
    }

    .maintenance-card summary {
        list-style: none;
        cursor: pointer;
        padding: 13px 18px;
        font-weight: 600;
        font-size: 14px;
        color: var(--text);
        display: flex;
        align-items: center;
        justify-content: space-between;
        user-select: none;
    }

    .maintenance-card summary::-webkit-details-marker { display: none; }
    .maintenance-card summary::after { content: '▾'; font-size: 13px; color: var(--text-muted); transition: transform 0.2s; }
    .maintenance-card[open] summary::after { transform: rotate(180deg); }

    .maintenance-body { padding: 16px 18px 20px; border-top: 1px solid var(--border); }

    .card { border: 1px solid var(--border) !important; border-radius: var(--radius) !important; box-shadow: none !important; }
    .card-body { padding: 20px 22px !important; }

    .form-label { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 5px; }

    .form-control, .form-select {
        border-radius: 9px !important;
        border: 1px solid var(--border) !important;
        padding: 9px 12px !important;
        font-size: 14px !important;
        font-family: inherit !important;
        color: var(--text) !important;
        background: var(--surface) !important;
        transition: border-color 0.2s, box-shadow 0.2s !important;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--accent) !important;
        box-shadow: 0 0 0 3px rgba(67,97,238,0.12) !important;
        outline: none !important;
    }

    .form-text { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

    .btn {
        font-family: inherit !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        border-radius: 9px !important;
        padding: 9px 18px !important;
        transition: all 0.18s !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        border: none !important;
    }

    .btn-primary  { background: var(--accent) !important; color: #fff !important; }
    .btn-primary:hover  { background: var(--accent-dk) !important; transform: translateY(-1px) !important; box-shadow: 0 4px 12px rgba(67,97,238,0.28) !important; }

    .btn-success  { background: var(--green) !important; color: #fff !important; }
    .btn-success:hover  { background: #15803d !important; transform: translateY(-1px) !important; }

    .btn-outline-success { background: transparent !important; color: var(--green) !important; border: 1.5px solid var(--green) !important; }
    .btn-outline-success:hover { background: var(--green-lt) !important; transform: translateY(-1px) !important; }

    .btn-outline-danger  { background: transparent !important; color: var(--red) !important; border: 1.5px solid var(--red) !important; }
    .btn-outline-danger:hover  { background: var(--red-lt) !important; transform: translateY(-1px) !important; }

    .btn-secondary { background: var(--surface2) !important; color: var(--text) !important; border: 1.5px solid var(--border) !important; }
    .btn-secondary:hover { background: var(--border) !important; }

    .table { font-size: 14px; border-collapse: separate !important; border-spacing: 0 !important; }

    .table thead th {
        background: var(--surface2) !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.6px !important;
        color: var(--text-muted) !important;
        border-bottom: 1px solid var(--border) !important;
        padding: 11px 14px !important;
        white-space: nowrap;
    }

    .table tbody td {
        padding: 12px 14px !important;
        border-bottom: 1px solid var(--border) !important;
        vertical-align: middle !important;
        color: var(--text) !important;
    }

    .table tbody tr:last-child td { border-bottom: none !important; }
    .table tbody tr:hover td { background: var(--accent-lt) !important; }

    .table-striped tbody tr:nth-child(odd) td { background: var(--surface2) !important; }
    .table-striped tbody tr:nth-child(odd):hover td { background: var(--accent-lt) !important; }

    .success-msg { background: var(--green-lt); color: #15803d; padding: 12px 16px; border-radius: 10px; border: 1px solid #bbf7d0; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
    .error-msg   { background: var(--red-lt);   color: #b91c1c; padding: 12px 16px; border-radius: 10px; border: 1px solid #fecaca; font-size: 14px; font-weight: 500; margin-bottom: 16px; }

    .alert { border-radius: 10px !important; font-size: 14px !important; }

    .modal-content  { border-radius: 16px !important; border: 1px solid var(--border) !important; }
    .modal-header   { border-bottom: 1px solid var(--border) !important; padding: 16px 20px !important; }
    .modal-footer   { border-top: 1px solid var(--border) !important; padding: 14px 20px !important; }

    @media (max-width: 768px) {
        body { padding: 12px 12px 40px; }
        .dashboard-container { border-radius: 14px; }
        .topbar, .dashboard-inner { padding: 16px 18px; }
        .summary-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .topbar-left h2 { font-size: 17px; }
        .table thead { display: none; }
        .table tbody tr { display: block; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 10px; padding: 10px; }
        .table tbody td { display: flex; justify-content: space-between; border: none !important; padding: 6px 8px !important; }
        .table tbody td::before { content: attr(data-label); font-weight: 600; color: var(--text-muted); font-size: 12px; text-transform: uppercase; }
    }
</style>
</head>
<?php $render_admin_overview = $render_admin_overview ?? true; ?>
<body class="portal-dashboard portal-admin">
<div class="dashboard-container">
    <div class="topbar">
        <div class="topbar-left">
            <h2>Welcome, <?= htmlspecialchars($admin['full_name'] ?? $admin['username']) ?>!</h2>
            <p>Admin Dashboard</p>
        </div>
        <div class="topbar-right">
            <span class="badge-role">Administrator</span>
            <a href="logout.php" class="logout-btn">⎋ Logout</a>
        </div>
    </div>

    <?php if ($render_admin_overview): ?>
    <div class="dashboard-inner">

    <div class="portal-hero portal-hero--dashboard">
        <div class="portal-hero-copy">
            <span class="portal-kicker">Administrative overview</span>
            <h3 class="portal-hero-title">Manage internship operations with a clearer system overview.</h3>
            <p class="portal-hero-text">
                Add supervisors, review attendance activity, and keep maintenance tasks visible from a dashboard that feels more like a control center than a plain form page.
            </p>
            <div class="portal-hero-meta">
                <span class="portal-chip"><?= htmlspecialchars($admin_overview_date) ?></span>
                <span class="portal-chip"><?= $supervisors_count ?> supervisor<?= $supervisors_count === 1 ? '' : 's' ?> configured</span>
                <span class="portal-chip"><?= $students_count ?> student<?= (int)$students_count === 1 ? '' : 's' ?> in the system</span>
            </div>
        </div>
        <div class="portal-highlight-card">
            <span class="portal-card-label">Platform footprint</span>
            <span class="portal-card-value"><?= $total_accounts_count ?></span>
            <span class="portal-card-note">combined administrator, supervisor, and student accounts currently represented in the portal.</span>
            <div class="portal-mini-actions">
                <a class="portal-link-pill" href="add_employer.php">Add supervisor</a>
                <a class="portal-link-pill" href="admin_attendance_records.php">Open attendance records</a>
            </div>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="label">Students</div>
            <div class="value"><?= $students_count ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Supervisors</div>
            <div class="value"><?= $supervisors_count ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Evaluations</div>
            <div class="value"><?= $evaluations_count ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Accounts</div>
            <div class="value"><?= $total_accounts_count ?></div>
        </div>
    </div>
    <?php endif; ?>
