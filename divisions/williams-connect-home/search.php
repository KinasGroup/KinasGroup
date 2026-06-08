<?php
/**
 * WILLIAMS CONNECT HOME — Real Estate Search
 * JE-style with full multivar filters.
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

$db = Database::getInstance()->getConnection();

$q          = trim($_GET['q'] ?? '');
$property_type = trim($_GET['property_type'] ?? '');
$listing_purpose = trim($_GET['listing_type'] ?? '');  // sale | rent
$min_price  = trim($_GET['min_price'] ?? '');
$max_price  = trim($_GET['max_price'] ?? '');
$beds       = (int)($_GET['beds'] ?? 0);
$baths      = (int)($_GET['baths'] ?? 0);
$min_sqft   = (int)($_GET['min_sqft'] ?? 0);
$max_sqft   = (int)($_GET['max_sqft'] ?? 0);
$min_year_built = (int)($_GET['min_year_built'] ?? 0);
$max_year_built = (int)($_GET['max_year_built'] ?? 0);
$view_type  = trim($_GET['view_type'] ?? '');
$city       = trim($_GET['city'] ?? '');
$state      = trim($_GET['state'] ?? '');
$sort       = $_GET['sort'] ?? 'newest';
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 12;
$offset     = ($page - 1) * $per_page;

$where  = ["p.status = 'active'"];
$params = [];
if ($q !== '') {
    $where[] = "(p.title LIKE ? OR p.description LIKE ? OR p.address LIKE ? OR p.city LIKE ?)";
    $like = "%$q%"; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($property_type !== '') { $where[] = "p.property_type = ?"; $params[] = $property_type; }
if ($listing_purpose !== '' && in_array($listing_purpose, ['sale','rent'], true)) { $where[] = "p.listing_type = ?"; $params[] = $listing_purpose; }
if ($min_price !== '' && is_numeric($min_price)) { $where[] = "p.price >= ?"; $params[] = $min_price; }
if ($max_price !== '' && is_numeric($max_price)) { $where[] = "p.price <= ?"; $params[] = $max_price; }
if ($beds > 0)            { $where[] = "p.beds >= ?";  $params[] = $beds; }
if ($baths > 0)           { $where[] = "p.baths >= ?"; $params[] = $baths; }
if ($min_sqft > 0)        { $where[] = "p.sqft >= ?";  $params[] = $min_sqft; }
if ($max_sqft > 0)        { $where[] = "p.sqft <= ?";  $params[] = $max_sqft; }
if ($min_year_built > 0)  { $where[] = "p.year_built >= ?"; $params[] = $min_year_built; }
if ($max_year_built > 0)  { $where[] = "p.year_built <= ?"; $params[] = $max_year_built; }
if ($view_type !== '')    { $where[] = "p.view_type = ?"; $params[] = $view_type; }
if ($city !== '')         { $where[] = "p.city = ?";   $params[] = $city; }
if ($state !== '')        { $where[] = "p.state = ?";  $params[] = $state; }
$whereSql = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM property_listings p WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $per_page));
$page = min($page, $totalPages);
$offset = ($page - 1) * $per_page;

$orderBy = match ($sort) {
    'price_low'  => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'sqft_high'  => 'p.sqft DESC',
    'sqft_low'   => 'p.sqft ASC',
    'beds_high'  => 'p.beds DESC',
    'newest'     => 'p.featured DESC, p.created_at DESC',
    default      => 'p.featured DESC, p.created_at DESC',
};

$sql = "
    SELECT p.id, p.title, p.property_type, p.listing_type, p.price,
           p.beds, p.baths, p.sqft, p.lot_size, p.year_built,
           p.city, p.state, p.country, p.view_type, p.featured, p.views,
           a.verified as agent_verified,
           (SELECT url FROM listing_images WHERE listing_id = p.id AND listing_type = 'property' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM property_listings p
    LEFT JOIN users a ON p.agent_id = a.id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $per_page OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$facet = function (string $col) use ($db): array {
    try {
        return $db->query("SELECT DISTINCT $col AS v FROM property_listings WHERE status='active' AND $col IS NOT NULL AND $col != '' ORDER BY $col")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { return []; }
};
$property_types = $facet('property_type');
$view_types     = $facet('view_type');
$cities         = $facet('city');
$states         = $facet('state');

$pageTitle = 'Search Luxury Properties - Williams Connect Home';
$pageDescription = 'Search luxury homes, villas, penthouses, and estates. Filter by price, beds, baths, square feet, year built, view type, location and more.';

include '../../templates/header.php';
?>

<div class="je-search-page">

<?php
je_render_hero_bar('Luxury Properties', 'Search city, neighborhood, keyword…', $q, 'search.php', $_GET);

$filters = [
    ['name' => 'listing_type',   'label' => 'For Sale / Rent', 'type' => 'select', 'options' => ['sale' => 'For Sale', 'rent' => 'For Rent']],
    ['name' => 'property_type',  'label' => 'Property Type',   'type' => 'select', 'options' => $property_types],
    ['name' => 'price',          'label' => 'Price',           'type' => 'price'],
    ['name' => 'beds',           'label' => 'Bedrooms (min)',  'type' => 'select', 'options' => [1,2,3,4,5,6]],
    ['name' => 'baths',          'label' => 'Bathrooms (min)', 'type' => 'select', 'options' => [1,2,3,4,5]],
    ['name' => 'sqft',           'label' => 'Square Feet',     'type' => 'range',
     'min_name' => 'min_sqft', 'max_name' => 'max_sqft', 'input_type' => 'number',
     'min_placeholder' => 'Min', 'max_placeholder' => 'Max'],
    ['name' => 'year_built',     'label' => 'Year Built',      'type' => 'range',
     'min_name' => 'min_year_built', 'max_name' => 'max_year_built', 'input_type' => 'number',
     'min_placeholder' => 'From', 'max_placeholder' => 'To'],
    ['name' => 'view_type',      'label' => 'View Type',       'type' => 'select', 'options' => $view_types],
    ['name' => 'city',           'label' => 'City',            'type' => 'select', 'options' => $cities],
    ['name' => 'state',          'label' => 'State',           'type' => 'select', 'options' => $states],
];

$current = compact('property_type','listing_type','min_price','max_price','beds','baths','min_sqft','max_sqft',
    'min_year_built','max_year_built','view_type','city','state');
?>

<div class="je-search-body">
    <?php je_render_filter_panel($filters, $current, 'search.php'); ?>

    <div class="je-results-panel">
        <div class="je-results-topbar">
            <div class="je-results-count">
                <strong><?= number_format($total) ?></strong> <?= $total === 1 ? 'property' : 'properties' ?> found
                <?php if ($q): ?>for "<strong><?= htmlspecialchars($q) ?></strong>"<?php endif; ?>
            </div>
            <div class="je-flex" style="gap:16px;">
                <button class="je-mobile-filter-btn" onclick="document.getElementById('jeMobileFilter').classList.add('is-open')">
                    <i class="fas fa-sliders-h"></i> Filters
                </button>
                <?php
                $sortOptions = [
                    'newest'    => 'Newest first',
                    'price_low' => 'Price: Low → High',
                    'price_high'=> 'Price: High → Low',
                    'sqft_high' => 'Largest first',
                    'beds_high' => 'Most bedrooms',
                ];
                je_render_sort_row($sortOptions, $sort, $current, 'search.php');
                ?>
            </div>
        </div>

        <?php
        $cards = array_map(function ($p) {
            $specParts = array_filter([
                ($p['beds'] ?? null) !== null ? (int)$p['beds'] . ' bd' : null,
                ($p['baths'] ?? null) !== null ? (int)$p['baths'] . ' ba' : null,
                ($p['sqft'] ?? null) !== null ? number_format((int)$p['sqft']) . ' sqft' : null,
                $p['property_type'] ?? null,
                $p['listing_type'] === 'rent' ? 'For Rent' : null,
            ]);
            $locParts = array_filter([$p['city'] ?? null, $p['state'] ?? null, $p['country'] ?? null]);
            return [
                'id'         => $p['id'],
                'title'      => $p['title'] ?? '',
                'division'   => 'WILLIAMS CONNECT HOME',
                'price'      => $p['price'],
                'thumbnail'  => $p['thumbnail'] ?: '',
                'specs'      => implode(' • ', $specParts),
                'location'   => implode(', ', $locParts),
                'detail_url' => 'detail.php?id=' . (int)$p['id'],
                'featured'   => !empty($p['featured']),
                'verified'   => !empty($p['agent_verified']),
                'views'      => $p['views'] ?? 0,
            ];
        }, $rows);

        je_render_listing_grid($cards, 'No properties match your filters', 'Try widening your price range, beds/baths, or city.', 'search.php');

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
