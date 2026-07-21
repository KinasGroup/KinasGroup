<?php
/**
 * KINAS VOLT — Solar / Energy Search
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

$db = Database::getInstance()->getConnection();

$q              = trim($_GET['q'] ?? '');
$service_type   = trim($_GET['service_type'] ?? '');
$brand          = trim($_GET['brand'] ?? '');
$min_capacity   = (float)($_GET['min_capacity'] ?? 0);
$max_capacity   = (float)($_GET['max_capacity'] ?? 0);
$min_warranty   = (int)($_GET['min_warranty'] ?? 0);
$min_price      = trim($_GET['min_price'] ?? '');
$max_price      = trim($_GET['max_price'] ?? '');
$city           = trim($_GET['city'] ?? '');
$state          = trim($_GET['state'] ?? '');
$sort           = $_GET['sort'] ?? 'newest';
$page           = max(1, (int)($_GET['page'] ?? 1));
$per_page       = 12;
$offset         = ($page - 1) * $per_page;

$where  = ["s.status = 'active'"];
$params = [];
if ($q !== '') {
    $where[] = "(s.title LIKE ? OR s.description LIKE ? OR s.brand LIKE ?)";
    $like = "%$q%"; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($service_type !== '' && in_array($service_type, ['residential','commercial','industrial','maintenance','financing'], true)) {
    $where[] = "s.service_type = ?"; $params[] = $service_type;
}
if ($brand !== '')        { $where[] = "s.brand = ?";            $params[] = $brand; }
if ($min_capacity > 0)    { $where[] = "s.capacity_kw >= ?";     $params[] = $min_capacity; }
if ($max_capacity > 0)    { $where[] = "s.capacity_kw <= ?";     $params[] = $max_capacity; }
if ($min_warranty > 0)    { $where[] = "s.warranty_years >= ?";  $params[] = $min_warranty; }
if ($min_price !== '' && is_numeric($min_price)) { $where[] = "s.price >= ?"; $params[] = $min_price; }
if ($max_price !== '' && is_numeric($max_price)) { $where[] = "s.price <= ?"; $params[] = $max_price; }
if ($city !== '')         { $where[] = "s.city = ?";  $params[] = $city; }
if ($state !== '')        { $where[] = "s.state = ?"; $params[] = $state; }
$whereSql = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM solar_listings s WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $per_page));
$page = min($page, $totalPages);
$offset = ($page - 1) * $per_page;

$orderBy = match ($sort) {
    'price_low'  => 's.price ASC',
    'price_high' => 's.price DESC',
    'capacity_high' => 's.capacity_kw DESC',
    'warranty_high' => 's.warranty_years DESC',
    default      => 's.created_at DESC',
};

$sql = "
    SELECT s.id, s.title, s.service_type, s.price, s.brand,
           s.capacity_kw, s.warranty_years, s.city, s.state, s.country, s.views,
           a.verified as agent_verified,
           (SELECT url FROM listing_images WHERE listing_id = s.id AND listing_type = 'solar' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM solar_listings s
    LEFT JOIN users a ON s.agent_id = a.id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $per_page OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$facet = function (string $col) use ($db): array {
    try {
        return $db->query("SELECT DISTINCT $col AS v FROM solar_listings WHERE status='active' AND $col IS NOT NULL AND $col != '' ORDER BY $col")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { return []; }
};
$brands = $facet('brand');
$cities = $facet('city');
$states = $facet('state');

$pageTitle = 'Search Solar & Energy Solutions - KINAS Volt';
$pageDescription = 'Search solar panels, inverters, batteries, and energy services. Filter by service type, brand, capacity, warranty, location and more.';

include '../../templates/header.php';
?>

<div class="je-search-page">

<?php
je_render_hero_bar('Solar & Energy', 'Search brand, system type, keyword…', $q, 'search.php', $_GET);

$filters = [
    ['name' => 'service_type', 'label' => 'Service Type', 'type' => 'select', 'options' => [
        'residential' => 'Residential', 'commercial' => 'Commercial',
        'industrial'  => 'Industrial',  'maintenance' => 'Maintenance',
        'financing'   => 'Financing',
    ]],
    ['name' => 'brand',        'label' => 'Brand',        'type' => 'select', 'options' => $brands],
    ['name' => 'price',        'label' => 'Price',        'type' => 'price'],
    ['name' => 'capacity',     'label' => 'Capacity (kW)','type' => 'range',
     'min_name' => 'min_capacity', 'max_name' => 'max_capacity', 'input_type' => 'number',
     'min_placeholder' => 'Min kW', 'max_placeholder' => 'Max kW'],
    ['name' => 'warranty',     'label' => 'Warranty (years, min)', 'type' => 'select', 'options' => [1,2,5,10,15,20,25]],
    ['name' => 'city',         'label' => 'City',         'type' => 'select', 'options' => $cities],
    ['name' => 'state',        'label' => 'State',        'type' => 'select', 'options' => $states],
];

$current = compact('service_type','brand','min_price','max_price','min_capacity','max_capacity','min_warranty','city','state');
?>

<div class="je-search-body">
    <?php je_render_filter_panel($filters, $current, 'search.php'); ?>

    <div class="je-results-panel">
        <div class="je-results-topbar">
            <div class="je-results-count">
                <strong><?= number_format($total) ?></strong> <?= $total === 1 ? 'system' : 'systems' ?> found
                <?php if ($q): ?>for "<strong><?= htmlspecialchars($q) ?></strong>"<?php endif; ?>
            </div>
            <div class="je-flex" style="gap:16px;">
                <button class="je-mobile-filter-btn" onclick="document.getElementById('jeMobileFilter').classList.add('is-open')">
                    <i class="fas fa-sliders-h"></i> Filters
                </button>
                <?php
                $sortOptions = [
                    'newest'        => 'Newest first',
                    'price_low'     => 'Price: Low → High',
                    'price_high'    => 'Price: High → Low',
                    'capacity_high' => 'Largest capacity',
                    'warranty_high' => 'Longest warranty',
                ];
                je_render_sort_row($sortOptions, $sort, $current, 'search.php');
                ?>
            </div>
        </div>

        <?php
        $cards = array_map(function ($s) {
            $specParts = array_filter([
                $s['service_type'] ?? null,
                ($s['capacity_kw'] ?? null) !== null ? rtrim(rtrim(number_format((float)$s['capacity_kw'], 2), '0'), '.') . ' kW' : null,
                ($s['warranty_years'] ?? null) !== null ? $s['warranty_years'] . '-yr warranty' : null,
                $s['brand'] ?? null,
            ]);
            $locParts = array_filter([$s['city'] ?? null, $s['state'] ?? null, $s['country'] ?? null]);
            return [
                'id'         => $s['id'],
                'title'      => $s['title'] ?? '',
                'division'   => 'KINAS VOLT',
                'price'      => $s['price'],
                'thumbnail'  => $s['thumbnail'] ?: '',
                'specs'      => implode(' • ', array_map('ucfirst', $specParts)),
                'location'   => implode(', ', $locParts),
                'detail_url' => 'detail.php?id=' . (int)$s['id'],
                'featured'   => false,
                'verified'   => !empty($s['agent_verified']),
                'views'      => $s['views'] ?? 0,
            ];
        }, $rows);

        je_render_listing_grid($cards, 'No solar systems match your filters', 'Try clearing some filters or expanding the capacity range.', 'search.php');

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
