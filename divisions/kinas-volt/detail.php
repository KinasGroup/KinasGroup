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
    $db->prepare("UPDATE solar_listings SET views = views + 1 WHERE id = ?")->execute([$id]);
}

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
$pageDescription = !empty($item['description'])
    ? substr(strip_tags($item['description']), 0, 160)
    : trim(($item['service_type'] ?? 'Solar solution') . ' from ' . ($item['brand'] ?? 'KINAS VOLT')) . '.';
// Link-preview thumbnail (WhatsApp/Facebook/Twitter/etc): the listing's
// own first photo when it has one, falling back to header.php's default
// group logo (via $pageImage staying unset) otherwise.
if (!empty($images[0]['url'])) {
    $pageImage = $images[0]['url'];
}
$division = 'solar';
include '../../templates/header.php';

$locParts = array_filter([$item['city'] ?? null, $item['state'] ?? null, $item['country'] ?? null]);
$location = implode(', ', $locParts);

$listingId = (int)$item['id'];
$agentId = (int)$item['agent_id'];
$agentName = htmlspecialchars($item['agent_name'] ?? 'Provider', ENT_QUOTES, 'UTF-8');
$listingTitle = htmlspecialchars($item['title'] ?? 'Solar System', ENT_QUOTES, 'UTF-8');
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

            <!-- ============================================================ -->
            <!-- BUTTONS - Fully Working -->
            <!-- ============================================================ -->
            <div class="je-cta-row">
                <!-- Schedule Viewing -->
                <button class="je-cta-primary" id="scheduleBtn" onclick="openScheduleViewing(<?= $listingId ?>, 'solar', <?= $agentId ?>);">
                    <i class="far fa-calendar-alt"></i> Schedule Viewing
                </button>
                
                <!-- Contact Provider -->
                <button class="je-cta-secondary" id="contactBtn" onclick="openContactAgent(<?= $agentId ?>, '<?= $agentName ?>', 'solar');">
                    <i class="far fa-envelope"></i> Contact Provider
                </button>
                
                <!-- Save Listing -->
                <button class="je-cta-secondary" id="saveBtn" onclick="jeSaveListing('solar', <?= $listingId ?>);">
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
                // FIXED: Full path to detail page
                'detail_url' => '/divisions/kinas-volt/detail.php?id=' . (int)$s['id'],
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
    // showSuccessBanner is the site-wide standard notification style
    // (see includes/kinas-ui.php) - preferred here over kinasToast for
    // visual consistency with other in-page banners like the add-to-cart
    // error.
    if (typeof window.showSuccessBanner === 'function') {
        window.showSuccessBanner('Please sign in to continue — redirecting you to login…', true);
    } else if (typeof kinasToast === 'function') {
        kinasToast('Please sign in to continue — redirecting you to login…', 'warning');
    }
    setTimeout(function() {
        window.location.href = '/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
    }, 1500);
}

// showSuccessBanner() is now defined globally in includes/kinas-ui.php
// (loaded site-wide via templates/footer.php) — no need to duplicate it here.

// ============================================================
// SCHEDULE VIEWING
// ============================================================

function openScheduleViewing(listingId, listingType, agentId) {
    console.log('Schedule Viewing clicked!', listingId, listingType, agentId);
    
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }
    
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
                <strong><?= $agentName ?></strong> · <?= $listingTitle ?>
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
                form.querySelector('input[name="name"]').value = data.name || '';
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
            const res = await fetch('../../../api/messages/send-inquiry.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                const modal = document.getElementById('schedule-modal');
                if (modal) modal.remove();
                showSuccessBanner('✅ Viewing requested successfully! The provider will confirm within 24 hours.', false);
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
}

// ============================================================
// CONTACT PROVIDER
// ============================================================

function openContactAgent(agentId, agentName, division) {
    console.log('Contact Provider clicked!', agentId, agentName, division);
    
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }
    
    const old = document.getElementById('contact-modal');
    if (old) old.remove();
    
    const verifiedBadge = <?= $agentVerified ? 'true' : 'false' ?> ? '<span style="color:#1B5E20;">✓ Verified</span>' : '';
    
    const html = `
    <div id="contact-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:999998;display:flex;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:30px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;font-size:20px;">✉️ Contact Provider</h3>
                <button onclick="document.getElementById('contact-modal').remove()" style="background:none;border:none;font-size:24px;cursor:pointer;">✕</button>
            </div>
            <div style="padding:12px;background:#f5f5f5;border-radius:8px;margin-bottom:20px;">
                <strong>${agentName}</strong> ${verifiedBadge} · ${division}
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
                form.querySelector('input[name="name"]').value = data.name || '';
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
            const res = await fetch('../../../api/messages/send-inquiry.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                const modal = document.getElementById('contact-modal');
                if (modal) modal.remove();
                showSuccessBanner('✅ Inquiry sent successfully! The provider will contact you shortly.', false);
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
    .catch(function(e) { console.log('Check favorite error:', e); });
});

console.log('=== KINAS VOLT detail page loaded ===');
</script>

<?php include '../../templates/footer.php'; ?>
