<?php
/**
 * KINAS GROUP — Admin: Listing Management
 * Pulls live data from all 4 listings tables (cars, properties, solar, marketplace).
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();

// ── Filters ──────────────────────────────────────────────────
$division = $_GET['division'] ?? '';
$status   = $_GET['status']   ?? '';
$search   = trim($_GET['q']   ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

// ── Build union across the 4 listings tables ────────────────
$tableMap = [
    'car'         => ['table' => 'car_listings',         'label' => 'Kinas Automobile',      'class' => 'automobile',  'div_slug' => 'kinas-automobile'],
    'property'    => ['table' => 'property_listings',    'label' => 'Williams Connect Home', 'class' => 'realestate',  'div_slug' => 'williams-connect-home'],
    'solar'       => ['table' => 'solar_listings',       'label' => 'Kinas Volt',            'class' => 'solar',       'div_slug' => 'kinas-volt'],
    'marketplace' => ['table' => 'marketplace_listings', 'label' => 'Kinas Marketplace',     'class' => 'marketplace', 'div_slug' => 'kinas-marketplace'],
];

$unions = [];
foreach ($tableMap as $type => $cfg) {
    if ($division !== '' && $division !== $type) continue;

    $where = [];
    $params = [];
    if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; }
    if ($search !== '') {
        $where[] = '(title LIKE ? OR id = ?)';
        $params[] = "%$search%";
        $params[] = is_numeric($search) ? (int)$search : 0;
    }
    $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $unions[] = [
        'type'   => $type,
        'sql'    => "SELECT '$type' AS ltype, id, title, price, status, created_at, agent_id,
                            NULL AS division
                     FROM {$cfg['table']} $whereSQL",
        'params' => $params,
        'cfg'    => $cfg,
    ];
}

if (empty($unions)) {
    // No division filter (all divisions)
    foreach ($tableMap as $type => $cfg) {
        $where = [];
        $params = [];
        if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; }
        if ($search !== '') {
            $where[] = '(title LIKE ? OR id = ?)';
            $params[] = "%$search%";
            $params[] = is_numeric($search) ? (int)$search : 0;
        }
        $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $unions[] = [
            'type'   => $type,
            'sql'    => "SELECT '$type' AS ltype, id, title, price, status, created_at, agent_id, NULL AS division FROM {$cfg['table']} $whereSQL",
            'params' => $params,
            'cfg'    => $cfg,
        ];
    }
}

$allRows = [];
foreach ($unions as $u) {
    $stmt = $db->prepare($u['sql']);
    $stmt->execute($u['params']);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $r['_cfg'] = $u['cfg'];
        $r['_type'] = $u['type'];
        $allRows[] = $r;
    }
}
// Sort by created_at DESC and paginate
usort($allRows, fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));
$total = count($allRows);
$rows  = array_slice($allRows, $offset, $perPage);

// Look up agent names in a single batch
$agentIds = array_values(array_unique(array_filter(array_column($rows, 'agent_id'))));
$agentMap = [];
if ($agentIds) {
    $ph = implode(',', array_fill(0, count($agentIds), '?'));
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE id IN ($ph)");
    $stmt->execute($agentIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $agentMap[(int)$u['id']] = $u;
    }
}

// ── Counts per division ──────────────────────────────────────
$divCounts = [];
foreach ($tableMap as $type => $cfg) {
    $c = (int)$db->query("SELECT COUNT(*) FROM {$cfg['table']}")->fetchColumn();
    $divCounts[$type] = $c;
}
$totalListings = array_sum($divCounts);
$totalPages    = max(1, (int)ceil($total / $perPage));

$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>
<main class="je-dash-main">
<div class="admin-container">
    <div class="admin-header">
        <h1><i class="fas fa-list-ul" style="color: #C6A43F; margin-right: 12px;"></i>Listing Management</h1>
        <p>Manage all <?= number_format($totalListings) ?> listings across all divisions</p>
    </div>

    <div class="stats-mini">
        <div class="stat-mini-card"><i class="fas fa-car"></i><div class="stat-mini-info"><span class="stat-mini-label">Automobiles</span><span class="stat-mini-number"><?= number_format($divCounts['car']) ?></span></div></div>
        <div class="stat-mini-card"><i class="fas fa-home"></i><div class="stat-mini-info"><span class="stat-mini-label">Real Estate</span><span class="stat-mini-number"><?= number_format($divCounts['property']) ?></span></div></div>
        <div class="stat-mini-card"><i class="fas fa-solar-panel"></i><div class="stat-mini-info"><span class="stat-mini-label">Solar</span><span class="stat-mini-number"><?= number_format($divCounts['solar']) ?></span></div></div>
        <div class="stat-mini-card"><i class="fas fa-store"></i><div class="stat-mini-info"><span class="stat-mini-label">Marketplace</span><span class="stat-mini-number"><?= number_format($divCounts['marketplace']) ?></span></div></div>
    </div>

    <form class="filters-bar" method="GET">
        <div class="search-wrapper"><i class="fas fa-search"></i><input type="text" name="q" placeholder="Search by title or ID…" value="<?= htmlspecialchars($search) ?>"></div>
        <div class="filter-group">
            <select name="division">
                <option value="">All Divisions</option>
                <option value="car"         <?= $division === 'car'         ? 'selected' : '' ?>>Kinas Automobile</option>
                <option value="property"    <?= $division === 'property'    ? 'selected' : '' ?>>Williams Connect Home</option>
                <option value="solar"       <?= $division === 'solar'       ? 'selected' : '' ?>>Kinas Volt</option>
                <option value="marketplace" <?= $division === 'marketplace' ? 'selected' : '' ?>>Kinas Marketplace</option>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="active"  <?= $status === 'active'  ? 'selected' : '' ?>>Active</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="flagged" <?= $status === 'flagged' ? 'selected' : '' ?>>Flagged</option>
                <option value="removed" <?= $status === 'removed' ? 'selected' : '' ?>>Removed</option>
                <option value="sold"    <?= $status === 'sold'    ? 'selected' : '' ?>>Sold</option>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
            <a href="listing-management.php" class="btn-filter" style="background:#F5F5F5; color:#333; border:1px solid #E0E0E0; text-decoration:none; display:inline-block;">Reset</a>
            <a href="listing-management-export.php?<?= htmlspecialchars(http_build_query(['division'=>$division,'status'=>$status,'q'=>$search])) ?>" class="btn-filter" style="background:#666; color:white; text-decoration:none; display:inline-block;"><i class="fas fa-download"></i> Export</a>
        </div>
    </form>

    <div class="table-container">
        <div class="table-responsive">
            <?php if (empty($rows)): ?>
                <div style="padding: 80px 20px; text-align:center; color:#999;">
                    <i class="fas fa-inbox" style="font-size:48px; color:#C6A43F; opacity:0.4; display:block; margin-bottom:14px;"></i>
                    <p style="font-size:14px;">No listings match the current filter.</p>
                    <?php if ($search || $division || $status): ?>
                        <p style="margin-top:8px;"><a href="listing-management.php" style="color:#C6A43F;">Clear filters</a></p>
                    <?php else: ?>
                        <p style="margin-top:8px; color:#bbb; font-size:12px;">Listings will appear here once agents create them.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>ID</th><th>Title</th><th>Division</th><th>Agent</th><th>Price (₦)</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody id="listingTableBody">
                    <?php foreach ($rows as $r):
                        $cfg  = $r['_cfg'];
                        $type = $r['_type'];
                        $agent = $agentMap[(int)$r['agent_id']] ?? null;
                        $detailUrl = "/divisions/{$cfg['div_slug']}/detail.php?id=" . (int)$r['id'];
                    ?>
                    <tr>
                        <td>#<?= str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT) ?></td>
                        <td><a href="<?= $detailUrl ?>" target="_blank" style="color:#0A0A0A; text-decoration:none; font-weight:600;"><?= htmlspecialchars($r['title']) ?></a></td>
                        <td><span class="division-badge <?= htmlspecialchars($cfg['class']) ?>"><?= htmlspecialchars($cfg['label']) ?></span></td>
                        <td>
                            <?php if ($agent): ?>
                                <?= htmlspecialchars($agent['name']) ?>
                                <br><span style="font-size:11px; color:#999;"><?= htmlspecialchars($agent['email']) ?></span>
                            <?php else: ?>
                                <span style="color:#999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>₦<?= number_format((float)$r['price']) ?></td>
                        <td><span class="status-badge <?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span></td>
                        <td><?= htmlspecialchars($r['created_at']) ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="<?= $detailUrl ?>" target="_blank" class="action-btn view" title="View listing"><i class="fas fa-eye" aria-hidden="true"></i><span class="action-btn-label">View</span></a>
                                <a href="<?= $detailUrl ?>" target="_blank" class="action-btn edit" title="Open in admin view"><i class="fas fa-external-link-alt" aria-hidden="true"></i><span class="action-btn-label">Open</span></a>
                                <?php if ($r['status'] !== 'flagged'): ?>
                                <form method="POST" action="/api/admin/review-listing.php" style="display:inline" onsubmit="return confirm('Flag this listing for review?');">
                                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                    <input type="hidden" name="listing_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="listing_type" value="<?= htmlspecialchars($type) ?>">
                                    <input type="hidden" name="action" value="flag">
                                    <button type="submit" class="action-btn flag" title="Flag listing"><i class="fas fa-flag" aria-hidden="true"></i><span class="action-btn-label">Flag</span></button>
                                </form>
                                <?php else: ?>
                                <form method="POST" action="/api/admin/review-listing.php" style="display:inline" onsubmit="return confirm('Clear flag and set back to active?');">
                                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                    <input type="hidden" name="listing_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="listing_type" value="<?= htmlspecialchars($type) ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="action-btn approve" title="Approve & unflag"><i class="fas fa-check" aria-hidden="true"></i><span class="action-btn-label">Approve</span></button>
                                </form>
                                <?php endif; ?>
                                <?php if ($r['status'] !== 'removed'): ?>
                                <form method="POST" action="/api/admin/remove-listing.php" style="display:inline" onsubmit="return confirm('Delete this listing? It will be hidden from public view (soft delete — the row is kept for audit).');">
                                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                    <input type="hidden" name="listing_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="listing_type" value="<?= htmlspecialchars($type) ?>">
                                    <button type="submit" class="action-btn delete" title="Delete listing"><i class="fas fa-trash-alt" aria-hidden="true"></i><span class="action-btn-label">Delete</span></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
                $baseQuery = array_diff_key($_GET, ['page' => '']);
                $base = '?' . http_build_query($baseQuery);
                $pageUrl = fn($p) => ($p <= 1 ? '?' . http_build_query($baseQuery) : $base . '&page=' . $p);
            ?>
            <?php if ($page > 1): ?>
                <a class="page-btn" href="<?= $pageUrl($page-1) ?>"><i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
                <button class="page-btn" disabled><i class="fas fa-chevron-left"></i></button>
            <?php endif; ?>

            <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
            ?>
                <?php if ($i === $page): ?>
                    <button class="page-btn active"><?= $i ?></button>
                <?php else: ?>
                    <a class="page-btn" href="<?= $pageUrl($i) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a class="page-btn" href="<?= $pageUrl($page+1) ?>"><i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
                <button class="page-btn" disabled><i class="fas fa-chevron-right"></i></button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- View Listing Modal -->
<div class="modal" id="viewModal"><div class="modal-content modal-large"><div class="modal-header"><h3>Listing Details</h3><button class="modal-close" onclick="closeViewModal()">&times;</button></div><div class="modal-body" id="viewModalBody"></div></div></div>

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
.stat-mini-card { background: white; border-radius: 16px; padding: 20px; display: flex; align-items: center; gap: 16px; border: 1.5px solid #C6A43F; transition: all 0.3s; }
.stat-mini-card:hover { border-color: #C6A43F; box-shadow: 0 8px 24px rgba(198,164,63,0.15); transform: translateY(-3px); }
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
.status-badge.removed { background: #F3F4F6; color: #6B7280; }
.status-badge.sold    { background: #E3F2FD; color: #1565C0; }
.action-buttons { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.action-btn { height: 30px; min-width: 30px; padding: 0 12px; border-radius: 7px; border: none; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 12px; font-weight: 600; font-family: inherit; line-height: 1; text-decoration: none; }
.action-btn-label { display: inline-block; }
.action-btn.view { background: rgba(59,130,246,0.1); color: #3B82F6; }
.action-btn.edit { background: rgba(198,164,63,0.1); color: #C6A43F; }
.action-btn.flag { background: rgba(245,158,11,0.12); color: #B45309; }
.action-btn.delete { background: rgba(220,38,38,0.12); color: #B91C1C; }
.action-btn.approve { background: rgba(34,197,94,0.12); color: #15803D; }
.action-btn.resolve { background: rgba(34,197,94,0.12); color: #15803D; }
.action-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.action-btn i { font-style: normal; min-width: 14px; text-align: center; }
.pagination { display: flex; justify-content: center; gap: 8px; padding: 20px; border-top: 1px solid #E0E0E0; }
.page-btn { padding: 8px 14px; background: white; border: 1px solid #E0E0E0; border-radius: 8px; cursor: pointer; transition: all 0.3s; text-decoration: none; color: #333; font-size: 13px; }
.page-btn.active, .page-btn:hover:not(:disabled) { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
.page-btn:disabled { color: #CCC; cursor: not-allowed; }
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
function closeViewModal() { document.getElementById('viewModal').classList.remove('show'); }
document.getElementById('viewModal')?.addEventListener('click', function(e) { if (e.target === this) closeViewModal(); });
</script>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
