<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../includes/kinas-search.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';
$db = Database::getInstance()->getConnection();
$q            = trim($_GET['q'] ?? '');
$brand        = trim($_GET['brand'] ?? '');
$model        = trim($_GET['model'] ?? '');
$min_year     = (int)($_GET['min_year'] ?? 0);
$max_year     = (int)($_GET['max_year'] ?? 0);
$min_price    = trim($_GET['min_price'] ?? '');
$max_price    = trim($_GET['max_price'] ?? '');
$min_mileage  = trim($_GET['min_mileage'] ?? '');
$max_mileage  = trim($_GET['max_mileage'] ?? '');
$transmission = trim($_GET['transmission'] ?? '');
$fuel_type    = trim($_GET['fuel_type'] ?? '');
$body_type    = trim($_GET['body_type'] ?? '');
$drivetrain   = trim($_GET['drivetrain'] ?? '');
$color        = trim($_GET['color'] ?? '');
$condition    = trim($_GET['condition'] ?? '');
$doors        = (int)($_GET['doors'] ?? 0);
$city         = trim($_GET['city'] ?? '');
$state        = trim($_GET['state'] ?? '');
$sort         = $_GET['sort'] ?? 'newest';
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 12;
$tokens = kinas_search_tokens($q);
$hay = "CONCAT_WS(' ', c.title, c.brand, c.model, c.body_type, c.fuel_type, c.transmission, c.condition_status, c.color, c.city, c.state, c.description)";
$where  = ["c.status = 'active'"];
$params = [];
if ($tokens) { [$w, $wp] = kinas_search_where($hay, $tokens); $where[] = $w; $params = array_merge($params, $wp); }
if ($brand !== '')        { $where[] = "c.brand = ?";        $params[] = $brand; }
if ($model !== '')        { $where[] = "c.model LIKE ?";     $params[] = "%$model%"; }
if ($min_year > 0)         { $where[] = "c.year >= ?";       $params[] = $min_year; }
if ($max_year > 0)         { $where[] = "c.year <= ?";       $params[] = $max_year; }
if ($min_price !== '' && is_numeric($min_price)) { $where[] = "c.price >= ?"; $params[] = $min_price; }
if ($max_price !== '' && is_numeric($max_price)) { $where[] = "c.price <= ?"; $params[] = $max_price; }
if ($min_mileage !== '' && is_numeric($min_mileage)) { $where[] = "c.mileage >= ?"; $params[] = $min_mileage; }
if ($max_mileage !== '' && is_numeric($max_mileage)) { $where[] = "c.mileage <= ?"; $params[] = $max_mileage; }
if ($transmission !== '') { $where[] = "c.transmission = ?"; $params[] = $transmission; }
if ($fuel_type !== '')    { $where[] = "c.fuel_type = ?";    $params[] = $fuel_type; }
if ($body_type !== '')    { $where[] = "c.body_type = ?";    $params[] = $body_type; }
if ($drivetrain !== '')   { $where[] = "c.drivetrain = ?";   $params[] = $drivetrain; }
if ($color !== '')        { $where[] = "c.color = ?";        $params[] = $color; }
if ($condition !== '')    { $where[] = "c.condition_status = ?"; $params[] = $condition; }
if ($doors > 0)           { $where[] = "c.doors = ?";        $params[] = $doors; }
if ($city !== '')         { $where[] = "c.city = ?";         $params[] = $city; }
if ($state !== '')        { $where[] = "c.state = ?";        $params[] = $state; }
$whereSql = implode(' AND ', $where);
$stmt = $db->prepare("SELECT c.id, c.title, c.brand, c.model, c.year, c.price, c.mileage, c.transmission, c.fuel_type, c.color, c.body_type, c.drivetrain, c.condition_status, c.featured, c.views, c.created_at, c.city, c.state, c.country, ($hay) AS hay, a.verified as agent_verified, (SELECT url FROM listing_images WHERE listing_id = c.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) AS thumbnail FROM car_listings c LEFT JOIN users a ON c.agent_id = a.id WHERE $whereSql");
$stmt->execute($params);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) { $r['score'] = $tokens ? kinas_search_score((string)$r['title'], (string)($r['hay'] ?? ''), $tokens) : 0; }
unset($r);
usort($rows, function ($a, $b) {
if ($b['score'] !== $a['score']) return $b['score'] - $a['score'];
switch ($GLOBALS['sort'] ?? 'newest') {
case 'price_low': return $a['price'] - $b['price'];
case 'price_high': return $b['price'] - $a['price'];
case 'year_new': return $b['year'] - $a['year'];
case 'year_old': return $a['year'] - $b['year'];
case 'mileage_low': return ($a['mileage'] ?? 0) - ($b['mileage'] ?? 0);
case 'mileage_high': return ($b['mileage'] ?? 0) - ($a['mileage'] ?? 0);
default: return strtotime($b['created_at']) - strtotime($a['created_at']);
}
});
$total = count($rows);
$totalPages = max(1, (int)ceil($total / $per_page));
$page = min($page, $totalPages);
$rows = array_slice($rows, ($page - 1) * $per_page, $per_page);
$facet = function (string $col) use ($db): array { try { return $db->query("SELECT DISTINCT $col AS v FROM car_listings WHERE status='active' AND $col IS NOT NULL AND $col != '' ORDER BY $col")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) { return []; } };
$brands=$facet('brand'); $models=$facet('model'); $years=$facet('year'); $transmissions=$facet('transmission'); $fuel_types=$facet('fuel_type'); $body_types=$facet('body_type'); $drivetrains=$facet('drivetrain'); $colors=$facet('color'); $conditions=$facet('condition_status'); $cities=$facet('city'); $states=$facet('state');
$pageTitle = 'Search Luxury Cars - KINAS Automobile';
$pageDescription = 'Search our inventory of luxury cars, supercars, and exotic vehicles.';
include '../../templates/header.php';
?>
<div class="je-search-page">
<?php
je_render_hero_bar('Luxury Automobiles', 'Search make, model, keyword…', $q, 'search.php', $_GET);
$filters = [
['name' => 'brand', 'label' => 'Brand', 'type' => 'select', 'options' => $brands],
['name' => 'model', 'label' => 'Model', 'type' => 'select', 'options' => $models],
['name' => 'price', 'label' => 'Price', 'type' => 'price'],
['name' => 'year', 'label' => 'Year', 'type' => 'range', 'min_name' => 'min_year', 'max_name' => 'max_year', 'input_type' => 'number', 'min_placeholder' => 'From', 'max_placeholder' => 'To'],
['name' => 'mileage', 'label' => 'Mileage (km)', 'type' => 'range', 'min_name' => 'min_mileage', 'max_name' => 'max_mileage', 'input_type' => 'number', 'min_placeholder' => 'Min', 'max_placeholder' => 'Max'],
['name' => 'transmission', 'label' => 'Transmission', 'type' => 'select', 'options' => $transmissions],
['name' => 'fuel_type', 'label' => 'Fuel Type', 'type' => 'select', 'options' => $fuel_types],
['name' => 'body_type', 'label' => 'Body Type', 'type' => 'select', 'options' => $body_types],
['name' => 'drivetrain', 'label' => 'Drivetrain', 'type' => 'select', 'options' => $drivetrains],
['name' => 'color', 'label' => 'Exterior Color', 'type' => 'select', 'options' => $colors],
['name' => 'condition', 'label' => 'Condition', 'type' => 'select', 'options' => $conditions],
['name' => 'city', 'label' => 'City', 'type' => 'select', 'options' => $cities],
['name' => 'state', 'label' => 'State', 'type' => 'select', 'options' => $states],
];
$current = compact('brand','model','min_year','max_year','min_price','max_price','min_mileage','max_mileage','transmission','fuel_type','body_type','drivetrain','color','condition','city','state');
?>
<div class="je-search-body">
<?php je_render_filter_panel($filters, $current, 'search.php'); ?>
<div class="je-results-panel">
<div class="je-results-topbar">
<div class="je-results-count"><strong><?= number_format($total) ?></strong> <?= $total === 1 ? 'vehicle' : 'vehicles' ?> found<?php if ($q): ?> for "<strong><?= htmlspecialchars($q) ?></strong>"<?php endif; ?></div>
<div class="je-flex" style="gap:16px;">
<button class="je-mobile-filter-btn" onclick="document.getElementById('jeMobileFilter').classList.add('is-open')"><i class="fas fa-sliders-h"></i> Filters</button>
<?php je_render_sort_row(['newest'=>'Newest first','price_low'=>'Price: Low → High','price_high'=>'Price: High → Low','year_new'=>'Year: Newest','year_old'=>'Year: Oldest','mileage_low'=>'Mileage: Low → High','mileage_high'=>'Mileage: High → Low'], $sort, $current, 'search.php'); ?>
</div>
</div>
<?php
$cards = array_map(function ($c) {
$specParts = array_filter([$c['year'] ?? null, ($c['mileage'] ?? null) !== null ? number_format((int)$c['mileage']) . ' km' : null, $c['transmission'] ?? null, $c['fuel_type'] ?? null, $c['body_type'] ?? null]);
$locParts = array_filter([$c['city'] ?? null, $c['state'] ?? null, $c['country'] ?? null]);
return ['id'=>$c['id'],'title'=>trim(($c['brand'] ?? '') . ' ' . ($c['model'] ?? '') . ' ' . ($c['year'] ?? '')),'division'=>'KINAS AUTOMOBILE','price'=>$c['price'],'thumbnail'=>$c['thumbnail'] ?: '','specs'=>implode(' • ', $specParts),'location'=>implode(', ', $locParts),'detail_url'=>'detail.php?id=' . (int)$c['id'],'featured'=>!empty($c['featured']),'verified'=>!empty($c['agent_verified']),'views'=>$c['views'] ?? 0];
}, $rows);
je_render_listing_grid($cards, 'No vehicles match your filters', 'Try widening your price or year range, or clear the filters to see all inventory.', 'search.php');
je_render_pagination($page, $total, $per_page, 'search.php', 'page', $current + ['q' => $q, 'sort' => $sort]);
?>
</div>
</div>
<div class="je-filter-overlay" id="jeMobileFilter" onclick="if(event.target===this) this.classList.remove('is-open')"><div class="je-filter-overlay-inner"><button class="je-filter-overlay-close" onclick="document.getElementById('jeMobileFilter').classList.remove('is-open')">✕</button><?php je_render_filter_panel($filters, $current, 'search.php'); ?></div></div>
</div>
<?php include '../../templates/footer.php'; ?>
