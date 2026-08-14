<?php
/**
* Global Search Page - KINAS GROUP (INTELLIGENT SEARCH)
* Tokenizes the query and matches each word across title/brand/category/
* specs/description/location, ranked by relevance. No exact-phrase needed.
*/
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'includes/helpers.php';
require_once 'includes/kinas-search.php';
require_once 'api/config/database.php';
require_once 'includes/je-components.php';
$db = Database::getInstance()->getConnection();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$division = isset($_GET['division']) ? $_GET['division'] : 'all';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;
$hasFilter = ($query !== '' || $division !== 'all');
$tokens = kinas_search_tokens($query);
$divisionFolders = [
'car' => 'kinas-automobile',
'solar' => 'kinas-volt',
'property' => 'williams-connect-home',
'marketplace' => 'kinas-marketplace'
];
function getListingImage($db, $listingId, $divisionName) {
$tableMap = ['car'=>'car_listings','solar'=>'solar_listings','property'=>'property_listings','marketplace'=>'marketplace_listings'];
$table = $tableMap[$divisionName] ?? '';
if (empty($table)) return null;
try {
$stmt = $db->prepare("SELECT url FROM listing_images WHERE listing_id = ? AND listing_type = ? ORDER BY sort_order LIMIT 1");
$stmt->execute([$listingId, $divisionName]);
$image = $stmt->fetch();
if ($image) return $image['url'];
$stmt2 = $db->prepare("SELECT thumbnail FROM $table WHERE id = ?");
$stmt2->execute([$listingId]);
$thumb = $stmt2->fetch();
return ($thumb && !empty($thumb['thumbnail'])) ? $thumb['thumbnail'] : null;
} catch (Exception $e) { return null; }
}
// Per-division haystacks (the searchable text per listing)
$haystacks = [
'car_listings'        => "CONCAT_WS(' ', t.title, t.brand, t.model, t.body_type, t.fuel_type, t.transmission, t.condition_status, t.color, t.city, t.state, t.description)",
'solar_listings'      => "CONCAT_WS(' ', t.title, t.brand, t.service_type, t.city, t.state, t.description)",
'property_listings'   => "CONCAT_WS(' ', t.title, t.property_type, t.listing_type, t.address, t.city, t.state, t.view_type, t.description)",
'marketplace_listings'=> "CONCAT_WS(' ', t.title, t.brand, mc.name, t.condition_status, t.city, t.state, t.description)",
];
$titles = [
'car_listings' => 't.title', 'solar_listings' => 't.title',
'property_listings' => 't.title', 'marketplace_listings' => 't.title',
];
$divisionsToSearch = [];
if ($division === 'all') {
$divisionsToSearch = ['car_listings'=>'car','solar_listings'=>'solar','property_listings'=>'property','marketplace_listings'=>'marketplace'];
} else {
$tableMap = ['car'=>'car_listings','solar'=>'solar_listings','property'=>'property_listings','marketplace'=>'marketplace_listings'];
if (isset($tableMap[$division])) $divisionsToSearch = [$tableMap[$division] => $division];
}
$allResults = [];
foreach ($divisionsToSearch as $table => $divName) {
$hay = $haystacks[$table];
$titleCol = $titles[$table];
$categoryJoin = ($table === 'marketplace_listings') ? 'LEFT JOIN marketplace_categories mc ON mc.id = t.category_id' : '';
$where = ["t.status = 'active'"];
$params = [];
if ($tokens) {
[$wSql, $wParams] = kinas_search_where($hay, $tokens);
$where[] = $wSql;
$params = array_merge($params, $wParams);
}
$whereSQL = implode(' AND ', $where);
try {
$brandColumn = ($table === 'property_listings') ? 'NULL' : 't.brand';
$stmt = $db->prepare("
SELECT t.id, t.title, t.price, t.status, t.views, t.created_at,
'$divName' as division, $brandColumn as brand, t.city, t.state, ($hay) AS hay
FROM $table t $categoryJoin
WHERE $whereSQL
");
$stmt->execute($params);
foreach ($stmt->fetchAll() as $row) {
$row['image'] = getListingImage($db, $row['id'], $row['division']);
$row['folder'] = $divisionFolders[$row['division']] ?? $row['division'];
$row['score'] = $tokens ? kinas_search_score((string)$row['title'], (string)($row['hay'] ?? ''), $tokens) : 0;
$allResults[] = $row;
}
} catch (Exception $e) {
error_log("Search error for $table: " . $e->getMessage());
}
}
// De-duplicate
$seen = [];
$allResults = array_values(array_filter($allResults, function ($r) use (&$seen) {
$key = $r['division'] . ':' . $r['id'];
if (isset($seen[$key])) return false;
$seen[$key] = true; return true;
}));
// Rank: relevance first, then newest
usort($allResults, function ($a, $b) {
if ($b['score'] !== $a['score']) return $b['score'] - $a['score'];
return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$totalCount = count($allResults);
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$paginatedResults = array_slice($allResults, $offset, $perPage);
// Suggestions when no matches
$suggestions = [];
if ($totalCount === 0 && $hasFilter) {
foreach ($divisionsToSearch as $sTable => $sDiv) {
try {
$categoryJoin = ($sTable === 'marketplace_listings') ? 'LEFT JOIN marketplace_categories mc ON mc.id = t.category_id' : '';
$stmt = $db->prepare("SELECT t.id, t.title, t.price, t.views, t.created_at, '$sDiv' as division FROM $sTable t $categoryJoin WHERE t.status='active' ORDER BY t.views DESC, t.created_at DESC LIMIT 3");
$stmt->execute();
foreach ($stmt->fetchAll() as $row) {
$row['image'] = getListingImage($db, $row['id'], $row['division']);
$row['folder'] = $divisionFolders[$row['division']] ?? $row['division'];
$suggestions[] = $row;
}
} catch (Exception $e) {}
}
$suggestions = array_slice($suggestions, 0, 8);
}
$pageTitle = 'Search Results - KINAS GROUP';
include 'templates/header.php';
?>
<style>
.search-result-card{display:flex;gap:20px;background:#fff;border:1px solid #E0E0E0;border-radius:12px;padding:16px;transition:all .3s ease;margin-bottom:16px;align-items:flex-start}
.search-result-card:hover{border-color:#C6A43F;box-shadow:0 4px 16px rgba(0,0,0,0.08)}
.search-result-image{flex-shrink:0;width:160px;height:120px;border-radius:8px;overflow:hidden;background:#f0f0f0;position:relative}
.search-result-image img{width:100%;height:100%;object-fit:cover}
.search-result-image .placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f0f0f0;color:#999;font-size:32px}
.search-result-content{flex:1;min-width:0}
.search-result-title{font-size:18px;font-weight:600;color:#0A0A0A;text-decoration:none}
.search-result-title:hover{color:#C6A43F}
.search-result-division{display:inline-block;padding:2px 12px;border-radius:20px;font-size:11px;font-weight:600}
.division-car{background:#E3F2FD;color:#0D47A1}.division-solar{background:#FFF3E0;color:#E65100}.division-property{background:#E8F5E9;color:#1B5E20}.division-marketplace{background:#F3E5F5;color:#4A148C}
.search-result-price{font-size:20px;font-weight:700;color:#C6A43F}
.search-result-meta{color:#888;font-size:13px;display:flex;gap:16px;flex-wrap:wrap;margin-top:6px}
.search-result-meta span{display:inline-flex;align-items:center;gap:4px}
.search-result-brand{font-size:13px;color:#666;margin-top:4px}
.search-result-location{font-size:13px;color:#888}
.search-result-actions{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
.search-result-actions .btn-view{display:inline-block;padding:6px 16px;background:#C6A43F;color:#0A0A0A;border-radius:4px;text-decoration:none;font-weight:600;font-size:13px}
.search-result-actions .btn-view:hover{background:#A8882E}
@media(max-width:768px){.search-result-card{flex-direction:column;align-items:stretch}.search-result-image{width:100%;height:180px}}
.status-badge{display:inline-block;padding:2px 10px;border-radius:12px;font-size:10px;font-weight:600}
.status-badge-active{background:#E8F5E9;color:#1B5E20}
.pagination{display:flex;justify-content:center;gap:8px;margin-top:40px;flex-wrap:wrap}
.pagination a,.pagination span{padding:8px 16px;border:1px solid #E0E0E0;border-radius:6px;text-decoration:none;color:#333}
.pagination a:hover{background:#C6A43F;color:#0A0A0A}
.pagination .active{background:#C6A43F;color:#0A0A0A}
.no-results{text-align:center;padding:80px 20px;background:#F8F8F8;border-radius:12px}
.no-results i{font-size:48px;color:#C6A43F;margin-bottom:16px;display:block}
</style>
<div style="max-width: 1400px; margin: 100px auto 40px; padding: 0 40px;">
<h1 style="font-family: 'Prata', serif; font-size: 32px; margin-bottom: 20px;"><i class="fas fa-search" style="color: #C6A43F;"></i> Search Results</h1>
<form method="GET" action="search.php" style="display: flex; gap: 12px; margin-bottom: 30px; flex-wrap: wrap;">
<input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search for cars, properties, solar, products..." style="flex: 1; min-width: 280px; padding: 14px 20px; border: 2px solid #E0E0E0; border-radius: 8px; font-size: 16px; font-family: 'Inter', sans-serif;">
<select name="division" style="padding: 14px 20px; border: 2px solid #E0E0E0; border-radius: 8px; background: #fff; min-width: 150px; font-family: 'Inter', sans-serif;">
<option value="all" <?php echo $division === 'all' ? 'selected' : ''; ?>>All Divisions</option>
<option value="car" <?php echo $division === 'car' ? 'selected' : ''; ?>>🚗 Automobile</option>
<option value="solar" <?php echo $division === 'solar' ? 'selected' : ''; ?>>☀️ Volt</option>
<option value="property" <?php echo $division === 'property' ? 'selected' : ''; ?>>🏠 Homes</option>
<option value="marketplace" <?php echo $division === 'marketplace' ? 'selected' : ''; ?>>🛍️ Marketplace</option>
</select>
<button type="submit" style="padding: 14px 32px; background: #C6A43F; color: #0A0A0A; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif;"><i class="fas fa-search"></i> Search</button>
</form>
<?php if ($hasFilter): ?>
<p style="color: #666; margin-bottom: 30px;">
<?php if ($query): ?><strong><?php echo $totalCount; ?></strong> result<?php echo $totalCount !== 1 ? 's' : ''; ?> for "<strong><?php echo htmlspecialchars($query); ?></strong>"<?php if ($division !== 'all'): ?> in <strong><?php echo ucfirst($division); ?></strong><?php endif; ?>
<?php else: ?>Browsing <strong><?php echo $totalCount; ?></strong> listing<?php echo $totalCount !== 1 ? 's' : ''; ?> in <strong><?php echo $division === 'all' ? 'All Divisions' : ucfirst($division); ?></strong><?php endif; ?>
</p>
<?php if (!empty($paginatedResults)): ?>
<?php foreach ($paginatedResults as $item): ?>
<div class="search-result-card">
<div class="search-result-image">
<?php if (!empty($item['image'])): ?><img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" loading="lazy"><?php else: ?><div class="placeholder"><i class="fas fa-image"></i></div><?php endif; ?>
</div>
<div class="search-result-content">
<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
<div>
<?php $folder = $item['folder']; ?>
<a href="/divisions/<?php echo $folder; ?>/detail.php?id=<?php echo $item['id']; ?>" class="search-result-title"><?php echo htmlspecialchars($item['title']); ?></a>
<div style="margin-top: 4px;">
<span class="search-result-division division-<?php echo $item['division']; ?>"><?php echo ['car'=>'Automobile','solar'=>'Volt','property'=>'Homes','marketplace'=>'Marketplace'][$item['division']] ?? ucfirst($item['division']); ?></span>
</div>
<?php if (!empty($item['brand'])): ?><div class="search-result-brand"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['brand']); ?></div><?php endif; ?>
<?php if (!empty($item['city']) || !empty($item['state'])): ?><div class="search-result-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($item['city'] ?? ''); ?><?php if (!empty($item['city']) && !empty($item['state'])): ?>, <?php endif; ?><?php echo htmlspecialchars($item['state'] ?? ''); ?></div><?php endif; ?>
<div class="search-result-meta"><span><i class="far fa-clock"></i> <?php echo date('M j, Y', strtotime($item['created_at'])); ?></span><span><i class="far fa-eye"></i> <?php echo number_format($item['views'] ?? 0); ?></span><span class="status-badge status-badge-<?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span></div>
</div>
<div style="text-align: right;">
<div class="search-result-price">₦<?php echo number_format($item['price']); ?></div>
<div class="search-result-actions"><a href="/divisions/<?php echo $folder; ?>/detail.php?id=<?php echo $item['id']; ?>" class="btn-view"><i class="fas fa-eye"></i> View Details</a></div>
</div>
</div>
</div>
</div>
<?php endforeach; ?>
<?php if ($totalPages > 1): ?>
<div class="pagination">
<?php if ($page > 1): ?><a href="?q=<?php echo urlencode($query); ?>&division=<?php echo $division; ?>&page=<?php echo $page - 1; ?>">‹ Previous</a><?php endif; ?>
<?php for ($i = 1; $i <= $totalPages; $i++): ?><?php if ($i == $page): ?><span class="active"><?php echo $i; ?></span><?php else: ?><a href="?q=<?php echo urlencode($query); ?>&division=<?php echo $division; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a><?php endif; ?><?php endfor; ?>
<?php if ($page < $totalPages): ?><a href="?q=<?php echo urlencode($query); ?>&division=<?php echo $division; ?>&page=<?php echo $page + 1; ?>">Next ›</a><?php endif; ?>
</div>
<?php endif; ?>
<?php else: ?>
<div class="no-results"><i class="fas fa-search"></i><h3>No results found</h3><p style="color: #666;">We couldn't find anything matching "<?php echo htmlspecialchars($query); ?>". Try different keywords.</p><a href="search.php" style="color: #C6A43F; text-decoration: none; font-weight: 600;">Clear all filters</a></div>
<?php if (!empty($suggestions)): ?>
<div style="margin-top:32px;"><h3 style="font-size:16px;color:#333;margin-bottom:16px;">You might like these</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">
<?php foreach ($suggestions as $sug): ?>
<a href="/divisions/<?php echo $sug['folder']; ?>/detail.php?id=<?php echo (int)$sug['id']; ?>" style="display:block;border:1px solid #eee;border-radius:8px;overflow:hidden;text-decoration:none;color:inherit;">
<div style="height:130px;background:#f5f5f5;"><?php if (!empty($sug['image'])): ?><img src="<?php echo htmlspecialchars($sug['image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;" loading="lazy"><?php endif; ?></div>
<div style="padding:10px 12px;"><div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($sug['title']); ?></div><?php if (!empty($sug['price'])): ?><div style="font-size:13px;color:#C6A43F;font-weight:700;margin-top:4px;">₦<?php echo number_format((float)$sug['price']); ?></div><?php endif; ?></div>
</a>
<?php endforeach; ?>
</div></div>
<?php endif; ?>
<?php endif; ?>
<?php else: ?>
<div style="background: #F8F8F8; padding: 80px 20px; text-align: center; border-radius: 12px;"><i class="fas fa-search" style="font-size: 48px; color: #C6A43F; margin-bottom: 16px; display: block;"></i><h3>Search for anything on KINAS GROUP</h3><p style="color: #666; max-width: 500px; margin: 0 auto;">Enter keywords above to search across all divisions.</p></div>
<?php endif; ?>
</div>
<?php include 'templates/footer.php'; ?>
