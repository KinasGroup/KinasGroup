<?php
/**
* Unified Detail Page - KINAS GROUP
* Displays details for any listing across all divisions
* Includes "You Might Also Like" and "Also Viewed" sections
* FIXED: Uses je_render_listing_grid() for consistent card rendering
* ADDED: Product review section
*/
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';
require_once '../api/config/database.php';
require_once '../includes/je-components.php';
require_once '../includes/security.php';
$db = Database::getInstance()->getConnection();

// Get the listing ID and division from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$division = isset($_GET['division']) ? $_GET['division'] : '';

if (!$id || !$division) {
header('Location: /search.php?error=Invalid listing');
exit;
}

// Map division to table and detail view
$divisionMap = [
'car' => [
'table' => 'car_listings',
'title' => 'KINAS Automobile',
'icon' => '🚗',
'folder' => 'kinas-automobile',
'fields' => ['brand', 'model', 'year', 'mileage', 'transmission', 'fuel_type', 'body_type', 'color']
],
'solar' => [
'table' => 'solar_listings',
'title' => 'KINAS Volt',
'icon' => '☀️',
'folder' => 'kinas-volt',
'fields' => ['brand', 'service_type', 'capacity_kw', 'warranty_years']
],
'property' => [
'table' => 'property_listings',
'title' => 'Williams Connect Home',
'icon' => '🏠',
'folder' => 'williams-connect-home',
'fields' => ['beds', 'baths', 'sqft', 'property_type', 'year_built']
],
'marketplace' => [
'table' => 'marketplace_listings',
'title' => 'KINAS Marketplace',
'icon' => '🛍️',
'folder' => 'kinas-marketplace',
'fields' => ['category', 'brand', 'condition', 'weight', 'dimensions']
]
];

if (!isset($divisionMap[$division])) {
header('Location: /search.php?error=Invalid division');
exit;
}

$config = $divisionMap[$division];
$table = $config['table'];

// Get the listing details.
// Allow sold/rented/completed listings to remain visible so verified
// purchasers can still leave reviews after the transaction completes.
$stmt = $db->prepare("
SELECT *
FROM $table
WHERE id = ?
  AND status IN ('active','sold','rented','completed','under_offer','pending_sale')
");
$stmt->execute([$id]);
$listing = $stmt->fetch();

if (!$listing) {
header('Location: /search.php?error=Listing not found');
exit;
}

// Get images for this listing
$images = $db->prepare("
SELECT url FROM listing_images
WHERE listing_id = ? AND listing_type = ?
ORDER BY sort_order
");
$images->execute([$id, $division]);
$images = $images->fetchAll();

// Increment view count
$updateView = $db->prepare("UPDATE $table SET views = views + 1 WHERE id = ?");
$updateView->execute([$id]);

// ============================================================
// YOU MIGHT ALSO LIKE - Similar listings in same division
// ============================================================
$similarStmt = $db->prepare("
SELECT id, title, price,
(SELECT url FROM listing_images WHERE listing_id = t.id AND listing_type = ? ORDER BY sort_order LIMIT 1) as thumbnail
FROM $table t
WHERE status = 'active' AND id != ?
ORDER BY RAND()
LIMIT 4
");
$similarStmt->execute([$division, $id]);
$similarListings = $similarStmt->fetchAll();

// ============================================================
// ALSO VIEWED - Track and show recently viewed listings
// ============================================================
if (!isset($_SESSION['recently_viewed'])) {
$_SESSION['recently_viewed'] = [];
}

$recentlyViewed = $_SESSION['recently_viewed'];

// Remove duplicate if exists
$recentlyViewed = array_filter($recentlyViewed, function($item) use ($id, $division) {
return !($item['id'] == $id && $item['division'] == $division);
});

// Add current listing at the beginning
array_unshift($recentlyViewed, ['id' => $id, 'division' => $division, 'title' => $listing['title']]);

// Keep only last 5
$_SESSION['recently_viewed'] = array_slice($recentlyViewed, 0, 5);

// Get the actual listings data for recently viewed
$recentListings = [];

if (!empty($_SESSION['recently_viewed'])) {
foreach ($_SESSION['recently_viewed'] as $viewed) {
// Skip the current listing
if ($viewed['id'] == $id && $viewed['division'] == $division) continue;

$viewDiv = $viewed['division'];

if (isset($divisionMap[$viewDiv])) {
$viewTable = $divisionMap[$viewDiv]['table'];

$stmt2 = $db->prepare("
SELECT id, title, price,
(SELECT url FROM listing_images WHERE listing_id = t.id AND listing_type = ? ORDER BY sort_order LIMIT 1) as thumbnail
FROM $viewTable t
WHERE id = ? AND status = 'active'
");
$stmt2->execute([$viewDiv, $viewed['id']]);
$result = $stmt2->fetch();

if ($result) {
$result['division'] = $viewDiv;
$recentListings[] = $result;
}
}
}
}

$pageTitle = $listing['title'] . ' - ' . $config['title'];
$pageDescription = !empty($listing['description'])
? substr(strip_tags($listing['description']), 0, 160)
: $listing['title'] . ' on ' . $config['title'] . '.';

if (!empty($images[0]['url'])) {
$pageImage = $images[0]['url'];
}

include '../templates/header.php';
?>
<style>
.detail-container {
max-width: 1400px;
margin: 100px auto 40px;
padding: 0 40px;
}
.detail-breadcrumb {
font-size: 13px;
color: #888;
margin-bottom: 24px;
}
.detail-breadcrumb a {
color: #C6A43F;
text-decoration: none;
}
.detail-breadcrumb a:hover {
text-decoration: underline;
}
.detail-grid {
display: grid;
grid-template-columns: 1fr 1fr;
gap: 40px;
}
.detail-gallery {
position: relative;
}
.detail-gallery .main-image {
width: 100%;
height: 400px;
border-radius: 12px;
overflow: hidden;
background: #f0f0f0;
display: flex;
align-items: center;
justify-content: center;
}
.detail-gallery .main-image img {
width: 100%;
height: 100%;
object-fit: cover;
}
.detail-gallery .main-image .placeholder {
font-size: 64px;
color: #ccc;
}
.detail-gallery .thumbnails {
display: flex;
gap: 8px;
margin-top: 12px;
overflow-x: auto;
padding-bottom: 4px;
}
.detail-gallery .thumbnails img {
width: 80px;
height: 60px;
object-fit: cover;
border-radius: 6px;
cursor: pointer;
border: 2px solid transparent;
transition: all 0.2s;
}
.detail-gallery .thumbnails img:hover,
.detail-gallery .thumbnails img.active {
border-color: #C6A43F;
}
.detail-info h1 {
font-family: 'Prata', serif;
font-size: 32px;
color: #0A0A0A;
margin-bottom: 8px;
}
.detail-info .division-badge {
display: inline-block;
padding: 4px 16px;
border-radius: 20px;
font-size: 13px;
font-weight: 600;
}
.division-badge-car { background: #E3F2FD; color: #0D47A1; }
.division-badge-solar { background: #FFF3E0; color: #E65100; }
.division-badge-property { background: #E8F5E9; color: #1B5E20; }
.division-badge-marketplace { background: #F3E5F5; color: #4A148C; }
.detail-info .price {
font-size: 32px;
font-weight: 700;
color: #C6A43F;
margin: 16px 0;
}
.detail-info .specs {
display: grid;
grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
gap: 12px;
margin: 20px 0;
padding: 20px;
background: #F8F8F8;
border-radius: 8px;
}
.detail-info .specs .spec-item {
display: flex;
flex-direction: column;
}
.detail-info .specs .spec-item .label {
font-size: 11px;
color: #888;
text-transform: uppercase;
letter-spacing: 0.5px;
}
.detail-info .specs .spec-item .value {
font-size: 16px;
font-weight: 600;
color: #0A0A0A;
}
.detail-info .description {
margin: 20px 0;
line-height: 1.8;
color: #444;
}
.detail-info .action-buttons {
display: flex;
gap: 12px;
margin-top: 24px;
flex-wrap: wrap;
}
.detail-info .action-buttons .btn-primary {
padding: 14px 32px;
background: #C6A43F;
color: #0A0A0A;
border: none;
border-radius: 8px;
font-weight: 600;
cursor: pointer;
text-decoration: none;
font-family: 'Inter', sans-serif;
}
.detail-info .action-buttons .btn-primary:hover {
background: #A8882E;
}
.detail-info .action-buttons .btn-secondary {
padding: 14px 32px;
background: #0A0A0A;
color: #fff;
border: none;
border-radius: 8px;
font-weight: 600;
cursor: pointer;
text-decoration: none;
font-family: 'Inter', sans-serif;
}
.detail-info .action-buttons .btn-secondary:hover {
background: #333;
}
/* You Might Also Like & Also Viewed */
.suggestions-section {
margin-top: 60px;
padding-top: 40px;
border-top: 1px solid #E0E0E0;
}
.suggestions-grid {
display: grid;
grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
gap: 20px;
margin-top: 20px;
}
.suggestion-card {
background: #fff;
border: 1px solid #E0E0E0;
border-radius: 12px;
overflow: hidden;
transition: all 0.3s ease;
text-decoration: none;
color: inherit;
}
.suggestion-card:hover {
transform: translateY(-4px);
box-shadow: 0 8px 24px rgba(0,0,0,0.1);
border-color: #C6A43F;
}
.suggestion-card .suggestion-image {
height: 150px;
overflow: hidden;
background: #f0f0f0;
}
.suggestion-card .suggestion-image img {
width: 100%;
height: 100%;
object-fit: cover;
}
.suggestion-card .suggestion-info {
padding: 12px 16px;
}
.suggestion-card .suggestion-info h4 {
font-size: 14px;
margin-bottom: 4px;
font-weight: 600;
}
.suggestion-card .suggestion-info .suggestion-price {
font-size: 16px;
font-weight: 700;
color: #C6A43F;
}
@media (max-width: 992px) {
.detail-grid {
grid-template-columns: 1fr;
gap: 24px;
}
.detail-gallery .main-image {
height: 300px;
}
}
@media (max-width: 768px) {
.detail-container {
padding: 0 16px;
margin-top: 80px;
}
.detail-info h1 {
font-size: 24px;
}
.detail-info .price {
font-size: 24px;
}
.detail-info .specs {
grid-template-columns: 1fr 1fr;
}
}
</style>

<div class="detail-container">

<!-- Breadcrumb -->
<div class="detail-breadcrumb">
<a href="/">Home</a> &gt;
<a href="/search.php?division=<?php echo $division; ?>"><?php echo $config['title']; ?></a> &gt;
<?php echo htmlspecialchars($listing['title']); ?>
</div>

<div class="detail-grid">

<!-- Gallery -->
<div class="detail-gallery">
<div class="main-image">
<?php if (!empty($images) && !empty($images[0]['url'])): ?>
<img src="<?php echo htmlspecialchars($images[0]['url']); ?>"
alt="<?php echo htmlspecialchars($listing['title']); ?>"
id="mainImage">
<?php else: ?>
<div class="placeholder">
<i class="fas fa-image"></i>
</div>
<?php endif; ?>
</div>

<?php if (count($images) > 1): ?>
<div class="thumbnails">
<?php foreach ($images as $index => $image): ?>
<img src="<?php echo htmlspecialchars($image['url']); ?>"
alt="Thumbnail <?php echo $index + 1; ?>"
onclick="document.getElementById('mainImage').src = this.src;"
class="<?php echo $index === 0 ? 'active' : ''; ?>">
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<!-- Info -->
<div class="detail-info">
<span class="division-badge division-badge-<?php echo $division; ?>">
<?php echo $config['icon']; ?> <?php echo $config['title']; ?>
</span>

<h1><?php echo htmlspecialchars($listing['title']); ?></h1>

<div class="price">₦<?php echo number_format($listing['price']); ?></div>

<!-- Specifications -->
<div class="specs">
<?php foreach ($config['fields'] as $field): ?>
<?php if (!empty($listing[$field])): ?>
<div class="spec-item">
<span class="label"><?php echo str_replace('_', ' ', ucfirst($field)); ?></span>
<span class="value"><?php echo htmlspecialchars($listing[$field]); ?></span>
</div>
<?php endif; ?>
<?php endforeach; ?>

<!-- Always show status -->
<div class="spec-item">
<span class="label">Status</span>
<span class="value" style="text-transform: capitalize;"><?php echo htmlspecialchars($listing['status']); ?></span>
</div>

<div class="spec-item">
<span class="label">Views</span>
<span class="value"><?php echo number_format($listing['views'] ?? 0); ?></span>
</div>

<div class="spec-item">
<span class="label">Listed</span>
<span class="value"><?php echo date('M j, Y', strtotime($listing['created_at'])); ?></span>
</div>
</div>

<!-- Description -->
<?php if (!empty($listing['description'])): ?>
<div class="description">
<?php echo nl2br(htmlspecialchars($listing['description'])); ?>
</div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="action-buttons">
<a href="/agent/contact-agent.php?id=<?php echo $id; ?>&division=<?php echo $division; ?>"
class="btn-primary">
<i class="fas fa-envelope"></i> Contact Seller
</a>

<a href="/search.php" class="btn-secondary">
<i class="fas fa-arrow-left"></i> Back to Search
</a>
</div>
</div>
</div>

<!-- ============================================================ -->
<!-- YOU MIGHT ALSO LIKE & ALSO VIEWED SECTIONS -->
<!-- ============================================================ -->
<?php if (!empty($similarListings) || !empty($recentListings)): ?>
<div class="suggestions-section">

<?php if (!empty($similarListings)): ?>
<div style="margin-bottom: 40px;">
<h2 style="font-family: 'Prata', serif; font-size: 24px; color: #0A0A0A;">
<i class="fas fa-lightbulb" style="color: #C6A43F;"></i> You Might Also Like
</h2>

<?php
$similarCards = array_map(function($item) use ($division, $config) {
$divisionSlug = $config['folder'] ?? 'kinas-automobile';

return [
'id' => $item['id'],
'title' => $item['title'],
'price' => $item['price'],
'thumbnail' => $item['thumbnail'] ?: '',
'specs' => '',
'location' => '',
'detail_url' => '/divisions/' . $divisionSlug . '/detail.php?id=' . $item['id'],
'featured' => false,
'verified' => false,
'views' => 0,
'division' => $config['title']
];
}, $similarListings);

je_render_listing_grid($similarCards);
?>
</div>
<?php endif; ?>

<?php if (!empty($recentListings)): ?>
<div>
<h2 style="font-family: 'Prata', serif; font-size: 24px; color: #0A0A0A;">
<i class="fas fa-history" style="color: #C6A43F;"></i> Also Viewed
</h2>

<?php
$recentCards = array_map(function($item) {
$recentDiv = $item['division'] ?? 'solar';

$divisionMap2 = [
'car' => 'kinas-automobile',
'solar' => 'kinas-volt',
'property' => 'williams-connect-home',
'marketplace' => 'kinas-marketplace'
];

$folder = $divisionMap2[$recentDiv] ?? 'kinas-automobile';

$divisionTitles = [
'car' => 'KINAS Automobile',
'solar' => 'KINAS Volt',
'property' => 'Williams Connect Home',
'marketplace' => 'KINAS Marketplace'
];

return [
'id' => $item['id'],
'title' => $item['title'],
'price' => $item['price'],
'thumbnail' => $item['thumbnail'] ?: '',
'specs' => '',
'location' => '',
'detail_url' => '/divisions/' . $folder . '/detail.php?id=' . $item['id'],
'featured' => false,
'verified' => false,
'views' => 0,
'division' => $divisionTitles[$recentDiv] ?? 'KINAS Group'
];
}, $recentListings);

if (!empty($recentCards)) {
je_render_listing_grid($recentCards);
}
?>
</div>
<?php endif; ?>

</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- KINAS PRODUCT REVIEWS -->
<!-- ============================================================ -->
<?php
$review_listing_type = $division;
$review_listing_id = (int)$id;

$kinasReviewsLoader = __DIR__ . '/../includes/reviews-detail.php';

if (file_exists($kinasReviewsLoader)) {
    require_once $kinasReviewsLoader;
}
?>

</div>

<?php include '../templates/footer.php'; ?>
