<?php
/**
 * KINAS AUTOMOBILE — Division landing
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

$db = Database::getInstance()->getConnection();

// Active cars
$cars = $db->query("
    SELECT c.id, c.title, c.brand, c.model, c.year, c.price, c.mileage, c.transmission, c.fuel_type, c.status, c.featured,
           c.city, c.state, c.country, c.views, c.body_type, c.color,
           a.verified as agent_verified,
           (SELECT url FROM listing_images WHERE listing_id = c.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM car_listings c
    LEFT JOIN users a ON c.agent_id = a.id
    WHERE c.status = 'active'
    ORDER BY c.featured DESC, c.created_at DESC
    LIMIT 12
")->fetchAll();

// Featured
$featured = array_filter($cars, fn($c) => !empty($c['featured']));

// Top brands
$brands = $db->query("
    SELECT brand, COUNT(*) as cnt FROM car_listings
    WHERE status='active' AND brand IS NOT NULL AND brand != ''
    GROUP BY brand ORDER BY cnt DESC LIMIT 8
")->fetchAll();

$totalCars = (int)$db->query("SELECT COUNT(*) FROM car_listings WHERE status='active'")->fetchColumn();

$pageTitle = 'KINAS AUTOMOBILE | Luxury Cars & Exotic Vehicles';
$pageDescription = 'Browse the world\'s finest luxury cars, supercars, and exotic vehicles from verified KINAS Automobile dealers.';

include '../../templates/header.php';
?>

<!-- ── Hero ── -->
<section id="heroSection" style="position:relative; height:70vh; min-height:480px; padding-top:90px; box-sizing:border-box; background:linear-gradient(135deg, rgba(10,10,10,0.5), rgba(0,0,0,0.7)), url('https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=2000&q=80') center/cover no-repeat; display:flex; align-items:center;">
    <div class="je-container" style="color:#fff; position:relative; z-index:1;">
        <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#C6A43F; margin-bottom:12px; font-weight:600;">KINAS AUTOMOBILE</div>
        <h1 style="font-family:'Prata',serif; font-size:42px; font-weight:400; line-height:1.15; max-width:680px; margin-bottom:18px;">Finest Luxury &amp; Exotic Vehicles</h1>
        <p style="font-size:17px; color:rgba(255,255,255,0.85); max-width:560px; line-height:1.6; margin-bottom:32px;">From supercars to grand tourers — discover <?= number_format($totalCars) ?>+ verified luxury vehicles from trusted dealers worldwide.</p>
        <div class="je-flex" style="gap:14px;">
            <a href="search.php" class="je-btn je-btn-gold je-btn-lg"><i class="fas fa-search"></i> Browse Inventory</a>
            <a href="search.php?sort=price_high" class="je-btn je-btn-lg" style="background:transparent;border-color:rgba(255,255,255,0.3);color:#fff;">Car Rentals</a>
        </div>
    </div>
</section>

<!-- ── Search strip ── -->
<section style="background:#0A0A0A; padding:24px 0;">
    <div class="je-container">
        <form method="GET" action="search.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="q" placeholder="Search by make, model, keyword…" style="flex:1; min-width:240px; padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px;">
            <select name="brand" style="padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px; min-width:160px;">
                <option value="">Any Brand</option>
                <?php foreach ($brands as $b): ?><option value="<?= htmlspecialchars($b['brand']) ?>"><?= htmlspecialchars($b['brand']) ?> (<?= (int)$b['cnt'] ?>)</option><?php endforeach; ?>
            </select>
            <button type="submit" class="je-btn je-btn-gold"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
</section>

<!-- ── Featured grid ── -->
<section style="padding:60px 0;">
    <div class="je-container">
        <div class="je-flex-between" style="margin-bottom:32px;">
            <div>
                <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">FEATURED COLLECTION</div>
                <h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A;">Exceptional vehicles</h2>
            </div>
            <a href="search.php" class="je-btn je-btn-outline">View all <i class="fas fa-arrow-right"></i></a>
        </div>

        <?php
        $cards = array_map(function ($c) {
            $specParts = array_filter([$c['year'] ?? null, ($c['mileage'] ?? null) !== null ? number_format((int)$c['mileage']) . ' km' : null, $c['transmission'] ?? null, $c['fuel_type'] ?? null]);
            $locParts = array_filter([$c['city'] ?? null, $c['state'] ?? null, $c['country'] ?? null]);
            return [
                'id'         => $c['id'],
                'title'      => trim(($c['brand'] ?? '') . ' ' . ($c['model'] ?? '') . ' ' . ($c['year'] ?? '')),
                'price'      => $c['price'],
                'thumbnail'  => $c['thumbnail'] ?: '',
                'specs'      => implode(' • ', $specParts),
                'location'   => implode(', ', $locParts),
                'detail_url' => 'detail.php?id=' . (int)$c['id'],
                'featured'   => !empty($c['featured']),
                'verified'   => !empty($c['agent_verified']),
                'views'      => $c['views'] ?? 0,
            ];
        }, array_slice($cars, 0, 9));
        je_render_listing_grid($cards);
        ?>
    </div>
</section>

<!-- ── Browse by brand ── -->
<section style="padding:60px 0; background:#F8F6F1;">
    <div class="je-container">
        <div style="text-align:center; margin-bottom:40px;">
            <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">BROWSE BY MARQUE</div>
            <h2 style="font-family:'Prata',serif; font-size:32px;">World-renowned brands</h2>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
            <?php foreach ($brands as $b): ?>
                <a href="search.php?brand=<?= urlencode($b['brand']) ?>" style="background:#fff; border:1px solid #e8e8e8; padding:24px; text-align:center; border-radius:4px; text-decoration:none; transition:all 0.25s;">
                    <div style="font-family:'Prata',serif; font-size:16px; color:#0A0A0A; margin-bottom:4px;"><?= htmlspecialchars($b['brand']) ?></div>
                    <div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:1px;"><?= (int)$b['cnt'] ?> vehicles</div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Why Kinas ── -->
<section style="padding:80px 0;">
    <div class="je-container">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:40px; text-align:center;">
            <div>
                <div style="width:120px; height:120px; border-radius:50%; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; margin-bottom:20px; box-shadow:0 8px 24px rgba(0,0,0,0.12);"><img src="/assets/images/trust/verified-dealers-icon-120.png" srcset="/assets/images/trust/verified-dealers-icon-240.png 2x" alt="Verified Dealers" width="120" height="120" loading="lazy" style="width:120px; height:120px; display:block;"></div>
                <h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Verified Dealers</h3>
                <p style="font-size:13px; color:#666; line-height:1.6;">Every dealer on KINAS is identity-verified through our secure KYC partner.</p>
            </div>
            <div>
                <div style="width:120px; height:120px; border-radius:50%; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; margin-bottom:20px; box-shadow:0 8px 24px rgba(0,0,0,0.12);"><img src="/assets/images/trust/global-inventory-icon-120.png" srcset="/assets/images/trust/global-inventory-icon-240.png 2x" alt="Global Inventory" width="120" height="120" loading="lazy" style="width:120px; height:120px; display:block;"></div>
                <h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Global Inventory</h3>
                <p style="font-size:13px; color:#666; line-height:1.6;">Browse vehicles from dealers across 100+ countries, all in one place.</p>
            </div>
            <div>
                <div style="width:120px; height:120px; border-radius:50%; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; margin-bottom:20px; box-shadow:0 8px 24px rgba(0,0,0,0.12);"><img src="/assets/images/trust/secure-transactions-icon-120.png" srcset="/assets/images/trust/secure-transactions-icon-240.png 2x" alt="Secure Transactions" width="120" height="120" loading="lazy" style="width:120px; height:120px; display:block;"></div>
                <h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Secure Transactions</h3>
                <p style="font-size:13px; color:#666; line-height:1.6;">End-to-end encrypted messaging and escrow-protected payments.</p>
            </div>
            <div>
                <div style="width:120px; height:120px; border-radius:50%; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; margin-bottom:20px; box-shadow:0 8px 24px rgba(0,0,0,0.12);"><img src="/assets/images/trust/concierge-service-icon-120.png" srcset="/assets/images/trust/concierge-service-icon-240.png 2x" alt="Concierge Service" width="120" height="120" loading="lazy" style="width:120px; height:120px; display:block;"></div>
                <h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Concierge Service</h3>
                <p style="font-size:13px; color:#666; line-height:1.6;">Our specialists can source specific vehicles on request.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA ── -->
<section style="background:#0A0A0A; padding:80px 0; text-align:center; color:#fff;">
    <div class="je-container">
        <h2 style="font-family:'Prata',serif; font-size:36px; margin-bottom:14px;">List your vehicle with KINAS</h2>
        <p style="color:rgba(255,255,255,0.7); font-size:15px; max-width:560px; margin:0 auto 28px;">Reach an audience of qualified luxury buyers. Get verified in minutes.</p>
        <a href="/auth/register.php" class="je-btn je-btn-gold je-btn-lg">Become a Dealer</a>
    </div>
</section>

<?php include '../../templates/footer.php'; ?>
