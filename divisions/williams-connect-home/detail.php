<?php
/**
 * WILLIAMS CONNECT HOME — Property detail
 *
 * COMPLETE REBUILT FILE
 *
 * Fixes:
 * - Uses property_listings only.
 * - Uses listing_type = 'property' for images.
 * - Similar listings remain inside Williams Connect Home.
 * - Prevents cross-division links.
 * - Reduces false 404s by using safer status handling.
 * - Adds Contact Agent / Inquiry modal.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../includes/security.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

$id = (int)($_GET['id'] ?? 0);

$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
    SELECT p.*,
           u.id AS agent_user_id,
           u.name AS agent_name,
           u.verified AS agent_verified,
           u.email AS agent_email,
           u.phone AS agent_phone,
           ap.company_name AS agent_company,
           ap.avatar AS agent_avatar
    FROM property_listings p
    LEFT JOIN users u ON p.agent_id = u.id
    LEFT JOIN agent_profiles ap ON u.id = ap.user_id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

// ============================================================
// VISIBILITY RULES
// ============================================================
$publicStatuses = [
    'active',
    'sold',
    'rented',
    'pending',
    'completed',
    'under_offer',
    'pending_sale',
];

$isOwnerOrAdmin = $item && SessionManager::isLoggedIn()
    && ((int)($item['agent_id'] ?? 0) === SessionManager::getUserId() || SessionManager::getUserRole() === 'admin');

$isPublicStatus = $item && in_array((string)($item['status'] ?? ''), $publicStatuses, true);

if (!$item || (!$isPublicStatus && !$isOwnerOrAdmin)) {
    http_response_code(404);
    include __DIR__ . '/../../pages/404.php';
    exit;
}

$isPreview = !$isPublicStatus;

// Increment views only for active listings.
if (!$isPreview && ($item['status'] ?? '') === 'active') {
    try {
        $db->prepare("UPDATE property_listings SET views = views + 1 WHERE id = ?")->execute([$id]);
    } catch (Throwable $e) {
        // Views column may not exist on all schemas.
    }
}

// Correct division image type.
$imagesStmt = $db->prepare("
    SELECT *
    FROM listing_images
    WHERE listing_id = ?
      AND listing_type = 'property'
    ORDER BY sort_order
");
$imagesStmt->execute([$id]);
$images = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);

// Correct division similar listings.
$similarStmt = $db->prepare("
    SELECT p.id, p.title, p.price,
           (SELECT url FROM listing_images WHERE listing_id = p.id AND listing_type = 'property' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM property_listings p
    WHERE p.status = 'active'
      AND p.id != ?
    ORDER BY RAND()
    LIMIT 4
");
$similarStmt->execute([$id]);
$similar = $similarStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$pageTitle = ($item['title'] ?? 'Property') . ' - Williams Connect Home';
$pageDescription = !empty($item['description'])
    ? substr(strip_tags($item['description']), 0, 160)
    : 'View ' . ($item['title'] ?? 'this property') . ' on Williams Connect Home.';

if (!empty($images[0]['url'])) {
    $pageImage = $images[0]['url'];
}

$division = 'property';

include '../../templates/header.php';

$listingId = (int)$item['id'];
$agentId = (int)($item['agent_id'] ?? 0);
$agentNameRaw = $item['agent_name'] ?? 'Agent';
$agentVerified = !empty($item['agent_verified']);

$locParts = array_filter([
    $item['city'] ?? null,
    $item['state'] ?? null,
    $item['country'] ?? null,
]);

$location = implode(', ', $locParts);

$specs = [
    'Property Type' => $item['property_type'] ?? null,
    'Bedrooms'      => $item['beds'] ?? ($item['bedrooms'] ?? null),
    'Bathrooms'     => $item['baths'] ?? ($item['bathrooms'] ?? null),
    'Size'          => isset($item['sqft']) ? $item['sqft'] . ' sqft' : ($item['size'] ?? null),
    'Year Built'    => $item['year_built'] ?? null,
    'Condition'     => $item['condition_status'] ?? ($item['condition'] ?? null),
    'City'          => $item['city'] ?? null,
    'State'         => $item['state'] ?? null,
    'Country'       => $item['country'] ?? null,
];
?>

<div class="je-page">
    <div class="je-detail-wrap">

        <?php if ($isPreview): ?>
            <div style="background:#FFF8E1; border:1px solid #F0C419; color:#7A5B00; padding:14px 18px; border-radius:4px; margin-bottom:20px; font-size:14px;">
                <i class="fas fa-eye"></i> <strong>Preview only</strong> — this listing is <?= htmlspecialchars(ucfirst((string)($item['status'] ?? ''))) ?> and may not be visible to the public.
            </div>
        <?php endif; ?>

        <?php if (!$isPreview && !in_array((string)($item['status'] ?? ''), ['active'], true)): ?>
            <div style="background:#F5F5F5; border:1px solid #E0E0E0; color:#555; padding:12px 16px; border-radius:4px; margin-bottom:20px; font-size:13px;">
                <i class="fas fa-info-circle"></i>
                This listing is currently marked as <strong><?= htmlspecialchars(ucfirst((string)($item['status'] ?? ''))) ?></strong>.
            </div>
        <?php endif; ?>

        <div class="je-breadcrumb">
            <a href="/">Home</a>
            <span class="je-breadcrumb-sep">/</span>
            <a href="/divisions/williams-connect-home/">WILLIAMS CONNECT HOME</a>
            <span class="je-breadcrumb-sep">/</span>
            <a href="/divisions/williams-connect-home/search.php">Search</a>
            <span class="je-breadcrumb-sep">/</span>
            <span><?= htmlspecialchars($item['title'] ?? '') ?></span>
        </div>

        <div class="je-detail-grid">
            <div>
                <?php if (empty($images)): ?>
                    <div class="je-gallery-main" style="background:linear-gradient(135deg,#1a1a1a,#0a0a0a); display:flex; align-items:center; justify-content:center; color:#C6A43F; font-size:64px;">
                        <i class="fas fa-home"></i>
                    </div>
                <?php else: ?>
                    <div class="je-gallery-main">
                        <img id="jeMainImage" src="<?= htmlspecialchars($images[0]['url']) ?>" alt="" onerror="this.onerror=null; this.src='/assets/images/placeholder/property-placeholder.svg';">
                    </div>

                    <?php if (count($images) > 1): ?>
                        <div class="je-gallery-thumbs">
                            <?php foreach ($images as $idx => $img): ?>
                                <div class="je-gallery-thumb <?= $idx === 0 ? 'is-active' : '' ?>"
                                     onclick="document.getElementById('jeMainImage').src='<?= htmlspecialchars($img['url']) ?>';
                                              document.querySelectorAll('.je-gallery-thumb').forEach(t=>t.classList.remove('is-active'));
                                              this.classList.add('is-active');">
                                    <img src="<?= htmlspecialchars($img['url']) ?>" alt="" onerror="this.onerror=null; this.src='/assets/images/placeholder/property-placeholder.svg';">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <aside class="je-spec-panel">
                <div class="je-spec-eyebrow">Williams Connect Home</div>
                <h1 class="je-spec-title"><?= htmlspecialchars($item['title'] ?? '') ?></h1>

                <?php if ($location): ?>
                    <div style="font-size:13px;color:#888;margin-bottom:8px;">
                        <i class="fas fa-map-marker-alt" style="color:#C6A43F"></i> <?= htmlspecialchars($location) ?>
                    </div>
                <?php endif; ?>

                <div class="je-spec-price"><?= formatPrice((float)($item['price'] ?? 0)) ?></div>

                <dl class="je-spec-key">
                    <?php foreach ($specs as $label => $val): ?>
                        <?php if ($val === null || $val === '') continue; ?>
                        <div>
                            <dt><?= htmlspecialchars($label) ?></dt>
                            <dd><?= htmlspecialchars(ucfirst((string)$val)) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>

                <div class="je-cta-row">
                    <?php if ((string)($item['status'] ?? '') === 'active'): ?>
                        <button class="je-cta-primary"
                                onclick="openContactAgentModal(
                                    <?= $listingId ?>,
                                    'property',
                                    <?= $agentId ?>,
                                    <?= htmlspecialchars(json_encode($agentNameRaw), ENT_QUOTES, 'UTF-8') ?>,
                                    <?= $agentVerified ? 'true' : 'false' ?>,
                                    'Real Estate'
                                );">
                            <i class="fas fa-envelope"></i> Contact Agent
                        </button>
                    <?php else: ?>
                        <button class="je-cta-secondary" disabled style="opacity:.55;cursor:not-allowed;">
                            <i class="fas fa-lock"></i> <?= htmlspecialchars(ucfirst((string)($item['status'] ?? 'Unavailable'))) ?>
                        </button>
                    <?php endif; ?>

                    <button class="je-cta-secondary" id="saveBtn" onclick="wchSaveListing(<?= $listingId ?>);">
                        <i class="far fa-heart"></i> Save
                    </button>
                </div>

                <div class="je-agent-card">
                    <div class="je-agent-avatar">
                        <?php if (!empty($item['agent_avatar'])): ?>
                            <img src="<?= htmlspecialchars($item['agent_avatar']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($agentNameRaw, 0, 1)) ?>
                        <?php endif; ?>
                    </div>

                    <div class="je-agent-info">
                        <div class="je-agent-name"><?= htmlspecialchars($agentNameRaw) ?></div>
                        <div class="je-agent-meta">
                            <?= htmlspecialchars($item['agent_company'] ?? 'Verified Agent') ?>
                            <?php if ($agentVerified): ?>
                                · <span style="color:#1B5E20;font-weight:600;">✓ Verified</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <section class="je-section" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8; margin-top:40px;">
            <h2>About this property</h2>
            <?php if (!empty($item['description'])): ?>
                <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
            <?php else: ?>
                <p style="color:#999;font-style:italic;">No description provided.</p>
            <?php endif; ?>
        </section>

        <?php if (!empty($item['features'])): ?>
            <section class="je-section" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8;">
                <h2>Features</h2>
                <p><?= nl2br(htmlspecialchars($item['features'])) ?></p>
            </section>
        <?php endif; ?>

        <?php if (!empty($similar)): ?>
            <section class="je-section" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8;">
                <h2>You may also like</h2>
                <?php
                $simCards = array_map(function ($s) {
                    return [
                        'id'         => $s['id'],
                        'title'      => $s['title'] ?? '',
                        'division'   => 'WILLIAMS CONNECT HOME',
                        'price'      => $s['price'],
                        'thumbnail'  => $s['thumbnail'] ?: '',
                        'specs'      => '',
                        'location'   => '',
                        'detail_url' => '/divisions/williams-connect-home/detail.php?id=' . (int)$s['id'],
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
            <?php
            $kinasReviewsEngine = __DIR__ . '/../../includes/reviews.php';

            if (file_exists($kinasReviewsEngine)) {
                require_once $kinasReviewsEngine;

                if (function_exists('kinas_render_reviews_section')) {
                    kinas_render_reviews_section($db, 'property', $listingId);
                }
            }
            ?>
        <?php endif; ?>

    </div>
</div>

<script>
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

function wchSaveListing(id) {
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
    formData.append('listing_type', 'property');
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

                if (typeof window.showSuccessBanner === 'function') {
                    window.showSuccessBanner('✅ Added to favorites!', false);
                }
            } else {
                if (btn) {
                    btn.innerHTML = '<i class="far fa-heart"></i> Save';
                    btn.style.backgroundColor = '';
                    btn.style.color = '';
                    btn.style.border = '';
                }

                if (typeof window.showSuccessBanner === 'function') {
                    window.showSuccessBanner('Removed from favorites', false);
                }
            }
        } else {
            if (btn) {
                btn.innerHTML = originalHTML;
                btn.style.backgroundColor = '';
                btn.style.color = '';
                btn.style.border = '';
            }

            if (typeof window.showSuccessBanner === 'function') {
                window.showSuccessBanner('❌ ' + (data.error || 'Failed to update favorites'), true);
            }
        }
    })
    .catch(function() {
        if (btn) {
            btn.innerHTML = originalHTML;
            btn.style.backgroundColor = '';
            btn.style.color = '';
            btn.style.border = '';
        }

        if (typeof window.showSuccessBanner === 'function') {
            window.showSuccessBanner('❌ Network error. Please try again.', true);
        }
    })
    .finally(function() {
        if (btn) btn.disabled = false;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('saveBtn');
    if (!btn) return;

    const formData = new FormData();
    formData.append('listing_type', 'property');
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

<?php include '../../templates/modal/contact-agent-modal.php'; ?>
<?php include '../../templates/footer.php'; ?>
