<?php
/**
* KINAS MARKETPLACE — Item detail
*
* AMENDED FOR PRODUCT REVIEWS:
* - Sold/completed marketplace items remain publicly accessible so verified
*   buyers can leave reviews after purchase.
* - Pending/flagged/non-public listings remain private to owner/admin.
* - Adds the KINAS Product Reviews section near the bottom of the page.
*
* AMENDED FOR RELATED PRODUCTS:
* - Replaces the old static similar-products query with the dynamic
*   weighted related-products engine.
*
* AMENDED FOR PUBLIC IDENTITY:
* - The seller is presented publicly as @username (seller card).
*   The legal name stays private (DB/admin/KYC).
*/
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';
// AMENDED: public identity helpers (@username display)
require_once '../../includes/public-identity.php';
// Related products engine
$kinasRelatedProductsEngine = __DIR__ . '/../../includes/related-products.php';
if (file_exists($kinasRelatedProductsEngine)) {
require_once $kinasRelatedProductsEngine;
}
$id = (int)($_GET['id'] ?? 0);
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
SELECT m.*, c.name AS category_name, c.slug AS category_slug,
a.verified as agent_verified, a.name as agent_name, a.username as agent_username, a.email as agent_email, a.phone as agent_phone,
ap.company_name as agent_company, ap.avatar as agent_avatar
FROM marketplace_listings m
LEFT JOIN marketplace_categories c ON m.category_id = c.id
LEFT JOIN users a ON m.agent_id = a.id
LEFT JOIN agent_profiles ap ON a.id = ap.user_id
WHERE m.id = ?
");
$stmt->execute([$id]);
$item = $stmt->fetch();
// ============================================================
// VISIBILITY RULES
// ============================================================
// Active listings are public.
//
// Sold / completed / under-offer listings are also kept public so that
// verified buyers can still view the product and leave a review after
// the transaction is completed.
//
// Pending / flagged / draft / rejected listings remain private and are
// only visible to the listing owner or an admin.
// ============================================================
$publicReviewStatuses = [
'active',
'sold',
'completed',
'under_offer',
'pending_sale',
];
$isOwnerOrAdmin = $item && SessionManager::isLoggedIn()
&& ((int)$item['agent_id'] === SessionManager::getUserId() || SessionManager::getUserRole() === 'admin');
$isPublicStatus = $item && in_array((string)($item['status'] ?? ''), $publicReviewStatuses, true);
if (!$item || (!$isPublicStatus && !$isOwnerOrAdmin)) {
http_response_code(404);
include __DIR__ . '/../../pages/404.php';
exit;
}
$isPreview = !$isPublicStatus;
// Only increment views for active listings.
if (!$isPreview && ($item['status'] ?? '') === 'active') {
$db->prepare("UPDATE marketplace_listings SET views = views + 1 WHERE id = ?")->execute([$id]);
}
$images = $db->prepare("SELECT * FROM listing_images WHERE listing_id = ? AND listing_type = 'marketplace' ORDER BY sort_order");
$images->execute([$id]);
$images = $images->fetchAll();
// ============================================================
// RELATED PRODUCTS — DYNAMIC WEIGHTED ENGINE
// ============================================================
$similar = [];
if (function_exists('kinas_get_related_marketplace_items')) {
$similar = kinas_get_related_marketplace_items($db, $item, 4);
} else {
// Safe fallback if the related-products engine is missing.
$clauses = [];
$params = [$id];
if (!empty($item['category_id'])) {
$clauses[] = 'm.category_id = ?';
$params[] = $item['category_id'];
}
if (!empty($item['brand'])) {
$clauses[] = 'm.brand = ?';
$params[] = $item['brand'];
}
$whereSimilar = !empty($clauses) ? ' AND (' . implode(' OR ', $clauses) . ')' : '';
$similarStmt = $db->prepare("
SELECT m.id, m.title, m.price, m.brand, m.category_id, c.name AS category_name,
(SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
FROM marketplace_listings m
LEFT JOIN marketplace_categories c ON m.category_id = c.id
WHERE m.id != ?
AND m.status = 'active'
{$whereSimilar}
ORDER BY RAND()
LIMIT 4
");
$similarStmt->execute($params);
$similar = $similarStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
$pageTitle = ($item['title'] ?? 'Item') . ' - KINAS Marketplace';
$pageDescription = !empty($item['description'])
? substr(strip_tags($item['description']), 0, 160)
: 'Shop ' . ($item['title'] ?? 'this item') . ' on KINAS Marketplace.';
// Link-preview thumbnail (WhatsApp/Facebook/Twitter/etc): the listing's
// own first photo when it has one, falling back to header.php's default
// group logo (via $pageImage staying unset) otherwise.
if (!empty($images[0]['url'])) {
$pageImage = $images[0]['url'];
}
$division = 'marketplace';
include '../../templates/header.php';
$locParts = array_filter([$item['city'] ?? null, $item['state'] ?? null, $item['country'] ?? null]);
$location = implode(', ', $locParts);
$listingId = (int)$item['id'];
$agentId = (int)$item['agent_id'];
$agentName = htmlspecialchars($item['agent_name'] ?? 'Seller', ENT_QUOTES, 'UTF-8');
// AMENDED: PUBLIC identity — @username (legal name stays private).
$agentPublicName = htmlspecialchars(kinas_public_display_name($item['agent_username'] ?? null, $item['agent_name'] ?? 'Seller'), ENT_QUOTES, 'UTF-8');
$listingTitle = htmlspecialchars($item['title'] ?? 'Item', ENT_QUOTES, 'UTF-8');
$agentVerified = !empty($item['agent_verified']);
// Reflect real cart state on load — previously the button always said
// "Add to Cart" regardless of whether the item was already in the cart,
// since nothing checked. Combined with cart_items never actually being
// created (see database/migrations/2026_07_08_create_cart_items.sql),
// this made it look like items were never really being saved.
$alreadyInCart = false;
if (SessionManager::isLoggedIn()) {
try {
$cartCheckStmt = $db->prepare("SELECT 1 FROM cart_items WHERE buyer_id = ? AND listing_id = ? AND listing_type = 'marketplace'");
$cartCheckStmt->execute([SessionManager::getUserId(), $listingId]);
$alreadyInCart = (bool)$cartCheckStmt->fetchColumn();
} catch (Throwable $e) {
$alreadyInCart = false;
}
}
?>
<div class="je-page">
<div class="je-detail-wrap">
<?php if ($isPreview): ?>
<div style="background:#FFF8E1; border:1px solid #F0C419; color:#7A5B00; padding:14px 18px; border-radius:4px; margin-bottom:20px; font-size:14px;">
<i class="fas fa-eye"></i> <strong>Preview only</strong> — this listing is <?= htmlspecialchars(ucfirst($item['status'])) ?> and not visible to the public yet. Only you and admins can see this page.
</div>
<?php endif; ?>
<?php if (!$isPreview && ($item['status'] ?? '') !== 'active'): ?>
<div style="background:#F5F5F5; border:1px solid #E0E0E0; color:#555; padding:12px 16px; border-radius:4px; margin-bottom:20px; font-size:13px;">
<i class="fas fa-info-circle"></i>
This listing is currently marked as <strong><?= htmlspecialchars(ucfirst((string)$item['status'])) ?></strong>.
Verified customers can still leave a review below.
</div>
<?php endif; ?>
<div class="je-breadcrumb">
<a href="/">Home</a>
<span class="je-breadcrumb-sep">/</span>
<a href="/divisions/kinas-marketplace/">KINAS MARKETPLACE</a>
<span class="je-breadcrumb-sep">/</span>
<a href="/divisions/kinas-marketplace/search.php">Search</a>
<?php if (!empty($item['category_slug'])): ?>
<span class="je-breadcrumb-sep">/</span>
<a href="/divisions/kinas-marketplace/search.php?category=<?= (int)$item['category_id'] ?>"><?= htmlspecialchars($item['category_name']) ?></a>
<?php endif; ?>
<span class="je-breadcrumb-sep">/</span>
<span><?= htmlspecialchars($item['title'] ?? '') ?></span>
</div>
<div class="je-detail-grid">
<div>
<?php if (empty($images)): ?>
<div class="je-gallery-main" style="background:linear-gradient(135deg,#1a1a1a,#0a0a0a); display:flex; align-items:center; justify-content:center; color:#C6A43F; font-size:64px;">
<i class="fas fa-gem"></i>
</div>
<?php else: ?>
<div class="je-gallery-main"><img id="jeMainImage" src="<?= htmlspecialchars($images[0]['url']) ?>" alt="" onerror="this.onerror=null; this.src='/assets/images/placeholder/product-placeholder.svg';"></div>
<?php if (count($images) > 1): ?>
<div class="je-gallery-thumbs">
<?php foreach ($images as $idx => $img): ?>
<div class="je-gallery-thumb <?= $idx === 0 ? 'is-active' : '' ?>"
onclick="document.getElementById('jeMainImage').src='<?= htmlspecialchars($img['url']) ?>';
document.querySelectorAll('.je-gallery-thumb').forEach(t=>t.classList.remove('is-active'));
this.classList.add('is-active');">
<img src="<?= htmlspecialchars($img['url']) ?>" alt="" onerror="this.onerror=null; this.src='/assets/images/placeholder/product-placeholder.svg';">
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
</div>
<aside class="je-spec-panel">
<div class="je-spec-eyebrow"><?= htmlspecialchars($item['category_name'] ?? 'Curated') ?></div>
<h1 class="je-spec-title"><?= htmlspecialchars($item['title'] ?? '') ?></h1>
<?php if ($location): ?><div style="font-size:13px;color:#888;margin-bottom:8px;"><i class="fas fa-map-marker-alt" style="color:#C6A43F"></i> <?= htmlspecialchars($location) ?></div><?php endif; ?>
<div class="je-spec-price"><?= formatPrice(marketplaceBuyerPrice((float)$item['price'])) ?></div>
<dl class="je-spec-key">
<?php
$keys = [
'Category' => $item['category_name'] ?? null,
'Brand'    => $item['brand'] ?? null,
'Condition'=> $item['condition_status'] ?? null,
];
foreach ($keys as $label => $val):
if (!$val) continue;
?>
<div><dt><?= htmlspecialchars($label) ?></dt><dd><?= htmlspecialchars(ucfirst($val)) ?></dd></div>
<?php endforeach; ?>
</dl>
<!-- ============================================================ -->
<!-- BUTTONS - Cart & Checkout (Paystack) -->
<!-- ============================================================ -->
<?php if ($isPreview || $item['status'] === 'sold'): ?>
<div class="je-cta-row">
<button class="je-cta-secondary" disabled style="opacity:.55;cursor:not-allowed;">
<i class="fas fa-lock"></i> <?= $item['status'] === 'sold' ? 'Sold' : 'Not yet available' ?>
</button>
</div>
<?php else: ?>
<div class="je-cta-row">
<!-- Buy Now -->
<button class="je-cta-primary" id="buyNowBtn" onclick="jeBuyNow(<?= $listingId ?>);">
<i class="fas fa-bolt"></i> Buy Now
</button>
<!-- Add to Cart -->
<button class="je-cta-secondary" id="addToCartBtn"
data-in-cart="<?= $alreadyInCart ? '1' : '0' ?>"
onclick="jeAddToCart(<?= $listingId ?>);"
<?= $alreadyInCart ? 'disabled' : '' ?>>
<?php if ($alreadyInCart): ?>
<i class="fas fa-check"></i> In Cart
<?php else: ?>
<i class="fas fa-shopping-bag"></i> Add to Cart
<?php endif; ?>
</button>
<!-- Save Listing -->
<button class="je-cta-secondary" id="saveBtn" onclick="jeSaveListing('marketplace', <?= $listingId ?>);">
<i class="far fa-heart"></i> Save
</button>
</div>
<?php endif; ?>
<div class="je-agent-card">
<div class="je-agent-avatar">
<?php if (!empty($item['agent_avatar'])): ?>
<img src="<?= htmlspecialchars($item['agent_avatar']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
<?php else: ?>
<?= strtoupper(substr($item['agent_name'] ?? 'A', 0, 1)) ?>
<?php endif; ?>
</div>
<div class="je-agent-info">
<!-- AMENDED: public @username instead of the legal name -->
<div class="je-agent-name"><?= $agentPublicName ?></div>
<div class="je-agent-meta">
<?= htmlspecialchars($item['agent_company'] ?? 'Verified Seller') ?>
<?php if (!empty($item['agent_verified'])): ?>
· <span style="color:#1B5E20;font-weight:600;">✓ Verified</span>
<?php endif; ?>
</div>
</div>
</div>
</aside>
</div>
<section class="je-section" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8; margin-top:40px;">
<h2>About this item</h2>
<?php if (!empty($item['description'])): ?>
<p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
<?php else: ?>
<p style="color:#999;font-style:italic;">No description provided.</p>
<?php endif; ?>
</section>
<!-- ── Similar listings ── -->
<?php if (!empty($similar)): ?>
<section class="je-section" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8;">
<h2>You may also like</h2>
<?php
$simCards = array_map(function ($s) {
return [
'id'         => $s['id'],
'title'      => $s['title'] ?? '',
'division'   => 'KINAS MARKETPLACE',
'price'      => $s['price'],
'thumbnail'  => $s['thumbnail'] ?: '',
'specs'      => $s['category_name'] ?? '',
'location'   => '',
'detail_url' => '/divisions/kinas-marketplace/detail.php?id=' . (int)$s['id'],
'featured'   => false,
'verified'   => false,
];
}, $similar);
je_render_listing_grid($simCards);
?>
</section>
<?php endif; ?>
<!-- ============================================================ -->
<!-- KINAS PRODUCT REVIEWS -->
<!-- ============================================================ -->
<?php if (!$isPreview): ?>
<!-- KINAS PRODUCT REVIEWS -->
<?php
$kinasReviewsEngine = __DIR__ . '/../../includes/reviews.php';
if (file_exists($kinasReviewsEngine)) {
require_once $kinasReviewsEngine;
if (function_exists('kinas_render_reviews_section')) {
kinas_render_reviews_section($db, 'marketplace', $listingId);
}
}
?>
<?php endif; ?>
</div>
</div>
<!-- ============================================================ -->
<!-- ALL JAVASCRIPT - INLINE, NO EXTERNAL DEPENDENCIES -->
<!-- ============================================================ -->
<script>
// ============================================================
// HELPER FUNCTIONS
// ============================================================
function isUserLoggedIn() {
const meta = document.querySelector('meta[name="user-data"]');
if (meta) {
try {
const data = JSON.parse(meta.content);
return data.loggedIn === true;
} catch (e) {
return false;
}
}
return document.querySelector('meta[name="user-id"]')?.content ? true : false;
}
function showLoginRequired() {
if (typeof window.showSuccessBanner === 'function') {
window.showSuccessBanner('Please sign in to continue — redirecting you to login…', true);
} else if (typeof kinasToast === 'function') {
kinasToast('Please sign in to continue — redirecting you to login…', 'warning');
}
setTimeout(function() {
window.location.href = '/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
}, 1500);
}
// ============================================================
// ADD TO CART
// ============================================================
function jeAddToCart(listingId) {
if (!isUserLoggedIn()) {
showLoginRequired();
return;
}
const btn = document.getElementById('addToCartBtn');
const original = btn ? btn.innerHTML : '';
if (btn) {
btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
btn.disabled = true;
}
const formData = new FormData();
formData.append('listing_id', listingId);
fetch('/api/cart/add.php', {
method: 'POST',
body: formData,
credentials: 'same-origin'
})
.then(function(r) { return r.json(); })
.then(function(data) {
if (data.success) {
if (btn) {
btn.innerHTML = '<i class="fas fa-check"></i> In Cart';
btn.disabled = true;
btn.dataset.inCart = '1';
}
updateCartBadge(data.cart_count);
showSuccessBanner('✅ Added to cart! <a href="/divisions/kinas-marketplace/cart.php" style="color:#155724;font-weight:700;text-decoration:underline;margin-left:6px;">View cart</a>', false);
} else {
if (btn) { btn.innerHTML = original; btn.disabled = false; }
showSuccessBanner('❌ ' + (data.error || 'Failed to add to cart'), true);
}
})
.catch(function() {
if (btn) { btn.innerHTML = original; btn.disabled = false; }
showSuccessBanner('❌ Network error. Please try again.', true);
});
}
function updateCartBadge(count) {
const badge = document.getElementById('jeCartBadge');
const badgeMobile = document.getElementById('jeCartBadgeMobile');
if (badge) {
if (count > 0) { badge.textContent = count; badge.style.display = 'flex'; }
else { badge.style.display = 'none'; }
}
if (badgeMobile) {
if (count > 0) { badgeMobile.textContent = count; badgeMobile.style.display = 'inline-block'; }
else { badgeMobile.style.display = 'none'; }
}
}
// ============================================================
// BUY NOW — skips the cart, goes straight to checkout
// ============================================================
function jeBuyNow(listingId) {
if (!isUserLoggedIn()) {
showLoginRequired();
return;
}
window.location.href = '/divisions/kinas-marketplace/checkout.php?buy_now=' + encodeURIComponent(listingId);
}
// ============================================================
// SAVE LISTING
// ============================================================
function jeSaveListing(type, id) {
console.log('Save clicked!', type, id);
if (!isUserLoggedIn()) {
showLoginRequired();
return;
}
const btn = document.getElementById('saveBtn');
const originalHTML = btn ? btn.innerHTML : '';
if (btn) {
btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
btn.disabled = true;
}
const formData = new FormData();
formData.append('listing_type', type);
formData.append('listing_id', id);
fetch('/api/listings/favorite.php', {
method: 'POST',
body: formData,
credentials: 'same-origin'
})
.then(function(r) { return r.json(); })
.then(function(data) {
if (data.success) {
if (data.action === 'added') {
if (btn) {
btn.innerHTML = '<i class="fas fa-heart" style="color:#28a745;"></i> FAVOURITE';
btn.style.backgroundColor = '#d4edda';
btn.style.color = '#155724';
btn.style.border = '1px solid #28a745';
}
showSuccessBanner('✅ Added to favorites!', false);
} else {
if (btn) {
btn.innerHTML = '<i class="far fa-heart"></i> Save';
btn.style.backgroundColor = '';
btn.style.color = '';
btn.style.border = '';
}
showSuccessBanner('Removed from favorites', false);
}
} else {
if (btn) {
btn.innerHTML = originalHTML;
btn.style.backgroundColor = '';
btn.style.color = '';
btn.style.border = '';
}
showSuccessBanner('❌ ' + (data.error || 'Failed to update favorites'), true);
}
})
.catch(function(error) {
if (btn) {
btn.innerHTML = originalHTML;
btn.style.backgroundColor = '';
btn.style.color = '';
btn.style.border = '';
}
showSuccessBanner('❌ Network error. Please try again.', true);
})
.finally(function() {
if (btn) btn.disabled = false;
});
}
// ============================================================
// CHECK FAVORITE STATE ON LOAD
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
const btn = document.getElementById('saveBtn');
if (!btn) return;
const formData = new FormData();
formData.append('listing_type', 'marketplace');
formData.append('listing_id', '<?= $listingId ?>');
formData.append('check_only', '1');
fetch('/api/listings/favorite.php', {
method: 'POST',
body: formData,
credentials: 'same-origin'
})
.then(function(r) { return r.json(); })
.then(function(data) {
if (data.success && data.action === 'added') {
btn.innerHTML = '<i class="fas fa-heart" style="color:#28a745;"></i> FAVOURITE';
btn.style.backgroundColor = '#d4edda';
btn.style.color = '#155724';
btn.style.border = '1px solid #28a745';
}
})
.catch(function(e) { console.log('Check favorite error:', e); });
});
console.log('=== KINAS MARKETPLACE detail page loaded ===');
</script>
<?php include '../../templates/footer.php'; ?>
