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

if (!$item || ($item['status'] !== 'active' && !$isOwnerOrAdmin)) {
    http_response_code(404);
    include __DIR__ . '/../../pages/404.php';
    exit;
}

$isOwnerOrAdmin = $item && SessionManager::isLoggedIn()
    && ((int)$item['agent_id'] === SessionManager::getUserId() || SessionManager::getUserRole() === 'admin');

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

$listingId = (int)$item['id'];
$agentId = (int)$item['agent_id'];
$agentName = htmlspecialchars($item['agent_name'] ?? 'Agent', ENT_QUOTES, 'UTF-8');
$listingTitle = htmlspecialchars($item['title'] ?? 'Property', ENT_QUOTES, 'UTF-8');
$agentVerified = !empty($item['agent_verified']);
?>

<div class="je-page">
<div class="je-detail-wrap">

<?php if ($isPreview): ?>
<div style="background:#FFF8E1; border:1px solid #F0C419; color:#7A5B00; padding:14px 18px; border-radius:4px; margin-bottom:20px; font-size:14px;">
    <i class="fas fa-eye"></i> <strong>Preview only</strong> — this listing is <?= htmlspecialchars(ucfirst($item['status'])) ?> and not visible to the public yet.
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
            <!-- BUTTONS -->
            <!-- ============================================================ -->
            <div class="je-cta-row">
                <button class="je-cta-primary" onclick="openScheduleViewing(<?= $listingId ?>, 'property', <?= $agentId ?>);">
                    <i class="far fa-calendar-alt"></i> Schedule Viewing
                </button>
                
                <button class="je-cta-secondary" onclick="openContactAgent(<?= $agentId ?>, '<?= $agentName ?>', 'property');">
                    <i class="far fa-envelope"></i> Contact Agent
                </button>
                
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

<!-- ============================================================ -->
<!-- ALL JAVASCRIPT - SELF-CONTAINED -->
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
    alert('Please login to continue');
    setTimeout(function() {
        window.location.href = '/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
    }, 1500);
}

// ============================================================
// SHOW BANNER
// ============================================================

function showBanner(message, isError) {
    const existing = document.querySelectorAll('.custom-banner');
    existing.forEach(function(b) { b.remove(); });
    
    const banner = document.createElement('div');
    banner.className = 'custom-banner';
    const bgColor = isError ? '#f8d7da' : '#d4edda';
    const textColor = isError ? '#721c24' : '#155724';
    const borderColor = isError ? '#dc3545' : '#28a745';
    const icon = isError ? 'fa-exclamation-circle' : 'fa-check-circle';
    
    banner.style.cssText = 'position:fixed;top:100px;right:20px;z-index:10000;padding:16px 24px;background:' + bgColor + ';color:' + textColor + ';border-left:4px solid ' + borderColor + ';border-radius:8px;font-family:Inter,sans-serif;font-size:14px;font-weight:500;box-shadow:0 8px 30px rgba(0,0,0,0.15);max-width:450px;display:flex;align-items:center;gap:12px;';
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
// SCHEDULE VIEWING
// ============================================================

function openScheduleViewing(listingId, listingType, agentId) {
    console.log('Schedule: Called with', listingId, listingType, agentId);
    
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }
    
    // Show schedule modal
    showScheduleModal(listingId, listingType, agentId);
}

function showScheduleModal(listingId, listingType, agentId) {
    // Remove existing
    const existing = document.getElementById('schedule-modal');
    if (existing) existing.remove();
    
    const modal = document.createElement('div');
    modal.id = 'schedule-modal';
    modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;display:flex;align-items:center;justify-content:center;';
    
    modal.innerHTML = `
        <div style="background:#fff;border-radius:16px;padding:30px;max-width:560px;width:90%;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="font-family:Prata,serif;font-size:22px;color:#0A0A0A;margin:0;">
                    <i class="fas fa-calendar-check" style="color:#C6A43F;"></i> Schedule Viewing
                </h3>
                <button onclick="this.closest('#schedule-modal').remove();document.body.style.overflow=''" style="background:none;border:none;font-size:24px;cursor:pointer;color:#888;padding:0 8px;">✕</button>
            </div>
            
            <div style="display:flex;align-items:center;gap:15px;padding:15px;background:#f9f9f9;border-radius:8px;margin-bottom:20px;">
                <div style="width:50px;height:50px;border-radius:50%;background:#C6A43F;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:18px;flex-shrink:0;"><?= substr($agentName, 0, 1) ?></div>
                <div>
                    <strong style="font-size:16px;color:#0A0A0A;"><?= $agentName ?></strong>
                    <p style="color:#888;font-size:13px;margin:2px 0 0;">Williams Connect Home · <?= $listingTitle ?></p>
                </div>
            </div>
            
            <form id="schedule-form">
                <input type="hidden" name="listing_id" value="${listingId}">
                <input type="hidden" name="listing_type" value="${listingType}">
                <input type="hidden" name="agent_id" value="${agentId}">
                <input type="hidden" name="inquiry_type" value="viewing">
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div style="margin-bottom:15px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Your Name *</label>
                        <input type="text" name="name" id="s-name" required placeholder="John Doe" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Your Email *</label>
                        <input type="email" name="email" id="s-email" required placeholder="john@example.com" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                    </div>
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Your Phone</label>
                    <input type="tel" name="phone" id="s-phone" placeholder="+1 (555) 000-0000" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div style="margin-bottom:15px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Preferred Date *</label>
                        <input type="date" name="preferred_date" id="s-date" required style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Preferred Time *</label>
                        <select name="preferred_time" id="s-time" required style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                            <option value="">Select time</option>
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
                
                <div style="margin-bottom:15px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Notes</label>
                    <textarea name="message" id="s-message" rows="3" placeholder="Any special requirements..." style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;resize:vertical;"></textarea>
                </div>
                
                <button type="submit" style="width:100%;padding:14px;background:#0A0A0A;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-calendar-check"></i> Request Viewing
                </button>
                
                <div id="s-error" style="display:none;margin-top:15px;padding:12px;background:#f8d7da;color:#721c24;border-radius:8px;"></div>
                <div id="s-success" style="display:none;margin-top:15px;padding:12px;background:#d4edda;color:#155724;border-radius:8px;"></div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Set default date
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const dateStr = tomorrow.toISOString().split('T')[0];
    const dateInput = document.getElementById('s-date');
    if (dateInput) { dateInput.min = dateStr; dateInput.value = dateStr; }
    
    // Pre-fill user info
    const meta = document.querySelector('meta[name="user-data"]');
    if (meta) {
        try {
            const userData = JSON.parse(meta.content);
            document.getElementById('s-name').value = userData.name || '';
            document.getElementById('s-email').value = userData.email || '';
            document.getElementById('s-phone').value = userData.phone || '';
        } catch (e) {}
    }
    
    document.body.style.overflow = 'hidden';
    
    // Handle form submission
    document.getElementById('schedule-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');
        const errorDiv = document.getElementById('s-error');
        const successDiv = document.getElementById('s-success');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        submitBtn.disabled = true;
        errorDiv.style.display = 'none';
        successDiv.style.display = 'none';
        
        const formData = new FormData(form);
        
        // Set message if empty
        const msgField = document.getElementById('s-message');
        if (!msgField.value.trim()) {
            msgField.value = 'I would like to schedule a viewing for this property.';
            formData.set('message', msgField.value);
        }
        
        fetch('/api/messages/send-inquiry.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                modal.remove();
                document.body.style.overflow = '';
                showBanner('✅ Viewing requested successfully! The agent will confirm within 24 hours.', false);
                form.reset();
            } else {
                errorDiv.textContent = data.error || 'Failed to schedule viewing.';
                errorDiv.style.display = 'block';
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(function(error) {
            errorDiv.textContent = 'Network error. Please try again.';
            errorDiv.style.display = 'block';
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
}

// ============================================================
// CONTACT AGENT
// ============================================================

function openContactAgent(agentId, agentName, division) {
    console.log('Contact: Called with', agentId, agentName, division);
    
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }
    
    showContactModal(agentId, agentName, division);
}

function showContactModal(agentId, agentName, division) {
    const existing = document.getElementById('contact-modal');
    if (existing) existing.remove();
    
    const modal = document.createElement('div');
    modal.id = 'contact-modal';
    modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;display:flex;align-items:center;justify-content:center;';
    
    const verifiedBadge = <?= $agentVerified ? 'true' : 'false' ?> ? '<span style="color:#1B5E20;font-weight:600;">✓ Verified</span>' : '';
    
    modal.innerHTML = `
        <div style="max-width:520px;background:#fff;border-radius:16px;padding:30px;width:90%;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="font-family:Prata,serif;font-size:22px;color:#0A0A0A;margin:0;">
                    <i class="fas fa-envelope" style="color:#C6A43F;"></i> Contact Agent
                </h3>
                <button onclick="this.closest('#contact-modal').remove();document.body.style.overflow=''" style="background:none;border:none;font-size:24px;cursor:pointer;color:#888;padding:0 8px;">✕</button>
            </div>
            
            <div style="display:flex;align-items:center;gap:15px;padding:15px;background:#f9f9f9;border-radius:8px;margin-bottom:20px;">
                <div style="width:50px;height:50px;border-radius:50%;background:#C6A43F;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:18px;flex-shrink:0;">${agentName.charAt(0).toUpperCase()}</div>
                <div>
                    <strong style="font-size:16px;color:#0A0A0A;">${agentName}</strong>
                    <p style="color:#888;font-size:13px;margin:2px 0 0;">${verifiedBadge} ${division}</p>
                </div>
            </div>
            
            <form id="contact-form">
                <input type="hidden" name="listing_id" value="<?= $listingId ?>">
                <input type="hidden" name="listing_type" value="property">
                <input type="hidden" name="agent_id" value="${agentId}">
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div style="margin-bottom:15px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Your Name *</label>
                        <input type="text" name="name" id="c-name" required placeholder="John Doe" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Your Email *</label>
                        <input type="email" name="email" id="c-email" required placeholder="john@example.com" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                    </div>
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Your Phone</label>
                    <input type="tel" name="phone" id="c-phone" placeholder="+1 (555) 000-0000" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Subject</label>
                    <input type="text" name="subject" id="c-subject" placeholder="Inquiry about your listing" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;" value="Inquiry about property">
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Message *</label>
                    <textarea name="message" id="c-message" rows="5" required placeholder="Hi, I'm interested in your listing..." style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;resize:vertical;"></textarea>
                </div>
                
                <button type="submit" style="width:100%;padding:14px;background:#0A0A0A;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-paper-plane"></i> Send Inquiry
                </button>
                
                <div id="c-error" style="display:none;margin-top:15px;padding:12px;background:#f8d7da;color:#721c24;border-radius:8px;"></div>
                <div id="c-success" style="display:none;margin-top:15px;padding:12px;background:#d4edda;color:#155724;border-radius:8px;"></div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Pre-fill user info
    const meta = document.querySelector('meta[name="user-data"]');
    if (meta) {
        try {
            const userData = JSON.parse(meta.content);
            document.getElementById('c-name').value = userData.name || '';
            document.getElementById('c-email').value = userData.email || '';
            document.getElementById('c-phone').value = userData.phone || '';
        } catch (e) {}
    }
    
    document.body.style.overflow = 'hidden';
    
    // Handle form submission
    document.getElementById('contact-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');
        const errorDiv = document.getElementById('c-error');
        const successDiv = document.getElementById('c-success');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        submitBtn.disabled = true;
        errorDiv.style.display = 'none';
        successDiv.style.display = 'none';
        
        const formData = new FormData(form);
        
        fetch('/api/messages/send-inquiry.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                modal.remove();
                document.body.style.overflow = '';
                showBanner('✅ Inquiry sent successfully! The agent will contact you shortly.', false);
                form.reset();
            } else {
                errorDiv.textContent = data.error || 'Failed to send inquiry.';
                errorDiv.style.display = 'block';
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(function(error) {
            errorDiv.textContent = 'Network error. Please try again.';
            errorDiv.style.display = 'block';
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
}

// ============================================================
// SAVE LISTING
// ============================================================

function jeSaveListing(type, id) {
    console.log('Save: Called with', type, id);
    
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }
    
    // Find the button
    const buttons = document.querySelectorAll('button[onclick*="jeSaveListing"]');
    let btn = null;
    for (let b of buttons) {
        if (b.getAttribute('onclick').includes("'" + type + "', " + id)) {
            btn = b;
            break;
        }
    }
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
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        console.log('Favorite response:', data);
        
        if (data.success) {
            const icon = btn?.querySelector('i');
            if (data.action === 'added') {
                showBanner('✅ Added to favorites!', false);
                if (icon) {
                    icon.className = 'fas fa-heart';
                    icon.style.color = '#C6A43F';
                }
            } else {
                showBanner('Removed from favorites', false);
                if (icon) {
                    icon.className = 'far fa-heart';
                    icon.style.color = '';
                }
            }
        } else {
            showBanner('❌ ' + (data.error || 'Failed to update favorites'), true);
        }
    })
    .catch(function(error) {
        console.error('Favorite error:', error);
        showBanner('❌ Network error. Please try again.', true);
    })
    .finally(function() {
        if (btn) {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    });
}

// ============================================================
// INIT
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('=== Detail page loaded ===');
    console.log('Listing ID: <?= $listingId ?>');
    console.log('Agent ID: <?= $agentId ?>');
    
    // Add CSS
    if (!document.getElementById('banner-styles')) {
        const style = document.createElement('style');
        style.id = 'banner-styles';
        style.textContent = '@keyframes slideInRight{from{opacity:0;transform:translateX(100px)}to{opacity:1;transform:translateX(0)}}.custom-banner{animation:slideInRight .3s ease}';
        document.head.appendChild(style);
    }
});
</script>

<?php include '../../templates/footer.php'; ?>
