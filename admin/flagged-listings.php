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
    <title>Flagged Listings - KINAS GROUP Admin</title>
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
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 16px; padding: 25px; text-align: center; border: 1px solid #E0E0E0; }
        .stat-card.danger .stat-number { color: #DC2626; }
        .stat-number { font-size: 32px; font-weight: 700; color: #C6A43F; font-family: 'Prata', serif; }
        .stat-label { color: #666; font-size: 13px; margin-top: 5px; }
        .filters-bar { background: white; border-radius: 16px; padding: 20px; margin-bottom: 25px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; border: 1px solid #E0E0E0; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #666; }
        .filter-group select, .filter-group input { padding: 10px 12px; border: 1px solid #E0E0E0; border-radius: 8px; font-family: 'Inter', sans-serif; min-width: 150px; }
        .btn-filter { background: #C6A43F; color: #0A0A0A; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .table-responsive { overflow-x: auto; }
        .flagged-table { width: 100%; border-collapse: collapse; background: white; border-radius: 20px; overflow: hidden; border: 1px solid #E0E0E0; }
        .flagged-table th { background: #F8F8F8; padding: 15px 20px; text-align: left; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; border-bottom: 1px solid #E0E0E0; }
        .flagged-table td { padding: 16px 20px; border-bottom: 1px solid #E0E0E0; vertical-align: middle; font-size: 13px; }
        .flagged-table tr:last-child td { border-bottom: none; }
        .flagged-table tr:hover { background: #FEF2F2; }
        .flag-reason { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .flag-spam { background: #FEF2F2; color: #DC2626; }
        .flag-fake { background: #FFF3E0; color: #F57C00; }
        .flag-price { background: #E3F2FD; color: #1565C0; }
        .listing-image { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
        .btn-review { background: #C6A43F; color: #0A0A0A; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.3s; margin-right: 6px; }
        .btn-review:hover { background: #A8882E; }
        .btn-remove { background: #DC2626; color: white; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-remove:hover { background: #B91C1C; }
        .btn-ignore { background: #666; color: white; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.3s; margin-right: 6px; }
        .btn-ignore:hover { background: #555; }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 30px; }
        .pagination a, .pagination span { padding: 10px 16px; background: white; border: 1px solid #E0E0E0; border-radius: 10px; text-decoration: none; color: #333; transition: all 0.3s; }
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
        <h1><i class="fas fa-flag" style="color: #DC2626; margin-right: 10px;"></i>Flagged Listings</h1>
        <p>Review and moderate reported content</p>
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-number">12</div><div class="stat-label">Pending Review</div></div>
        <div class="stat-card"><div class="stat-number">8</div><div class="stat-label">Resolved This Week</div></div>
        <div class="stat-card danger"><div class="stat-number">3</div><div class="stat-label">High Priority</div></div>
        <div class="stat-card"><div class="stat-number">2</div><div class="stat-label">Removed Listings</div></div>
    </div>

    <div class="filters-bar">
        <div class="filter-group"><label>Status</label><select><option>All Flags</option><option>Pending Review</option><option>Under Investigation</option><option>Resolved</option></select></div>
        <div class="filter-group"><label>Division</label><select><option>All Divisions</option><option>KINAS Automobile</option><option>Williams Connect Home</option><option>KINAS Marketplace</option><option>KINAS Volt</option></select></div>
        <div class="filter-group"><label>Flag Reason</label><select><option>All Reasons</option><option>Spam/Fake</option><option>Incorrect Pricing</option><option>Suspicious Activity</option><option>Copyright Issue</option></select></div>
        <button class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
    </div>

    <div class="table-responsive">
        <table class="flagged-table">
            <thead>
                <tr><th>Listing</th><th>Division</th><th>Flag Reason</th><th>Reported By</th><th>Date</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><div style="display: flex; align-items: center; gap: 12px;"><img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=100&q=80" class="listing-image" onerror="this.src='https://via.placeholder.com/60'"><div><strong>2024 Mercedes-Benz S-Class</strong><br><span style="font-size: 11px; color:#666;">ID: #12345</span></div></div></td>
                    <td>KINAS Automobile</td>
                    <td><span class="flag-reason flag-spam"><i class="fas fa-ban"></i> Suspicious Listing</span></td>
                    <td>buyer@example.com</td>
                    <td>Jan 20, 2024</td>
                    <td><span style="color:#F57C00;"><i class="fas fa-clock"></i> Pending</span></td>
                    <td><button class="btn-review"><i class="fas fa-eye"></i> Review</button><button class="btn-remove"><i class="fas fa-trash"></i> Remove</button><button class="btn-ignore"><i class="fas fa-check"></i> Ignore</button></td>
                </tr>
                <tr style="background:#FEF2F2;">
                    <td><div style="display: flex; align-items: center; gap: 12px;"><img src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=100&q=80" class="listing-image"><div><strong>Lagos Waterfront Mansion</strong><br><span style="font-size: 11px; color:#666;">ID: #12378</span></div></div></td>
                    <td>Williams Connect Home</td>
                    <td><span class="flag-reason flag-fake"><i class="fas fa-exclamation-triangle"></i> Fake Listing</span></td>
                    <td>anonymous@report.com</td>
                    <td>Jan 19, 2024</td>
                    <td><span style="color:#DC2626;"><i class="fas fa-flag"></i> High Priority</span></td>
                    <td><button class="btn-review"><i class="fas fa-eye"></i> Review</button><button class="btn-remove"><i class="fas fa-trash"></i> Remove</button><button class="btn-ignore"><i class="fas fa-check"></i> Ignore</button></td>
                </tr>
                <tr>
                    <td><div style="display: flex; align-items: center; gap: 12px;"><img src="https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=100&q=80" class="listing-image"><div><strong>Premium Rolex Watch</strong><br><span style="font-size: 11px; color:#666;">ID: #12456</span></div></div></td>
                    <td>KINAS Marketplace</td>
                    <td><span class="flag-reason flag-price"><i class="fas fa-tag"></i> Inaccurate Price</span></td>
                    <td>watchcollector@email.com</td>
                    <td>Jan 18, 2024</td>
                    <td><span style="color:#2E7D32;"><i class="fas fa-check-circle"></i> Under Review</span></td>
                    <td><button class="btn-review"><i class="fas fa-eye"></i> Review</button><button class="btn-remove"><i class="fas fa-trash"></i> Remove</button><button class="btn-ignore"><i class="fas fa-check"></i> Ignore</button></td>
                </tr>
                <tr>
                    <td><div style="display: flex; align-items: center; gap: 12px;"><img src="https://images.unsplash.com/photo-1509391366360-2e959784a276?w=100&q=80" class="listing-image"><div><strong>Industrial Solar 500kW</strong><br><span style="font-size: 11px; color:#666;">ID: #12789</span></div></div></td>
                    <td>KINAS Volt</td>
                    <td><span class="flag-reason flag-spam"><i class="fas fa-ban"></i> Duplicate Listing</span></td>
                    <td>solarbuyer@domain.com</td>
                    <td>Jan 17, 2024</td>
                    <td><span style="color:#F57C00;"><i class="fas fa-clock"></i> Pending</span></td>
                    <td><button class="btn-review"><i class="fas fa-eye"></i> Review</button><button class="btn-remove"><i class="fas fa-trash"></i> Remove</button><button class="btn-ignore"><i class="fas fa-check"></i> Ignore</button></td>
                </tr>
                <tr>
                    <td colspan="7" style="text-align: center; color: #666; padding: 40px;"><i class="fas fa-check-circle" style="color: #2E7D32; font-size: 24px; margin-bottom: 10px; display: block;"></i> All caught up! No more flagged listings to review.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <span class="active">1</span><a href="#">2</a><a href="#">3</a><a href="#">Next →</a>
    </div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
