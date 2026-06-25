<?php
/**
 * KINAS GROUP — Homepage
 */
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'includes/helpers.php';
require_once 'api/config/database.php';
require_once 'includes/je-components.php';
require_once 'includes/security.php';

$db = Database::getInstance()->getConnection();

// Get counts for each division
$carCount = (int)$db->query("SELECT COUNT(*) FROM car_listings WHERE status = 'active'")->fetchColumn();
$propertyCount = (int)$db->query("SELECT COUNT(*) FROM property_listings WHERE status = 'active'")->fetchColumn();
$solarCount = (int)$db->query("SELECT COUNT(*) FROM solar_listings WHERE status = 'active'")->fetchColumn();
$marketplaceCount = (int)$db->query("SELECT COUNT(*) FROM marketplace_listings WHERE status = 'active'")->fetchColumn();

// Get featured listings from all divisions
// CAR - has featured column
$featuredCar = $db->query("
    SELECT c.id, c.title, c.brand, c.model, c.year, c.price, c.featured,
           'car' as listing_type, 'KINAS Automobile' as division,
           (SELECT url FROM listing_images WHERE listing_id = c.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM car_listings c
    WHERE c.status = 'active' AND c.featured = 1
    ORDER BY c.created_at DESC
    LIMIT 2
")->fetchAll();

// PROPERTY - has featured column
$featuredProperty = $db->query("
    SELECT p.id, p.title, p.price, p.featured, p.property_type,
           'property' as listing_type, 'Williams Connect Home' as division,
           (SELECT url FROM listing_images WHERE listing_id = p.id AND listing_type = 'property' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM property_listings p
    WHERE p.status = 'active' AND p.featured = 1
    ORDER BY p.created_at DESC
    LIMIT 2
")->fetchAll();

// SOLAR - does NOT have featured column, get latest 2 instead
$featuredSolar = $db->query("
    SELECT s.id, s.title, s.price, s.service_type,
           'solar' as listing_type, 'KINAS Volt' as division,
           (SELECT url FROM listing_images WHERE listing_id = s.id AND listing_type = 'solar' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM solar_listings s
    WHERE s.status = 'active'
    ORDER BY s.created_at DESC
    LIMIT 2
")->fetchAll();

// MARKETPLACE - has featured column
$featuredMarketplace = $db->query("
    SELECT m.id, m.title, m.price, m.featured, m.brand,
           'marketplace' as listing_type, 'KINAS Marketplace' as division,
           (SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM marketplace_listings m
    WHERE m.status = 'active' AND m.featured = 1
    ORDER BY m.created_at DESC
    LIMIT 2
")->fetchAll();

// Combine all featured listings
$featuredListings = array_merge($featuredCar, $featuredProperty, $featuredSolar, $featuredMarketplace);

// Shuffle to mix them up
shuffle($featuredListings);

// Limit to 8 featured items
$featuredListings = array_slice($featuredListings, 0, 8);

$pageTitle = 'KINAS GROUP — The World\'s Luxury Marketplace';
include 'templates/header.php';
?>

<style>
/* ----- Hero Section ----- */
#heroSection {
    position: relative;
    height: 80vh;
    min-height: 600px;
    display: flex;
    align-items: center;
    overflow: hidden;
}
#heroSection .hero-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    z-index: 0;
}
#heroSection .hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(10,10,10,0.4), rgba(0,0,0,0.7));
    z-index: 1;
}
#heroSection .hero-content {
    position: relative;
    z-index: 2;
    color: #fff;
    max-width: 700px;
}
#heroSection .hero-content h1 {
    font-family: 'Prata', serif;
    font-size: 52px;
    font-weight: 400;
    line-height: 1.1;
    margin-bottom: 20px;
}
#heroSection .hero-content p {
    font-size: 18px;
    color: rgba(255,255,255,0.85);
    line-height: 1.7;
    margin-bottom: 32px;
    max-width: 540px;
}

/* ----- Division Cards ----- */
.division-card {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.4s ease;
    cursor: default;
    min-height: 280px;
    display: flex;
    align-items: flex-end;
    text-decoration: none;
}
.division-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 48px rgba(0,0,0,0.15);
}
.division-card .card-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    transition: transform 0.6s ease;
}
.division-card:hover .card-bg {
    transform: scale(1.05);
}
.division-card .card-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 60%, transparent 100%);
}
.division-card .card-content {
    position: relative;
    z-index: 2;
    padding: 32px 28px 28px;
    color: #fff;
    width: 100%;
}
.division-card .card-content .icon {
    font-size: 32px;
    margin-bottom: 10px;
    display: block;
}
.division-card .card-content h3 {
    font-family: 'Prata', serif;
    font-size: 22px;
    margin-bottom: 6px;
    font-weight: 400;
}
.division-card .card-content p {
    font-size: 13px;
    color: rgba(255,255,255,0.8);
    margin-bottom: 4px;
    line-height: 1.5;
}
.division-card .card-content .count {
    font-size: 12px;
    color: #C6A43F;
    font-weight: 500;
}

/* ----- Feature Cards ----- */
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

/* ----- Stats Section ----- */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
    text-align: center;
}
.stats-grid .stat-item .number {
    font-family: 'Prata', serif;
    font-size: 36px;
    color: #C6A43F;
    display: block;
}
.stats-grid .stat-item .label {
    font-size: 13px;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 4px;
}

/* ----- Featured Listing Card (Homepage Style) ----- */
.featured-item-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid #f0ede8;
    text-decoration: none;
    color: inherit;
    display: block;
}
.featured-item-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.08);
    border-color: #C6A43F;
}
.featured-item-card .item-image {
    height: 200px;
    background-size: cover;
    background-position: center;
    position: relative;
}
.featured-item-card .item-image .division-tag {
    position: absolute;
    top: 12px;
    left: 12px;
    background: rgba(0,0,0,0.8);
    color: #fff;
    font-size: 10px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.featured-item-card .item-body {
    padding: 16px 18px 18px;
}
.featured-item-card .item-body .item-title {
    font-family: 'Prata', serif;
    font-size: 16px;
    color: #0A0A0A;
    margin-bottom: 4px;
    font-weight: 400;
}
.featured-item-card .item-body .item-price {
    font-size: 18px;
    font-weight: 600;
    color: #C6A43F;
}
.featured-item-card .item-body .item-meta {
    font-size: 13px;
    color: #888;
    margin-top: 4px;
}

/* ----- Responsive ----- */
@media (max-width: 992px) {
    #heroSection .hero-content h1 { font-size: 38px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 20px; }
}

@media (max-width: 768px) {
    #heroSection { min-height: 500px; }
    #heroSection .hero-content h1 { font-size: 30px; }
    #heroSection .hero-content p { font-size: 16px; }
    .division-card { min-height: 220px; }
    .feature-card { min-height: 220px; }
    .featured-item-card .item-image { height: 160px; }
}

@media (max-width: 576px) {
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 16px; }
    .stats-grid .stat-item .number { font-size: 28px; }
}
</style>

<!-- ============================================================ -->
<!-- HERO SECTION -->
<!-- ============================================================ -->
<section id="heroSection">
    <div class="hero-bg" style="background-image: url('https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1920&q=80');"></div>
    <div class="hero-overlay"></div>
    
    <div class="je-container" style="position:relative; z-index:2;">
        <div class="hero-content">
            <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#C6A43F; margin-bottom:12px; font-weight:600;">THE WORLD'S LUXURY MARKETPLACE</div>
            <h1>Where the world's finest<br>luxury comes to life</h1>
            <p>Discover extraordinary automobiles, homes, solar solutions, and curated luxury goods from verified sellers across the globe.</p>
            <div style="display:flex; gap:14px; flex-wrap:wrap;">
                <a href="/search.php" class="je-btn je-btn-gold je-btn-lg"><i class="fas fa-search"></i> Explore All</a>
                <a href="/divisions/kinas-automobile/" class="je-btn je-btn-lg" style="background:transparent;border-color:rgba(255,255,255,0.3);color:#fff;">Browse Divisions</a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- STATS SECTION -->
<!-- ============================================================ -->
<section style="padding:40px 0; background:#F8F6F1; border-bottom:1px solid #e8e5e0;">
    <div class="je-container">
        <div class="stats-grid">
            <div class="stat-item">
                <span class="number"><?= number_format($carCount) ?>+</span>
                <span class="label">Luxury Vehicles</span>
            </div>
            <div class="stat-item">
                <span class="number"><?= number_format($propertyCount) ?>+</span>
                <span class="label">Premium Properties</span>
            </div>
            <div class="stat-item">
                <span class="number"><?= number_format($solarCount) ?>+</span>
                <span class="label">Solar Solutions</span>
            </div>
            <div class="stat-item">
                <span class="number"><?= number_format($marketplaceCount) ?>+</span>
                <span class="label">Curated Goods</span>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- FEATURED LISTINGS SECTION -->
<!-- ============================================================ -->
<?php if (!empty($featuredListings)): ?>
<section style="padding:60px 0;">
    <div class="je-container">
        <div class="je-flex-between" style="margin-bottom:32px;">
            <div>
                <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">FEATURED LISTINGS</div>
                <h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A;">Exceptional finds</h2>
            </div>
            <a href="/search.php" class="je-btn je-btn-outline">View all <i class="fas fa-arrow-right"></i></a>
        </div>
        
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:24px;">
            <?php foreach ($featuredListings as $item): 
                $divisionSlug = strtolower(str_replace(' ', '-', str_replace('KINAS ', '', $item['division'])));
                // Special handling for Williams Connect Home
                if (strpos($item['division'], 'Williams') !== false) {
                    $divisionSlug = 'williams-connect-home';
                }
                $detailUrl = '/divisions/' . $divisionSlug . '/detail.php?id=' . $item['id'];
                $price = isset($item['price']) ? '₦' . number_format($item['price']) : 'Contact for price';
                $title = $item['title'] ?? 'Featured Listing';
                $division = $item['division'] ?? '';
                $thumbnail = $item['thumbnail'] ?? '';
            ?>
            <a href="<?= $detailUrl ?>" class="featured-item-card">
                <div class="item-image" style="background-image: url('<?= htmlspecialchars($thumbnail ?: '/assets/images/placeholder/product-placeholder.svg') ?>');">
                    <span class="division-tag"><?= htmlspecialchars($division) ?></span>
                </div>
                <div class="item-body">
                    <div class="item-title"><?= htmlspecialchars($title) ?></div>
                    <div class="item-price"><?= $price ?></div>
                    <div class="item-meta">
                        <?php if ($item['listing_type'] == 'car' && isset($item['brand'])): ?>
                            <?= htmlspecialchars($item['brand']) ?>
                            <?php if (isset($item['model'])): ?> · <?= htmlspecialchars($item['model']) ?><?php endif; ?>
                        <?php elseif ($item['listing_type'] == 'property' && isset($item['property_type'])): ?>
                            <?= htmlspecialchars($item['property_type']) ?>
                        <?php elseif ($item['listing_type'] == 'solar' && isset($item['service_type'])): ?>
                            <?= htmlspecialchars(ucfirst($item['service_type'])) ?> Solar
                        <?php elseif ($item['listing_type'] == 'marketplace' && isset($item['brand'])): ?>
                            <?= htmlspecialchars($item['brand']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================================ -->
<!-- DIVISIONS SECTION -->
<!-- ============================================================ -->
<section style="padding:60px 0; <?= empty($featuredListings) ? '' : 'padding-top:0;' ?>">
    <div class="je-container">
        <div style="text-align:center; margin-bottom:40px;">
            <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">OUR DIVISIONS</div>
            <h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A;">Explore our luxury portfolio</h2>
            <p style="color:#888; max-width:560px; margin:8px auto 0;">Four exceptional divisions, one unparalleled standard of luxury.</p>
        </div>
        
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:24px;">
            
            <!-- KINAS Automobile -->
            <a href="/divisions/kinas-automobile/" class="division-card">
                <div class="card-bg" style="background-image: url('https://images.unsplash.com/photo-1583121274602-3e2820c69888?w=600&q=80');"></div>
                <div class="card-overlay"></div>
                <div class="card-content">
                    <span class="icon">🚗</span>
                    <h3>KINAS Automobile</h3>
                    <p>Luxury cars, supercars & exotic vehicles</p>
                    <span class="count"><?= number_format($carCount) ?> vehicles</span>
                </div>
            </a>
            
            <!-- Williams Connect Home -->
            <a href="/divisions/williams-connect-home/" class="division-card">
                <div class="card-bg" style="background-image: url('https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=600&q=80');"></div>
                <div class="card-overlay"></div>
                <div class="card-content">
                    <span class="icon">🏠</span>
                    <h3>Williams Connect Home</h3>
                    <p>Luxury estates, villas & penthouses</p>
                    <span class="count"><?= number_format($propertyCount) ?> properties</span>
                </div>
            </a>
            
            <!-- KINAS Volt -->
            <a href="/divisions/kinas-volt/" class="division-card">
                <div class="card-bg" style="background-image: url('https://images.unsplash.com/photo-1509391366360-2e959784a276?w=600&q=80');"></div>
                <div class="card-overlay"></div>
                <div class="card-content">
                    <span class="icon">☀️</span>
                    <h3>KINAS Volt</h3>
                    <p>Premium solar & energy solutions</p>
                    <span class="count"><?= number_format($solarCount) ?> systems</span>
                </div>
            </a>
            
            <!-- KINAS Marketplace -->
            <a href="/divisions/kinas-marketplace/" class="division-card">
                <div class="card-bg" style="background-image: url('https://images.unsplash.com/photo-1601924582970-9238bcb495d9?w=600&q=80');"></div>
                <div class="card-overlay"></div>
                <div class="card-content">
                    <span class="icon">🛍️</span>
                    <h3>KINAS Marketplace</h3>
                    <p>Curated watches, jewelry, art & fashion</p>
                    <span class="count"><?= number_format($marketplaceCount) ?> items</span>
                </div>
            </a>
            
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- WHY KINAS GROUP - Feature Cards -->
<!-- ============================================================ -->
<section style="padding:80px 0; background:#F8F6F1;">
    <div class="je-container">
        <div style="text-align:center; margin-bottom:48px;">
            <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">WHY KINAS GROUP</div>
            <h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A;">The standard of luxury</h2>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:24px;">
            
            <!-- Verified Sellers -->
            <div class="feature-card">
                <div class="feature-bg" style="background-image: url('https://images.unsplash.com/photo-1556742502-ec7c0e9f34b1?w=600&q=80'); background-color: #1a2e1a;"></div>
                <div class="feature-overlay"></div>
                <div class="feature-content">
                    <h3>Verified Sellers</h3>
                    <p>Every seller is identity-verified through our secure KYC process. Trust is our foundation.</p>
                </div>
            </div>
            
            <!-- Global Reach -->
            <div class="feature-card">
                <div class="feature-bg" style="background-image: url('https://images.pexels.com/photos/393570/pexels-photo-393570.jpeg?w=600&q=80'); background-color: #0c1a2e;"></div>
                <div class="feature-overlay"></div>
                <div class="feature-content">
                    <h3>Global Reach</h3>
                    <p>Access luxury listings from verified sellers across 100+ countries, all in one marketplace.</p>
                </div>
            </div>
            
            <!-- Secure Transactions -->
            <div class="feature-card">
                <div class="feature-bg" style="background-image: url('/assets/images/trust/secure-transactions-240.jpg'); background-color: #2e1a0c;"></div>
                <div class="feature-overlay"></div>
                <div class="feature-content">
                    <h3>Secure Transactions</h3>
                    <p>End-to-end encrypted messaging and escrow-protected payments for complete peace of mind.</p>
                </div>
            </div>
            
            <!-- Quality Assurance -->
            <div class="feature-card">
                <div class="feature-bg" style="background-image: url('https://images.pexels.com/photos/3862631/pexels-photo-3862631.jpeg?w=600&q=80'); background-color: #1a0c2e;"></div>
                <div class="feature-overlay"></div>
                <div class="feature-content">
                    <h3>Quality Assurance</h3>
                    <p>Every listing is reviewed for quality, accuracy, and authenticity before publication.</p>
                </div>
            </div>
            
        </div>
    </div>
</section>

<!-- ============================================================ -->
<!-- CTA SECTION -->
<!-- ============================================================ -->
<section style="background:#0A0A0A; padding:80px 0; text-align:center; color:#fff;">
    <div class="je-container">
        <h2 style="font-family:'Prata',serif; font-size:36px; margin-bottom:14px;">Join the KINAS Group community</h2>
        <p style="color:rgba(255,255,255,0.7); font-size:15px; max-width:560px; margin:0 auto 28px;">Whether you're buying or selling, experience luxury redefined.</p>
        <div style="display:flex; gap:14px; justify-content:center; flex-wrap:wrap;">
            <a href="/search.php" class="je-btn je-btn-gold je-btn-lg">Start Exploring</a>
            <a href="/auth/register.php" class="je-btn je-btn-lg" style="background:transparent;border-color:rgba(255,255,255,0.3);color:#fff;">Become a Seller</a>
        </div>
    </div>
</section>

<script>
// ============================================
// HERO BACKGROUND ROTATION
// ============================================
const heroBg = document.querySelector('#heroSection .hero-bg');
if (heroBg) {
    const images = [
        'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1920&q=80',
        'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=1920&q=80',
        'https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=1920&q=80',
        'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=1920&q=80'
    ];
    let current = 0;
    
    setInterval(function() {
        current = (current + 1) % images.length;
        heroBg.style.opacity = '0';
        heroBg.style.transition = 'opacity 1s ease';
        
        setTimeout(function() {
            heroBg.style.backgroundImage = 'url(' + images[current] + ')';
            heroBg.style.opacity = '1';
        }, 1000);
    }, 6000);
}
</script>

<?php include 'templates/footer.php'; ?>
