<?php
/**
 * KINAS BUILD: 2026.08.15.09
 * FILE: divisions/kinas-marketplace/detail.php
 *
 * KINAS MARKETPLACE — Item detail
 *
 * RESTORED:
 * - Contact Seller button.
 * - Contact Seller inquiry modal.
 *
 * KEEPS:
 * - Buy Now.
 * - Add to Cart.
 * - Save.
 * - Product reviews.
 * - Dynamic related products.
 * - Sold/completed public visibility for reviews.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

// Related products engine.
$kinasRelatedProductsEngine = __DIR__ . '/../../includes/related-products.php';
if (file_exists($kinasRelatedProductsEngine)) {
    require_once $kinasRelatedProductsEngine;
}

$id = (int)($_GET['id'] ?? 0);

$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
    SELECT m.*, c.name AS category_name, c.slug AS category_slug,
           a.verified as agent_verified, a.name as agent_name, a.email as agent_email, a.phone as agent_phone,
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
    try {
        $db->prepare("UPDATE marketplace_listings SET views = views + 1 WHERE id = ?")->execute([$id]);
    } catch (Throwable $e) {
        // Views column may not exist.
    }
}

$images = $db->prepare("
    SELECT *
    FROM listing_images
    WHERE listing_id = ?
      AND listing_type = 'marketplace'
    ORDER BY sort_order
");
$images->execute([$id]);
$images = $images->fetchAll();

// ============================================================
// RELATED PRODUCTS
// ============================================================
$similar = [];

if (function_exists('kinas_get_related_marketplace_items')) {
    $similar = kinas_get_related_marketplace_items($db, $item, 4);
} else {
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

if (!empty($images[0]['url'])) {
    $pageImage = $images[0]['url'];
}

$division = 'marketplace';

include '../../templates/header.php';

$locParts = array_filter([
    $item['city'] ?? null,
    $item['state'] ?? null,
    $item['country'] ?? null,
]);

$location = implode(', ', $locParts);

$listingId = (int)$item['id'];
$agentId = (int)$item['agent_id'];

$agentNameRaw = $item['agent_name'] ?? 'Seller';
$listingTitleRaw = $item['title'] ?? 'Item';

$agentName = htmlspecialchars($agentNameRaw, ENT_QUOTES, 'UTF-8');
$listingTitle = htmlspecialchars($listingTitleRaw, ENT_QUOTES, 'UTF-8');

$agentVerified = !empty($item['agent_verified']);

$alreadyInCart = false;

if (SessionManager::isLoggedIn()) {
    try {
        $cartCheckStmt = $db->prepare("
            SELECT 1
            FROM cart_items
            WHERE buyer_id = ?
              AND listing_id = ?
              AND listing_type = 'marketplace'
        ");
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
                <i class="fas fa-eye"></i>
                <strong>Preview only</strong> — this listing is <?= htmlspecialchars(ucfirst((string)($item['status'] ?? ''))) ?> and not visible to the public yet.
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
                <a href="/divisions/kinas-marketplace/search.php?category=<?= (int)$item['category_id'] ?>">
                    <?= htmlspecialchars($item['category_name']) ?>
                </a>
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
                    <div class="je-gallery-main">
                        <img id="jeMainImage"
                             src="<?= htmlspecialchars($images[0]['url']) ?>"
                             alt="<?= htmlspecialchars($item['title'] ?? 'Item') ?>"
                             onerror="this.onerror=null; this.src='/assets/images/placeholder/product-placeholder.svg';">
                    </div>

                    <?php if (count($images) > 1): ?>
                        <div class="je-gallery-thumbs">
                            <?php foreach ($images as $idx => $img): ?>
                                <div class="je-gallery-thumb <?= $idx === 0 ? 'is-active' : '' ?>"
                                     onclick="document.getElementById('jeMainImage').src='<?= htmlspecialchars($img['url']) ?>';
                                              document.querySelectorAll('.je-gallery-thumb').forEach(t=>t.classList.remove('is-active'));
                                              this.classList.add('is-active');">
                                    <img src="<?= htmlspecialchars($img['url']) ?>"
                                         alt="thumb <?= $idx + 1 ?>"
                                         onerror="this.onerror=null; this.src='/assets/images/placeholder/product-placeholder.svg';">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <aside class="je-spec-panel">
                <div class="je-spec-eyebrow"><?= htmlspecialchars($item['category_name'] ?? 'Curated') ?></div>

                <h1 class="je-spec-title"><?= htmlspecialchars($item['title'] ?? '') ?></h1>

                <?php if ($location): ?>
                    <div style="font-size:13px;color:#888;margin-bottom:8px;">
                        <i class="fas fa-map-marker-alt" style="color:#C6A43F"></i>
                        <?= htmlspecialchars($location) ?>
                    </div>
                <?php endif; ?>

                <div class="je-spec-price">
                    <?php
                    if (function_exists('marketplaceBuyerPrice') && function_exists('formatPrice')) {
                        echo formatPrice(marketplaceBuyerPrice((float)$item['price']));
                    } elseif (function_exists('formatPrice')) {
                        echo formatPrice((float)$item['price']);
                    } else {
                        echo '₦' . number_format((float)$item['price']);
                    }
                    ?>
                </div>

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
                        <div>
                            <dt><?= htmlspecialchars($label) ?></dt>
                            <dd><?= htmlspecialchars(ucfirst($val)) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>

                <!-- ============================================================ -->
                <!-- BUTTONS -->
                <!-- ============================================================ -->
                <?php if ($isPreview || ($item['status'] ?? '') !== 'active'): ?>
                    <div class="je-cta-row">
                        <button class="je-cta-secondary" disabled style="opacity:.55;cursor:not-allowed;">
                            <i class="fas fa-lock"></i>
                            <?= ($item['status'] ?? '') === 'sold' ? 'Sold' : htmlspecialchars(ucfirst((string)($item['status'] ?? 'Unavailable'))) ?>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="je-cta-row">
                        <button class="je-cta-primary" id="buyNowBtn" onclick="jeBuyNow(<?= $listingId ?>);">
                            <i class="fas fa-bolt"></i> Buy Now
                        </button>

                        <button class="je-cta-secondary"
                                id="addToCartBtn"
                                data-in-cart="<?= $alreadyInCart ? '1' : '0' ?>"
                                onclick="jeAddToCart(<?= $listingId ?>);"
                                <?= $alreadyInCart ? 'disabled' : '' ?>>
                            <?php if ($alreadyInCart): ?>
                                <i class="fas fa-check"></i> In Cart
                            <?php else: ?>
                                <i class="fas fa-shopping-bag"></i> Add to Cart
                            <?php endif; ?>
                        </button>

                        <button class="je-cta-secondary" id="saveBtn" onclick="jeSaveListing('marketplace', <?= $listingId ?>);">
                            <i class="far fa-heart"></i> Save
                        </button>

                        <button class="je-cta-secondary" id="contactSellerBtn" onclick="openMarketplaceContactModal();">
                            <i class="far fa-envelope"></i> Contact Seller
                        </button>
                    </div>
                <?php endif; ?>

                <div class="je-agent-card">
                    <div class="je-agent-avatar">
                        <?php if (!empty($item['agent_avatar'])): ?>
                            <img src="<?= htmlspecialchars($item['agent_avatar']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($agentNameRaw, 0, 1)) ?>
                        <?php endif; ?>
                    </div>

                    <div class="je-agent-info">
                        <div class="je-agent-name"><?= $agentName ?></div>
                        <div class="je-agent-meta">
                            <?= htmlspecialchars($item['agent_company'] ?? 'Verified Seller') ?>
                            <?php if ($agentVerified): ?>
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
        <!-- PRODUCT REVIEWS -->
        <!-- ============================================================ -->
        <?php if (!$isPreview): ?>
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

<script>
// ============================================================
// SAFE PAGE CONSTANTS
// ============================================================
window.__kinasAgentName = <?= json_encode($agentNameRaw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.__kinasListingTitle = <?= json_encode($listingTitleRaw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.__kinasAgentVerified = <?= $agentVerified ? 'true' : 'false' ?>;

function marketplaceEscapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

function marketplaceNotify(message, isError) {
    if (typeof window.showSuccessBanner === 'function') {
        window.showSuccessBanner(message, !!isError);
        return;
    }

    if (typeof kinasToast === 'function') {
        kinasToast(message, isError ? 'error' : 'success');
        return;
    }

    alert(message);
}

// ============================================================
// HELPERS
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
    marketplaceNotify('Please sign in to continue — redirecting you to login…', true);

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
            marketplaceNotify('✅ Added to cart! <a href="/divisions/kinas-marketplace/cart.php" style="color:#155724;font-weight:700;text-decoration:underline;margin-left:6px;">View cart</a>', false);
        } else {
            if (btn) {
                btn.innerHTML = original;
                btn.disabled = false;
            }

            marketplaceNotify('❌ ' + (data.error || 'Failed to add to cart'), true);
        }
    })
    .catch(function() {
        if (btn) {
            btn.innerHTML = original;
            btn.disabled = false;
        }

        marketplaceNotify('❌ Network error. Please try again.', true);
    });
}

function updateCartBadge(count) {
    const badge = document.getElementById('jeCartBadge');
    const badgeMobile = document.getElementById('jeCartBadgeMobile');

    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    if (badgeMobile) {
        if (count > 0) {
            badgeMobile.textContent = count;
            badgeMobile.style.display = 'inline-block';
        } else {
            badgeMobile.style.display = 'none';
        }
    }
}

// ============================================================
// BUY NOW
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

                marketplaceNotify('✅ Added to favorites!', false);
            } else {
                if (btn) {
                    btn.innerHTML = '<i class="far fa-heart"></i> Save';
                    btn.style.backgroundColor = '';
                    btn.style.color = '';
                    btn.style.border = '';
                }

                marketplaceNotify('Removed from favorites', false);
            }
        } else {
            if (btn) {
                btn.innerHTML = originalHTML;
                btn.style.backgroundColor = '';
                btn.style.color = '';
                btn.style.border = '';
            }

            marketplaceNotify('❌ ' + (data.error || 'Failed to update favorites'), true);
        }
    })
    .catch(function() {
        if (btn) {
            btn.innerHTML = originalHTML;
            btn.style.backgroundColor = '';
            btn.style.color = '';
            btn.style.border = '';
        }

        marketplaceNotify('❌ Network error. Please try again.', true);
    })
    .finally(function() {
        if (btn) btn.disabled = false;
    });
}

// ============================================================
// CONTACT SELLER MODAL
// ============================================================
function openMarketplaceContactModal() {
    const old = document.getElementById('marketplace-contact-modal');
    if (old) old.remove();

    const verifiedBadge = window.__kinasAgentVerified
        ? '<span style="color:#1B5E20;">✓ Verified</span>'
        : '';

    const html = `
    <div id="marketplace-contact-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:999998;display:flex;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:30px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;font-size:20px;">✉️ Contact Seller</h3>
                <button onclick="document.getElementById('marketplace-contact-modal').remove()" style="background:none;border:none;font-size:24px;cursor:pointer;">✕</button>
            </div>

            <div style="padding:12px;background:#f5f5f5;border-radius:8px;margin-bottom:20px;">
                <strong>${marketplaceEscapeHtml(window.__kinasAgentName)}</strong> ${verifiedBadge} · Marketplace
            </div>

            <form id="marketplaceContactForm">
                <input type="hidden" name="listing_id" value="<?= $listingId ?>">
                <input type="hidden" name="listing_type" value="marketplace">
                <input type="hidden" name="agent_id" value="<?= $agentId ?>">

                <input type="text" name="website" value="" style="display:none !important;" tabindex="-1" autocomplete="off">

                <div style="margin-bottom:12px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Your Name *</label>
                    <input type="text" name="name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:12px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Your Email *</label>
                    <input type="email" name="email" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:12px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Your Phone</label>
                    <input type="tel" name="phone" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:12px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Subject</label>
                    <input type="text" name="subject" value="Inquiry about marketplace item" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Message *</label>
                    <textarea name="message" rows="5" required placeholder="Hi, I'm interested in this item..." style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;resize:vertical;"></textarea>
                </div>

                <button type="submit" style="width:100%;padding:12px;background:#0A0A0A;color:#fff;border:none;border-radius:6px;font-size:16px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-paper-plane"></i> Send Inquiry
                </button>

                <div id="marketplaceContactMsg" style="margin-top:12px;padding:10px;border-radius:6px;display:none;"></div>
            </form>
        </div>
    </div>
    `;

    document.body.insertAdjacentHTML('beforeend', html);

    const modal = document.getElementById('marketplace-contact-modal');

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });

    const meta = document.querySelector('meta[name="user-data"]');
    if (meta) {
        try {
            const data = JSON.parse(meta.content);
            const form = document.getElementById('marketplaceContactForm');

            if (form) {
                form.querySelector('input[name="name"]').value = data.name || '';
                form.querySelector('input[name="email"]').value = data.email || '';
                form.querySelector('input[name="phone"]').value = data.phone || '';
            }
        } catch (e) {}
    }

    document.getElementById('marketplaceContactForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = this.querySelector('button[type="submit"]');
        const msg = document.getElementById('marketplaceContactMsg');
        const original = btn.innerHTML;

        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        btn.disabled = true;
        msg.style.display = 'none';

        const formData = new FormData(this);

        try {
            const res = await fetch('/api/messages/send-inquiry.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await res.json();

            if (data.success) {
                const modal = document.getElementById('marketplace-contact-modal');
                if (modal) modal.remove();

                marketplaceNotify('✅ Inquiry sent successfully! The seller will contact you shortly.', false);
            } else {
                msg.style.display = 'block';
                msg.style.background = '#f8d7da';
                msg.style.color = '#721c24';
                msg.textContent = data.error || 'Failed to send. Please try again.';
                btn.innerHTML = original;
                btn.disabled = false;
            }
        } catch (error) {
            msg.style.display = 'block';
            msg.style.background = '#f8d7da';
            msg.style.color = '#721c24';
            msg.textContent = 'Network error. Please try again.';
            btn.innerHTML = original;
            btn.disabled = false;
        }
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
    .catch(function(e) {
        console.log('Check favorite error:', e);
    });
});
</script>

<?php include '../../templates/footer.php'; ?>
