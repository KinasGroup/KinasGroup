<?php
/**
 * Kinas Group - Listing Management
 * Luxury Design - Nigerian Naira Currency
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

// Auth: handled by SessionManager::requireAdmin()

require_once __DIR__ . '/../templates/header.php';
?>
<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>
<main class="je-dash-main">
<div class="admin-container">
    <div class="admin-header">
        <h1><i class="fas fa-list-ul" style="color: #C6A43F; margin-right: 12px;"></i>Listing Management</h1>
        <p>Manage all listings across all divisions</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-mini">
        <div class="stat-mini-card"><i class="fas fa-car"></i><div class="stat-mini-info"><span class="stat-mini-label">Automobiles</span><span class="stat-mini-number">342</span></div></div>
        <div class="stat-mini-card"><i class="fas fa-home"></i><div class="stat-mini-info"><span class="stat-mini-label">Real Estate</span><span class="stat-mini-number">256</span></div></div>
        <div class="stat-mini-card"><i class="fas fa-solar-panel"></i><div class="stat-mini-info"><span class="stat-mini-label">Solar Energy</span><span class="stat-mini-number">128</span></div></div>
        <div class="stat-mini-card"><i class="fas fa-store"></i><div class="stat-mini-info"><span class="stat-mini-label">Marketplace</span><span class="stat-mini-number">508</span></div></div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
        <div class="search-wrapper"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search listings by title, ID, or agent..."></div>
        <div class="filter-group">
            <select id="divisionFilter"><option value="all">All Divisions</option><option value="automobile">Kinas Automobile</option><option value="realestate">Williams Connect Home</option><option value="solar">Kinas Volt</option><option value="marketplace">Kinas Marketplace</option></select>
            <select id="statusFilter"><option value="all">All Status</option><option value="active">Active</option><option value="pending">Pending</option><option value="flagged">Flagged</option><option value="expired">Expired</option></select>
            <button class="btn-filter" onclick="exportData()"><i class="fas fa-download"></i> Export</button>
        </div>
    </div>

    <!-- Data Table -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Title</th><th>Division</th><th>Agent</th><th>Price (₦)</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody id="listingTableBody">
                    <tr><td>#LK-001</td><td>2024 Mercedes-Benz S-Class</td><td><span class="division-badge automobile">Automobile</span></td><td>John Smith</td><td>₦185,000,000</td><td><span class="status-badge active">Active</span></td><td>2024-01-15</td><td><div class="action-buttons"><button class="action-btn view" onclick="viewListing(1)"><i class="fas fa-eye"></i></button><button class="action-btn edit" onclick="editListing(1)"><i class="fas fa-edit"></i></button><button class="action-btn flag" onclick="flagListing(1)"><i class="fas fa-flag"></i></button></div></td></tr>
                    <tr><td>#LK-002</td><td>Luxury Villa with Ocean View</td><td><span class="division-badge realestate">Real Estate</span></td><td>Sarah Johnson</td><td>₦3,450,000,000</td><td><span class="status-badge active">Active</span></td><td>2024-02-20</td><td><div class="action-buttons"><button class="action-btn view" onclick="viewListing(2)"><i class="fas fa-eye"></i></button><button class="action-btn edit" onclick="editListing(2)"><i class="fas fa-edit"></i></button><button class="action-btn flag" onclick="flagListing(2)"><i class="fas fa-flag"></i></button></div></td></tr>
                    <tr><td>#LK-003</td><td>10kW Solar Panel Installation</td><td><span class="division-badge solar">Solar</span></td><td>Mike Chen</td><td>₦28,500,000</td><td><span class="status-badge pending">Pending</span></td><td>2024-03-05</td><td><div class="action-buttons"><button class="action-btn view" onclick="viewListing(3)"><i class="fas fa-eye"></i></button><button class="action-btn edit" onclick="editListing(3)"><i class="fas fa-edit"></i></button><button class="action-btn approve" onclick="approveListing(3)"><i class="fas fa-check"></i></button></div></td></tr>
                    <tr><td>#LK-004</td><td>Vintage Rolex Watch</td><td><span class="division-badge marketplace">Marketplace</span></td><td>David Wilson</td><td>₦12,500,000</td><td><span class="status-badge flagged">Flagged</span></td><td>2024-03-10</td><td><div class="action-buttons"><button class="action-btn view" onclick="viewListing(4)"><i class="fas fa-eye"></i></button><button class="action-btn edit" onclick="editListing(4)"><i class="fas fa-edit"></i></button><button class="action-btn resolve" onclick="resolveFlag(4)"><i class="fas fa-check-circle"></i></button></div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="pagination"><button class="page-btn" disabled><i class="fas fa-chevron-left"></i></button><button class="page-btn active">1</button><button class="page-btn">2</button><button class="page-btn">3</button><button class="page-btn"><i class="fas fa-chevron-right"></i></button></div>
    </div>

<!-- View Listing Modal -->
<div class="modal" id="viewModal"><div class="modal-content modal-large"><div class="modal-header"><h3>Listing Details</h3><button class="modal-close" onclick="closeViewModal()">&times;</button></div><div class="modal-body" id="viewModalBody"><div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#C6A43F;"></i><p>Loading listing details...</p></div></div></div></div>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.admin-layout { display: flex; min-height: 100vh; }
.admin-main { flex: 1; padding: 30px; background: #F5F7FA; }
.admin-container { max-width: 1400px; margin: 0 auto; }
.admin-header { margin-bottom: 30px; }
.admin-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; margin-bottom: 8px; }
.admin-header p { color: #666; font-size: 14px; }
.stats-mini { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-mini-card { background: white; border-radius: 16px; padding: 20px; display: flex; align-items: center; gap: 16px; border: 1px solid #E0E0E0; }
.stat-mini-card i { font-size: 32px; color: #C6A43F; }
.stat-mini-info { display: flex; flex-direction: column; }
.stat-mini-label { font-size: 12px; color: #666; }
.stat-mini-number { font-size: 24px; font-weight: 700; color: #C6A43F; font-family: 'Prata', serif; }
.filters-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 25px; background: white; padding: 16px 24px; border-radius: 16px; border: 1px solid #E0E0E0; }
.search-wrapper { flex: 1; position: relative; max-width: 350px; }
.search-wrapper i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #C6A43F; }
.search-wrapper input { width: 100%; padding: 10px 16px 10px 40px; border: 1px solid #E0E0E0; border-radius: 10px; font-family: 'Inter', sans-serif; }
.filter-group { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.filter-group select { padding: 10px 16px; border: 1px solid #E0E0E0; border-radius: 10px; background: white; cursor: pointer; }
.btn-filter { padding: 10px 20px; background: #C6A43F; border: none; border-radius: 10px; font-weight: 600; color: #0A0A0A; cursor: pointer; transition: all 0.3s; }
.btn-filter:hover { background: #A8882E; transform: translateY(-2px); }
.table-container { background: white; border-radius: 20px; border: 1px solid #E0E0E0; overflow: hidden; }
.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 16px 20px; background: #F8F8F8; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; border-bottom: 1px solid #E0E0E0; }
.data-table td { padding: 16px 20px; border-bottom: 1px solid #E0E0E0; color: #333; font-size: 13px; }
.data-table tr:hover { background: #F8F8F8; }
.division-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.division-badge.automobile { background: #E3F2FD; color: #1565C0; }
.division-badge.realestate { background: #E8F5E9; color: #2E7D32; }
.division-badge.solar { background: #FFF3E0; color: #F57C00; }
.division-badge.marketplace { background: #F3E5F5; color: #7B1FA2; }
.status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.status-badge.active { background: #E8F5E9; color: #2E7D32; }
.status-badge.pending { background: #FFF3E0; color: #F57C00; }
.status-badge.flagged { background: #FEF2F2; color: #DC2626; }
.status-badge.expired { background: #F3F4F6; color: #6B7280; }
.action-buttons { display: flex; gap: 8px; }
.action-btn { width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer; transition: all 0.3s; }
.action-btn.view { background: rgba(59,130,246,0.1); color: #3B82F6; }
.action-btn.edit { background: rgba(198,164,63,0.1); color: #C6A43F; }
.action-btn.flag { background: rgba(220,38,38,0.1); color: #DC2626; }
.action-btn.approve { background: rgba(34,197,94,0.1); color: #22C55E; }
.action-btn.resolve { background: rgba(34,197,94,0.1); color: #22C55E; }
.action-btn:hover { transform: scale(1.05); }
.pagination { display: flex; justify-content: center; gap: 8px; padding: 20px; border-top: 1px solid #E0E0E0; }
.page-btn { padding: 8px 14px; background: white; border: 1px solid #E0E0E0; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
.page-btn.active, .page-btn:hover { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
.modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index: 10000; align-items: center; justify-content: center; }
.modal.show { display: flex; }
.modal-content { background: white; border-radius: 20px; max-width: 600px; width: 90%; }
.modal-large { max-width: 800px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #E0E0E0; }
.modal-header h3 { font-family: 'Prata', serif; font-size: 20px; color: #0A0A0A; }
.modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }
.modal-body { padding: 24px; }
@media (max-width: 768px) { .admin-main { padding: 20px; } .filters-bar { flex-direction: column; } .search-wrapper { max-width: 100%; width: 100%; } .filter-group { width: 100%; justify-content: stretch; } .filter-group select { flex: 1; } .stats-mini { grid-template-columns: 1fr 1fr; } }
</style>


<script>
function filterTable() { const term = document.getElementById('searchInput')?.value.toLowerCase() || ''; const division = document.getElementById('divisionFilter')?.value; const status = document.getElementById('statusFilter')?.value; const rows = document.querySelectorAll('#listingTableBody tr'); rows.forEach(row => { const title = row.cells[1]?.textContent.toLowerCase() || ''; const div = row.cells[2]?.textContent.toLowerCase() || ''; const stat = row.cells[5]?.textContent.toLowerCase() || ''; const matchesSearch = title.includes(term); const matchesDivision = division === 'all' || div.includes(division); const matchesStatus = status === 'all' || stat.includes(status); row.style.display = matchesSearch && matchesDivision && matchesStatus ? '' : 'none'; }); }
document.getElementById('searchInput')?.addEventListener('input', filterTable);
document.getElementById('divisionFilter')?.addEventListener('change', filterTable);
document.getElementById('statusFilter')?.addEventListener('change', filterTable);
function viewListing(id) { const modal = document.getElementById('viewModal'); const body = document.getElementById('viewModalBody'); body.innerHTML = `<div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#C6A43F;"></i><p>Loading listing details...</p></div>`; modal.classList.add('show'); setTimeout(() => { body.innerHTML = `<div style="display:grid;gap:16px;"><div><strong>Listing ID:</strong> #LK-00${id}</div><div><strong>Title:</strong> Sample Listing Title</div><div><strong>Division:</strong> Kinas Automobile</div><div><strong>Agent:</strong> John Smith</div><div><strong>Price:</strong> ₦185,000,000</div><div><strong>Status:</strong> Active</div><div><strong>Created:</strong> 2024-01-15</div><div><strong>Description:</strong> This is a premium luxury listing.</div></div>`; }, 500); }
function closeViewModal() { document.getElementById('viewModal').classList.remove('show'); }
function editListing(id) { alert(`Edit listing ${id}`); }
function flagListing(id) { if (confirm('Flag this listing for review?')) alert(`Listing ${id} has been flagged`); }
function approveListing(id) { if (confirm('Approve this listing?')) alert(`Listing ${id} has been approved`); }
function resolveFlag(id) { if (confirm('Resolve flag on this listing?')) alert(`Flag on listing ${id} has been resolved`); }
function exportData() { alert('Exporting listing data...'); }
</script>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
