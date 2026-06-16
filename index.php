<?php
// Load environment variables from .env file
require_once 'includes/dotenv.php';

require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'includes/helpers.php';
require_once "api/config/database.php";
require_once "api/config/database_class.php";
require_once 'api/config/constants.php';

$pageTitle = 'KINAS GROUP | The World\'s Luxury Marketplace';
$pageDescription = 'KINAS GROUP - The World\'s Luxury Marketplace: Homes, Cars, Solar & Products for Sale';

$db = Database::getInstance()->getConnection();

// Get featured listings across all divisions
$featuredListings = [];

// Featured Cars
$stmt = $db->query("
    SELECT c.*, 'car' as listing_type, a.verified as agent_verified,
           (SELECT url FROM listing_images WHERE listing_id = c.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) as thumbnail
    FROM car_listings c
    LEFT JOIN users a ON c.agent_id = a.id
    WHERE c.status = 'active' AND c.featured = 1
    ORDER BY c.created_at DESC
    LIMIT 8
");
$cars = $stmt->fetchAll();

// Featured Properties
$stmt = $db->query("
    SELECT p.*, 'property' as listing_type, a.verified as agent_verified,
           (SELECT url FROM listing_images WHERE listing_id = p.id AND listing_type = 'property' ORDER BY sort_order LIMIT 1) as thumbnail
    FROM property_listings p
    LEFT JOIN users a ON p.agent_id = a.id
    WHERE p.status = 'active' AND p.featured = 1
    ORDER BY p.created_at DESC
    LIMIT 8
");
$properties = $stmt->fetchAll();

// Featured Marketplace Items
$stmt = $db->query("
    SELECT m.*, 'marketplace' as listing_type, c.name as category_name, a.verified as seller_verified,
           (SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) as thumbnail
    FROM marketplace_listings m
    LEFT JOIN marketplace_categories c ON m.category_id = c.id
    LEFT JOIN users a ON m.agent_id = a.id
    WHERE m.status = 'active' AND m.featured = 1
    ORDER BY m.created_at DESC
    LIMIT 8
");
$marketplaceItems = $stmt->fetchAll();

// Get agent stats
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'agent' AND verified = 1 AND status = 'active'");
$verifiedAgents = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM car_listings WHERE status = 'active'");
$activeCars = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM property_listings WHERE status = 'active'");
$activeProperties = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM marketplace_listings WHERE status = 'active'");
$activeProducts = $stmt->fetch()['count'];

$totalListings = $activeCars + $activeProperties + $activeProducts;

// Build featured listings array
$allFeatured = array_merge($cars, $properties, $marketplaceItems);
$featuredListings = array_slice($allFeatured, 0, 8);

$extraScripts = ['/assets/js/main.js'];

include 'templates/header.php';
?>

<style>
/* ── Viewport width fix ── */
html, body {
    overflow-x: hidden;
    width: 100%;
    max-width: 100%;
    margin: 0;
    padding: 0;
}

/* ── Container: full-bleed on mobile ── */
@media (max-width: 768px) {
    .container {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 16px !important;
        padding-right: 16px !important;
        box-sizing: border-box !important;
    }
}

@media (max-width: 480px) {
    .container {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
}

/* Hero Section with Rotating Backgrounds */
.hero-section {
    position: relative;
    height: 85vh;
    min-height: 650px;
    overflow: hidden;
}

.hero-slides {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.hero-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    opacity: 0;
    transition: opacity 1.5s ease-in-out;
}

/* Mobile-specific background positioning */
@media (max-width: 768px) {
    .hero-slide {
        background-position: 65% center;
    }
}

@media (max-width: 480px) {
    .hero-slide {
        background-position: 70% center;
    }
}

.hero-slide.active {
    opacity: 1;
}

.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6));
    z-index: 1;
}

.hero-text {
    position: absolute;
    top: 50%;
    left: 10%;
    transform: translateY(-50%);
    text-align: left;
    z-index: 2;
    width: auto;
    max-width: 600px;
    padding: 0 20px;
}

/* Mobile text alignment */
@media (max-width: 768px) {
    .hero-text {
        left: 5%;
        right: 5%;
        top: 50%;
        transform: translateY(-50%);
        text-align: left;
        max-width: 90%;
    }
}

.hero-text h1 {
    font-family: 'Prata', serif;
    font-size: 64px;
    font-weight: 400;
    color: #FFFFFF;
    margin-bottom: 20px;
    letter-spacing: 2px;
    line-height: 1.2;
}

@media (max-width: 768px) {
    .hero-text h1 {
        font-size: 36px;
        letter-spacing: 1px;
    }
}

@media (max-width: 480px) {
    .hero-text h1 {
        font-size: 28px;
    }
}

.hero-text p {
    font-family: 'Inter', sans-serif;
    font-size: 20px;
    color: rgba(255,255,255,0.9);
    font-weight: 300;
}

@media (max-width: 768px) {
    .hero-text p {
        font-size: 16px;
    }
}

/* Search Bar - Centered below hero */
/* ── Search Widget — single authoritative definition ── */
.hs-wrap {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    width: 100%;
    padding: 0 20px;
    margin-top: -44px;
    position: relative;
    z-index: 10;
    box-sizing: border-box;
}

.hs-card {
    width: 100%;
    max-width: 760px;
    margin: 0 auto;
    background: #ffffff;
    border-radius: 18px;
    padding: 26px 30px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.18);
    box-sizing: border-box;
}

.hs-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 16px;
    flex-wrap: wrap;
    justify-content: center;
}

.hs-tab {
    padding: 8px 18px;
    background: #F5F5F5;
    border: none;
    font-family: 'Inter', sans-serif;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    border-radius: 30px;
    font-size: 14px;
    color: #666;
}

.hs-tab.active {
    background: #C6A43F;
    color: #0A0A0A;
}

.hs-bar {
    display: flex;
    align-items: center;
    gap: 10px;
}

.hs-input {
    flex: 1;
    padding: 13px 18px;
    border: 1px solid #E0E0E0;
    border-radius: 50px;
    font-family: 'Inter', sans-serif;
    font-size: 15px;
    outline: none;
    min-width: 0;
    box-sizing: border-box;
}

.hs-input:focus {
    border-color: #C6A43F;
}

.hs-btn {
    background: #C6A43F;
    color: #0A0A0A;
    padding: 13px 30px;
    border: none;
    border-radius: 50px;
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 17px;
    white-space: nowrap;
    flex-shrink: 0;
    box-sizing: border-box;
}

.hs-btn:hover {
    background: #A8882E;
}

@media (max-width: 600px) {
    .hs-wrap {
        padding: 0 20px;
        margin-top: -24px;
        justify-content: center;
    }
    .hs-card {
        width: 100%;
        max-width: 420px;
        margin: 0 auto;
        padding: 18px 16px;
        border-radius: 14px;
    }
    .hs-tabs {
        justify-content: center;
        gap: 5px;
        margin-bottom: 14px;
    }
    .hs-tab {
        padding: 6px 12px;
        font-size: 12px;
    }
    .hs-bar {
        flex-direction: column;
        gap: 8px;
        align-items: stretch;
    }
    .hs-input {
        width: 100%;
        padding: 12px 16px;
        font-size: 14px;
        text-align: center;
    }
    .hs-btn {
        width: 100%;
        padding: 13px 16px;
        font-size: 16px;
        text-align: center;
    }
}

/* Section Styles */
.section {
    padding: 80px 0;
}

.section.bg-light {
    background: #F5F5F5;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 40px;
}
/* Header must always span full width regardless of .container constraints */
.container.header-inner {
    max-width: 100% !important;
}

@media (max-width: 768px) {
    .container {
        padding: 0 15px;
        width: 100%;
        max-width: 100%;
    }
    .section {
        padding: 50px 0;
    }
    .hero-section {
        height: 75vh;
        min-height: 480px;
    }
    .hero-text {
        left: 15px;
        right: 15px;
        max-width: calc(100% - 30px);
    }
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 40px;
    flex-wrap: wrap;
}

.section-heading {
    font-family: 'Prata', serif;
    font-size: 36px;
    font-weight: 400;
    color: #0A0A0A;
    letter-spacing: -0.5px;
}

@media (max-width: 768px) {
    .section-heading {
        font-size: 28px;
    }
}

.view-all {
    font-family: 'Inter', sans-serif;
    color: #C6A43F;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s;
}

.view-all:hover {
    color: #A8882E;
}

/* Categories Grid - 6 columns for popular categories */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 20px;
}

@media (max-width: 1200px) {
    .categories-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 768px) {
    .categories-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
    }
}

@media (max-width: 480px) {
    .categories-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
}

.category-card {
    text-decoration: none;
    transition: transform 0.3s;
    display: flex;
    flex-direction: column;
}

.category-card:hover {
    transform: translateY(-5px);
}

.category-img {
    width: 100%;
    aspect-ratio: 1;
    overflow: hidden;
    border-radius: 12px;
    margin-bottom: 8px;
}

.category-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}

.category-card:hover .category-img img {
    transform: scale(1.05);
}

.category-label {
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    color: #C6A43F;
    text-align: center;
    font-size: 13px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    padding-top: 4px;
}

/* Listings Grid */
.listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 30px;
}

@media (max-width: 768px) {
    .listings-grid {
        grid-template-columns: 1fr;
    }
}

.listing-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.listing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}

.listing-card a {
    text-decoration: none;
    color: inherit;
}

.listing-img {
    position: relative;
    height: 240px;
    overflow: hidden;
}

.listing-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}

.listing-card:hover .listing-img img {
    transform: scale(1.05);
}

.listing-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: #C6A43F;
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.favorite-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: white;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    color: #999;
}

.favorite-btn:hover {
    background: #C6A43F;
    color: white;
}

.listing-info {
    padding: 20px;
}

.listing-dealer {
    font-family: 'Inter', sans-serif;
    font-size: 12px;
    color: #C6A43F;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}

.listing-title {
    font-family: 'Prata', serif;
    font-size: 20px;
    font-weight: 400;
    margin: 10px 0;
    color: #0A0A0A;
    line-height: 1.3;
}

.listing-specs {
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    color: #666666;
    margin-bottom: 10px;
}

.listing-price {
    font-family: 'Inter', sans-serif;
    font-size: 24px;
    font-weight: 700;
    color: #0A0A0A;
    margin: 10px 0;
}

.verified-tag {
    display: inline-block;
    background: #E8F5E9;
    color: #2E7D32;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
}

/* Trust Row */
.trust-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 40px;
    text-align: center;
}

@media (max-width: 768px) {
    .trust-row {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
}

@media (max-width: 480px) {
    .trust-row {
        grid-template-columns: 1fr;
    }
}

.trust-item {
    padding: 20px;
}

.trust-icon {
    font-size: 36px;
    display: block;
    margin-bottom: 15px;
}

.trust-item h4 {
    font-family: 'Prata', serif;
    font-size: 18px;
    margin-bottom: 10px;
    color: #0A0A0A;
}

.trust-item p {
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    color: #666666;
}

/* CTA Banner */
.cta-banner {
    background: linear-gradient(135deg, #0A0A0A 0%, #1a1a1a 100%);
    padding: 80px 0;
    text-align: center;
}

@media (max-width: 768px) {
    .cta-banner {
        padding: 50px 0;
    }
}

.cta-banner h2 {
    font-family: 'Prata', serif;
    font-size: 42px;
    color: white;
    margin-bottom: 15px;
}

@media (max-width: 768px) {
    .cta-banner h2 {
        font-size: 28px;
    }
}

.cta-banner p {
    font-family: 'Inter', sans-serif;
    font-size: 18px;
    color: rgba(255,255,255,0.7);
    margin-bottom: 30px;
}

.cta-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

@media (max-width: 480px) {
    .cta-buttons {
        flex-direction: column;
        align-items: center;
    }
}

.cta-buttons a {
    display: inline-block;
    padding: 14px 32px;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

/* Mobile Nav Drawer — index.php specific full-screen slide-in */
.mobile-nav-drawer {
    position: fixed;
    top: 0;
    right: -100%;
    width: 80%;
    max-width: 320px;
    height: 100%;
    background: #0A0A0A;
    z-index: 1002;
    transition: right 0.3s ease;
    padding: 30px 20px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    overflow-y: auto;
}

.mobile-nav-drawer.open {
    right: 0;
}

.mobile-nav-drawer a {
    color: #e0e0e0;
    text-decoration: none;
    font-family: 'Inter', sans-serif;
    padding: 14px 0;
    border-bottom: 1px solid #2a2a2a;
    font-size: 15px;
    letter-spacing: 0.5px;
}

.mobile-nav-drawer .close-menu {
    background: none;
    border: none;
    color: white;
    font-size: 28px;
    cursor: pointer;
    align-self: flex-end;
    margin-bottom: 10px;
    padding: 5px;
    line-height: 1;
}

.menu-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 1001;
}

.menu-overlay.active {
    display: block;
}
</style>

<!-- HERO SECTION WITH 4 ROTATING BACKGROUNDS -->
<section class="hero-section" id="heroSection">
    <div class="hero-slides">
        <div class="hero-slide active" style="background-image: url('https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=1920&q=80');"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1920&q=80');"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=1920&q=80');"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=1920&q=80');"></div>
    </div>
    <div class="hero-overlay"></div>
    <div class="hero-text">
        <h1>Extraordinary Living</h1>
        <p>Discover the world's finest cars, homes, and luxury goods</p>
    </div>
</section>

<!-- SEARCH BAR - Centered below hero -->
<div class="hs-wrap">
    <div class="hs-card">
        <div class="hs-tabs">
            <button type="button" class="hs-tab active" onclick="switchSearchTab(this, 'cars')">Cars</button>
            <button type="button" class="hs-tab" onclick="switchSearchTab(this, 'homes')">Homes</button>
            <button type="button" class="hs-tab" onclick="switchSearchTab(this, 'solar')">Solar</button>
            <button type="button" class="hs-tab" onclick="switchSearchTab(this, 'marketplace')">Marketplace</button>
        </div>
        <form action="/search.php" method="GET">
            <input type="hidden" name="division" id="searchDivision" value="automobile">
            <div class="hs-bar">
                <input type="text" name="q" placeholder="Search luxury cars, homes, and more..." class="hs-input">
                <button type="submit" class="hs-btn">Search</button>
            </div>
        </form>
    </div>
</div>

<!-- MOBILE MENU DRAWER -->
<div id="mobileNavDrawer" class="mobile-nav-drawer">
    <button class="close-menu" id="closeMobileMenu">✕</button>
    <a href="/divisions/kinas-automobile/">KINAS AUTOMOBILE</a>
    <a href="/divisions/williams-connect-home/">WILLIAMS CONNECT HOME</a>
    <a href="/divisions/kinas-volt/">KINAS VOLT</a>
    <a href="/divisions/kinas-marketplace/">KINAS MARKETPLACE</a>
    <a href="/pages/about.php">ABOUT US</a>
    <hr style="border-color:#333;">
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="/agent/dashboard.php">Dashboard</a>
        <a href="/auth/logout.php">Sign Out</a>
    <?php else: ?>
        <a href="/auth/login.php">Sign In</a>
        <a href="/auth/register.php">Register</a>
    <?php endif; ?>
</div>
<div id="menuOverlay" class="menu-overlay"></div>

<!-- POPULAR CATEGORIES -->
<section class="section">
    <div class="container">
        <h2 class="section-heading">Popular Categories</h2>
        <div class="categories-grid">
            <a href="/divisions/kinas-automobile/" class="category-card">
                <div class="category-img"><img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=600&q=80" alt="Luxury Cars" loading="lazy"></div>
                <div class="category-label">Luxury Cars</div>
            </a>
            <a href="/divisions/kinas-automobile/rentals.html" class="category-card">
                <div class="category-img"><img src="https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=600&q=80" alt="Car Rentals" loading="lazy"></div>
                <div class="category-label">Car Rentals</div>
            </a>
            <a href="/divisions/williams-connect-home/" class="category-card">
                <div class="category-img"><img src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=600&q=80" alt="Homes for Sale" loading="lazy"></div>
                <div class="category-label">Homes for Sale</div>
            </a>
            <a href="/divisions/williams-connect-home/" class="category-card">
                <div class="category-img"><img src="https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=600&q=80" alt="Luxury Rentals" loading="lazy"></div>
                <div class="category-label">Luxury Rentals</div>
            </a>
            <a href="/divisions/kinas-volt/" class="category-card">
                <div class="category-img"><img src="https://images.unsplash.com/photo-1509391366360-2e959784a276?w=600&q=80" alt="Solar Energy" loading="lazy"></div>
                <div class="category-label">Solar Energy</div>
            </a>
            <a href="/divisions/kinas-marketplace/" class="category-card">
                <div class="category-img"><img src="https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&q=80" alt="Luxury Goods" loading="lazy"></div>
                <div class="category-label">Luxury Goods</div>
            </a>
        </div>
    </div>
</section>

<!-- FEATURED LISTINGS -->
<section class="section bg-light">
    <div class="container">
        <div class="section-header">
            <h2 class="section-heading">Featured Listings</h2>
            <a href="/search.php" class="view-all">View All →</a>
        </div>
        <div class="listings-grid">
            <?php foreach ($featuredListings as $listing): ?>
                <?php
                $url = '#';
                $image = '/assets/images/placeholder/car-placeholder.jpg';
                $divisionName = 'KINAS GROUP';
                $specs = '';
                $price = '₦0';

                switch ($listing['listing_type']) {
                    case 'car':
                        $url = '/divisions/kinas-automobile/detail.php?id=' . $listing['id'];
                        $image = $listing['thumbnail'] ?? '/assets/images/placeholder/car-placeholder.jpg';
                        $divisionName = 'KINAS Automobile';
                        $specs = number_format($listing['mileage'] ?? 0) . ' km · ' . ucfirst($listing['transmission'] ?? 'Auto') . ' · ' . ucfirst($listing['fuel_type'] ?? 'Petrol');
                        $price = '₦' . number_format($listing['price'] ?? 0);
                        break;
                    case 'property':
                        $url = '/divisions/williams-connect-home/detail.php?id=' . $listing['id'];
                        $image = $listing['thumbnail'] ?? '/assets/images/placeholder/property-placeholder.jpg';
                        $divisionName = 'Williams Connect Home';
                        $specs = ($listing['beds'] ?? 0) . ' Beds · ' . ($listing['baths'] ?? 0) . ' Baths · ' . number_format($listing['sqft'] ?? 0) . ' sqft';
                        $price = '₦' . number_format($listing['price'] ?? 0);
                        break;
                    case 'marketplace':
                        $url = '/divisions/kinas-marketplace/detail.php?id=' . $listing['id'];
                        $image = $listing['thumbnail'] ?? '/assets/images/placeholder/product-placeholder.jpg';
                        $divisionName = 'KINAS Marketplace';
                        $price = '₦' . number_format($listing['price'] ?? 0);
                        break;
                }
                ?>
                <div class="listing-card">
                    <a href="<?php echo $url; ?>">
                        <div class="listing-img">
                            <img src="<?php echo $image; ?>" alt="<?php echo htmlspecialchars($listing['title'] ?? 'Listing'); ?>" loading="lazy">
                            <?php if (!empty($listing['featured'])): ?>
                                <span class="listing-badge">Featured</span>
                            <?php endif; ?>
                            <button class="favorite-btn" onclick="event.preventDefault(); toggleFavorite(this);">♡</button>
                        </div>
                        <div class="listing-info">
                            <span class="listing-dealer"><?php echo htmlspecialchars($divisionName); ?></span>
                            <h3 class="listing-title"><?php echo htmlspecialchars($listing['title'] ?? 'Untitled Listing'); ?></h3>
                            <?php if ($specs): ?>
                                <p class="listing-specs"><?php echo htmlspecialchars($specs); ?></p>
                            <?php endif; ?>
                            <p class="listing-price"><?php echo $price; ?></p>
                            <?php if (!empty($listing['agent_verified']) || !empty($listing['seller_verified'])): ?>
                                <span class="verified-tag">✓ Verified</span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>

            <?php if (empty($featuredListings)): ?>
                <!--
                    No hardcoded fallback listings.
                    When the database has no featured/active listings, the grid
                    stays empty until listings arrive from the Agent / Super Agent
                    dashboards.
                -->
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- TRUST BADGES -->
<section class="section">
    <div class="container">
        <div class="trust-row">
            <div class="trust-item">
                <img src="/assets/images/trust/verified-badge.jpg" alt="Verified Agents" style="height:60px; width:auto; margin-bottom:15px; object-fit:contain;">
                <h4>Verified Agents</h4>
                <p>All agents undergo KYC verification</p>
            </div>
            <div class="trust-item">
                <img src="/assets/images/trust/secure-payment.jpg" alt="Secure Transactions" style="height:60px; width:auto; margin-bottom:15px; object-fit:contain;">
                <h4>Secure Transactions</h4>
                <p>Your safety is our priority</p>
            </div>
            <div class="trust-item">
                <img src="/assets/images/trust/magnifying-glass.jpg" alt="Quality Assurance" style="height:60px; width:auto; margin-bottom:15px; object-fit:contain;">
                <h4>Quality Assurance</h4>
                <p>Every listing reviewed by our team</p>
            </div>
            <div class="trust-item">
                <img src="/assets/images/trust/world-map-phone.jpg" alt="Global Reach" style="height:60px; width:auto; margin-bottom:15px; object-fit:contain;">
                <h4>Global Reach</h4>
                <p>Listings from verified agents worldwide</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA BANNER -->
<section class="cta-banner">
    <div class="container">
        <h2>Ready to List Your Item?</h2>
        <p>Join thousands of verified agents on KINAS GROUP</p>
        <div class="cta-buttons">
            <a href="/auth/register.php" class="je2-button" style="background:white; color:#151515; font-weight:600; padding:14px 32px;">Become an Agent</a>
            <a href="/auth/login.php" class="je2-button" style="background:#0A0A0A; color:#ffffff; border:2px solid #0A0A0A; font-weight:600; padding:14px 32px;">Sign In</a>
        </div>
    </div>
</section>

<script>
// ============================================
// ROTATING HERO BACKGROUND (4 images)
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
// SEARCH TAB SWITCHER
// ============================================
function switchSearchTab(btn, tab) {
    document.querySelectorAll('.hs-tab').forEach(function(t) {
        t.classList.remove('active');
    });
    btn.classList.add('active');

    // Map tab name to division query value
    var divisionMap = {
        'cars':        'automobile',
        'homes':       'real_estate',
        'marketplace': 'marketplace',
        'solar':       'solar'
    };
    var hiddenInput = document.getElementById('searchDivision');
    if (hiddenInput) hiddenInput.value = divisionMap[tab] || '';

    // Update placeholder to match context
    var placeholderMap = {
        'cars':        'Search luxury cars, SUVs, exotic vehicles…',
        'homes':       'Search properties, apartments, luxury homes…',
        'marketplace': 'Search products, collectibles, luxury goods…',
        'solar':       'Search solar panels, installations, energy solutions…'
    };
    var input = document.querySelector('.hs-input');
    if (input) input.placeholder = placeholderMap[tab] || 'Search…';
}

// ============================================
// TRANSPARENT HEADER SCROLL EFFECT
// ============================================
// Moved to /assets/js/header-scroll.js (loaded by templates/footer.php
// on every page). It no-ops automatically on pages that don't have a
// hero section.

// Mobile menu handled by /assets/js/mobile-menu.js (loaded in footer)

// ============================================
// FAVORITE TOGGLE
// ============================================
function toggleFavorite(btn) {
    if (btn.textContent === '♡') {
        btn.textContent = '♥';
        btn.style.color = '#e74c3c';
    } else {
        btn.textContent = '♡';
        btn.style.color = '';
    }
}
</script>

<?php include 'templates/footer.php'; ?>
