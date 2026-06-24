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
            <!-- BUTTONS - UPDATED with working onclick handlers -->
            <!-- ============================================================ -->
            <div class="je-cta-row">
                <!-- Schedule Viewing - opens calendar -->
                <button class="je-cta-primary" onclick="openScheduleViewing(<?= (int)$item['id'] ?>, 'marketplace', <?= (int)$item['agent_id'] ?>)">
                    <i class="far fa-calendar-alt"></i> Schedule Viewing
                </button>
    
                <!-- Contact Agent - opens contact form (not phone) -->
                <button class="je-cta-secondary" onclick="openContactAgent(<?= (int)$item['agent_id'] ?>, '<?= htmlspecialchars($item['agent_name'] ?? 'Seller') ?>', 'marketplace')">
                    <i class="far fa-envelope"></i> Contact Seller
                </button>
    
                <!-- Save Listing -->
                <button class="je-cta-secondary" onclick="jeSaveListing('marketplace', <?= (int)$item['id'] ?>)">
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

<?php include '../../templates/footer.php'; ?>
