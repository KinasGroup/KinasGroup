<?php
/**
 * KINAS MARKETPLACE — Item detail
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

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

$isOwnerOrAdmin = $item && SessionManager::isLoggedIn()
    && ((int)$item['agent_id'] === SessionManager::getUserId() || SessionManager::getUserRole() === 'admin');

if (!$item || ($item['status'] !== 'active' && !$isOwnerOrAdmin)) {
    http_response_code(404);
    include __DIR__ . '/../../pages/404.php';
    exit;
}

$isPreview = $item['status'] !== 'active';

if (!$isPreview) {
    $db->prepare("UPDATE marketplace_listings SET views = views + 1 WHERE id = ?")->execute([$id]);
}

$images = $db->prepare("SELECT * FROM listing_images WHERE listing_id = ? AND listing_type = 'marketplace' ORDER BY sort_order");
$images->execute([$id]);
$images = $images->fetchAll();

$similar = $db->prepare("
    SELECT m.id, m.title, m.price, m.brand, m.category_id, c.name AS category_name,
           (SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM marketplace_listings m
    LEFT JOIN marketplace_categories c ON m.category_id = c.id
    WHERE m.id != ? AND m.status = 'active' AND (m.category_id = ? OR m.brand = ?)
    ORDER BY m.featured DESC, m.created_at DESC
    LIMIT 4
");
$similar->execute([$id, $item['category_id'] ?? 0, $item['brand'] ?? '']);
$similar = $similar->fetchAll();

$pageTitle = ($item['title'] ?? 'Item') . ' - KINAS Marketplace';
$division = 'marketplace';
include '../../templates/header.php';

$locParts = array_filter([$item['city'] ?? null, $item['state'] ?? null, $item['country'] ?? null]);
$location = implode(', ', $locParts);

$listingId = (int)$item['id'];
$agentId = (int)$item['agent_id'];
$agentName = htmlspecialchars($item['agent_name'] ?? 'Seller', ENT_QUOTES, 'UTF-8');
$listingTitle = htmlspecialchars($item['title'] ?? 'Item', ENT_QUOTES, 'UTF-8');
$agentVerified = !empty($item['agent_verified']);
?>

<div class="je-page">
<div class="je-detail-wrap">

<?php if ($isPreview): ?>
<div style="background:#FFF8E1; border:1px solid #F0C419; color:#7A5B00; padding:14px 18px; border-radius:4px; margin-bottom:20px; font-size:14px;">
    <i class="fas fa-eye"></i> <strong>Preview only</strong> — this listing is <?= htmlspecialchars(ucfirst($item['status'])) ?> and not visible to the public yet. Only you and admins can see this page.
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
                <div class="je-gallery-main"><img id="jeMainImage" src="<?= htmlspecialchars($images[0]['url']) ?>" alt=""></div>
                <?php if (count($images) > 1): ?>
                <div class="je-gallery-thumbs">
                    <?php foreach ($images as $idx => $img): ?>
                        <div class="je-gallery-thumb <?= $idx === 0 ? 'is-active' : '' ?>"
                             onclick="document.getElementById('jeMainImage').src='<?= htmlspecialchars($img['url']) ?>';
                                      document.querySelectorAll('.je-gallery-thumb').forEach(t=>t.classList.remove('is-active'));
                                      this.classList.add('is-active');">
                            <img src="<?= htmlspecialchars($img['url']) ?>" alt="">
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

            <div class="je-spec-price"><?= function_exists('formatPrice') ? formatPrice((float)$item['price']) : '₦' . number_format((float)$item['price']) ?></div>

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
                <button class="je-cta-secondary" id="addToCartBtn" onclick="jeAddToCart(<?= $listingId ?>);">
                    <i class="fas fa-shopping-bag"></i> Add to Cart
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
                    <div class="je-agent-name"><?= htmlspecialchars($item['agent_name'] ?? 'Seller') ?></div>
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

    <!-- ── Similar listings ── FIXED -->
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
                // FIXED: Full path to detail page
                'detail_url' => '/divisions/kinas-marketplace/detail.php?id=' . (int)$s['id'],
                'featured'   => false,
                'verified'   => false,
            ];
        }, $similar);
        // FIXED: Use je_render_listing_grid instead of je_render_card
        je_render_listing_grid($simCards);
        ?>
    </section>
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
    // In-page, mobile-responsive banner instead of a native browser
    // alert() (which shows as a generic "kinas-group.com says: ..." popup).
    if (typeof kinasToast === 'function') {
        kinasToast('Please sign in to continue — redirecting you to login…', 'warning');
    } else if (typeof window.showSuccessBanner === 'function') {
        window.showSuccessBanner('Please sign in to continue — redirecting you to login…', true);
    }
    setTimeout(function() {
        window.location.href = '/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
    }, 1500);
}

// ============================================================
// GREEN SUCCESS BANNER
// ============================================================

function showSuccessBanner(message, isError) {
    const existing = document.querySelectorAll('.custom-success-banner');
    existing.forEach(function(b) { b.remove(); });
    
    const banner = document.createElement('div');
    banner.className = 'custom-success-banner';
    const bgColor = isError ? '#f8d7da' : '#d4edda';
    const textColor = isError ? '#721c24' : '#155724';
    const borderColor = isError ? '#dc3545' : '#28a745';
    const icon = isError ? 'fa-exclamation-circle' : 'fa-check-circle';
    
    banner.style.cssText = 'position:fixed;top:100px;right:20px;z-index:100000;padding:16px 24px;background:' + bgColor + ';color:' + textColor + ';border-left:4px solid ' + borderColor + ';border-radius:8px;font-family:Inter,sans-serif;font-size:14px;font-weight:500;box-shadow:0 8px 30px rgba(0,0,0,0.15);max-width:450px;display:flex;align-items:center;gap:12px;';
    banner.innerHTML = '<i class="fas ' + icon + '" style="color:' + borderColor + ';font-size:18px;"></i><span>' + message + '</span><button onclick="this.parentElement.remove()" style="background:none;border:none;font-size:18px;cursor:pointer;color:' + textColor + ';margin-left:auto;">✕</button>';
    document.body.appendChild(banner);
    
    setTimeout(function() {
        if (banner.parentElement) {
            banner.style.opacity = '0';
            banner.style.transition = 'opacity 0.3s ease';
            setTimeout(function() { banner.remove(); }, 300);
        }
    }, 5000);
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
            }
            updateCartBadge(data.cart_count);
            showSuccessBanner('✅ Added to cart! <a href="/divisions/kinas-marketplace/cart.php" style="color:#155724;font-weight:700;text-decoration:underline;margin-left:6px;">View cart</a>', false);
            setTimeout(function() {
                if (btn) { btn.innerHTML = original; btn.disabled = false; }
            }, 2000);
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
    if (!badge) return;
    if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
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
