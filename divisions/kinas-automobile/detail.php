<?php
/**
 * KINAS AUTOMOBILE — Listing detail page
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

$id = (int)($_GET['id'] ?? 0);
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
    SELECT c.*, a.verified as agent_verified, a.name as agent_name, a.email as agent_email, a.phone as agent_phone,
           ap.company_name as agent_company, ap.avatar as agent_avatar
    FROM car_listings c
    LEFT JOIN users a ON c.agent_id = a.id
    LEFT JOIN agent_profiles ap ON a.id = ap.user_id
    WHERE c.id = ? AND c.status = 'active'
");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    include __DIR__ . '/../../pages/404.php';
    exit;
}

$db->prepare("UPDATE car_listings SET views = views + 1 WHERE id = ?")->execute([$id]);

$images = $db->prepare("SELECT * FROM listing_images WHERE listing_id = ? AND listing_type = 'car' ORDER BY sort_order");
$images->execute([$id]);
$images = $images->fetchAll();

$similar = $db->prepare("
    SELECT c.id, c.title, c.brand, c.model, c.year, c.price,
           (SELECT url FROM listing_images WHERE listing_id = c.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM car_listings c
    WHERE c.id != ? AND c.status = 'active' AND (c.brand = ? OR c.body_type = ?)
    ORDER BY c.featured DESC, c.created_at DESC
    LIMIT 4
");
$similar->execute([$id, $item['brand'] ?? '', $item['body_type'] ?? '']);
$similar = $similar->fetchAll();

$features = [];
if (!empty($item['features'])) {
    $features = is_array($item['features']) ? $item['features'] : (json_decode($item['features'], true) ?: []);
}

$pageTitle = trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? '') . ' ' . ($item['year'] ?? '')) . ' - KINAS AUTOMOBILE';
$pageDescription = substr(strip_tags($item['description'] ?? ''), 0, 160);

include '../../templates/header.php';

$locParts = array_filter([$item['city'] ?? null, $item['state'] ?? null, $item['country'] ?? null]);
$location = implode(', ', $locParts);
?>

<div class="je-page">
<div class="je-detail-wrap">

    <div class="je-breadcrumb">
        <a href="/">Home</a>
        <span class="je-breadcrumb-sep">/</span>
        <a href="/divisions/kinas-automobile/">KINAS AUTOMOBILE</a>
        <span class="je-breadcrumb-sep">/</span>
        <a href="/divisions/kinas-automobile/search.php">Search</a>
        <span class="je-breadcrumb-sep">/</span>
        <span><?= htmlspecialchars(trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? ''))) ?></span>
    </div>

    <div class="je-detail-grid">
        <!-- ── Gallery ── -->
        <div>
            <?php if (empty($images)): ?>
                <div class="je-gallery-main" style="background:linear-gradient(135deg,#1a1a1a,#0a0a0a); display:flex; align-items:center; justify-content:center; color:#C6A43F; font-size:64px;">
                    <i class="fas fa-car"></i>
                </div>
            <?php else: ?>
                <div class="je-gallery-main" id="jeGalleryMain">
                    <img id="jeMainImage" src="<?= htmlspecialchars($images[0]['url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                    <?php if (!empty($item['featured'])): ?>
                        <span class="je-card-badge" style="top:16px;left:16px;">Featured</span>
                    <?php endif; ?>
                </div>
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

        <!-- ── Spec panel (sticky) ── -->
        <aside class="je-spec-panel">
            <div class="je-spec-eyebrow">KINAS AUTOMOBILE</div>
            <h1 class="je-spec-title"><?= htmlspecialchars(trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? ''))) ?></h1>
            <div style="font-size:13px; color:#888; margin-bottom:8px;"><?= htmlspecialchars($item['year'] ?? '') ?></div>

            <div class="je-spec-price"><?= function_exists('formatPrice') ? formatPrice((float)$item['price']) : '₦' . number_format((float)$item['price']) ?></div>
            <div class="je-spec-price-note"><?= !empty($item['negotiable']) ? 'Negotiable' : 'Fixed price' ?></div>

            <dl class="je-spec-key">
                <?php
                $keys = [
                    'Year'           => $item['year'] ?? null,
                    'Mileage'        => ($item['mileage'] ?? null) !== null ? number_format((int)$item['mileage']) . ' km' : null,
                    'Transmission'   => $item['transmission'] ?? null,
                    'Fuel Type'      => $item['fuel_type'] ?? null,
                    'Body Type'      => $item['body_type'] ?? null,
                    'Drivetrain'     => $item['drivetrain'] ?? null,
                    'Exterior Color' => $item['color'] ?? null,
                    'Condition'      => $item['condition_status'] ?? null,
                    'Doors'          => $item['doors'] ?? null,
                    'VIN'            => $item['vin'] ?? null,
                    'Location'       => $location ?: null,
                ];
                foreach ($keys as $label => $val):
                    if (!$val) continue;
                ?>
                    <div><dt><?= htmlspecialchars($label) ?></dt><dd><?= htmlspecialchars($val) ?></dd></div>
                <?php endforeach; ?>
            </dl>

            <div class="je-cta-row">
                <button class="je-cta-primary" onclick="document.getElementById('contact-agent-modal').style.display='flex'">
                    <i class="far fa-envelope"></i> Contact Agent
                </button>
                <a href="tel:<?= htmlspecialchars($item['agent_phone'] ?? '') ?>" class="je-cta-secondary">
                    <i class="fas fa-phone"></i> Call Agent
                </a>
                <button class="je-cta-secondary" onclick="jeSaveListing('car', <?= (int)$item['id'] ?>)">
                    <i class="far fa-heart"></i> Save Listing
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

    <!-- ── Description ── -->
    <section class="je-section" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8; margin-top:40px;">
        <h2>Description</h2>
        <?php if (!empty($item['description'])): ?>
            <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
        <?php else: ?>
            <p style="color:#999;font-style:italic;">No description provided.</p>
        <?php endif; ?>

        <?php if (!empty($features)): ?>
        <h2 style="margin-top:32px;">Features &amp; Equipment</h2>
        <div class="je-features-grid">
            <?php foreach ($features as $f): ?>
                <div class="je-feature-pill"><i class="fas fa-check"></i> <?= htmlspecialchars(is_array($f) ? ($f['name'] ?? json_encode($f)) : $f) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- ── Similar listings ── -->
    <?php if (!empty($similar)): ?>
    <section class="je-section" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8;">
        <h2>You may also like</h2>
        <?php
        $simCards = array_map(function ($s) {
            $specParts = array_filter([$s['year'] ?? null, $s['model'] ?? null]);
            return [
                'id'         => $s['id'],
                'title'      => trim(($s['brand'] ?? '') . ' ' . ($s['model'] ?? '') . ' ' . ($s['year'] ?? '')),
                'division'   => 'KINAS AUTOMOBILE',
                'price'      => $s['price'],
                'thumbnail'  => $s['thumbnail'] ?: '',
                'specs'      => implode(' • ', $specParts),
                'location'   => '',
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

<!-- Contact agent modal (re-use the shared partial) -->
<?php include __DIR__ . '/../../templates/modal/contact-agent-modal.php'; ?>

<script>
function jeSaveListing(type, id) {
    var fd = new FormData();
    fd.append('listing_type', type);
    fd.append('listing_id', id);
    fetch('/api/listings/favorite.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json()).then(d => {
            if (d.success) {
                alert('Saved to your favorites');
            } else if (d.error && d.error.toLowerCase().includes('login')) {
                window.location.href = '/auth/login.php';
            } else {
                alert(d.error || 'Could not save.');
            }
        }).catch(() => alert('Network error.'));
}
</script>

<?php include '../../templates/footer.php'; ?>
