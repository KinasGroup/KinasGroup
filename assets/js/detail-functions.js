// /assets/js/detail-functions.js
// KINAS GROUP - Detail Page Functions

/**
 * Open Schedule Viewing Modal with Calendar
 */
function openScheduleViewing(listingId, listingType, agentId) {
    // First, check if user is logged in
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }
    
    // Get listing and agent details
    fetch(`/api/listings/get.php?id=${listingId}&type=${listingType}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const listing = data.listing;
                const agentName = data.agent_name || 'Agent';
                const division = listingType.charAt(0).toUpperCase() + listingType.slice(1);
                
                // Open the schedule viewing modal
                openScheduleModal(listingId, listingType, agentId, agentName, division, listing.title);
            }
        })
        .catch(() => {
            // Fallback: open with basic info
            openScheduleModal(listingId, listingType, agentId, 'Agent', listingType, 'Listing');
        });
}

/**
 * Open the Schedule Viewing Modal
 */
function openScheduleModal(listingId, listingType, agentId, agentName, division, listingTitle) {
    // Check if modal exists, create if not
    let modal = document.getElementById('schedule-viewing-modal');
    if (!modal) {
        modal = createScheduleModal();
        document.body.appendChild(modal);
    }
    
    // Set values
    document.getElementById('schedule-listing-id').value = listingId;
    document.getElementById('schedule-listing-type').value = listingType;
    document.getElementById('schedule-agent-id').value = agentId;
    document.getElementById('schedule-agent-name').textContent = agentName;
    document.getElementById('schedule-listing-title').textContent = listingTitle;
    document.getElementById('schedule-division').textContent = division;
    
    // Set default date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const dateStr = tomorrow.toISOString().split('T')[0];
    document.getElementById('schedule-date').min = dateStr;
    document.getElementById('schedule-date').value = dateStr;
    
    // Show modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Reset form
    const form = document.getElementById('schedule-form');
    if (form) form.reset();
    document.getElementById('schedule-error').style.display = 'none';
    document.getElementById('schedule-success').style.display = 'none';
    
    // Pre-fill user info if available
    const userName = document.getElementById('schedule-user-name');
    const userEmail = document.getElementById('schedule-user-email');
    const userPhone = document.getElementById('schedule-user-phone');
    
    if (userName && window.userData) {
        userName.value = window.userData.name || '';
        userEmail.value = window.userData.email || '';
        userPhone.value = window.userData.phone || '';
    }
}

/**
 * Create Schedule Viewing Modal HTML
 */
function createScheduleModal() {
    const modal = document.createElement('div');
    modal.id = 'schedule-viewing-modal';
    modal.className = 'admin-modal';
    modal.style.display = 'none';
    modal.innerHTML = `
        <div class="admin-modal-content" style="max-width: 560px;">
            <div class="admin-modal-header">
                <h3><i class="fas fa-calendar-check" style="color:#C6A43F;"></i> Schedule Viewing</h3>
                <button onclick="closeScheduleModal()" class="modal-close">✕</button>
            </div>
            
            <div id="schedule-agent-info" style="display: flex; align-items: center; gap: 15px; padding: 15px; background: #f9f9f9; border-radius: 8px; margin-bottom: 20px;">
                <div style="width: 50px; height: 50px; border-radius: 50%; background: #C6A43F; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 18px;">
                    <span id="schedule-agent-initial">A</span>
                </div>
                <div>
                    <strong id="schedule-agent-name">Agent Name</strong>
                    <p style="color: #888; font-size: 13px; margin: 2px 0 0;">
                        <span id="schedule-division">Division</span> · 
                        <span id="schedule-listing-title">Listing</span>
                    </p>
                </div>
            </div>
            
            <form id="schedule-form">
                <input type="hidden" name="listing_id" id="schedule-listing-id">
                <input type="hidden" name="listing_type" id="schedule-listing-type">
                <input type="hidden" name="agent_id" id="schedule-agent-id">
                <input type="hidden" name="inquiry_type" value="viewing">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Your Name *</label>
                        <input type="text" name="name" id="schedule-user-name" required placeholder="John Doe">
                    </div>
                    <div class="form-group">
                        <label>Your Email *</label>
                        <input type="email" name="email" id="schedule-user-email" required placeholder="john@example.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Your Phone</label>
                    <input type="tel" name="phone" id="schedule-user-phone" placeholder="+1 (555) 000-0000">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Preferred Date *</label>
                        <input type="date" name="preferred_date" id="schedule-date" required>
                    </div>
                    <div class="form-group">
                        <label>Preferred Time *</label>
                        <select name="preferred_time" id="schedule-time" required>
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
                
                <div class="form-group">
                    <label>Additional Notes</label>
                    <textarea name="message" id="schedule-message" rows="3" placeholder="Any special requirements or questions about the property..."></textarea>
                </div>
                
                <div style="background: #FFF8E1; border-radius: 8px; padding: 12px; margin-bottom: 20px; border-left: 3px solid #C6A43F;">
                    <p style="font-size: 12px; color: #7A5B00; margin: 0;">
                        📅 <strong>What happens next?</strong> The agent will confirm your viewing appointment within 24 hours.
                        Both the listing agent and super agent will be notified of your request.
                    </p>
                </div>
                
                <button type="submit" class="je2-button black" style="width: 100%; padding: 14px; font-size: 16px;">
                    <i class="fas fa-calendar-check"></i> Request Viewing
                </button>
                
                <div id="schedule-error" class="alert alert-danger" style="display: none; margin-top: 15px;"></div>
                <div id="schedule-success" class="alert alert-success" style="display: none; margin-top: 15px;"></div>
            </form>
        </div>
    `;
    
    // Close on overlay click
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeScheduleModal();
        }
    });
    
    // Handle form submission
    modal.querySelector('#schedule-form').addEventListener('submit', async function(e) {
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
        
        try {
            const response = await fetch('/api/messages/send-inquiry.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                successDiv.innerHTML = '✅ <strong>Viewing requested!</strong> The agent will confirm your appointment shortly.';
                successDiv.style.display = 'block';
                
                setTimeout(() => {
                    closeScheduleModal();
                    this.reset();
                }, 3000);
            } else {
                errorDiv.textContent = data.error || 'Failed to schedule viewing. Please try again.';
                errorDiv.style.display = 'block';
            }
        } catch (error) {
            errorDiv.textContent = 'Network error. Please check your connection and try again.';
            errorDiv.style.display = 'block';
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
    
    return modal;
}

/**
 * Close Schedule Viewing Modal
 */
function closeScheduleModal() {
    const modal = document.getElementById('schedule-viewing-modal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

/**
 * Open Contact Agent (replaces phone call)
 */
function openContactAgent(agentId, agentName, division) {
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }
    
    // Get listing info from the page
    const listingId = document.querySelector('input[name="listing_id"]')?.value || 
                     document.querySelector('[data-listing-id]')?.dataset.listingId || 0;
    const listingType = document.querySelector('input[name="listing_type"]')?.value || 
                       document.querySelector('[data-listing-type]')?.dataset.listingType || 'car';
    
    // Use existing contact agent modal
    if (typeof openContactAgentModal === 'function') {
        openContactAgentModal(listingId, listingType, agentId, agentName, false, division);
    } else {
        // Fallback: redirect to contact page
        window.location.href = `/contact.php?agent=${agentId}&listing=${listingId}`;
    }
}

/**
 * Save Listing to Favorites
 */
function jeSaveListing(type, id) {
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }
    
    const btn = event?.target?.closest?.('button') || document.querySelector(`[data-save-btn="${id}"]`);
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
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const icon = btn?.querySelector('i') || document.querySelector(`[data-save-btn="${id}"] i`);
            if (data.action === 'added') {
                showToast('✅ Added to favorites!', 'success');
                if (icon) {
                    icon.className = 'fas fa-heart';
                    icon.style.color = '#C6A43F';
                }
            } else {
                showToast('Removed from favorites', 'info');
                if (icon) {
                    icon.className = 'far fa-heart';
                    icon.style.color = '';
                }
            }
        } else if (data.error) {
            showToast(data.error, 'error');
        }
    })
    .catch(() => {
        showToast('Network error. Please try again.', 'error');
    })
    .finally(() => {
        if (btn) {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    });
}

/**
 * Check if user is logged in
 */
function isUserLoggedIn() {
    return window.userLoggedIn === true || document.querySelector('[data-user-id]') !== null;
}

/**
 * Show login required message
 */
function showLoginRequired() {
    showToast('Please login to continue', 'warning');
    setTimeout(() => {
        window.location.href = '/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
    }, 1500);
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    // Remove existing toasts
    const existing = document.querySelectorAll('.je-toast');
    existing.forEach(t => t.remove());
    
    const toast = document.createElement('div');
    toast.className = `je-toast toast-${type}`;
    toast.innerHTML = message;
    toast.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        padding: 14px 24px;
        border-radius: 12px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#17a2b8'};
        color: ${type === 'warning' ? '#1a1a2e' : 'white'};
        font-weight: 500;
        z-index: 99999;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        max-width: 400px;
        animation: slideInRight 0.3s ease;
        font-family: 'Inter', sans-serif;
        font-size: 14px;
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Load user data from meta tags or session
document.addEventListener('DOMContentLoaded', function() {
    // Check for user data in meta tags
    const userMeta = document.querySelector('meta[name="user-data"]');
    if (userMeta) {
        try {
            window.userData = JSON.parse(userMeta.content);
            window.userLoggedIn = true;
        } catch (e) {
            window.userData = null;
            window.userLoggedIn = false;
        }
    }
    
    // Add toast styles if not present
    if (!document.querySelector('#je-toast-styles')) {
        const style = document.createElement('style');
        style.id = 'je-toast-styles';
        style.textContent = `
            @keyframes slideInRight {
                from { opacity: 0; transform: translateX(100px); }
                to { opacity: 1; transform: translateX(0); }
            }
            .je-toast {
                animation: slideInRight 0.3s ease;
            }
        `;
        document.head.appendChild(style);
    }
});
