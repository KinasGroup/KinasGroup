<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

// Auth: handled by SessionManager::requireAgent()


$db = Database::getInstance()->getConnection();
$agent_id = $_SESSION['user_id'];
$division = $_SESSION['user_division'] ?? null;

// Determine which table to query based on division
$division_map = [
    'kinas-automobile'      => 'car_listings',
    'williams-connect-home' => 'property_listings',
    'kinas-volt'            => 'solar_listings',
    'kinas-marketplace'     => 'marketplace_listings',
];
$table = $division_map[$division] ?? 'car_listings';

$search = trim($_GET['search'] ?? '');
$status_f = $_GET['status'] ?? 'all';
$where = ["agent_id = $agent_id"];
$params = [];
if ($status_f !== 'all') { $where[] = "status = ?"; $params[] = $status_f; }
if ($search) { $where[] = "title LIKE ?"; $params[] = "%$search%"; }
$whereSQL = implode(' AND ', $where);

$page = max(1,(int)($_GET['page']??1)); $limit=15; $offset=($page-1)*$limit;
$stmt = $db->prepare("SELECT * FROM $table WHERE $whereSQL ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$listings = $stmt->fetchAll();

$cntStmt = $db->prepare("SELECT COUNT(*) FROM $table WHERE $whereSQL");
$cntStmt->execute($params);
$totalListings = (int)$cntStmt->fetchColumn();
$totalPages = max(1, ceil($totalListings/$limit));

// KYC soft-guard
$kycStatus='pending';try{$st=Database::getInstance()->getConnection()->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");$st->execute([(int)$_SESSION['user_id']]);$kycStatus=$st->fetchColumn()?:'pending';}catch(Exception $e){}

require_once __DIR__ . '/../templates/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.agent-container { max-width: 1400px; margin: 0 auto; padding: 30px; }
.agent-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 32px; }
.agent-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; }
.agent-header h1 i { color: #C6A43F; margin-right: 12px; }
.btn-primary { background: #C6A43F; color: #0A0A0A; padding: 12px 24px; border-radius: 40px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
.btn-primary:hover { background: #A8882E; transform: translateY(-2px); }
.filters-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; background: white; padding: 16px 24px; border-radius: 16px; border: 1px solid #E0E0E0; }
.search-wrapper { position: relative; flex: 1; max-width: 350px; }
.search-wrapper i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #C6A43F; }
.search-wrapper input { width: 100%; padding: 10px 16px 10px 40px; border: 1px solid #E0E0E0; border-radius: 12px; font-family: 'Inter', sans-serif; }
.filter-group { display: flex; gap: 12px; }
.filter-group select { padding: 10px 16px; border: 1px solid #E0E0E0; border-radius: 12px; background: white; cursor: pointer; }
.table-container { background: white; border-radius: 20px; border: 1px solid #E0E0E0; overflow: hidden; }
.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 16px 20px; background: #F8F8F8; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; border-bottom: 1px solid #E0E0E0; }
.data-table td { padding: 16px 20px; border-bottom: 1px solid #E0E0E0; color: #333; font-size: 13px; }
.data-table tr:hover { background: #F8F8F8; }
.table-image { width: 60px; height: 45px; object-fit: cover; border-radius: 8px; }
.division-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.division-badge.automobile { background: #E3F2FD; color: #1565C0; }
.division-badge.realestate { background: #E8F5E9; color: #2E7D32; }
.division-badge.solar { background: #FFF3E0; color: #F57C00; }
.division-badge.marketplace { background: #F3E5F5; color: #7B1FA2; }
.status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.status-badge.active { background: #E8F5E9; color: #2E7D32; }
.status-badge.pending { background: #FFF3E0; color: #F57C00; }
.action-buttons { display: flex; gap: 8px; }
.action-btn { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s; border: none; cursor: pointer; }
.action-btn.edit { background: rgba(198,164,63,0.1); color: #C6A43F; }
.action-btn.delete { background: rgba(220,38,38,0.1); color: #DC2626; }
.action-btn.view { background: rgba(59,130,246,0.1); color: #3B82F6; }
.action-btn:hover { transform: scale(1.05); }
.pagination { display: flex; justify-content: center; gap: 8px; padding: 20px; border-top: 1px solid #E0E0E0; }
.page-btn { padding: 8px 14px; background: white; border: 1px solid #E0E0E0; border-radius: 8px; cursor: pointer; }
.page-btn.active, .page-btn:hover { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
@media (max-width: 768px) { .agent-container { padding: 20px; } .filters-bar { flex-direction: column; } .search-wrapper { max-width: 100%; width: 100%; } .filter-group { width: 100%; justify-content: space-between; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">

<div class="agent-container">
    <div class="agent-header"><div><h1><i class="fas fa-list-ul"></i> My Listings</h1><p>Manage all your listings across divisions</p></div><a href="/agent/add-listing.php" class="btn-primary"><i class="fas fa-plus"></i> Add New Listing</a></div>

    <div class="filters-bar"><div class="search-wrapper"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search listings..."></div><div class="filter-group"><select id="divisionFilter"><option value="all">All Divisions</option><option value="automobile">Automobile</option><option value="realestate">Real Estate</option><option value="solar">Solar</option><option value="marketplace">Marketplace</option></select><select id="statusFilter"><option value="all">All Status</option><option value="active">Active</option><option value="pending">Pending</option><option value="inactive">Inactive</option></select></div></div>

    <div class="table-container"><div class="table-responsive"><table class="data-table" id="listingsTable"><thead><tr><th>Image</th><th>Title</th><th>Division</th><th>Price</th><th>Views</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody>
<?php if (empty($listings)): ?>
<tr><td colspan="7" style="text-align:center;padding:40px;color:#999">
    No listings yet. <a href="/agent/add-listing.php" style="color:#C6A43F;font-weight:600">Add your first listing</a>.
</td></tr>
<?php else: ?>
<?php foreach ($listings as $l):
    $imgs = $db->prepare("SELECT url FROM listing_images WHERE listing_id=? AND listing_type=? LIMIT 1");
    $imgs->execute([$l['id'], str_replace('_listings','',$table)]);
    $imgUrl = $imgs->fetchColumn() ?: '';
?>
<tr>
    <td><?php if ($imgUrl): ?><img src="<?= htmlspecialchars($imgUrl) ?>" class="table-image"><?php else: ?><div class="table-image" style="background:#F0F0F0;display:flex;align-items:center;justify-content:center"><i class="fas fa-image" style="color:#ccc"></i></div><?php endif; ?></td>
    <td><strong><?= htmlspecialchars($l['title']) ?></strong></td>
    <td><span class="division-badge <?= str_replace('-','',$division??'') ?>"><?= ucfirst(str_replace(['kinas-','williams-connect-home','kinas-volt'],['','Real Estate','Solar'],$division??'')) ?></span></td>
    <td>₦<?= number_format($l['price']) ?></td>
    <td><span class="status-badge <?= $l['status'] ?>"><?= ucfirst($l['status']) ?></span></td>
    <td><?= date('M j, Y', strtotime($l['created_at'])) ?></td>
    <td>
        <div class="action-buttons">
            <a class="action-btn view" href="/agent/edit-listing.php?id=<?= $l['id'] ?>" title="View"><i class="fas fa-eye"></i></a>
            <a class="action-btn edit" href="/agent/edit-listing.php?id=<?= $l['id'] ?>" title="Edit"><i class="fas fa-edit"></i></a>
            <button class="action-btn delete" onclick="if(confirm('Delete this listing?')) window.location.href='/api/agent/delete-listing.php?id=<?= $l['id'] ?>&table=<?= $table ?>&csrf=<?= Security::generateCSRFToken() ?>'" title="Delete"><i class="fas fa-trash"></i></button>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody></table></div><div class="pagination"><button class="page-btn active">1</button><button class="page-btn">2</button><button class="page-btn">3</button></div></div>
</div>

<script>
function deleteListing(id) { if(confirm('Are you sure you want to delete this listing?')) alert('Listing deleted'); }
function filterListings() { const term = document.getElementById('searchInput')?.value.toLowerCase() || ''; const division = document.getElementById('divisionFilter')?.value; const status = document.getElementById('statusFilter')?.value; const rows = document.querySelectorAll('#listingsTable tbody tr'); rows.forEach(row => { const title = row.cells[1]?.textContent.toLowerCase() || ''; const div = row.cells[2]?.textContent.toLowerCase() || ''; const stat = row.cells[5]?.textContent.toLowerCase() || ''; const matchesSearch = title.includes(term); const matchesDivision = division === 'all' || div.includes(division); const matchesStatus = status === 'all' || stat.includes(status); row.style.display = matchesSearch && matchesDivision && matchesStatus ? '' : 'none'; }); }
document.getElementById('searchInput')?.addEventListener('input', filterListings);
document.getElementById('divisionFilter')?.addEventListener('change', filterListings);
document.getElementById('statusFilter')?.addEventListener('change', filterListings);
</script>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
