<?php
/**
 * KINAS VOLT — Solar listing detail
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

$id = (int)($_GET['id'] ?? 0);
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
    SELECT s.*, a.verified as agent_verified, a.name as agent_name, a.email as agent_email, a.phone as agent_phone,
           ap.company_name as agent_company, ap.avatar as agent_avatar
    FROM solar_listings s
    LEFT JOIN users a ON s.agent_id = a.id
    LEFT JOIN agent_profiles ap ON a.id = ap.user_id
    WHERE s.id = ? AND s.status = 'active'
");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) { http_response_code(404); include __DIR__ . '/../../pages/404.php'; exit; }

$db->prepare("UPDATE solar_listings SET views = views + 1 WHERE id = ?")->execute([$id]);

$images = $db->prepare("SELECT * FROM listing_images WHERE listing_id = ? AND listing_type = 'solar' ORDER BY sort_order");
$images->execute([$id]);
$images = $images->fetchAll();

$similar = $db->prepare("
    SELECT s.id, s.title, s.service_type, s.price, s.brand, s.capacity_kw,
           (SELECT url FROM listing_images WHERE listing_id = s.id AND listing_type = 'solar' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM solar_listings s
    WHERE s.id != ? AND s.status = 'active' AND (s.brand = ? OR s.service_type = ?)
    ORDER BY s.created_at DESC
    LIMIT 4
");
$similar->execute([$id, $item['brand'] ?? '', $item['service_type'] ?? '']);
$similar = $similar->fetchAll();

$features = !empty($item['features']) ? (is_array($item['features']) ? $item['features'] : (json_decode($item['features'], true) ?: [])) : [];

$pageTitle = ($item['title'] ?? 'Solar System') . ' - KINAS VOLT';
include '../../templates/header.php';

$locParts = array_filter([$item['city'] ?? null, $item['state'] ?? null, $item['country'] ?? null]);
$location = implode(', ', $locParts);
?>

<div class="je-page">
<div class="je-detail-wrap">

    <div class="je-breadcrumb">
        <a href="/">Home</a>
        <span class="je-breadcrumb-sep">/</span>
        <a href="/divisions/kinas-volt/">KINAS VOLT</a>
        <span class="je-breadcrumb-sep">/</span>
        <a href="/divisions/kinas-volt/search.php">Search</a>
        <span class="je-breadcrumb-sep">/</span>
        <span><?= htmlspecialchars($item['title'] ?? '') ?></span>
    </div>

    <div class="je-detail-grid">
        <div>
            <?php if (empty($images)): ?>
                <div class="je-gallery-main" style="background:linear-gradient(135deg,#1a1a1a,#0a0a0a); display:flex; align-items:center; justify-content:center; color:#C6A43F; font-size:64px;">
                    <i class="fas fa-solar-panel"></i>
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
            <div class="je-spec-eyebrow"><?= htmlspecialchars(ucfirst($item['service_type'] ?? 'Residential')) ?> Solar</div>
            <h1 class="je-spec-title"><?= htmlspecialchars($item['title'] ?? '') ?></h1>
            <?php if ($location): ?><div style="font-size:13px;color:#888;margin-bottom:8px;"><i class="fas fa-map-marker-alt" style="color:#C6A43F"></i> <?= htmlspecialchars($location) ?></div><?php endif; ?>

            <?php if (!empty($item['price'])): ?>
            <div class="je-spec-price"><?= function_exists('formatPrice') ? formatPrice((float)$item['price']) : '₦' . number_format((float)$item['price']) ?></div>
            <div class="je-spec-price-note">Estimated project cost</div>
            <?php else: ?>
            <div class="je-spec-price" style="color:#888;">Get a quote</div>
            <?php endif; ?>

            <dl class="je-spec-key">
                <?php
                $keys = [
                    'Service Type' => $item['service_type'] ?? null,
                    'Brand'        => $item['brand'] ?? null,
                    'Capacity'     => ($item['capacity_kw'] ?? null) !== null ? rtrim(rtrim(number_format((float)$item['capacity_kw'], 2), '0'), '.') . ' kW' : null,
                    'Warranty'     => ($item['warranty_years'] ?? null) !== null ? (int)$item['warranty_years'] . ' years' : null,
                ];
                foreach ($keys as $label => $val):
                    if (!$val) continue;
                ?>
                    <div><dt><?= htmlspecialchars($label) ?></dt><dd><?= htmlspecialchars(ucfirst($val)) ?></dd></div>
                <?php endforeach; ?>
            </dl>

            <div class="je-cta-row">
                <button class="je-cta-primary" onclick="document.getElementById('contact-agent-modal').style.display='flex'">
                    <i class="far fa-envelope"></i> Request Quote
                </button>
                <a href="tel:<?= htmlspecialchars($item['agent_phone'] ?? '') ?>" class="je-cta-secondary">
                    <i class="fas fa-phone"></i> Call Provider
                </a>
                <button class="je-cta-secondary" onclick="jeSaveListing('solar', <?= (int)$item['id'] ?>)">
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
                    <div class="je-agent-name"><?= htmlspecialchars($item['agent_name'] ?? 'Provider') ?></div>
                    <div class="je-agent-meta">
                        <?= htmlspecialchars($item['agent_company'] ?? 'Independent Provider') ?>
                        <?php if (!empty($item['agent_verified'])): ?>
                            · <span style="color:#1B5E20;font-weight:600;">✓ Verified</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <section class="je-section" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8; margin-top:40px;">
        <h2>About this system</h2>
        <?php if (!empty($item['description'])): ?>
            <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
        <?php else: ?>
            <p style="color:#999;font-style:italic;">No description provided.</p>
        <?php endif; ?>

        <?php if (!empty($features)): ?>
        <h2 style="margin-top:32px;">What's included</h2>
        <div class="je-features-grid">
            <?php foreach ($features as $f): ?>
                <div class="je-feature-pill"><i class="fas fa-check"></i> <?= htmlspecialchars(is_array($f) ? ($f['name'] ?? json_encode($f)) : $f) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($similar)): ?>
    <section class="je-section" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8;">
        <h2>Related systems</h2>
        <?php
        $simCards = array_map(function ($s) {
            $specParts = array_filter([
                $s['service_type'] ?? null,
                ($s['capacity_kw'] ?? null) !== null ? rtrim(rtrim(number_format((float)$s['capacity_kw'], 2), '0'), '.') . ' kW' : null,
                $s['brand'] ?? null,
            ]);
            return [
                'id'         => $s['id'],
                'title'      => $s['title'] ?? '',
                'division'   => 'KINAS VOLT',
                'price'      => $s['price'] ?? null,
                'thumbnail'  => $s['thumbnail'] ?: '',
                'specs'      => implode(' • ', array_map('ucfirst', $specParts)),
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

<?php include __DIR__ . '/../../templates/modal/contact-agent-modal.php'; ?>

<script>
function jeSaveListing(type, id) {
    var fd = new FormData();
    fd.append('listing_type', type);
    fd.append('listing_id', id);
    fetch('/api/listings/favorite.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json()).then(d => {
            if (d.success) alert('Saved to your favorites');
            else if (d.error && d.error.toLowerCase().includes('login')) window.location.href = '/auth/login.php';
            else alert(d.error || 'Could not save.');
        }).catch(() => alert('Network error.'));
}
</script>

<?php include '../../templates/footer.php'; ?>
