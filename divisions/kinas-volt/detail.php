<?php
/**
* KINAS BUILD: 2026.08.16.01
* FILE: divisions/kinas-volt/detail.php
*
* KINAS VOLT — Solar listing detail
*
* AMENDED:
* - Agent/public identity now prefers @username where available.
* - Inquiry and schedule forms prefill the logged-in user's public username.
*
* RESTORED / ADDED:
* - Free scheduling.
* - Contact Provider modal.
* - Save listing.
* - Solar financing calculator.
*
* NOT ADDED:
* - Reviews.
*/
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';
require_once '../../includes/security.php';
require_once '../../includes/public-identity.php';

$id = (int)($_GET['id'] ?? 0);
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
SELECT s.*,
       a.verified AS agent_verified,
       a.name AS agent_name,
       a.username AS agent_username,
       a.email AS agent_email,
       a.phone AS agent_phone,
       ap.company_name AS agent_company,
       ap.avatar AS agent_avatar
FROM solar_listings s
LEFT JOIN users a ON s.agent_id = a.id
LEFT JOIN agent_profiles ap ON a.id = ap.user_id
WHERE s.id = ?
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
    try {
        $db->prepare("UPDATE solar_listings SET views = views + 1 WHERE id = ?")->execute([$id]);
    } catch (Throwable $e) {
        // Views column may not exist.
    }
}

$images = $db->prepare("
SELECT *
FROM listing_images
WHERE listing_id = ?
AND listing_type = 'solar'
ORDER BY sort_order
");

$images->execute([$id]);
$images = $images->fetchAll();

$similar = $db->prepare("
SELECT s.id, s.title, s.service_type, s.price, s.brand, s.capacity_kw,
(
    SELECT url
    FROM listing_images
    WHERE listing_id = s.id
    AND listing_type = 'solar'
    ORDER BY sort_order
    LIMIT 1
) AS thumbnail
FROM solar_listings s
WHERE s.id != ?
AND s.status = 'active'
AND (s.brand = ? OR s.service_type = ?)
ORDER BY s.created_at DESC
LIMIT 4
");

$similar->execute([
    $id,
    (string)($item['brand'] ?? ''),
    (string)($item['service_type'] ?? ''),
]);

$similar = $similar->fetchAll(PDO::FETCH_ASSOC) ?: [];

$features = [];

if (!empty($item['features'])) {
    $features = is_array($item['features'])
        ? $item['features']
        : (json_decode((string)$item['features'], true) ?: []);
}

$pageTitle = ($item['title'] ?? 'Solar System') . ' - KINAS VOLT';

$pageDescription = !empty($item['description'])
    ? substr(strip_tags($item['description']), 0, 160)
    : trim(($item['service_type'] ?? 'Solar solution') . ' from ' . ($item['brand'] ?? 'KINAS VOLT')) . '.';

if (!empty($images[0]['url'])) {
    $pageImage = $images[0]['url'];
}

$division = 'solar';

include '../../templates/header.php';

$locParts = array_filter([
    $item['city'] ?? null,
    $item['state'] ?? null,
    $item['country'] ?? null,
]);

$location = implode(', ', $locParts);

$listingId = (int)$item['id'];
$agentId = (int)$item['agent_id'];

$agentNameRaw = $item['agent_name'] ?? 'Provider';
$agentUsername = (string)($item['agent_username'] ?? '');

$agentDisplayName = function_exists('kinas_public_display_name')
    ? kinas_public_display_name($agentUsername !== '' ? $agentUsername : null, $agentNameRaw)
    : ($agentUsername !== '' ? '@' . $agentUsername : $agentNameRaw);

$agentInitialSource = ltrim($agentDisplayName, '@');

$listingTitleRaw = $item['title'] ?? 'Solar System';
$agentVerified = !empty($item['agent_verified']);
?>
<div class="je-page">
    <div class="je-detail-wrap">

        <?php if ($isPreview): ?>
        <div style="background:#FFF8E1; border:1px solid #F0C419; color:#7A5B00; padding:14px 18px; border-radius:4px; margin-bottom:20px; font-size:14px;">
            <i class="fas fa-eye"></i>
            <strong>Preview only</strong> — this listing is <?= htmlspecialchars(ucfirst((string)$item['status'])) ?> and not visible to the public yet.
        </div>
        <?php endif; ?>

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
                    <div class="je-gallery-main">
                        <img id="jeMainImage"
                             src="<?= htmlspecialchars($images[0]['url']) ?>"
                             alt="<?= htmlspecialchars($item['title'] ?? 'Solar System') ?>"
                             onerror="this.onerror=null; this.src='/assets/images/placeholder/product-placeholder.svg';">
                    </div>

                    <?php if (count($images) > 1): ?>
                        <div class="je-gallery-thumbs">
                            <?php foreach ($images as $idx => $img): ?>
                                <div class="je-gallery-thumb <?= $idx === 0 ? 'is-active' : '' ?>"
                                     onclick="document.getElementById('jeMainImage').src='<?= htmlspecialchars($img['url']) ?>';
                                              document.querySelectorAll('.je-gallery-thumbs').forEach(t=>t.classList.remove('is-active'));
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
                <div class="je-spec-eyebrow">
                    <?= htmlspecialchars(ucfirst($item['service_type'] ?? 'Residential')) ?> Solar
                </div>

                <h1 class="je-spec-title"><?= htmlspecialchars($item['title'] ?? '') ?></h1>

                <?php if ($location): ?>
                    <div style="font-size:13px;color:#888;margin-bottom:8px;">
                        <i class="fas fa-map-marker-alt" style="color:#C6A43F"></i>
                        <?= htmlspecialchars($location) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($item['price'])): ?>
                    <div class="je-spec-price">
                        <?= function_exists('formatPrice') ? formatPrice((float)$item['price']) : '₦' . number_format((float)$item['price']) ?>
                    </div>
                    <div class="je-spec-price-note">Estimated project cost</div>
                <?php else: ?>
                    <div class="je-spec-price" style="color:#888;">Get a quote</div>
                <?php endif; ?>

                <dl class="je-spec-key">
                    <?php
                    $keys = [
                        'Service Type' => $item['service_type'] ?? null,
                        'Brand'        => $item['brand'] ?? null,
                        'Capacity'     => ($item['capacity_kw'] ?? null) !== null
                            ? rtrim(rtrim(number_format((float)$item['capacity_kw'], 2), '0'), '.') . ' kW'
                            : null,
                        'Warranty'     => ($item['warranty_years'] ?? null) !== null
                            ? (int)$item['warranty_years'] . ' years'
                            : null,
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
                <div class="je-cta-row">
                    <button class="je-cta-primary"
                            id="scheduleBtn"
                            onclick="window.openScheduleViewing(<?= $listingId ?>, 'solar', <?= $agentId ?>, 0);">
                        <i class="far fa-calendar-alt"></i> Schedule Viewing
                    </button>

                    <button class="je-cta-secondary"
                            id="contactBtn"
                            onclick="window.openContactAgent(
                                <?= $agentId ?>,
                                <?= htmlspecialchars(json_encode($agentDisplayName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>,
                                'solar'
                            );">
                        <i class="far fa-envelope"></i> Contact Provider
                    </button>

                    <button class="je-cta-secondary"
                            id="saveBtn"
                            onclick="window.jeSaveListing('solar', <?= $listingId ?>);">
                        <i class="far fa-heart"></i> Save
                    </button>
                </div>

                <div class="je-agent-card">
                    <div class="je-agent-avatar">
                        <?php if (!empty($item['agent_avatar'])): ?>
                            <img src="<?= htmlspecialchars($item['agent_avatar']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($agentInitialSource, 0, 1)) ?>
                        <?php endif; ?>
                    </div>

                    <div class="je-agent-info">
                        <div class="je-agent-name"><?= htmlspecialchars($agentDisplayName) ?></div>
                        <div class="je-agent-meta">
                            <?= htmlspecialchars($item['agent_company'] ?? 'Independent Provider') ?>
                            <?php if ($agentVerified): ?>
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
                        <div class="je-feature-pill">
                            <i class="fas fa-check"></i>
                            <?= htmlspecialchars(is_array($f) ? ($f['name'] ?? json_encode($f)) : $f) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ============================================================ -->
            <!-- SOLAR FINANCING CALCULATOR -->
            <!-- ============================================================ -->
            <h2 style="margin-top:32px;">Payment Calculator</h2>

            <div class="je-finance-calc" style="background:#f9f8f5;border:1px solid #e8e8e8;border-radius:8px;padding:24px;max-width:520px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:6px;">System Cost (₦)</label>
                        <input type="number" id="solPrice" value="<?= (int)($item['price'] ?? 0) ?>" min="0" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;">
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:6px;">Down Payment (%)</label>
                        <input type="number" id="solDown" value="20" min="0" max="100" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;">
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:6px;">Interest Rate (% / yr)</label>
                        <input type="number" id="solRate" value="18" min="0" max="100" step="0.1" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;">
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:6px;">Loan Term (months)</label>
                        <select id="solTerm" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;">
                            <option value="12">12</option>
                            <option value="24" selected>24</option>
                            <option value="36">36</option>
                            <option value="48">48</option>
                            <option value="60">60</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:20px;padding-top:20px;border-top:1px solid #e0ddd3;display:flex;justify-content:space-between;align-items:baseline;">
                    <span style="font-size:13px;color:#666;">Estimated monthly payment</span>
                    <span id="solMonthly" style="font-size:24px;font-weight:700;color:#151515;">₦0</span>
                </div>

                <p style="margin:10px 0 0;font-size:11px;color:#999;">
                    Estimate only. Actual financing terms are set by your chosen lender and subject to approval.
                </p>

                <p style="margin:14px 0 0;">
                    <a href="/divisions/kinas-volt/calculator.php" style="color:#C6A43F;font-weight:600;text-decoration:none;">
                        <i class="fas fa-calculator"></i> Open full solar calculator
                    </a>
                </p>
            </div>
        </section>

        <?php if (!empty($similar)): ?>
            <section class="je-section" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8;">
                <h2>Related systems</h2>

                <?php
                $simCards = array_map(function ($s) {
                    $specParts = array_filter([
                        $s['service_type'] ?? null,
                        ($s['capacity_kw'] ?? null) !== null
                            ? rtrim(rtrim(number_format((float)$s['capacity_kw'], 2), '0'), '.') . ' kW'
                            : null,
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
                        'detail_url' => '/divisions/kinas-volt/detail.php?id=' . (int)$s['id'],
                        'featured'   => false,
                        'verified'   => false,
                    ];
                }, $similar);

                je_render_listing_grid($simCards);
                ?>
            </section>
        <?php endif; ?>
    </div>
</div>

<script>
// ============================================================
// SAFE PAGE CONSTANTS
// ============================================================
window.__kinasAgentName = <?= json_encode($agentDisplayName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.__kinasListingTitle = <?= json_encode($listingTitleRaw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.__kinasAgentVerified = <?= $agentVerified ? 'true' : 'false' ?>;

function voltEscapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

function getKinasPublicNameFromMeta() {
    try {
        const meta = document.querySelector('meta[name="user-data"]');

        if (!meta) {
            return '';
        }

        const data = JSON.parse(meta.content);

        if (data.username) {
            return String(data.username).startsWith('@') ? data.username : '@' + data.username;
        }

        return data.name || '';
    } catch (e) {
        return '';
    }
}

// ============================================================
// HELPERS
// ============================================================
window.isUserLoggedIn = function() {
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
};

window.showLoginRequired = function() {
    if (typeof window.showSuccessBanner === 'function') {
        window.showSuccessBanner('Please sign in to continue — redirecting you to login…', true);
    } else if (typeof kinasToast === 'function') {
        kinasToast('Please sign in to continue — redirecting you to login…', 'warning');
    }

    setTimeout(function() {
        window.location.href = '/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
    }, 1500);
};

// ============================================================
// FREE SCHEDULE VIEWING
// ============================================================
window.openScheduleViewing = function(listingId, listingType, agentId, inspectionFee) {
    if (!window.isUserLoggedIn()) {
        window.showLoginRequired();
        return;
    }

    window.showScheduleModal(listingId, listingType, agentId);
};

window.showScheduleModal = function(listingId, listingType, agentId) {
    const old = document.getElementById('schedule-modal');
    if (old) old.remove();

    const html = `
    <div id="schedule-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:999999;display:flex;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:30px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;font-size:20px;">📅 Schedule Viewing</h3>
                <button onclick="document.getElementById('schedule-modal').remove()" style="background:none;border:none;font-size:24px;cursor:pointer;">✕</button>
            </div>

            <div style="padding:12px;background:#f5f5f5;border-radius:8px;margin-bottom:20px;">
                <strong>${voltEscapeHtml(window.__kinasAgentName)}</strong> · ${voltEscapeHtml(window.__kinasListingTitle)}
            </div>

            <form id="scheduleForm">
                <input type="hidden" name="listing_id" value="${listingId}">
                <input type="hidden" name="listing_type" value="${listingType}">
                <input type="hidden" name="agent_id" value="${agentId}">
                <input type="hidden" name="inquiry_type" value="viewing">

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

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Date *</label>
                        <input type="date" name="preferred_date" id="prefDate" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                    </div>

                    <div>
                        <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Time *</label>
                        <select name="preferred_time" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                            <option value="">Select</option>
                            <option value="09:00">9:00 AM</option>
                            <option value="10:00">10:00 AM</option>
                            <option value="11:00">11:00 AM</option>
                            <option value="12:00">12:00 PM</option>
                            <option value="13:00">1:00 PM</option>
                            <option value="14:00">2:00 PM</option>
                            <option value="15:00">3:00 PM</option>
                            <option value="16:00">4:00 PM</option>
                            <option value="17:00">5:00 PM</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Notes</label>
                    <textarea name="message" rows="3" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;resize:vertical;"></textarea>
                </div>

                <button type="submit" style="width:100%;padding:12px;background:#0A0A0A;color:#fff;border:none;border-radius:6px;font-size:16px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-calendar-check"></i> Request Viewing
                </button>

                <div id="scheduleMsg" style="margin-top:12px;padding:10px;border-radius:6px;display:none;"></div>
            </form>
        </div>
    </div>
    `;

    document.body.insertAdjacentHTML('beforeend', html);

    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);

    const dateInput = document.getElementById('prefDate');

    if (dateInput) {
        dateInput.min = tomorrow.toISOString().split('T')[0];
        dateInput.value = tomorrow.toISOString().split('T')[0];
    }

    const meta = document.querySelector('meta[name="user-data"]');

    if (meta) {
        try {
            const data = JSON.parse(meta.content);
            const form = document.getElementById('scheduleForm');

            if (form) {
                const publicName = getKinasPublicNameFromMeta();

                form.querySelector('input[name="name"]').value = publicName || data.name || '';
                form.querySelector('input[name="email"]').value = data.email || '';
                form.querySelector('input[name="phone"]').value = data.phone || '';
            }
        } catch (e) {}
    }

    document.getElementById('scheduleForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = this.querySelector('button[type="submit"]');
        const msg = document.getElementById('scheduleMsg');
        const original = btn.innerHTML;

        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        btn.disabled = true;
        msg.style.display = 'none';

        const formData = new FormData(this);

        const notes = formData.get('message') || '';

        if (!notes.trim()) {
            formData.set('message', 'I would like to schedule a viewing for this solar system.');
        }

        try {
            const res = await fetch('/api/messages/send-inquiry.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await res.json();

            if (data.success) {
                const modal = document.getElementById('schedule-modal');
                if (modal) modal.remove();

                if (typeof window.showSuccessBanner === 'function') {
                    window.showSuccessBanner('✅ Viewing requested successfully! The provider will confirm within 24 hours.', false);
                }
            } else {
                msg.style.display = 'block';
                msg.style.background = '#f8d7da';
                msg.style.color = '#721c24';
                msg.textContent = data.error || 'Failed to schedule. Please try again.';
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
};

// ============================================================
// CONTACT PROVIDER
// ============================================================
window.openContactAgent = function(agentId, agentName, division) {
    if (!window.isUserLoggedIn()) {
        window.showLoginRequired();
        return;
    }

    if (!agentName) {
        agentName = window.__kinasAgentName;
    }

    const old = document.getElementById('contact-modal');
    if (old) old.remove();

    const verifiedBadge = window.__kinasAgentVerified
        ? '<span style="color:#1B5E20;">✓ Verified</span>'
        : '';

    const html = `
    <div id="contact-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:999998;display:flex;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:30px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;font-size:20px;">✉️ Contact Provider</h3>
                <button onclick="document.getElementById('contact-modal').remove()" style="background:none;border:none;font-size:24px;cursor:pointer;">✕</button>
            </div>

            <div style="padding:12px;background:#f5f5f5;border-radius:8px;margin-bottom:20px;">
                <strong>${voltEscapeHtml(agentName)}</strong> ${verifiedBadge} · ${voltEscapeHtml(division || 'solar')}
            </div>

            <form id="contactForm">
                <input type="hidden" name="listing_id" value="<?= $listingId ?>">
                <input type="hidden" name="listing_type" value="solar">
                <input type="hidden" name="agent_id" value="${agentId}">

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
                    <input type="text" name="subject" value="Inquiry about solar system" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Message *</label>
                    <textarea name="message" rows="5" required placeholder="Hi, I'm interested in your solar system..." style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;resize:vertical;"></textarea>
                </div>

                <button type="submit" style="width:100%;padding:12px;background:#0A0A0A;color:#fff;border:none;border-radius:6px;font-size:16px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-paper-plane"></i> Send Inquiry
                </button>

                <div id="contactMsg" style="margin-top:12px;padding:10px;border-radius:6px;display:none;"></div>
            </form>
        </div>
    </div>
    `;

    document.body.insertAdjacentHTML('beforeend', html);

    const meta = document.querySelector('meta[name="user-data"]');

    if (meta) {
        try {
            const data = JSON.parse(meta.content);
            const form = document.getElementById('contactForm');

            if (form) {
                const publicName = getKinasPublicNameFromMeta();

                form.querySelector('input[name="name"]').value = publicName || data.name || '';
                form.querySelector('input[name="email"]').value = data.email || '';
                form.querySelector('input[name="phone"]').value = data.phone || '';
            }
        } catch (e) {}
    }

    document.getElementById('contactForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = this.querySelector('button[type="submit"]');
        const msg = document.getElementById('contactMsg');
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
                const modal = document.getElementById('contact-modal');
                if (modal) modal.remove();

                if (typeof window.showSuccessBanner === 'function') {
                    window.showSuccessBanner('✅ Inquiry sent successfully! The provider will contact you shortly.', false);
                }
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
};

// ============================================================
// SAVE LISTING
// ============================================================
window.jeSaveListing = function(type, id) {
    if (!window.isUserLoggedIn()) {
        window.showLoginRequired();
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
};

// ============================================================
// CHECK FAVORITE STATE ON LOAD
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('saveBtn');
    if (!btn) return;

    const formData = new FormData();
    formData.append('listing_type', 'solar');
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

// ============================================================
// SOLAR FINANCING CALCULATOR
// ============================================================
(function () {
    const price = document.getElementById('solPrice');
    const down = document.getElementById('solDown');
    const rate = document.getElementById('solRate');
    const term = document.getElementById('solTerm');
    const out = document.getElementById('solMonthly');

    if (!price || !out) return;

    function fmt(n) {
        return '₦' + Math.round(n).toLocaleString('en-NG');
    }

    function calc() {
        const p = Math.max(0, parseFloat(price.value) || 0);
        const downPct = Math.min(100, Math.max(0, parseFloat(down.value) || 0));
        const principal = p * (1 - downPct / 100);
        const annualRate = Math.max(0, parseFloat(rate.value) || 0) / 100;
        const months = parseInt(term.value, 10) || 24;
        const monthlyRate = annualRate / 12;

        let monthly;

        if (monthlyRate === 0) {
            monthly = principal / months;
        } else {
            monthly = principal * (monthlyRate * Math.pow(1 + monthlyRate, months)) / (Math.pow(1 + monthlyRate, months) - 1);
        }

        out.textContent = fmt(monthly > 0 ? monthly : 0);
    }

    [price, down, rate, term].forEach(el => el.addEventListener('input', calc));

    calc();
})();
</script>

<?php include '../../templates/footer.php'; ?>
