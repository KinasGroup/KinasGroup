<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();

// ── Filters ──────────────────────────────────────────────────
$division = $_GET['division'] ?? '';
$period   = $_GET['period']   ?? 'all';

$where = ["c.status = 'flagged'"];
$params = [];
if ($division !== '') {
    $where[] = "(c.division = ? OR c.listing_type = ?)";
    $params[] = $division;
    $params[] = $division;
}
if ($period === 'week')   $where[] = "c.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
if ($period === 'month')  $where[] = "c.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$whereSQL = implode(' AND ', $where);

// ── Union flagged listings across all 4 tables ───────────────
$sql = "
    SELECT 'car' AS type, c.id, c.title, c.price, c.status, c.updated_at, c.city, c.state,
           u.name AS agent_name, u.email AS agent_email,
           (SELECT url FROM listing_images WHERE listing_id = c.id AND listing_type='car' ORDER BY sort_order LIMIT 1) AS image
    FROM car_listings c LEFT JOIN users u ON c.agent_id = u.id
    WHERE c.status = 'flagged'
    UNION ALL
    SELECT 'property', p.id, p.title, p.price, p.status, p.updated_at, p.city, p.state,
           u.name, u.email,
           (SELECT url FROM listing_images WHERE listing_id = p.id AND listing_type='property' ORDER BY sort_order LIMIT 1)
    FROM property_listings p LEFT JOIN users u ON p.agent_id = u.id
    WHERE p.status = 'flagged'
    UNION ALL
    SELECT 'solar', s.id, s.title, s.price, s.status, s.updated_at, s.city, s.state,
           u.name, u.email,
           (SELECT url FROM listing_images WHERE listing_id = s.id AND listing_type='solar' ORDER BY sort_order LIMIT 1)
    FROM solar_listings s LEFT JOIN users u ON s.agent_id = u.id
    WHERE s.status = 'flagged'
    UNION ALL
    SELECT 'marketplace', m.id, m.title, m.price, m.status, m.updated_at, m.city, m.state,
           u.name, u.email,
           (SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type='marketplace' ORDER BY sort_order LIMIT 1)
    FROM marketplace_listings m LEFT JOIN users u ON m.agent_id = u.id
    WHERE m.status = 'flagged'
    ORDER BY updated_at DESC
    LIMIT 100
";
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// If a division filter was set, filter in PHP (unions + params get ugly otherwise)
if ($division !== '') {
    $rows = array_values(array_filter($rows, fn($r) => $r['type'] === $division));
}

// ── Stats ────────────────────────────────────────────────────
$countAll   = (int)$db->query("SELECT
        (SELECT COUNT(*) FROM car_listings WHERE status='flagged') +
        (SELECT COUNT(*) FROM property_listings WHERE status='flagged') +
        (SELECT COUNT(*) FROM solar_listings WHERE status='flagged') +
        (SELECT COUNT(*) FROM marketplace_listings WHERE status='flagged')")->fetchColumn();
$countWeek  = (int)$db->query("SELECT
        (SELECT COUNT(*) FROM car_listings WHERE status='flagged' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) +
        (SELECT COUNT(*) FROM property_listings WHERE status='flagged' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) +
        (SELECT COUNT(*) FROM solar_listings WHERE status='flagged' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) +
        (SELECT COUNT(*) FROM marketplace_listings WHERE status='flagged' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))")->fetchColumn();
$countRemoved = (int)$db->query("SELECT
        (SELECT COUNT(*) FROM car_listings WHERE status='removed') +
        (SELECT COUNT(*) FROM property_listings WHERE status='removed') +
        (SELECT COUNT(*) FROM solar_listings WHERE status='removed') +
        (SELECT COUNT(*) FROM marketplace_listings WHERE status='removed')")->fetchColumn();

// High-priority heuristic: no images = high pri
$countHigh = count(array_filter($rows, fn($r) => empty($r['image'])));

$headerDepth = '../';
$pageTitle = 'Flagged Listings - KINAS GROUP';
require_once __DIR__ . '/../templates/header.php';
?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-main { flex: 1; padding: 30px; background: #F5F7FA; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; margin-bottom: 8px; }
        .page-header p { color: #666; font-size: 14px; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 16px; padding: 25px; text-align: center; border: 1.5px solid #C6A43F; transition: all 0.3s; }
        .stat-card:hover { border-color: #C6A43F; box-shadow: 0 8px 24px rgba(198,164,63,0.15); transform: translateY(-3px); }
        .stat-card.danger .stat-number { color: #DC2626; }
        .stat-number { font-size: 32px; font-weight: 700; color: #C6A43F; font-family: 'Prata', serif; }
        .stat-label { color: #666; font-size: 13px; margin-top: 5px; }
        .filters-bar { background: white; border-radius: 16px; padding: 20px; margin-bottom: 25px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; border: 1px solid #E0E0E0; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #666; }
        .filter-group select, .filter-group input { padding: 10px 12px; border: 1px solid #E0E0E0; border-radius: 8px; font-family: 'Inter', sans-serif; min-width: 150px; }
        .btn-filter { background: #C6A43F; color: #0A0A0A; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-filter:hover { background: #A8882E; }
        .btn-secondary { background: #F5F5F5; color: #333; border: 1px solid #E0E0E0; padding: 10px 16px; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 13px; }
        .table-responsive { overflow-x: auto; }
        .flagged-table { width: 100%; border-collapse: collapse; background: white; border-radius: 20px; overflow: hidden; border: 1px solid #E0E0E0; }
        .flagged-table th { background: #F8F8F8; padding: 15px 20px; text-align: left; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; border-bottom: 1px solid #E0E0E0; }
        .flagged-table td { padding: 16px 20px; border-bottom: 1px solid #E0E0E0; vertical-align: middle; font-size: 13px; }
        .flagged-table tr:last-child td { border-bottom: none; }
        .flagged-table tr:hover { background: #FEFBF5; }
        .division-tag { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        .division-tag.car         { background:#E3F2FD; color:#1565C0; }
        .division-tag.property    { background:#E8F5E9; color:#2E7D32; }
        .division-tag.solar       { background:#FFF3E0; color:#F57C00; }
        .division-tag.marketplace { background:#F3E5F5; color:#7B1FA2; }
        .listing-image { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; background: #F0F0F0; display: flex; align-items: center; justify-content: center; color: #ccc; }
        .listing-image img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
        .btn-review { background: #C6A43F; color: #0A0A0A; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.3s; margin-right: 6px; text-decoration: none; display: inline-block; }
        .btn-review:hover { background: #A8882E; }
        .btn-remove { background: #DC2626; color: white; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-remove:hover { background: #B91C1C; }
        .btn-ignore { background: #666; color: white; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.3s; margin-right: 6px; }
        .btn-ignore:hover { background: #555; }
        .empty-state { padding: 80px 20px; text-align: center; color: #999; }
        .empty-state i { font-size: 48px; color: #2E7D32; margin-bottom: 16px; display: block; opacity: 0.7; }
        .empty-state p { font-size: 14px; }
        .flag-actions { display: flex; flex-wrap: wrap; gap: 6px; }
        @media (max-width: 768px) { .admin-main { padding: 20px; } .filters-bar { flex-direction: column; align-items: stretch; } .filter-group select, .filter-group input { width: 100%; } }
    
/* ============================================================
   DARK MODE — force this page's own styling to stay identical
   to light mode. Auto-generated from every hardcoded
   background/color/border-color rule already on this page.
   ============================================================ */
@media (prefers-color-scheme: dark) {
    body { background: #F5F7FA !important; }
    .admin-main { background: #F5F7FA !important; }
    .page-header h1 { color: #0A0A0A !important; }
    .page-header p { color: #666 !important; }
    .stat-card { background: white !important; }
    .stat-card:hover { border-color: #C6A43F !important; }
    .stat-card.danger .stat-number { color: #DC2626 !important; }
    .stat-number { color: #C6A43F !important; }
    .stat-label { color: #666 !important; }
    .filters-bar { background: white !important; }
    .filter-group label { color: #666 !important; }
    .btn-filter { background: #C6A43F !important; color: #0A0A0A !important; }
    .btn-filter:hover { background: #A8882E !important; }
    .btn-secondary { background: #F5F5F5 !important; color: #333 !important; }
    .flagged-table { background: white !important; }
    .flagged-table th { background: #F8F8F8 !important; color: #666 !important; }
    .flagged-table tr:hover { background: #FEFBF5 !important; }
    .division-tag.car { background: #E3F2FD !important; color: #1565C0 !important; }
    .division-tag.property { background: #E8F5E9 !important; color: #2E7D32 !important; }
    .division-tag.solar { background: #FFF3E0 !important; color: #F57C00 !important; }
    .division-tag.marketplace { background: #F3E5F5 !important; color: #7B1FA2 !important; }
    .listing-image { background: #F0F0F0 !important; color: #ccc !important; }
    .btn-review { background: #C6A43F !important; color: #0A0A0A !important; }
    .btn-review:hover { background: #A8882E !important; }
    .btn-remove { background: #DC2626 !important; color: white !important; }
    .btn-remove:hover { background: #B91C1C !important; }
    .btn-ignore { background: #666 !important; color: white !important; }
    .btn-ignore:hover { background: #555 !important; }
    .empty-state { color: #999 !important; }
    .empty-state i { color: #2E7D32 !important; }
}
</style>
<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main">
    <div class="page-header">
        <h1><i class="fas fa-flag" style="color: #DC2626; margin-right: 10px;"></i>Flagged Listings</h1>
        <p>Review and moderate reported content</p>
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-number"><?= number_format($countAll) ?></div><div class="stat-label">Currently Flagged</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($countWeek) ?></div><div class="stat-label">Flagged This Week</div></div>
        <div class="stat-card danger"><div class="stat-number"><?= number_format($countHigh) ?></div><div class="stat-label">High Priority</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($countRemoved) ?></div><div class="stat-label">Removed Listings</div></div>
    </div>

    <form class="filters-bar" method="GET">
        <div class="filter-group"><label>Division</label>
            <select name="division">
                <option value="">All Divisions</option>
                <option value="car"         <?= $division === 'car'         ? 'selected' : '' ?>>KINAS Automobile</option>
                <option value="property"    <?= $division === 'property'    ? 'selected' : '' ?>>Williams Connect Home</option>
                <option value="solar"       <?= $division === 'solar'       ? 'selected' : '' ?>>KINAS Volt</option>
                <option value="marketplace" <?= $division === 'marketplace' ? 'selected' : '' ?>>KINAS Marketplace</option>
            </select>
        </div>
        <div class="filter-group"><label>Period</label>
            <select name="period">
                <option value="all"   <?= $period === 'all'   ? 'selected' : '' ?>>All time</option>
                <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This month</option>
                <option value="week"  <?= $period === 'week'  ? 'selected' : '' ?>>This week</option>
            </select>
        </div>
        <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
        <a href="flagged-listings.php" class="btn-secondary"><i class="fas fa-undo"></i> Reset</a>
    </form>

    <div class="table-responsive">
        <?php if (empty($rows)): ?>
            <div class="flagged-table" style="text-align:center;">
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>All caught up! No flagged listings matching the current filter.</p>
                </div>
            </div>
        <?php else: ?>
        <table class="flagged-table">
            <thead>
                <tr><th>Listing</th><th>Division</th><th>Status</th><th>Agent</th><th>Last Update</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php
                $divisionLabels = ['car'=>'KINAS Automobile','property'=>'Williams Connect Home','solar'=>'KINAS Volt','marketplace'=>'KINAS Marketplace'];
                foreach ($rows as $r):
                    $detailUrl = '/divisions/kinas-' . str_replace(['car','property','solar','marketplace'],['automobile','williams-connect-home','volt','marketplace'], $r['type']) . '/detail.php?id=' . (int)$r['id'];
            ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="listing-image">
                                <?php if (!empty($r['image'])): ?>
                                    <img src="<?= htmlspecialchars($r['image']) ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-image"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong><a href="<?= $detailUrl ?>" style="color:#0A0A0A; text-decoration:none;"><?= htmlspecialchars($r['title']) ?></a></strong>
                                <br><span style="font-size: 11px; color:#666;">ID: #<?= (int)$r['id'] ?> · ₦<?= number_format((float)$r['price']) ?></span>
                            </div>
                        </div>
                    </td>
                    <td><span class="division-tag <?= htmlspecialchars($r['type']) ?>"><?= htmlspecialchars($divisionLabels[$r['type']] ?? $r['type']) ?></span></td>
                    <td>
                        <span style="color:#DC2626;"><i class="fas fa-flag"></i> Flagged</span>
                        <?php if (empty($r['image'])): ?>
                            <br><span style="font-size:11px; color:#F57C00;"><i class="fas fa-exclamation-triangle"></i> No images</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($r['agent_name'] ?: '—') ?>
                        <br><span style="font-size:11px; color:#666;"><?= htmlspecialchars($r['agent_email'] ?? '') ?></span>
                    </td>
                    <td><?= htmlspecialchars($r['updated_at']) ?></td>
                    <td>
                        <div class="flag-actions">
                            <a href="<?= $detailUrl ?>" target="_blank" class="btn-review"><i class="fas fa-eye"></i> Review</a>
                            <form method="POST" action="/api/admin/review-listing.php" style="display:inline" data-kinas-confirm="Approve this listing? It will be set back to active status." data-kinas-title="Approve Listing" data-kinas-label="Approve" data-kinas-variant="gold" data-kinas-icon="fa-check">
                                <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                <input type="hidden" name="listing_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="listing_type" value="<?= htmlspecialchars($r['type']) ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-ignore" title="Approve and un-flag"><i class="fas fa-check"></i> Approve</button>
                            </form>
                            <form method="POST" action="/api/admin/remove-listing.php" style="display:inline" data-kinas-confirm="Remove this listing? It will be hidden from public view." data-kinas-title="Remove Listing" data-kinas-label="Remove" data-kinas-variant="danger" data-kinas-icon="fa-eye-slash">
                                <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                <input type="hidden" name="listing_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="listing_type" value="<?= htmlspecialchars($r['type']) ?>">
                                <button type="submit" class="btn-remove" title="Remove listing"><i class="fas fa-trash"></i> Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
