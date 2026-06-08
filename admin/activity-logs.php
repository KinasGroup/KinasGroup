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
    <title>Activity Logs - KINAS GROUP Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-main { flex: 1; padding: 30px; background: #F5F7FA; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; margin-bottom: 8px; }
        .page-header p { color: #666; font-size: 14px; }
        .filters-bar { background: white; border-radius: 16px; padding: 20px; margin-bottom: 25px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; border: 1px solid #E0E0E0; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #666; letter-spacing: 0.5px; }
        .filter-group select, .filter-group input { padding: 10px 12px; border: 1px solid #E0E0E0; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 13px; min-width: 150px; }
        .btn-filter { background: #C6A43F; color: #0A0A0A; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 12px; padding: 15px 20px; border: 1px solid #E0E0E0; }
        .stat-card .number { font-size: 24px; font-weight: 700; color: #C6A43F; font-family: 'Prata', serif; }
        .stat-card .label { font-size: 12px; color: #666; margin-top: 4px; }
        .logs-card { background: white; border-radius: 20px; border: 1px solid #E0E0E0; overflow: hidden; }
        .logs-header { padding: 20px 25px; background: #F8F8F8; border-bottom: 1px solid #E0E0E0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .logs-header h2 { font-family: 'Prata', serif; font-size: 18px; color: #0A0A0A; }
        .export-btn { background: #666; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 12px; cursor: pointer; }
        .table-responsive { overflow-x: auto; }
        .logs-table { width: 100%; border-collapse: collapse; }
        .logs-table th { text-align: left; padding: 15px 20px; background: #F8F8F8; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; letter-spacing: 0.5px; border-bottom: 1px solid #E0E0E0; }
        .logs-table td { padding: 14px 20px; border-bottom: 1px solid #E0E0E0; font-size: 13px; color: #333; }
        .logs-table tr:hover { background: #F8F8F8; }
        .log-type { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .log-type.user { background: #E3F2FD; color: #1565C0; }
        .log-type.listing { background: #E8F5E9; color: #2E7D32; }
        .log-type.agent { background: #FFF3E0; color: #F57C00; }
        .log-type.admin { background: #F3E5F5; color: #7B1FA2; }
        .pagination { display: flex; justify-content: center; gap: 8px; padding: 20px; border-top: 1px solid #E0E0E0; }
        .pagination a, .pagination span { padding: 8px 14px; background: white; border: 1px solid #E0E0E0; border-radius: 8px; text-decoration: none; color: #333; transition: all 0.3s; }
        .pagination a:hover, .pagination .active { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
        @media (max-width: 768px) { .admin-main { padding: 20px; } .filters-bar { flex-direction: column; align-items: stretch; } .filter-group select, .filter-group input { width: 100%; } }
    </style>

</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>
<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main">
    <div class="page-header">
        <h1><i class="fas fa-history" style="color: #C6A43F; margin-right: 10px;"></i>Activity Logs</h1>
        <p>Monitor all user actions and system events</p>
    </div>

    <div class="filters-bar">
        <div class="filter-group"><label>Date Range</label><input type="date" value="2024-01-01"></div>
        <div class="filter-group"><label>To</label><input type="date" value="<?php echo date('Y-m-d'); ?>"></div>
        <div class="filter-group"><label>Action Type</label><select><option>All Actions</option><option>User Registrations</option><option>Listings</option><option>Agent Approvals</option><option>Admin Actions</option></select></div>
        <div class="filter-group"><label>User</label><input type="text" placeholder="Search by user..."></div>
        <button class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="number">1,482</div><div class="label">Total Events</div></div>
        <div class="stat-card"><div class="number">156</div><div class="label">This Week</div></div>
        <div class="stat-card"><div class="number">23</div><div class="label">Critical Events</div></div>
        <div class="stat-card"><div class="number">8</div><div class="label">Pending Actions</div></div>
    </div>

    <div class="logs-card">
        <div class="logs-header">
            <h2><i class="fas fa-list" style="color: #C6A43F; margin-right: 8px;"></i>Event Timeline</h2>
            <button class="export-btn"><i class="fas fa-download"></i> Export CSV</button>
        </div>
        <div class="table-responsive">
            <table class="logs-table">
                <thead>
                    <tr><th>Time</th><th>User</th><th>Action Type</th><th>Description</th><th>IP Address</th></tr>
                </thead>
                <tbody>
                    <tr><td>2024-01-20 14:30:22</td><td>john.smith@example.com</td><td><span class="log-type agent">Agent Registration</span></td><td>John Smith submitted agent verification documents</td><td>192.168.1.1</td></tr>
                    <tr><td>2024-01-20 13:15:08</td><td>sarah@williams.com</td><td><span class="log-type listing">Listing Created</span></td><td>New property listing: "Lagos Waterfront Mansion" added</td><td>192.168.1.2</td></tr>
                    <tr><td>2024-01-20 11:45:33</td><td>admin@kinasgroup.com</td><td><span class="log-type admin">Admin Action</span></td><td>Approved agent application for Michael Adebayo</td><td>192.168.1.100</td></tr>
                    <tr><td>2024-01-20 10:20:17</td><td>buyer1@gmail.com</td><td><span class="log-type user">User Action</span></td><td>Saved listing #12345 to favorites</td><td>192.168.1.3</td></tr>
                    <tr><td>2024-01-19 16:55:44</td><td>admin@kinasgroup.com</td><td><span class="log-type admin">System</span></td><td>System settings updated (commission rates changed)</td><td>192.168.1.100</td></tr>
                    <tr><td>2024-01-19 14:22:11</td><td>flag.report@example.com</td><td><span class="log-type listing">Flagged Listing</span></td><td>Listing #12378 reported as suspicious by user</td><td>192.168.1.4</td></tr>
                    <tr><td>2024-01-19 11:08:35</td><td>newuser@domain.com</td><td><span class="log-type user">User Registration</span></td><td>New user account created: newuser@domain.com</td><td>192.168.1.5</td></tr>
                    <tr><td>2024-01-19 09:30:52</td><td>agent@kinasauto.com</td><td><span class="log-type listing">Listing Updated</span></td><td>Car listing price updated: Mercedes S-Class (₦110,000,000)</td><td>192.168.1.6</td></tr>
                    <tr><td>2024-01-18 17:20:45</td><td>admin@kinasgroup.com</td><td><span class="log-type admin">User Management</span></td><td>Changed user role for james@example.com to Agent</td><td>192.168.1.100</td></tr>
                    <tr><td>2024-01-18 15:10:23</td><td>support@kinasvolt.com</td><td><span class="log-type system">Solar Quote</span></td><td>New solar quote request received from customer</td><td>192.168.1.7</td></tr>
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <span class="active">1</span><a href="#">2</a><a href="#">3</a><a href="#">4</a><a href="#">5</a><a href="#">Next →</a>
        </div>
    </div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
