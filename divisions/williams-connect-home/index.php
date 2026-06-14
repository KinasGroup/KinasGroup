<?php
/**
 * WILLIAMS CONNECT HOME — Real Estate division landing
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

$db = Database::getInstance()->getConnection();

$props = $db->query("
    SELECT p.id, p.title, p.property_type, p.listing_type, p.price, p.beds, p.baths, p.sqft, p.featured, p.views,
           p.city, p.state, p.country,
           a.verified as agent_verified,
           (SELECT url FROM listing_images WHERE listing_id = p.id AND listing_type = 'property' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM property_listings p
    LEFT JOIN users a ON p.agent_id = a.id
    WHERE p.status = 'active'
    ORDER BY p.featured DESC, p.created_at DESC
    LIMIT 12
")->fetchAll();

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

<!-- Hero Carousel Styles -->
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
    
    <!-- ORIGINAL CONTENT - EXACTLY AS WAS -->
    <div class="je-container" style="color:#fff; position:relative; z-index:1;">
        <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#C6A43F; margin-bottom:12px; font-weight:600;">WILLIAMS CONNECT HOME</div>
        <h1 style="font-family:'Prata',serif; font-size:42px; font-weight:400; line-height:1.15; max-width:680px; margin-bottom:18px;">Where Luxury Meets Address</h1>
        <p style="font-size:17px; color:rgba(255,255,255,0.85); max-width:560px; line-height:1.6; margin-bottom:32px;">From penthouses to private estates — discover <?= number_format($totalProps) ?>+ luxury properties from verified agents across the globe.</p>
        <div class="je-flex" style="gap:14px;">
            <a href="search.php" class="je-btn je-btn-gold je-btn-lg"><i class="fas fa-search"></i> Browse Properties</a>
            <a href="search.php?listing_type=rent" class="je-btn je-btn-lg" style="background:transparent;border-color:rgba(255,255,255,0.3);color:#fff;">For Rent</a>
        </div>
    </div>
</section>

<section style="background:#0A0A0A; padding:24px 0;">
    <div class="je-container">
        <form method="GET" action="search.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="q" placeholder="City, neighborhood, or keyword…" style="flex:1; min-width:240px; padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px;">
            <select name="property_type" style="padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px; min-width:160px;">
                <option value="">Any Type</option>
                <?php foreach ($propTypes as $pt): ?><option value="<?= htmlspecialchars($pt['property_type']) ?>"><?= htmlspecialchars($pt['property_type']) ?> (<?= (int)$pt['cnt'] ?>)</option><?php endforeach; ?>
            </select>
            <button type="submit" class="je-btn je-btn-gold"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
</section>

<section style="padding:60px 0;">
    <div class="je-container">
        <div class="je-flex-between" style="margin-bottom:32px;">
            <div>
                <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">FEATURED LISTINGS</div>
                <h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A;">Extraordinary properties</h2>
            </div>
            <a href="search.php" class="je-btn je-btn-outline">View all <i class="fas fa-arrow-right"></i></a>
        </div>

        <?php
        $cards = array_map(function ($p) {
            $specParts = array_filter([($p['beds'] ?? null) !== null ? (int)$p['beds'] . ' bd' : null, ($p['baths'] ?? null) !== null ? (int)$p['baths'] . ' ba' : null, ($p['sqft'] ?? null) !== null ? number_format((int)$p['sqft']) . ' sqft' : null, $p['property_type'] ?? null]);
            $locParts = array_filter([$p['city'] ?? null, $p['state'] ?? null, $p['country'] ?? null]);
            return [
                'id' => $p['id'], 'title' => $p['title'] ?? '',
                'price' => $p['price'], 'thumbnail' => $p['thumbnail'] ?: '',
                'specs' => implode(' • ', $specParts),
                'location' => implode(', ', $locParts),
                'detail_url' => 'detail.php?id=' . (int)$p['id'],
                'featured' => !empty($p['featured']),
                'verified' => !empty($p['agent_verified']),
                'views' => $p['views'] ?? 0,
            ];
        }, array_slice($props, 0, 9));
        je_render_listing_grid($cards);
        ?>
    </div>
</section>

<section style="padding:60px 0; background:#F8F6F1;">
    <div class="je-container">
        <div style="text
