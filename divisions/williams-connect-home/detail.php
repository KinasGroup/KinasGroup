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

$listingId = (int)$item['id'];
$agentId = (int)$item['agent_id'];
$agentName = htmlspecialchars($item['agent_name'] ?? 'Agent', ENT_QUOTES, 'UTF-8');
$listingTitle = htmlspecialchars($item['title'] ?? 'Property', ENT_QUOTES, 'UTF-8');
$agentVerified = !empty($item['agent_verified']);
?>

<!-- ============================================================ -->
<!-- JAVASCRIPT - ALL FUNCTIONS -->
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
// SHOW SUCCESS BANNER
// ============================================================

function showSuccessBanner(message, isError) {
    const existingBanners = document.querySelectorAll('.custom-success-banner');
    existingBanners.forEach(function(b) { b.remove(); });
    
    const banner = document.createElement('div');
    banner.className = 'custom-success-banner';
    const bgColor = isError ? '#f8d7da' : '#d4edda';
    const textColor = isError ? '#721c24' : '#155724';
    const borderColor = isError ? '#dc3545' : '#28a745';
    const icon = isError ? 'fa-exclamation-circle' : 'fa-check-circle';
    
    banner.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        z-index: 10000;
        padding: 16px 24px;
        background: ${bgColor};
        color: ${textColor};
        border-left: 4px solid ${borderColor};
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        font-weight: 500;
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        max-width: 450px;
        animation: slideInRight 0.3s ease;
        display: flex;
        align-items: center;
        gap: 12px;
    `;
    banner.innerHTML = `
        <i class="fas ${icon}" style="color: ${borderColor}; font-size: 18px;"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="background:none;border:none;font-size:18px;cursor:pointer;color:${textColor};margin-left:auto;">✕</button>
    `;
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
// SCHEDULE VIEWING - YOUR WORKING CODE
// ============================================================

function openScheduleViewing(listingId, listingType, agentId) {
    console.log('openScheduleViewing called!', listingId, listingType, agentId);
    
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }
    
    openScheduleModal(listingId, listingType, agentId);
}

function openScheduleModal(listingId, listingType, agentId) {
    let modal = document.getElementById('schedule-viewing-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'schedule-viewing-modal';
        modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;display:none;align-items:center;justify-content:center;';
        modal.innerHTML = `
            <div style="background:#fff;border-radius:16px;padding:30px;max-width:560px;width:90%;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h3 style="font-family:'Prata',serif;font-size:22px;color:#0A0A0A;margin:0;">
                        <i class="fas fa-calendar-check" style="color:#C6A43F;"></i> Schedule Viewing
                    </h3>
                    <button onclick="closeScheduleModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#888;padding:0 8px;">✕</button>
                </div>
                
                <div style="display:flex;align-items:center;gap:15px;padding:15px;background:#f9f9f9;border-radius:8px;margin-bottom:20px;">
                    <div style="width:50px;height:50px;border-radius:50%;background:#C6A43F;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:18px;flex-shrink:0;">
                        <span id="schedule-agent-initial">A</span>
                    </div>
                    <div>
                        <strong id="schedule-agent-name" style="font-size:16px;color:#0A0A0A;"><?= $agentName ?></strong>
                        <p style="color:#888;font-size:13px;margin:2px 0 0;">
                            <span id="schedule-division">Williams Connect Home</span> · 
                            <span id="schedule-listing-title"><?= $listingTitle ?></span>
                        </p>
                    </div>
                </div>
                
                <form id="schedule-form">
                    <input type="hidden" name="listing_id" id="schedule-listing-id" value="${listingId}">
                    <input type="hidden" name="listing_type" id="schedule-listing-type" value="${listingType}">
                    <input type="hidden" name="agent_id" id="schedule-agent-id" value="${agentId}">
                    <input type="hidden" name="inquiry_type" value="viewing">
                    
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div style="margin-bottom:15px;">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Your Name *</label>
                            <input type="text" name="name" id="schedule-user-name" required placeholder="John Doe" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                        </div>
                        <div style="margin-bottom:15px;">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Your Email *</label>
                            <input type="email" name="email" id="schedule-user-email" required placeholder="john@example.com" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                        </div>
                    </div>
                    
                    <div style="margin-bottom:15px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Your Phone</label>
                        <input type="tel" name="phone" id="schedule-user-phone" placeholder="+1 (555) 000-0000" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                    </div>
                    
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div style="margin-bottom:15px;">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Preferred Date *</label>
                            <input type="date" name="preferred_date" id="schedule-date" required style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                        </div>
                        <div style="margin-bottom:15px;">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Preferred Time *</label>
                            <select name="preferred_time" id="schedule-time" required style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
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
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Additional Notes</label>
                        <textarea name="message" id="schedule-message" rows="3" placeholder="Any special requirements..." style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;resize:vertical;"></textarea>
                    </div>
                    
                    <div style="background:#FFF8E1;border-radius:8px;padding:12px;margin-bottom:20px;border-left:3px solid #C6A43F;">
                        <p style="font-size:12px;color:#7A5B00;margin:0;">
                            📅 <strong>What happens next?</strong> The agent will confirm your viewing appointment within 24 hours.
                        </p>
                    </div>
                    
                    <button type="submit" style="width:100%;padding:14px;background:#0A0A0A;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;transition:background 0.3s;">
                        <i class="fas fa-calendar-check"></i> Request Viewing
                    </button>
                    
                    <div id="schedule-error" style="display:none;margin-top:15px;padding:12px;background:#f8d7da;color:#721c24;border-radius:8px;"></div>
                    <div id="schedule-success" style="display:none;margin-top:15px;padding:12px;background:#d4edda;color:#155724;border-radius:8px;"></div>
                </form>
            </div>
        `;
        document.body.appendChild(modal);
        
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeScheduleModal();
            }
        });
        
        // Handle form submission - CLOSE MODAL AND SHOW BANNER
        document.getElementById('schedule-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const errorDiv = document.getElementById('schedule-error');
            const successDiv = document.getElementById('schedule-success');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            submitBtn.disabled = true;
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            
            const formData = new FormData(this);
            
            const messageField = document.getElementById('schedule-message');
            if (!messageField.value.trim()) {
                messageField.value = 'I would like to schedule a viewing for this property.';
            }
            
            try {
                const response = await fetch('/api/messages/send-inquiry.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    closeScheduleModal();
                    showSuccessBanner('✅ Viewing requested successfully! The agent will confirm your appointment within 24 hours.', false);
                    document.getElementById('schedule-form').reset();
                } else {
                    errorDiv.textContent = data.error || 'Failed to schedule viewing. Please try again.';
                    errorDiv.style.display = 'block';
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                errorDiv.textContent = 'Network error. Please check your connection and try again.';
                errorDiv.style.display = 'block';
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    }
    
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const dateStr = tomorrow.toISOString().split('T')[0];
    const dateInput = document.getElementById('schedule-date');
    if (dateInput) {
        dateInput.min = dateStr;
        dateInput.value = dateStr;
    }
    
    const meta = document.querySelector('meta[name="user-data"]');
    if (meta) {
        try {
            const userData = JSON.parse(meta.content);
            document.getElementById('schedule-user-name').value = userData.name || '';
            document.getElementById('schedule-user-email').value = userData.email || '';
            document.getElementById('schedule-user-phone').value = userData.phone || '';
        } catch (e) {}
    }
    
    document.getElementById('schedule-agent-initial').textContent = '<?= substr($agentName, 0, 1) ?>';
    document.getElementById('schedule-agent-name').textContent = '<?= $agentName ?>';
    document.getElementById('schedule-listing-title').textContent = '<?= $listingTitle ?>';
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeScheduleModal() {
    const modal = document.getElementById('schedule-viewing-modal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// ============================================================
// CONTACT AGENT - FIXED
// ============================================================

function openContactAgent(agentId, agentName, division) {
    console.log('openContactAgent called!', agentId, agentName, division);
    
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }
    
    const listingId = <?= $listingId ?>;
    const listingType = 'property';
    
    showContactModal(listingId, listingType, agentId, agentName, division);
}

function showContactModal(listingId, listingType, agentId, agentName, division) {
    let modal = document.getElementById('contact-agent-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'contact-agent-modal';
        modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;display:none;align-items:center;justify-content:center;';
        modal.innerHTML = `
            <div style="max-width:520px;background:#fff;border-radius:16px;padding:30px;width:90%;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h3 style="font-family:'Prata',serif;font-size:22px;color:#0A0A0A;margin:0;">
                        <i class="fas fa-envelope" style="color:#C6A43F;"></i> Contact Agent
                    </h3>
                    <button onclick="closeContactModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#888;padding:0 8px;">✕</button>
                </div>
                
                <div style="display:flex;align-items:center;gap:15px;padding:15px;background:#f9f9f9;border-radius:8px;margin-bottom:20px;">
                    <div style="width:50px;height:50px;border-radius:50%;background:#C6A43F;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:18px;flex-shrink:0;">
                        <span id="contact-agent-initial">A</span>
                    </div>
                    <div>
                        <strong id="contact-agent-name" style="font-size:16px;color:#0A0A0A;"><?= $agentName ?></strong>
                        <p style="color:#888;font-size:13px;margin:2px 0 0;">
                            <span id="contact-verified-badge"></span>
                            <span id="contact-division">property</span>
                        </p>
                    </div>
                </div>
                
                <form id="contact-form">
                    <input type="hidden" name="listing_id" id="contact-listing-id" value="${listingId}">
                    <input type="hidden" name="listing_type" id="contact-listing-type" value="${listingType}">
                    <input type="hidden" name="agent_id" id="contact-agent-id" value="${agentId}">
                    
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
                    
                    <div style="background:#f0f9ff;border-radius:8px;padding:12px;margin-bottom:20px;">
                        <p style="font-size:12px;color:#0c5460;margin:0;">🔒 Your contact information is only shared with the agent for this specific inquiry.</p>
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
        
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeContactModal();
            }
        });
        
        // Handle form submission
        document.getElementById('contact-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const errorDiv = document.getElementById('c-error');
            const successDiv = document.getElementById('c-success');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            submitBtn.disabled = true;
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('/api/messages/send-inquiry.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    closeContactModal();
                    showSuccessBanner('✅ Inquiry sent successfully! The agent will contact you shortly.', false);
                    document.getElementById('contact-form').reset();
                } else {
                    errorDiv.textContent = data.error || 'Failed to send inquiry. Please try again.';
                    errorDiv.style.display = 'block';
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                errorDiv.textContent = 'Network error. Please try again.';
                errorDiv.style.display = 'block';
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    }
    
    // Set values
    document.getElementById('contact-listing-id').value = listingId;
    document.getElementById('contact-listing-type').value = listingType;
    document.getElementById('contact-agent-id').value = agentId;
    document.getElementById('contact-agent-name').textContent = agentName;
    document.getElementById('contact-agent-initial').textContent = agentName.charAt(0).toUpperCase();
    document.getElementById('contact-division').textContent = division;
    document.getElementById('c-subject').value = 'Inquiry about ' + division + ' listing';
    
    // Verified badge
    const verifiedBadge = document.getElementById('contact-verified-badge');
    if (<?= $agentVerified ? 'true' : 'false' ?>) {
        verifiedBadge.innerHTML = '<span style="color:#1B5E20;font-weight:600;">✓ Verified</span> ';
    } else {
        verifiedBadge.innerHTML = '';
    }
    
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
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeContactModal() {
    const modal = document.getElementById('contact-agent-modal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
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
    
    const buttons = document.querySelectorAll('button[onclick*="jeSaveListing"]');
    let btn = null;
    for (let b of buttons) {
        if (b.getAttribute('onclick').includes(`'${type}', ${id}`)) {
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
                showSuccessBanner('✅ Added to favorites!', false);
                if (icon) {
                    icon.className = 'fas fa-heart';
                    icon.style.color = '#C6A43F';
                }
            } else {
                showSuccessBanner('Removed from favorites', false);
                if (icon) {
                    icon.className = 'far fa-heart';
                    icon.style.color = '';
                }
            }
        } else {
            showSuccessBanner('❌ ' + (data.error || 'Failed to update favorites'), true);
        }
    })
    .catch(function(error) {
        console.error('Favorite error:', error);
        showSuccessBanner('❌ Network error. Please try again.', true);
    })
    .finally(function() {
        if (btn) {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    });
}

// ============================================================
// PAGE INITIALIZATION
// ============================================================

console.log('=== Detail page loaded successfully ===');
console.log('Listing ID: <?= $listingId ?>');
console.log('Agent ID: <?= $agentId ?>');
console.log('Agent Name: <?= $agentName ?>');

if (!document.getElementById('banner-styles')) {
    const style = document.createElement('style');
    style.id = 'banner-styles';
    style.textContent = `
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .custom-success-banner {
            animation: slideInRight 0.3s ease;
        }
    `;
    document.head.appendChild(style);
}
</script>

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

<?php include '../../templates/footer.php'; ?>
