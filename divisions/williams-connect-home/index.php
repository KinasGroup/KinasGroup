<?php
/**
* WILLIAMS CONNECT HOME — Real Estate division landing
*/
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';
require_once '../../includes/featured-algorithm.php';

$db = Database::getInstance()->getConnection();

// ============================================================
// PRODUCT ROTATION / FAIR VISIBILITY
// ============================================================
// Instead of always showing the same featured/latest properties,
// rotate through all active property listings.
// ============================================================
$props = kinas_get_rotated_properties($db, 12);

$propTypes = $db->query("
SELECT property_type, COUNT(*) as cnt FROM property_listings
WHERE status='active' AND property_type IS NOT NULL AND property_type != ''
GROUP BY property_type ORDER BY cnt DESC LIMIT 8
")->fetchAll();

$totalProps = (int)$db->query("SELECT COUNT(*) FROM property_listings WHERE status='active'")->fetchColumn();

$pageTitle = 'WILLIAMS CONNECT HOME | Luxury Real Estate';
$pageDescription = 'Discover luxury homes, villas, penthouses, and estates from verified Williams Connect Home agents.';

include '../../templates/header.php';
?>
<style>
#heroSection {
position: relative;
height: 70vh;
min-height: 480px;
padding-top: 90px;
box-sizing: border-box;
display: flex;
align-items: center;
overflow: hidden;
}
.hero-slides { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; }
.hero-slide {
position: absolute; top: 0; left: 0; width: 100%; height: 100%;
background-size: cover; background-position: center;
opacity: 0; transition: opacity 1.5s ease-in-out;
}
@media (max-width: 768px) { .hero-slide { background-position: 65% center; } }
@media (max-width: 480px) { .hero-slide { background-position: 70% center; } }
.hero-slide.active { opacity: 1; }
.hero-overlay {
position: absolute; top: 0; left: 0; width: 100%; height: 100%;
background: linear-gradient(135deg, rgba(10,10,10,0.5), rgba(0,0,0,0.7));
z-index: 1;
}
.je-container { position: relative; z-index: 2; }

/* Custom Dropdown Styles */
.custom-dropdown {
position: relative;
display: inline-block;
min-width: 180px;
font-family: 'Inter', sans-serif;
}
.custom-dropdown-toggle {
padding: 14px 18px;
background: rgba(255, 255, 255, 0.06);
border: 1px solid rgba(255, 255, 255, 0.12);
border-radius: 3px;
color: #fff;
font-size: 14px;
cursor: pointer;
display: flex;
align-items: center;
justify-content: space-between;
gap: 12px;
white-space: nowrap;
transition: all 0.2s ease;
}
.custom-dropdown-toggle:hover {
background: rgba(255, 255, 255, 0.1);
border-color: rgba(255, 255, 255, 0.2);
}
.custom-dropdown-toggle .arrow {
font-size: 12px;
transition: transform 0.2s ease;
}
.custom-dropdown.open .custom-dropdown-toggle .arrow {
transform: rotate(180deg);
}
.custom-dropdown-menu {
position: absolute;
top: 100%;
left: 0;
right: 0;
background: #1a1a1a;
border: 1px solid rgba(255, 255, 255, 0.12);
border-radius: 4px;
margin-top: 4px;
max-height: 280px;
overflow-y: auto;
z-index: 1000;
display: none;
box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}
.custom-dropdown.open .custom-dropdown-menu {
display: block;
}
.custom-dropdown-item {
padding: 12px 18px;
color: #e0e0e0;
cursor: pointer;
transition: all 0.15s ease;
font-size: 14px;
display: flex;
justify-content: space-between;
align-items: center;
}
.custom-dropdown-item:hover {
background: rgba(198, 164, 63, 0.15);
color: #C6A43F;
}
.custom-dropdown-item.selected {
background: rgba(198, 164, 63, 0.25);
color: #C6A43F;
font-weight: 500;
}
.custom-dropdown-item .count {
font-size: 11px;
color: #888;
background: rgba(255, 255, 255, 0.08);
padding: 2px 8px;
border-radius: 12px;
}
.custom-dropdown-item:hover .count {
background: rgba(198, 164, 63, 0.2);
color: #C6A43F;
}
.custom-dropdown-menu::-webkit-scrollbar {
width: 6px;
}
.custom-dropdown-menu::-webkit-scrollbar-track {
background: #2a2a2a;
border-radius: 3px;
}
.custom-dropdown-menu::-webkit-scrollbar-thumb {
background: #C6A43F;
border-radius: 3px;
}
@media (max-width: 768px) {
.custom-dropdown {
width: 100%;
}
.custom-dropdown-menu {
max-height: 240px;
}
}

.feature-card {
position: relative;
border-radius: 16px;
overflow: hidden;
transition: all 0.4s ease;
cursor: default;
min-height: 280px;
display: flex;
align-items: flex-end;
}
.feature-card:hover {
transform: translateY(-6px);
box-shadow: 0 16px 48px rgba(0,0,0,0.15);
}
.feature-card .feature-bg {
position: absolute;
top: 0;
left: 0;
width: 100%;
height: 100%;
background-size: cover;
background-position: center;
transition: transform 0.6s ease;
}
.feature-card:hover .feature-bg {
transform: scale(1.05);
}
.feature-card .feature-overlay {
position: absolute;
top: 0;
left: 0;
width: 100%;
height: 100%;
background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 60%, transparent 100%);
}
.feature-card .feature-content {
position: relative;
z-index: 2;
padding: 32px 28px 28px;
color: #fff;
width: 100%;
}
.feature-card .feature-content h3 {
font-family: 'Prata', serif;
font-size: 22px;
margin-bottom: 8px;
font-weight: 400;
}
.feature-card .feature-content p {
font-size: 14px;
color: rgba(255,255,255,0.8);
margin-bottom: 12px;
line-height: 1.5;
}
</style>

<!-- Hero with Rotating Backgrounds -->
<section id="heroSection">
<div class="hero-slides">
<div class="hero-slide active" style="background-image: url('https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1920&q=80'); background-position: center 30%;"></div>
<div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=1920&q=80'); background-position: center 35%;"></div>
<div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=1920&q=80'); background-position: center 25%;"></div>
<div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=1920&q=80'); background-position: center 30%;"></div>
</div>
<div class="hero-overlay"></div>

<div class="je-container" style="color:#fff; position:relative; z-index:1;">
<div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#C6A43F; margin-bottom:12px; font-weight:600;">WILLIAMS CONNECT HOME</div>
<h1 style="font-family:'Prata',serif; font-size:42px; font-weight:400; line-height:1.15; max-width:680px; margin-bottom:18px;">Where Luxury Meets Address</h1>
<p style="font-size:17px; color:rgba(255,255,255,0.85); max-width:560px; line-height:1.6; margin-bottom:32px;">Real Estate, Property Sales, Rentals, Short-let Aprtments, and Property investment opportunities.</p>
<div class="je-flex" style="gap:14px;">
<a href="/divisions/williams-connect-home/search.php" class="je-btn je-btn-gold je-btn-lg"><i class="fas fa-search"></i> Browse Properties</a>
<a href="/divisions/williams-connect-home/search.php?listing_type=rent" class="je-btn je-btn-lg" style="background:transparent;border-color:rgba(255,255,255,0.3);color:#fff;">For Rent</a>
</div>
</div>
</section>

<!-- Search strip with custom dropdown -->
<section style="background:#0A0A0A; padding:24px 0;">
<div class="je-container">
<form method="GET" action="/divisions/williams-connect-home/search.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
<input type="text" name="q" placeholder="City, neighborhood, or keyword…" style="flex:1; min-width:240px; padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px;">

<!-- Custom Dropdown for Property Types -->
<div class="custom-dropdown" id="propertyDropdown">
<div class="custom-dropdown-toggle">
<span id="selectedPropertyText">Any Type</span>
<span class="arrow">▼</span>
</div>
<div class="custom-dropdown-menu">
<div class="custom-dropdown-item" data-value="" data-count="<?= $totalProps ?>">
<span>Any Type</span>
<span class="count"><?= $totalProps ?></span>
</div>
<?php foreach ($propTypes as $pt): ?>
<div class="custom-dropdown-item" data-value="<?= htmlspecialchars($pt['property_type']) ?>" data-count="<?= (int)$pt['cnt'] ?>">
<span><?= htmlspecialchars($pt['property_type']) ?></span>
<span class="count"><?= (int)$pt['cnt'] ?></span>
</div>
<?php endforeach; ?>
</div>
</div>

<input type="hidden" name="property_type" id="propertyInput" value="">
<button type="submit" class="je-btn je-btn-gold"><i class="fas fa-search"></i> Search</button>
</form>
</div>
</section>

<!-- Featured listings -->
<section style="padding:60px 0;">
<div class="je-container">
<div class="je-flex-between" style="margin-bottom:32px;">
<div>
<div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">FEATURED LISTINGS</div>
<h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A;">Extraordinary properties</h2>
</div>
<a href="/divisions/williams-connect-home/search.php" class="je-btn je-btn-outline">View all <i class="fas fa-arrow-right"></i></a>
</div>

<?php
$cards = array_map(function ($p) {
$specParts = array_filter([($p['beds'] ?? null) !== null ? (int)$p['beds'] . ' bd' : null, ($p['baths'] ?? null) !== null ? (int)$p['baths'] . ' ba' : null, ($p['sqft'] ?? null) !== null ? number_format((int)$p['sqft']) . ' sqft' : null, $p['property_type'] ?? null]);
$locParts = array_filter([$p['city'] ?? null, $p['state'] ?? null, $p['country'] ?? null]);

return [
'id' => $p['id'],
'title' => $p['title'] ?? '',
'price' => $p['price'],
'thumbnail' => $p['thumbnail'] ?: '',
'specs' => implode(' • ', $specParts),
'location' => implode(', ', $locParts),
// FIXED: Full path to detail page
'detail_url' => '/divisions/williams-connect-home/detail.php?id=' . (int)$p['id'],
'featured' => !empty($p['featured']),
'verified' => !empty($p['agent_verified']),
'views' => $p['views'] ?? 0,
];
}, array_slice($props, 0, 12));

je_render_listing_grid($cards);
?>
</div>
</section>

<!-- Explore by type -->
<section style="padding:60px 0; background:#F8F6F1;">
<div class="je-container">
<div style="text-align:center; margin-bottom:40px;">
<div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">EXPLORE BY TYPE</div>
<h2 style="font-family:'Prata',serif; font-size:32px;">Find your property type</h2>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
<?php foreach ($propTypes as $pt): ?>
<a href="/divisions/williams-connect-home/search.php?property_type=<?= urlencode($pt['property_type']) ?>" style="background:#fff; border:1px solid #e8e8e8; padding:24px; text-align:center; border-radius:4px; text-decoration:none; transition:all 0.25s;">
<div style="font-family:'Prata',serif; font-size:16px; color:#0A0A0A; margin-bottom:4px;"><?= htmlspecialchars($pt['property_type']) ?></div>
<div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:1px;"><?= (int)$pt['cnt'] ?> properties</div>
</a>
<?php endforeach; ?>
</div>
</div>
</section>

<!-- Why Choose Us - Updated with Feature Cards (same as Volt style) -->
<section style="padding:80px 0;">
<div class="je-container">
<div style="text-align:center; margin-bottom:48px;">
<div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">WHY WILLIAMS CONNECT HOME</div>
<h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A;">Trusted luxury real estate</h2>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:24px;">
<!-- Verified Agents -->
<div class="feature-card">
<div class="feature-bg" style="background-image: url('/assets/images/trust/verified-agents-wch-240.jpg'); background-color: #1a2e1a;"></div>
<div class="feature-overlay"></div>
<div class="feature-content">
<h3>Verified Agents</h3>
<p>Every agent is identity-verified for your safety and confidence.</p>
</div>
</div>

<!-- Curated Locations -->
<div class="feature-card">
<div class="feature-bg" style="background-image: url('/assets/images/trust/curated-locations-wch-240.jpg'); background-color: #0c1a2e;"></div>
<div class="feature-overlay"></div>
<div class="feature-content">
<h3>Curated Locations</h3>
<p>Hand-picked properties in the world's most desirable addresses.</p>
</div>
</div>

<!-- Transparent Listings -->
<div class="feature-card">
<div class="feature-bg" style="background-image: url('/assets/images/trust/transparent-listings-wch-240.jpg'); background-color: #2e1a0c;"></div>
<div class="feature-overlay"></div>
<div class="feature-content">
<h3>Transparent Listings</h3>
<p>Detailed specs, full image galleries, and verified ownership.</p>
</div>
</div>

<!-- Concierge -->
<div class="feature-card">
<div class="feature-bg" style="background-image: url('/assets/images/trust/concierge-wch-240.jpg'); background-color: #1a0c2e;"></div>
<div class="feature-overlay"></div>
<div class="feature-content">
<h3>Concierge</h3>
<p>Our concierge can arrange private viewings anywhere.</p>
</div>
</div>
</div>
</div>
</section>

<!-- CTA Section -->
<section style="background:#0A0A0A; padding:80px 0; text-align:center; color:#fff;">
<div class="je-container">
<h2 style="font-family:'Prata',serif; font-size:36px; margin-bottom:14px;">List your property with KINAS</h2>
<p style="color:rgba(255,255,255,0.7); font-size:15px; max-width:560px; margin:0 auto 28px;">Reach a global audience of qualified luxury buyers.</p>
<a href="/auth/register.php" class="je-btn je-btn-gold je-btn-lg">Become an Agent</a>
</div>
</section>

<script>
// ============================================
// ROTATING HERO BACKGROUND
// ============================================
let currentSlide = 0;
const slides = document.querySelectorAll('.hero-slide');
const totalSlides = slides.length;

function rotateHeroBackground() {
if (totalSlides > 1) {
slides[currentSlide].classList.remove('active');
currentSlide = (currentSlide + 1) % totalSlides;
slides[currentSlide].classList.add('active');
}
}

if (totalSlides > 1) {
setInterval(rotateHeroBackground, 6000);
}

// ============================================
// CUSTOM DROPDOWN FUNCTIONALITY
// ============================================
(function() {
const dropdown = document.getElementById('propertyDropdown');
if (!dropdown) return;

const toggle = dropdown.querySelector('.custom-dropdown-toggle');
const items = dropdown.querySelectorAll('.custom-dropdown-item');
const selectedText = document.getElementById('selectedPropertyText');
const propertyInput = document.getElementById('propertyInput');

let isOpen = false;

toggle.addEventListener('click', function(e) {
e.stopPropagation();
isOpen = !isOpen;

if (isOpen) {
dropdown.classList.add('open');
document.addEventListener('click', closeDropdownOnClickOutside);
} else {
dropdown.classList.remove('open');
document.removeEventListener('click', closeDropdownOnClickOutside);
}
});

items.forEach(function(item) {
item.addEventListener('click', function(e) {
e.stopPropagation();

const value = this.getAttribute('data-value');
const text = this.querySelector('span:first-child').innerText;

selectedText.innerHTML = text;
propertyInput.value = value;

items.forEach(function(i) {
i.classList.remove('selected');
});

this.classList.add('selected');

isOpen = false;
dropdown.classList.remove('open');
document.removeEventListener('click', closeDropdownOnClickOutside);
});
});

function closeDropdownOnClickOutside(e) {
if (!dropdown.contains(e.target)) {
isOpen = false;
dropdown.classList.remove('open');
document.removeEventListener('click', closeDropdownOnClickOutside);
}
}

const defaultItem = dropdown.querySelector('.custom-dropdown-item[data-value=""]');
if (defaultItem) {
defaultItem.classList.add('selected');
}
})();
</script>

<?php include '../../templates/footer.php'; ?>
