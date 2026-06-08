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
    <title>Reports - KINAS GROUP Admin</title>
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
        .date-range { background: white; border-radius: 16px; padding: 20px; margin-bottom: 30px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; border: 1px solid #E0E0E0; }
        .date-group { display: flex; flex-direction: column; gap: 5px; }
        .date-group label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #666; }
        .date-group input { padding: 10px 12px; border: 1px solid #E0E0E0; border-radius: 8px; font-family: 'Inter', sans-serif; }
        .btn-filter { background: #C6A43F; color: #0A0A0A; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 16px; padding: 25px; border: 1px solid #E0E0E0; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-3px); border-color: #C6A43F; }
        .stat-card .icon { font-size: 32px; color: #C6A43F; margin-bottom: 15px; }
        .stat-card h3 { font-family: 'Prata', serif; font-size: 32px; color: #0A0A0A; margin-bottom: 5px; }
        .stat-card p { color: #666; font-size: 13px; }
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 30px; }
        .chart-card { background: white; border-radius: 16px; padding: 25px; border: 1px solid #E0E0E0; }
        .chart-card h3 { font-family: 'Prata', serif; font-size: 18px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #C6A43F; display: inline-block; }
        .chart-placeholder { background: #F8F8F8; border-radius: 12px; height: 250px; display: flex; align-items: center; justify-content: center; color: #666; flex-direction: column; gap: 10px; }
        .chart-placeholder i { font-size: 48px; color: #C6A43F; }
        .recent-table { background: white; border-radius: 16px; border: 1px solid #E0E0E0; overflow: hidden; }
        .recent-table h3 { padding: 20px 25px; margin: 0; font-family: 'Prata', serif; font-size: 18px; border-bottom: 1px solid #E0E0E0; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 20px; background: #F8F8F8; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; }
        td { padding: 12px 20px; border-bottom: 1px solid #E0E0E0; font-size: 13px; }
        tr:hover { background: #F8F8F8; }
        .export-buttons { display: flex; gap: 12px; margin-top: 20px; justify-content: flex-end; }
        .btn-export { background: #666; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        @media (max-width: 992px) { .charts-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .admin-main { padding: 20px; } }
    </style>

</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>
<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main">
    <div class="page-header">
        <h1><i class="fas fa-chart-line" style="color: #C6A43F; margin-right: 10px;"></i>Analytics & Reports</h1>
        <p>Comprehensive marketplace insights and performance metrics</p>
    </div>

    <div class="date-range">
        <div class="date-group"><label>From Date</label><input type="date" value="2024-01-01"></div>
        <div class="date-group"><label>To Date</label><input type="date" value="<?php echo date('Y-m-d'); ?>"></div>
        <button class="btn-filter"><i class="fas fa-sync-alt"></i> Generate Report</button>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="icon"><i class="fas fa-users"></i></div><h3>1,482</h3><p>Verified Agents</p></div>
        <div class="stat-card"><div class="icon"><i class="fas fa-car"></i></div><h3>892</h3><p>Active Listings</p></div>
        <div class="stat-card"><div class="icon"><i class="fas fa-envelope"></i></div><h3>3,421</h3><p>Total Inquiries</p></div>
        <div class="stat-card"><div class="icon"><i class="fas fa-chart-line"></i></div><h3>₦2.4B</h3><p>Total Value</p></div>
    </div>

    <div class="charts-grid">
        <div class="chart-card"><h3>Listings by Category</h3><div class="chart-placeholder"><i class="fas fa-chart-pie"></i><span>Cars: 45% | Properties: 35% | Marketplace: 20%</span></div></div>
        <div class="chart-card"><h3>Monthly Growth</h3><div class="chart-placeholder"><i class="fas fa-chart-line"></i><span>↑ 23% increase this month</span></div></div>
    </div>

    <div class="recent-table">
        <h3>Recent Activity</h3>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Status</th></tr></thead>
                <tbody>
                    <tr><td>2024-01-20</td><td>New Agent</td><td>John Smith registered as Automobile Agent</td><td><span style="color:#2E7D32;">✓ Approved</span></td></tr>
                    <tr><td>2024-01-19</td><td>New Listing</td><td>Luxury Villa listed in Lagos</td><td><span style="color:#2E7D32;">✓ Published</span></td></tr>
                    <tr><td>2024-01-19</td><td>Inquiry</td><td>Buyer inquiry received for Mercedes S-Class</td><td><span style="color:#F57C00;">Pending Response</span></td></tr>
                    <tr><td>2024-01-18</td><td>Agent Approval</td><td>Sarah Williams - Real Estate Agent approved</td><td><span style="color:#2E7D32;">✓ Completed</span></td></tr>
                    <tr><td>2024-01-18</td><td>Flagged Listing</td><td>Suspicious listing reported and removed</td><td><span style="color:#DC2626;">Resolved</span></td></tr>
                </tbody>
            </table>
        </div>
        <div class="export-buttons">
            <button class="btn-export"><i class="fas fa-file-pdf"></i> Export PDF</button>
            <button class="btn-export"><i class="fas fa-file-excel"></i> Export CSV</button>
        </div>
    </div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
