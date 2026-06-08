<?php
/**
 * KINAS MARKETPLACE — Curated goods division landing
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

$db = Database::getInstance()->getConnection();

$items = $db->query("
    SELECT m.id, m.title, m.category_id, m.price, m.brand, m.condition_status, m.featured, m.views,
           m.city, m.state, m.country, c.name AS category_name, c.slug AS category_slug,
           a.verified AS agent_verified,
           (SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM marketplace_listings m
    LEFT JOIN marketplace_categories c ON m.category_id = c.id
    LEFT JOIN users a ON m.agent_id = a.id
    WHERE m.status = 'active'
    ORDER BY m.featured DESC, m.created_at DESC
    LIMIT 12
")->fetchAll();

$categories = $db->query("
    SELECT id, name, slug, (SELECT COUNT(*) FROM marketplace_listings ml WHERE ml.category_id = marketplace_categories.id AND ml.status='active') AS cnt
    FROM marketplace_categories
    ORDER BY name
")->fetchAll();

$totalItems = (int)$db->query("SELECT COUNT(*) FROM marketplace_listings WHERE status='active'")->fetchColumn();

$pageTitle = 'KINAS MARKETPLACE | Curated Luxury Goods';
$pageDescription = 'Watches, jewelry, art, fashion and other curated luxury goods from verified KINAS sellers.';
include '../../templates/header.php';
?>

<section style="position:relative; height:70vh; min-height:480px; background:linear-gradient(135deg, rgba(40,20,40,0.5), rgba(0,0,0,0.7)), url('https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=2000&q=80') center/cover no-repeat; display:flex; align-items:center;">
    <div class="je-container" style="color:#fff; position:relative; z-index:1;">
        <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#C6A43F; margin-bottom:12px; font-weight:600;">KINAS MARKETPLACE</div>
        <h1 style="font-family:'Prata',serif; font-size:56px; line-height:1.1; max-width:680px; margin-bottom:18px;">Curated Luxury Goods</h1>
        <p style="font-size:17px; color:rgba(255,255,255,0.85); max-width:560px; line-height:1.6; margin-bottom:32px;">Watches, jewelry, art, fashion and rare collectibles — <?= number_format($totalItems) ?>+ authenticated pieces from verified sellers.</p>
        <div class="je-flex" style="gap:14px;">
            <a href="search.php" class="je-btn je-btn-gold je-btn-lg"><i class="fas fa-search"></i> Browse Items</a>
            <a href="search.php?sort=price_high" class="je-btn je-btn-lg" style="background:transparent;border-color:rgba(255,255,255,0.3);color:#fff;">Most Expensive →</a>
        </div>
    </div>
</section>

<section style="background:#0A0A0A; padding:24px 0;">
    <div class="je-container">
        <form method="GET" action="search.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="q" placeholder="Brand, item, category…" style="flex:1; min-width:240px; padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px;">
            <select name="category" style="padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px; min-width:160px;">
                <option value="">Any Category</option>
                <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= (int)$c['cnt'] ?>)</option><?php endforeach; ?>
            </select>
            <button type="submit" class="je-btn je-btn-gold"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
</section>

<section style="padding:60px 0;">
    <div class="je-container">
        <div class="je-flex-between" style="margin-bottom:32px;">
            <div>
                <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">FEATURED ITEMS</div>
                <h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A;">Exceptional pieces</h2>
            </div>
            <a href="search.php" class="je-btn je-btn-outline">View all <i class="fas fa-arrow-right"></i></a>
        </div>

        <?php
        $cards = array_map(function ($r) {
            $specParts = array_filter([$r['category_name'] ?? null, $r['brand'] ?? null, $r['condition_status'] ?? null]);
            $locParts = array_filter([$r['city'] ?? null, $r['state'] ?? null, $r['country'] ?? null]);
            return [
                'id' => $r['id'], 'title' => $r['title'] ?? '',
                'price' => $r['price'], 'thumbnail' => $r['thumbnail'] ?: '',
                'specs' => implode(' • ', array_map('ucfirst', $specParts)),
                'location' => implode(', ', $locParts),
                'detail_url' => 'detail.php?id=' . (int)$r['id'],
                'featured' => !empty($r['featured']),
                'verified' => !empty($r['agent_verified']),
                'views' => $r['views'] ?? 0,
            ];
        }, array_slice($items, 0, 9));
        je_render_listing_grid($cards);
        ?>
    </div>
</section>

<section style="padding:60px 0; background:#F8F6F1;">
    <div class="je-container">
        <div style="text-align:center; margin-bottom:40px;">
            <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">BROWSE BY CATEGORY</div>
            <h2 style="font-family:'Prata',serif; font-size:32px;">Find what you're looking for</h2>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
            <?php foreach ($categories as $c): ?>
                <a href="search.php?category=<?= (int)$c['id'] ?>" style="background:#fff; border:1px solid #e8e8e8; padding:24px; text-align:center; border-radius:4px; text-decoration:none; transition:all 0.25s;">
                    <div style="font-family:'Prata',serif; font-size:16px; color:#0A0A0A; margin-bottom:4px;"><?= htmlspecialchars($c['name']) ?></div>
                    <div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:1px;"><?= (int)$c['cnt'] ?> items</div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section style="padding:80px 0;">
    <div class="je-container">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:40px; text-align:center;">
            <div><div style="width:60px; height:60px; border-radius:50%; background:rgba(198,164,63,0.1); color:#C6A43F; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:16px;"><i class="fas fa-certificate"></i></div><h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Authenticated</h3><p style="font-size:13px; color:#666; line-height:1.6;">Every item is verified for authenticity before listing.</p></div>
            <div><div style="width:60px; height:60px; border-radius:50%; background:rgba(198,164,63,0.1); color:#C6A43F; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:16px;"><i class="fas fa-lock"></i></div><h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Secure Payments</h3><p style="font-size:13px; color:#666; line-height:1.6;">Escrow-protected transactions for peace of mind.</p></div>
            <div><div style="width:60px; height:60px; border-radius:50%; background:rgba(198,164,63,0.1); color:#C6A43F; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:16px;"><i class="fas fa-truck"></i></div><h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">White-Glove Shipping</h3><p style="font-size:13px; color:#666; line-height:1.6;">Insured, door-to-door delivery for high-value items.</p></div>
            <div><div style="width:60px; height:60px; border-radius:50%; background:rgba(198,164,63,0.1); color:#C6A43F; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:16px;"><i class="fas fa-undo"></i></div><h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">14-Day Returns</h3><p style="font-size:13px; color:#666; line-height:1.6;">Buyer protection with hassle-free returns on eligible items.</p></div>
        </div>
    </div>
</section>

<section style="background:#0A0A0A; padding:80px 0; text-align:center; color:#fff;">
    <div class="je-container">
        <h2 style="font-family:'Prata',serif; font-size:36px; margin-bottom:14px;">Sell on KINAS Marketplace</h2>
        <p style="color:rgba(255,255,255,0.7); font-size:15px; max-width:560px; margin:0 auto 28px;">Reach a global audience of luxury collectors and enthusiasts.</p>
        <a href="/auth/register.php" class="je-btn je-btn-gold je-btn-lg">Become a Seller</a>
    </div>
</section>

<?php include '../../templates/footer.php'; ?>
