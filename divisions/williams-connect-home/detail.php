<?php
/**
 * WILLIAMS CONNECT HOME — Property detail
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

$id = (int)($_GET['id'] ?? 0);
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
    SELECT p.*, a.verified as agent_verified, a.name as agent_name, a.email as agent_email, a.phone as agent_phone,
           ap.company_name as agent_company, ap.avatar as agent_avatar
    FROM property_listings p
    LEFT JOIN users a ON p.agent_id = a.id
    LEFT JOIN agent_profiles ap ON a.id = ap.user_id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$item = $stmt->fetch();

// Non-active listings (pending review, flagged, etc) are only visible to
// the agent who owns them or an admin previewing before approval.
$isOwnerOrAdmin = $item && SessionManager::isLoggedIn()
    && ((int)$item['agent_id'] === SessionManager::getUserId() || SessionManager::getUserRole() === 'admin');

if (!$item || ($item['status'] !== 'active' && !$isOwnerOrAdmin)) {
    http_response_code(404);
    include __DIR__ . '/../../pages/404.php';
    exit;
}

$isPreview = $item['status'] !== 'active';

if (!$isPreview) {
    $db->prepare("UPDATE property_listings SET views = views + 1 WHERE id = ?")->execute([$id]);
}

$images = $db->prepare("SELECT * FROM listing_images WHERE listing_id = ? AND listing_type = 'property' ORDER BY sort_order");
$images->execute([$id]);
$images = $images->fetchAll();

$similar = $db->prepare("
    SELECT p.id, p.title, p.property_type, p.price, p.beds, p.baths, p.sqft, p.city, p.state,
           (SELECT url FROM listing_images WHERE listing_id = p.id AND listing_type = 'property' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM property_listings p
    WHERE p.id != ? AND p.status = 'active' AND (p.property_type = ? OR p.city = ?)
    ORDER BY p.featured DESC, p.created_at DESC
    LIMIT 4
");
$similar->execute([$id, $item['property_type'] ?? '', $item['city'] ?? '']);
$similar = $similar->fetchAll();

$features = [];
if (!empty($item['features']))     $features = array_merge($features, is_array($item['features'])     ? $item['features']     : (json_decode($item['features'], true) ?: []));
if (!empty($item['amenities']))    $features = array_merge($features, is_array($item['amenities'])    ? $item['amenities']    : (json_decode($item['amenities'], true) ?: []));

$pageTitle = ($item['title'] ?? 'Property') . ' - Williams Connect Home';
$division = 'property';
include '../../templates/header.php';

$locParts = array_filter([$item['city'] ?? null, $item['state'] ?? null, $item['country'] ?? null]);
$location = implode(', ', $locParts);

// Store listing data for JavaScript
$listingId = (int)$item['id'];
$agentId = (int)$item['agent_id'];
$agentName = htmlspecialchars($item['agent_name'] ?? 'Agent', ENT_QUOTES, 'UTF-8');
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
                <div class="je-gallery-main"><img id="jeMainImage" src="<?= htmlspecialchars($images[0]['url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>"></div>
                <?php if (count($images) > 1): ?>
                <div class="je-gallery-thumbs">
                    <?php foreach ($images as $idx => $img): ?>
                        <div class="je-gallery-thumb <?= $idx === 0 ? 'is-active' : '' ?>"
                             onclick="document.getElementById('jeMainImage').src='<?= htmlspecialchars($img['url']) ?>';
                                      document.querySelectorAll('.je-gallery-thumb').forEach(t=>t.classList.remove('is-active'));
                                      this.classList.add('is-active');">
                            <img src="<?= htmlspecialchars($img['url']) ?>" alt="thumb <?= $idx + 1 ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <aside class="je-spec-panel">
            <div class="je-spec-eyebrow">
                <?= htmlspecialchars($item['property_type'] ?? 'Residential') ?>
                · <?= ($item['listing_type'] ?? '') === 'rent' ? 'For Rent' : 'For Sale' ?>
            </div>
            <h1 class="je-spec-title"><?= htmlspecialchars($item['title'] ?? '') ?></h1>
            <?php if ($location): ?><div style="font-size:13px;color:#888;margin-bottom:8px;"><i class="fas fa-map-marker-alt" style="color:#C6A43F"></i> <?= htmlspecialchars($location) ?></div><?php endif; ?>

            <div class="je-spec-price"><?= function_exists('formatPrice') ? formatPrice((float)$item['price']) : '₦' . number_format((float)$item['price']) ?><?php if (($item['listing_type'] ?? '') === 'rent'): ?> <span style="font-size:14px;color:#888;font-weight:400;">/year</span><?php endif; ?></div>

            <dl class="je-spec-key">
                <?php
                $keys = [
                    'Bedrooms'    => ($item['beds'] ?? null) !== null ? (int)$item['beds'] : null,
                    'Bathrooms'   => ($item['baths'] ?? null) !== null ? (int)$item['baths'] : null,
                    'Square Feet' => ($item['sqft'] ?? null) !== null ? number_format((int)$item['sqft']) : null,
                    'Lot Size'    => ($item['lot_size'] ?? null) !== null ? rtrim(rtrim(number_format((float)$item['lot_size'], 2), '0'), '.') . ' acres' : null,
                    'Year Built'  => $item['year_built'] ?? null,
                    'View'        => $item['view_type'] ?? null,
                    'HOA Fees'    => ($item['hoa_fees'] ?? null) !== null ? formatPrice((float)$item['hoa_fees']) . '/mo' : null,
                    'Address'     => $item['address'] ?? null,
                ];
                foreach ($keys as $label => $val):
                    if (!$val) continue;
                ?>
                    <div><dt><?= htmlspecialchars($label) ?></dt><dd><?= htmlspecialchars($val) ?></dd></div>
                <?php endforeach; ?>
            </dl>

            <!-- ============================================================ -->
            <!-- BUTTONS - Using direct onclick with inline JavaScript -->
            <!-- ============================================================ -->
            <div class="je-cta-row">
                <!-- Schedule Viewing -->
                <button class="je-cta-primary" onclick="openScheduleViewing(<?= $listingId ?>, 'property', <?= $agentId ?>);">
                    <i class="far fa-calendar-alt"></i> Schedule Viewing
                </button>
                
                <!-- Contact Agent -->
                <button class="je-cta-secondary" onclick="openContactAgent(<?= $agentId ?>, '<?= $agentName ?>', 'property');">
                    <i class="far fa-envelope"></i> Contact Agent
                </button>
                
                <!-- Save Listing -->
                <button class="je-cta-secondary" onclick="jeSaveListing('property', <?= $listingId ?>);">
                    <i class="far fa-heart"></i> Save
                </button>
            </div>

            <div class="je-agent-card">
                <div class="je-agent-avatar">
                    <?php if (!empty($item['agent_avatar'])): ?>
                        <img src="<?= htmlspecialchars($item['agent_avatar']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        <?= strtoupper(substr($item['agent_name'] ?? 'A', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="je-agent-info">
                    <div class="je-agent-name"><?= htmlspecialchars($item['agent_name'] ?? 'Agent') ?></div>
                    <div class="je-agent-meta">
                        <?= htmlspecialchars($item['agent_company'] ?? 'Independent Agent') ?>
                        <?php if (!empty($item['agent_verified'])): ?>
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

        <?php if (!empty($features)): ?>
        <h2 style="margin-top:32px;">Features &amp; Amenities</h2>
        <div class="je-features-grid">
            <?php foreach ($features as $f): ?>
                <div class="je-feature-pill"><i class="fas fa-check"></i> <?= htmlspecialchars(is_array($f) ? ($f['name'] ?? json_encode($f)) : $f) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($similar)): ?>
    <section class="je-section" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8;">
        <h2>Similar properties</h2>
        <?php
        $simCards = array_map(function ($s) {
            $specParts = array_filter([
                ($s['beds'] ?? null) !== null ? (int)$s['beds'] . ' bd' : null,
                ($s['baths'] ?? null) !== null ? (int)$s['baths'] . ' ba' : null,
                ($s['sqft'] ?? null) !== null ? number_format((int)$s['sqft']) . ' sqft' : null,
            ]);
            return [
                'id'         => $s['id'],
                'title'      => $s['title'] ?? '',
                'division'   => 'WILLIAMS CONNECT HOME',
                'price'      => $s['price'],
                'thumbnail'  => $s['thumbnail'] ?: '',
                'specs'      => implode(' • ', $specParts),
                'location'   => trim(($s['city'] ?? '') . ', ' . ($s['state'] ?? ''), ', '),
                'detail_url' => 'detail.php?id=' . (int)$s['id'],
                'featured'   => false,
                'verified'   => false,
            ];
        }, $similar);
        echo '<div class="je-listings-grid" style="grid-template-columns:repeat(4,1fr);">';
        foreach ($simCards as $c) je_render_card($c);
        echo '</div>';
        ?>
    </section>
    <?php endif; ?>

</div>
</div>

<?php include __DIR__ . '/../../templates/modal/contact-agent-modal.php'; ?>

<!-- ============================================================ -->
<!-- JAVASCRIPT - IMMEDIATELY AFTER THE CONTENT -->
<!-- ============================================================ -->
<script>
// ============================================================
// SIMPLE TEST - Verify JavaScript is working
// ============================================================
console.log('=== JavaScript loaded successfully ===');

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
    alert('Please login to continue');
    setTimeout(function() {
        window.location.href = '/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
    }, 1500);
}

// ============================================================
// SCHEDULE VIEWING
// ============================================================

function openScheduleViewing(listingId, listingType, agentId) {
    console.log('openScheduleViewing called!', listingId, listingType, agentId);
    
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }
    
    // Simple alert for testing
    alert('Schedule Viewing clicked!\n\nListing ID: ' + listingId + '\nType: ' + listingType + '\nAgent ID: ' + agentId);
    
    // Open the schedule modal (will add this next)
    // For now, just show the alert
}

// ============================================================
// CONTACT AGENT
// ============================================================

function openContactAgent(agentId, agentName, division) {
    console.log('openContactAgent called!', agentId, agentName, division);
    
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }
    
    // Simple alert for testing
    alert('Contact Agent clicked!\n\nAgent: ' + agentName + '\nDivision: ' + division + '\nAgent ID: ' + agentId);
}

// ============================================================
// SAVE LISTING
// ============================================================

function jeSaveListing(type, id) {
    console.log('jeSaveListing called!', type, id);
    
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }
    
    // Simple alert for testing
    alert('Save Listing clicked!\n\nType: ' + type + '\nID: ' + id);
}

// ============================================================
// PAGE INITIALIZATION
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('=== Page fully loaded ===');
    console.log('Listing ID: <?= $listingId ?>');
    console.log('Agent ID: <?= $agentId ?>');
    console.log('Agent Name: <?= $agentName ?>');
});
</script>

<?php include '../../templates/footer.php'; ?>
