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
    <title>Manage Agents - KINAS GROUP Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-main { flex: 1; padding: 30px; background: #F5F7FA; }
        .admin-container { max-width: 1400px; margin: 0 auto; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; }
        .admin-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; margin: 0; }
        .admin-header h1 i { color: #C6A43F; margin-right: 12px; }
        .admin-header p { color: #666; font-size: 14px; margin-top: 5px; }
        .search-input { padding: 10px 16px; border: 1px solid #E0E0E0; border-radius: 10px; width: 280px; font-family: 'Inter', sans-serif; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; text-align: center; border: 1px solid #E0E0E0; }
        .stat-number { font-size: 32px; font-weight: 700; color: #C6A43F; font-family: 'Prata', serif; }
        .stat-label { color: #666; font-size: 13px; margin-top: 5px; }
        .filters-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 25px; background: white; padding: 16px 24px; border-radius: 16px; border: 1px solid #E0E0E0; }
        .filter-group { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .filter-group select { padding: 10px 16px; border: 1px solid #E0E0E0; border-radius: 10px; background: white; cursor: pointer; }
        .btn-add { padding: 10px 20px; background: #C6A43F; border: none; border-radius: 10px; font-weight: 600; color: #0A0A0A; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-add:hover { background: #A8882E; transform: translateY(-2px); }
        .table-container { background: white; border-radius: 20px; border: 1px solid #E0E0E0; overflow: hidden; }
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 16px 20px; background: #F8F8F8; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; border-bottom: 1px solid #E0E0E0; }
        .data-table td { padding: 16px 20px; border-bottom: 1px solid #E0E0E0; color: #333; font-size: 13px; }
        .data-table tr:hover { background: #F8F8F8; }
        .agent-cell { display: flex; align-items: center; gap: 12px; }
        .agent-avatar { width: 40px; height: 40px; background: #C6A43F; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #0A0A0A; }
        .division-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .division-badge.automobile { background: #E3F2FD; color: #1565C0; }
        .division-badge.realestate { background: #E8F5E9; color: #2E7D32; }
        .division-badge.solar { background: #FFF3E0; color: #F57C00; }
        .division-badge.marketplace { background: #F3E5F5; color: #7B1FA2; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-badge.active { background: #E8F5E9; color: #2E7D32; }
        .status-badge.pending { background: #FFF3E0; color: #F57C00; }
        .status-badge.suspended { background: #FEF2F2; color: #DC2626; }
        .action-buttons { display: flex; gap: 8px; }
        .action-btn { width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer; transition: all 0.3s; }
        .action-btn.view { background: rgba(59,130,246,0.1); color: #3B82F6; }
        .action-btn.edit { background: rgba(198,164,63,0.1); color: #C6A43F; }
        .action-btn.suspend { background: rgba(220,38,38,0.1); color: #DC2626; }
        .action-btn.verify { background: rgba(34,197,94,0.1); color: #22C55E; }
        .action-btn:hover { transform: scale(1.05); }
        .pagination { display: flex; justify-content: center; gap: 8px; padding: 20px; border-top: 1px solid #E0E0E0; }
        .page-btn { padding: 8px 14px; background: white; border: 1px solid #E0E0E0; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
        .page-btn.active, .page-btn:hover { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
        @media (max-width: 768px) { .admin-main { padding: 20px; } .admin-header { flex-direction: column; align-items: flex-start; } .search-input { width: 100%; } .filters-bar { flex-direction: column; } .filter-group { width: 100%; justify-content: stretch; } .filter-group select { flex: 1; } }
    </style>

</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>
<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main">
<main class="je-dash-main">
    <div class="admin-container">
        <div class="admin-header">
            <div><h1><i class="fas fa-user-tie"></i>Manage Agents</h1><p>View and manage all registered agents</p></div>
            <input type="search" id="searchInput" class="search-input" placeholder="Search agents by name or email...">
        </div>

        <div class="stats-row">
            <div class="stat-card"><div class="stat-number">156</div><div class="stat-label">Total Agents</div></div>
            <div class="stat-card"><div class="stat-number">142</div><div class="stat-label">Active</div></div>
            <div class="stat-card"><div class="stat-number">12</div><div class="stat-label">Pending Verification</div></div>
            <div class="stat-card"><div class="stat-number">2</div><div class="stat-label">Suspended</div></div>
        </div>

        <div class="filters-bar">
            <div class="filter-group">
                <select id="divisionFilter"><option value="all">All Divisions</option><option value="automobile">Kinas Automobile</option><option value="realestate">Williams Connect Home</option><option value="solar">Kinas Volt</option><option value="marketplace">Kinas Marketplace</option></select>
                <select id="statusFilter"><option value="all">All Status</option><option value="active">Active</option><option value="pending">Pending</option><option value="suspended">Suspended</option></select>
            </div>
            <button class="btn-add" onclick="openAgentModal()"><i class="fas fa-plus"></i> Add Agent</button>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Agent</th><th>Email</th><th>Division</th><th>Listings</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
                    <tbody id="agentTableBody">
                        <tr><td><div class="agent-cell"><div class="agent-avatar">JS</div><div><strong>John Smith</strong></div></div></td><td>john.smith@example.com</td><td><span class="division-badge automobile">Automobile</span></td><td>12</td><td><span class="status-badge active">Active</span></td><td>Jan 15, 2024</td><td><div class="action-buttons"><button class="action-btn view" onclick="viewAgent(1)"><i class="fas fa-eye"></i></button><button class="action-btn edit" onclick="editAgent(1)"><i class="fas fa-edit"></i></button><button class="action-btn suspend" onclick="suspendAgent(1)"><i class="fas fa-ban"></i></button></div></td></tr>
                        <tr><td><div class="agent-cell"><div class="agent-avatar">SJ</div><div><strong>Sarah Johnson</strong></div></div></td><td>sarah@williamsrealty.com</td><td><span class="division-badge realestate">Real Estate</span></td><td>28</td><td><span class="status-badge active">Active</span></td><td>Dec 10, 2023</td><td><div class="action-buttons"><button class="action-btn view" onclick="viewAgent(2)"><i class="fas fa-eye"></i></button><button class="action-btn edit" onclick="editAgent(2)"><i class="fas fa-edit"></i></button><button class="action-btn suspend" onclick="suspendAgent(2)"><i class="fas fa-ban"></i></button></div></td></tr>
                        <tr><td><div class="agent-cell"><div class="agent-avatar">MC</div><div><strong>Mike Chen</strong></div></div></td><td>mike@kinasvolt.com</td><td><span class="division-badge solar">Solar</span></td><td>8</td><td><span class="status-badge pending">Pending</span></td><td>Feb 20, 2024</td><td><div class="action-buttons"><button class="action-btn view" onclick="viewAgent(3)"><i class="fas fa-eye"></i></button><button class="action-btn verify" onclick="verifyAgent(3)"><i class="fas fa-check-circle"></i></button><button class="action-btn suspend" onclick="suspendAgent(3)"><i class="fas fa-ban"></i></button></div></td></tr>
                        <tr><td><div class="agent-cell"><div class="agent-avatar">DW</div><div><strong>David Wilson</strong></div></div></td><td>david@kinasmarket.com</td><td><span class="division-badge marketplace">Marketplace</span></td><td>42</td><td><span class="status-badge active">Active</span></td><td>Nov 5, 2023</td><td><div class="action-buttons"><button class="action-btn view" onclick="viewAgent(4)"><i class="fas fa-eye"></i></button><button class="action-btn edit" onclick="editAgent(4)"><i class="fas fa-edit"></i></button><button class="action-btn suspend" onclick="suspendAgent(4)"><i class="fas fa-ban"></i></button></div></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination"><button class="page-btn" disabled><i class="fas fa-chevron-left"></i></button><button class="page-btn active">1</button><button class="page-btn">2</button><button class="page-btn">3</button><button class="page-btn"><i class="fas fa-chevron-right"></i></button></div>
        </div>
    </div>

<script>
function filterTable() { const term = document.getElementById('searchInput')?.value.toLowerCase() || ''; const division = document.getElementById('divisionFilter')?.value; const status = document.getElementById('statusFilter')?.value; const rows = document.querySelectorAll('#agentTableBody tr'); rows.forEach(row => { const name = row.cells[0]?.textContent.toLowerCase() || ''; const email = row.cells[1]?.textContent.toLowerCase() || ''; const div = row.cells[2]?.textContent.toLowerCase() || ''; const stat = row.cells[4]?.textContent.toLowerCase() || ''; const matchesSearch = name.includes(term) || email.includes(term); const matchesDivision = division === 'all' || div.includes(division); const matchesStatus = status === 'all' || stat.includes(status); row.style.display = matchesSearch && matchesDivision && matchesStatus ? '' : 'none'; }); }
document.getElementById('searchInput')?.addEventListener('input', filterTable);
document.getElementById('divisionFilter')?.addEventListener('change', filterTable);
document.getElementById('statusFilter')?.addEventListener('change', filterTable);
function viewAgent(id) { alert(`Viewing agent ${id}`); }
function editAgent(id) { alert(`Edit agent ${id}`); }
function suspendAgent(id) { if (confirm('Suspend this agent?')) alert(`Agent ${id} suspended`); }
function verifyAgent(id) { if (confirm('Verify this agent?')) alert(`Agent ${id} verified`); }
function openAgentModal() { alert('Add new agent functionality'); }
</script>


</main>
</div>

</main>
</div>

<?php require_once __DIR__ . "/../templates/footer.php"; ?>
AGENTMANAGEMENT_EOF

echo "✅ Updated: admin/agent-management.php"
