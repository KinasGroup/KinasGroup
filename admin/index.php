<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
SessionManager::requireAdmin();
$headerDepth = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
.admin-layout { display: flex; min-height: 100vh; padding-top: 66px; }
        .admin-sidebar { width: 260px; background: #1a1a1a; color: #fff; flex-shrink: 0; padding: 0; }
        .admin-sidebar-header { padding: 24px 20px; border-bottom: 1px solid #333; }
        .admin-sidebar-header h3 { font-family: 'Inter', sans-serif; font-size: 16px; font-weight: 600; margin: 0; }
        .admin-sidebar-header p { font-size: 12px; color: #999; margin: 4px 0 0; }
        .admin-nav a { display: flex; align-items: center; padding: 13px 20px; color: #ccc; font-size: 14px; border-left: 3px solid transparent; transition: all 0.2s; }
        .admin-nav a:hover { background: #333; color: #fff; border-left-color: var(--accent); }
        .admin-nav a.active { background: #333; color: #fff; border-left-color: var(--accent); font-weight: 600; }
        .admin-nav a .badge { margin-left: auto; background: #e74c3c; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .admin-main { flex: 1; padding: 30px; background: #f5f7fa; overflow-y: auto; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .admin-header h2 { font-family: 'Inter', sans-serif; font-size: 22px; font-weight: 600; margin: 0; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .stat-card h3 { font-family: 'Inter', sans-serif; font-size: 32px; font-weight: 700; margin: 0 0 4px; }
        .stat-card p { color: var(--tertiary); font-size: 13px; margin: 0; }
        .admin-table-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); overflow: hidden; }
        .admin-table-card h3 { padding: 20px; font-family: 'Inter', sans-serif; font-size: 16px; font-weight: 600; border-bottom: 1px solid var(--border); }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th { background: #f9fafb; padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #666; border-bottom: 2px solid var(--border); }
        .admin-table td { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
        .admin-table tr:hover td { background: #f9fafb; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .status-pending { background: #fff3e0; color: #e65100; }
        .status-approved { background: #e8f5e9; color: #2e7d32; }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 4px; border: 1px solid var(--border); background: #fff; cursor: pointer; font-family: 'Inter', sans-serif; }
        .btn-sm:hover { border-color: var(--primary); }
        .btn-sm.primary { background: var(--accent); color: #fff; border-color: var(--accent); }
        .btn-sm.danger { color: #e74c3c; border-color: #e74c3c; }
        @media (max-width: 768px) { .admin-layout { flex-direction: column; } .admin-sidebar { width: 100%; } .stats-grid { grid-template-columns: repeat(2, 1fr); } .admin-main { padding: 20px; } }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
    </style>

</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>
<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>
<main class="je-dash-main">
<div class="admin-header"><h2>Dashboard Overview</h2></div>
        <div class="stats-grid">
            <div class="stat-card"><p>Pending Approvals</p><h3>24</h3></div>
            <div class="stat-card"><p>Total Agents</p><h3>1,482</h3></div>
            <div class="stat-card"><p>Active Listings</p><h3>5,670</h3></div>
            <div class="stat-card"><p style="color:#e74c3c;">Flagged Items</p><h3 style="color:#e74c3c;">8</h3></div>
        </div>

        <div class="admin-table-card">
            <h3>Pending Agent Approvals</h3>
            <table class="admin-table">
                <thead><tr><th>Agent Name</th><th>Division</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <tr><td>John Smith</td><td>Automobile</td><td>2024-01-15</td><td><span class="status-badge status-pending">Pending</span></td><td><a href="agent-approvals.html" class="btn-sm primary">Review</a></td></tr>
                    <tr><td>Sarah Johnson</td><td>Real Estate</td><td>2024-01-14</td><td><span class="status-badge status-pending">Pending</span></td><td><a href="agent-approvals.html" class="btn-sm primary">Review</a></td></tr>
                </tbody>
            </table>
        </div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
