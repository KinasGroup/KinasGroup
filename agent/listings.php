<?php
/**
 * KINAS GROUP — Agent: My Listings
 *
 * Pulls listings from all 4 division tables (car, property, solar, marketplace),
 * applies search/division/status filters, paginates, and renders a working
 * delete form for each row.
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireAgent();

$db       = Database::getInstance()->getConnection();
$agent_id = (int)$_SESSION['user_id'];

// One-shot flash messages from prior redirect
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Filters ──────────────────────────────────────────────────
$search   = trim($_GET['q'] ?? '');
$division = $_GET['division'] ?? '';   // '' = all, or car/property/solar/marketplace
$status   = $_GET['status']   ?? 'all';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

// ── Build UNION across the 4 listings tables ────────────────
$tableMap = [
    'car'         => ['table' => 'car_listings',         'slug' => 'kinas-automobile',      'label' => 'Automobile',  'badge' => 'automobile'],
    'property'    => ['table' => 'property_listings',    'slug' => 'williams-connect-home', 'label' => 'Real Estate', 'badge' => 'realestate'],
    'solar'       => ['table' => 'solar_listings',       'slug' => 'kinas-volt',            'label' => 'Solar',       'badge' => 'solar'],
    'marketplace' => ['table' => 'marketplace_listings', 'slug' => 'kinas-marketplace',     'label' => 'Marketplace', 'badge' => 'marketplace'],
];

$unions = [];
foreach ($tableMap as $type => $cfg) {
    if ($division !== '' && $division !== $type) continue;

    $where = ['agent_id = ?']; $params = [$agent_id];
    if ($status !== 'all' && $status !== '') {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $where[] = 'title LIKE ?';
        $params[] = "%$search%";
    }
    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    $unions[] = [
        'type'   => $type,
        'sql'    => "SELECT id, title, price, status, views, created_at, '$type' AS ltype, '$cfg[label]' AS label, '$cfg[badge]' AS badge
                     FROM {$cfg['table']} $whereSQL",
        'params' => $params,
        'cfg'    => $cfg,
    ];
}

// We need a separate count query to keep it correct
$countParts = []; $countParams = [];
foreach ($tableMap as $type => $cfg) {
    if ($division !== '' && $division !== $type) continue;
    $where = ['agent_id = ?']; $params = [$agent_id];
    if ($status !== 'all' && $status !== '') { $where[] = 'status = ?'; $params[] = $status; }
    if ($search !== '')                       { $where[] = 'title LIKE ?'; $params[] = "%$search%"; }
    $countParts[] = "SELECT '$type' AS ltype FROM {$cfg['table']} WHERE " . implode(' AND ', $where);
    $countParams = array_merge($countParams, $params);
}
$countSql = implode(' UNION ALL ', $countParts);
$countStmt = $db->prepare($countSql);
$countStmt->execute($countParams);
$totalListings = $countStmt->rowCount();
$totalPages    = max(1, (int)ceil($totalListings / $perPage));

// Main rows
$allRows = [];
foreach ($unions as $u) {
    $stmt = $db->prepare($u['sql']);
    $stmt->execute($u['params']);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $allRows[] = $r;
    }
}
// Sort by created_at DESC and paginate
usort($allRows, fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));
$listings = array_slice($allRows, $offset, $perPage);

// ── Fetch thumbnails in a single batch ──────────────────────
$listingIdsByType = [];
foreach ($listings as $l) {
    $listingIdsByType[$l['ltype']][] = (int)$l['id'];
}
$thumbs = [];
foreach ($listingIdsByType as $type => $ids) {
    if (empty($ids)) continue;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT listing_id, url FROM listing_images WHERE listing_id IN ($ph) AND listing_type = ? ORDER BY sort_order");
    $stmt->execute([...$ids, $type]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $thumbs[$type . '_' . (int)$row['listing_id']] = $row['url'];
    }
}

// KYC soft-guard
$kycStatus = 'pending';
try {
    $st = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
    $st->execute([$agent_id]);
    $kycStatus = $st->fetchColumn() ?: 'pending';
} catch (Exception $e) {}

$csrf = Security::generateCSRFToken();
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
.search-wrapper input { width: 100%; padding: 10px 16px 10px 40px; border: 1px solid #E0E0E0; border-radius: 12px; font-family: 'Inter', sans-serif; box-sizing: border-box; }
.filter-group { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.filter-group select { padding: 10px 16px; border: 1px solid #E0E0E0; border-radius: 12px; background: white; cursor: pointer; }
.btn-filter { padding: 10px 18px; background: #C6A43F; color: #0A0A0A; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; }
.btn-filter:hover { background: #A8882E; }
.btn-secondary { background: #F5F5F5; color: #333; border: 1px solid #E0E0E0; padding: 10px 16px; border-radius: 12px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
.table-container { background: white; border-radius: 20px; border: 1px solid #E0E0E0; overflow: hidden; }
.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 16px 20px; background: #F8F8F8; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; border-bottom: 1px solid #E0E0E0; }
.data-table td { padding: 16px 20px; border-bottom: 1px solid #E0E0E0; color: #333; font-size: 13px; vertical-align: middle; }
.data-table tr:hover { background: #F8F8F8; }
.table-image { width: 60px; height: 45px; object-fit: cover; border-radius: 8px; }
.table-image-placeholder { width: 60px; height: 45px; border-radius: 8px; background: #F0F0F0; display: flex; align-items: center; justify-content: center; color: #ccc; }
.division-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.division-badge.automobile  { background: #E3F2FD; color: #1565C0; }
.division-badge.realestate  { background: #E8F5E9; color: #2E7D32; }
.division-badge.solar       { background: #FFF3E0; color: #F57C00; }
.division-badge.marketplace { background: #F3E5F5; color: #7B1FA2; }
.status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.status-badge.active  { background: #E8F5E9; color: #2E7D32; }
.status-badge.pending { background: #FFF3E0; color: #F57C00; }
.status-badge.flagged { background: #FEF2F2; color: #DC2626; }
.status-badge.sold    { background: #E3F2FD; color: #1565C0; }
.status-badge.removed { background: #F3F4F6; color: #6B7280; }
.status-badge.inactive{ background: #ECEFF1; color: #607D8B; }
.action-buttons { display: flex; gap: 8px; align-items: center; }
.action-btn { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s; border: none; cursor: pointer; }
.action-btn.edit    { background: rgba(198,164,63,0.1); color: #C6A43F; }
.action-btn.delete  { background: rgba(220,38,38,0.1); color: #DC2626; }
.action-btn.view    { background: rgba(59,130,246,0.1); color: #3B82F6; }
.action-btn:hover   { transform: scale(1.05); }
.pagination { display: flex; justify-content: center; gap: 8px; padding: 20px; border-top: 1px solid #E0E0E0; flex-wrap: wrap; }
.page-btn { padding: 8px 14px; background: white; border: 1px solid #E0E0E0; border-radius: 8px; cursor: pointer; transition: all 0.3s; text-decoration: none; color: #333; font-size: 13px; }
.page-btn.active, .page-btn:hover:not(.disabled) { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
.page-btn.disabled { color: #CCC; cursor: not-allowed; }
.empty-state { padding: 80px 20px; text-align: center; color: #999; }
.empty-state i { font-size: 48px; color: #C6A43F; opacity: 0.4; display: block; margin-bottom: 14px; }
.empty-state p { font-size: 14px; }
.empty-state a { color: #C6A43F; font-weight: 600; }
.flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
.flash.success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
.flash.error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
@media (max-width: 768px) { .agent-container { padding: 20px; } .filters-bar { flex-direction: column; } .search-wrapper { max-width: 100%; width: 100%; } .filter-group { width: 100%; justify-content: space-between; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">

<div class="agent-container">
    <?php if ($flashSuccess): ?>
        <div class="flash success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="flash error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <div class="agent-header">
        <div>
            <h1><i class="fas fa-list-ul"></i> My Listings</h1>
            <p><?= number_format($totalListings) ?> <?= $totalListings === 1 ? 'listing' : 'listings' ?> across all your divisions</p>
        </div>
        <a href="/agent/add-listing.php" class="btn-primary"><i class="fas fa-plus"></i> Add New Listing</a>
    </div>

    <form class="filters-bar" method="GET">
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" name="q" placeholder="Search listings by title…" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-group">
            <select name="division">
                <option value="">All Divisions</option>
                <option value="car"         <?= $division === 'car'         ? 'selected' : '' ?>>Automobile</option>
                <option value="property"    <?= $division === 'property'    ? 'selected' : '' ?>>Real Estate</option>
                <option value="solar"       <?= $division === 'solar'       ? 'selected' : '' ?>>Solar</option>
                <option value="marketplace" <?= $division === 'marketplace' ? 'selected' : '' ?>>Marketplace</option>
            </select>
            <select name="status">
                <option value="all"     <?= $status === 'all'     ? 'selected' : '' ?>>All Status</option>
                <option value="active"  <?= $status === 'active'  ? 'selected' : '' ?>>Active</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="flagged" <?= $status === 'flagged' ? 'selected' : '' ?>>Flagged</option>
                <option value="sold"    <?= $status === 'sold'    ? 'selected' : '' ?>>Sold</option>
                <option value="removed" <?= $status === 'removed' ? 'selected' : '' ?>>Removed</option>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
            <a href="listings.php" class="btn-secondary"><i class="fas fa-undo"></i> Reset</a>
        </div>
    </form>

    <div class="table-container">
        <div class="table-responsive">
            <table class="data-table" id="listingsTable">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Title</th>
                        <th>Division</th>
                        <th>Price</th>
                        <th>Views</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($listings)): ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <p>
                                <?php if ($search || $division || ($status && $status !== 'all')): ?>
                                    No listings match the current filter.
                                <?php else: ?>
                                    You haven't created any listings yet.
                                <?php endif; ?>
                            </p>
                            <?php if (!$search && !$division && ($status === 'all' || $status === '')): ?>
                                <p><a href="/agent/add-listing.php">Add your first listing →</a></p>
                            <?php else: ?>
                                <p><a href="listings.php">Clear filters</a></p>
                            <?php endif; ?>
                        </div>
                    </td></tr>
                <?php else: ?>
                <?php foreach ($listings as $l):
                    $imgKey  = $l['ltype'] . '_' . (int)$l['id'];
                    $imgUrl  = $thumbs[$imgKey] ?? '';
                    $detailSlug = $tableMap[$l['ltype']]['slug'] ?? '';
                ?>
                <tr>
                    <td>
                        <?php if ($imgUrl): ?>
                            <img src="<?= htmlspecialchars($imgUrl) ?>" class="table-image" alt="">
                        <?php else: ?>
                            <div class="table-image-placeholder"><i class="fas fa-image"></i></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($l['title']) ?></strong>
                        <?php if (!empty($detailSlug)): ?>
                            <br><a href="/divisions/<?= htmlspecialchars($detailSlug) ?>/detail.php?id=<?= (int)$l['id'] ?>" target="_blank" style="font-size:11px; color:#999;"><?= $l['status'] === 'active' ? 'View live →' : 'Preview →' ?></a>
                        <?php endif; ?>
                    </td>
                    <td><span class="division-badge <?= htmlspecialchars($l['badge']) ?>"><?= htmlspecialchars($l['label']) ?></span></td>
                    <td>₦<?= number_format((float)$l['price']) ?></td>
                    <td><?= number_format((int)($l['views'] ?? 0)) ?></td>
                    <td><span class="status-badge <?= htmlspecialchars($l['status']) ?>"><?= htmlspecialchars(ucfirst($l['status'])) ?></span></td>
                    <td><?= htmlspecialchars(date('M j, Y', strtotime($l['created_at']))) ?></td>
                    <td>
                        <div class="action-buttons">
                            <a class="action-btn view" href="/divisions/<?= htmlspecialchars($detailSlug) ?>/detail.php?id=<?= (int)$l['id'] ?>" target="_blank" title="<?= $l['status'] === 'active' ? 'View live' : 'Preview' ?>"><i class="fas fa-eye"></i></a>
                            <a class="action-btn edit" href="/agent/edit-listing.php?id=<?= (int)$l['id'] ?>" title="Edit"><i class="fas fa-edit"></i></a>
                            <form method="POST" action="/api/listings/delete.php" style="display:inline" onsubmit="return confirm('Delete this listing? This cannot be undone.');">
                                <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                <input type="hidden" name="type" value="<?= htmlspecialchars($l['ltype']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="redirect" value="/agent/listings.php">
                                <button type="submit" class="action-btn delete" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1):
            $baseQuery = array_diff_key($_GET, ['page' => '']);
            $pageUrl = fn($p) => ($p <= 1
                ? '?' . http_build_query($baseQuery)
                : '?' . http_build_query($baseQuery + ['page' => $p]));
        ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a class="page-btn" href="<?= $pageUrl($page-1) ?>"><i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
                <span class="page-btn disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
            ?>
                <?php if ($i === $page): ?>
                    <span class="page-btn active"><?= $i ?></span>
                <?php else: ?>
                    <a class="page-btn" href="<?= $pageUrl($i) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a class="page-btn" href="<?= $pageUrl($page+1) ?>"><i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
                <span class="page-btn disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
