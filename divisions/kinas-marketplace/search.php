<?php
/**
* KINAS MARKETPLACE — Curated Goods Search (INTELLIGENT SEARCH)
* Tokenized matching: every word of the query must appear somewhere in
* the listing (title / description / brand / category / condition /
* city / state), with singular/plural tolerance, ranked by relevance.
*/
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';
$db = Database::getInstance()->getConnection();

$q         = trim($_GET['q'] ?? '');
$category  = (int)($_GET['category'] ?? 0);
$brand     = trim($_GET['brand'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$min_price = trim($_GET['min_price'] ?? '');
$max_price = trim($_GET['max_price'] ?? '');
$city      = trim($_GET['city'] ?? '');
$state     = trim($_GET['state'] ?? '');
$sort      = $_GET['sort'] ?? 'newest';
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 12;
$offset    = ($page - 1) * $per_page;

// ============================================
// INTELLIGENT TOKENIZED SEARCH
// ============================================
$where  = ["m.status = 'active'"];
$whereParams = [];
$tokens = [];
if ($q !== '') {
    $tokens = preg_split('/[^a-z0-9]+/i', $q, -1, PREG_SPLIT_NO_EMPTY);
    $tokens = array_values(array_unique(array_map('strtolower', $tokens)));
    $tokens = array_values(array_filter($tokens, function ($t) { return strlen($t) >= 2; }));
    if (!empty($tokens)) {
        $fields = ['m.title', 'm.description', 'm.brand', 'm.condition_status', 'm.city', 'm.state', 'mc.name'];
        $tokenConds = [];
        foreach ($tokens as $t) {
            $variants = ['%' . $t . '%'];
            if (strlen($t) > 3 && substr($t, -1) === 's') {
                $variants[] = '%' . substr($t, 0, -1) . '%';
            }
            $conds = [];
            foreach ($variants as $v) {
                foreach ($fields as $f) {
                    $conds[] = "$f LIKE ?";
                    $whereParams[] = $v;
                }
            }
            $tokenConds[] = '(' . implode(' OR ', $conds) . ')';
        }
        $where[] = implode(' AND ', $tokenConds);
    }
}

if ($category > 0)         { $where[] = "m.category_id = ?";      $whereParams[] = $category; }
if ($brand !== '')         { $where[] = "m.brand = ?";            $whereParams[] = $brand; }
if ($condition !== '')     { $where[] = "m.condition_status = ?"; $whereParams[] = $condition; }
if ($min_price !== '' && is_numeric($min_price)) { $where[] = "m.price >= ?"; $whereParams[] = $min_price; }
if ($max_price !== '' && is_numeric($max_price)) { $where[] = "m.price <= ?"; $whereParams[] = $max_price; }
if ($city !== '')          { $where[] = "m.city = ?";  $whereParams[] = $city; }
if ($state !== '')         { $where[] = "m.state = ?"; $whereParams[] = $state; }
$whereSql = implode(' AND ', $where);

// Relevance ranking: exact title match > title prefix > title contains first token > rest.
$firstToken = $tokens[0] ?? $q;
$relevanceSQL = "
CASE
  WHEN m.title = ? THEN 0
  WHEN m.title LIKE ? THEN 1
  WHEN m.title LIKE ? THEN 2
  ELSE 3
END
";
$relevanceParams = [$q, $q . '%', '%' . $firstToken . '%'];

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM marketplace_listings m LEFT JOIN marketplace_categories mc ON mc.id = m.category_id WHERE $whereSql");
$countStmt->execute($whereParams);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $per_page));
$page = min($page, $totalPages);
$offset = ($page - 1) * $per_page;

$orderBy = match ($sort) {
'price_low'  => 'm.price ASC',
'price_high' => 'm.price DESC',
default      => "$relevanceSQL ASC, m.featured DESC, m.created_at DESC",
};
$selectParams = ($sort === 'price_low' || $sort === 'price_high')
? $whereParams
: array_merge($relevanceParams, $whereParams);

$sql = "
SELECT m.id, m.title, m.category_id, m.price, m.brand, m.condition_status,
m.city, m.state, m.country, m.featured, m.views,
c.name AS category_name, c.slug AS category_slug,
a.verified AS agent_verified,
(SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
FROM marketplace_listings m
LEFT JOIN marketplace_categories c ON m.category_id = c.id
LEFT JOIN users a ON m.agent_id = a.id
WHERE $whereSql
ORDER BY $orderBy
LIMIT $per_page OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($selectParams);
$rows = $stmt->fetchAll();

// Categories + facets
$categories = $db->query("SELECT id, name FROM marketplace_categories ORDER BY name")->fetchAll();
$facet = function (string $col) use ($db): array {
try {
return $db->query("SELECT DISTINCT $col AS v FROM marketplace_listings WHERE status='active' AND $col IS NOT NULL AND $col != '' ORDER BY $col")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { return []; }
};
$brands     = $facet('brand');
$conditions = $facet('condition_status');
$cities     = $facet('city');
$states     = $facet('state');

$pageTitle = 'Search Curated Goods - KINAS Marketplace';
$pageDescription = 'Browse luxury watches, jewelry, art, fashion, and more. Filter by category, brand, condition, price, location and more.';
include '../../templates/header.php';
?>
<div class="je-search-page">
<?php
je_render_hero_bar('Curated Marketplace', 'Search brand, item, category…', $q, 'search.php', $_GET);
$catOptions = [];
foreach ($categories as $c) $catOptions[$c['id']] = $c['name'];
$filters = [
['name' => 'category',  'label' => 'Category',  'type' => 'select', 'options' => $catOptions],
['name' => 'brand',     'label' => 'Brand',     'type' => 'select', 'options' => $brands],
['name' => 'price',     'label' => 'Price',     'type' => 'price'],
['name' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => $conditions],
['name' => 'city',      'label' => 'City',      'type' => 'select', 'options' => $cities],
['name' => 'state',     'label' => 'State',     'type' => 'select', 'options' => $states],
];
$current = compact('category','brand','condition','min_price','max_price','city','state');
?>
<div class="je-search-body">
<?php je_render_filter_panel($filters, $current, 'search.php'); ?>
<div class="je-results-panel">
<div class="je-results-topbar">
<div class="je-results-count">
<strong><?= number_format($total) ?></strong> <?= $total === 1 ? 'item' : 'items' ?> found
<?php if ($q): ?>for "<strong><?= htmlspecialchars($q) ?></strong>"<?php endif; ?>
</div>
<div class="je-flex" style="gap:16px;">
<button class="je-mobile-filter-btn" onclick="document.getElementById('jeMobileFilter').classList.add('is-open')">
<i class="fas fa-sliders-h"></i> Filters
</button>
<?php
$sortOptions = [
'newest'    => 'Best match / Newest',
'price_low' => 'Price: Low → High',
'price_high'=> 'Price: High → Low',
];
je_render_sort_row($sortOptions, $sort, $current, 'search.php');
?>
</div>
</div>
<?php
$cards = array_map(function ($r) {
$specParts = array_filter([
$r['category_name'] ?? null,
$r['brand'] ?? null,
$r['condition_status'] ?? null,
]);
$locParts = array_filter([$r['city'] ?? null, $r['state'] ?? null, $r['country'] ?? null]);
return [
'id'         => $r['id'],
'title'      => $r['title'] ?? '',
'division'   => 'KINAS MARKETPLACE',
'price'      => marketplaceBuyerPrice((float)$r['price']),
'thumbnail'  => $r['thumbnail'] ?: '',
'specs'      => implode(' • ', array_map('ucfirst', $specParts)),
'location'   => implode(', ', $locParts),
'detail_url' => 'detail.php?id=' . (int)$r['id'],
'featured'   => !empty($r['featured']),
'verified'   => !empty($r['agent_verified']),
'views'      => $r['views'] ?? 0,
];
}, $rows);
je_render_listing_grid($cards, 'No items match your filters', 'Try a different category, brand, or price range.', 'search.php');
je_render_pagination($page, $total, $per_page, 'search.php', 'page', $current + ['q' => $q, 'sort' => $sort]);
?>
</div>
</div>
<div class="je-filter-overlay" id="jeMobileFilter" onclick="if(event.target===this) this.classList.remove('is-open')">
<div class="je-filter-overlay-inner">
<button class="je-filter-overlay-close" onclick="document.getElementById('jeMobileFilter').classList.remove('is-open')">✕</button>
<?php je_render_filter_panel($filters, $current, 'search.php'); ?>
</div>
</div>
</div>
<?php include '../../templates/footer.php'; ?>
