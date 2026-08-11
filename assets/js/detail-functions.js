// /assets/js/detail-functions.js
// KINAS GROUP - Detail Page Functions
//
// AMENDED:
// - Preserves existing schedule/contact/save behaviour.
// - Adds product review modal behaviour.
// - Adds review loading/rendering helpers.
// - Adds review reporting helper.
// - Adds CSRF token handling for review endpoints.

/**
Open Schedule Viewing Modal with Calendar
*/
function openScheduleViewing(listingId, listingType, agentId) {
    // Check if user is logged in
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }

    // Get listing details
    fetch(`/api/listings/get.php?id=${listingId}&type=${listingType}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const listing = data.listing;
                const agentName = data.agent_name || 'Agent';
                const division = listingType.charAt(0).toUpperCase() + listingType.slice(1);
                openScheduleModal(listingId, listingType, agentId, agentName, division, listing.title);
            }
        })
        .catch(() => {
            // Fallback: open with basic info
            openScheduleModal(listingId, listingType, agentId, 'Agent', listingType, 'Listing');
        });
}

/**
Open the Schedule Viewing Modal
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
    document.getElementById('schedule-division').textContent = division.charAt(0).toUpperCase() + division.slice(1);
    document.getElementById('schedule-agent-initial').textContent = agentName.charAt(0).toUpperCase();

    // Set default date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const dateStr = tomorrow.toISOString().split('T')[0];

    const dateInput = document.getElementById('schedule-date');

    if (dateInput) {
        dateInput.min = dateStr;
        dateInput.value = dateStr;
    }

    // Show modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Reset form
    const form = document.getElementById('schedule-form');

    if (form) {
        form.reset();
    }

    document.getElementById('schedule-error').style.display = 'none';
    document.getElementById('schedule-success').style.display = 'none';

    // Pre-fill user info if available
    const userName = document.getElementById('schedule-user-name');
    const userEmail = document.getElementById('schedule-user-email');
    const userPhone = document.getElementById('schedule-user-phone');

    if (window.userData) {
        if (userName) userName.value = window.userData.name || '';
        if (userEmail) userEmail.value = window.userData.email || '';
        if (userPhone) userPhone.value = window.userData.phone || '';
    }
}

/**
Create Schedule Viewing Modal HTML
*/
function createScheduleModal() {
    const modal = document.createElement('div');

    modal.id = 'schedule-viewing-modal';
    modal.className = 'admin-mo dal';
    modal.style.display = 'none';

    modal.innerHTML = `
    <div class="admin-modal-content" style="max-width:560px;background:#fff;border-radius:16px;padding:30px;position:relative;margin:20px;">
        <div class="admin-modal-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="font-family:'Prata',serif;font-size:22px;color:#0A0A0A;margin:0;">
                <i class="fas fa-calendar-check" style="color:#C6A43F;"></i> Schedule Viewing
            </h3>
            <button onclick="closeScheduleModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#888;padding:0 8px;">✕</button>
        </div>

        <div id="schedule-agent-info" style="display:flex;align-items:center;gap:15px;padding:15px;background:#f9f9f9;border-radius:8px;margin-bottom:20px;">
            <div style="width:50px;height:50px;border-radius:50%;background:#C6A43F;display:flex;align-items:center;justify-content:center;color:white;font-weight:600;font-size:18px;flex-shrink:0;">
                <span id="schedule-agent-initial">A</span>
            </div>
            <div>
                <strong id="schedule-agent-name" style="font-size:16px;color:#0A0A0A;">Agent Name</strong>
                <p style="color:#888;font-size:13px;margin:2px 0 0;">
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

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Your Name *</label>
                    <input type="text" name="name" id="schedule-user-name" required placeholder="John Doe" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Your Email *</label>
                    <input type="email" name="email" id="schedule-user-email" required placeholder="john@example.com" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:15px;">
                <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Your Phone</label>
                <input type="tel" name="phone" id="schedule-user-phone" placeholder="+1 (555) 000-0000" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Preferred Date *</label>
                    <input type="date" name="preferred_date" id="schedule-date" required style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                </div>

                <div class="form-group" style="margin-bottom:15px;">
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

            <div class="form-group" style="margin-bottom:15px;">
                <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px;">Additional Notes</label>
                <textarea name="message" id="schedule-message" rows="3" placeholder="Any special requirements or questions about the property..." style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;resize:vertical;"></textarea>
            </div>

            <div style="background:#FFF8E1;border-radius:8px;padding:12px;margin-bottom:20px;border-left:3px solid #C6A43F;">
                <p style="font-size:12px;color:#7A5B00;margin:0;">
                    📅 <strong>What happens next?</strong> The agent will confirm your viewing appointment within 24 hours.
                    Both the listing agent and super agent will be notified of your request.
                </p>
            </div>

            <button type="submit" style="width:100%;padding:14px;background:#0A0A0A;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;transition:background 0.3s;">
                <i class="fas fa-calendar-check"></i> Request Viewing
            </button>

            <div id="schedule-error" class="alert alert-danger" style="display:none;margin-top:15px;padding:12px;background:#f8d7da;color:#721c24;border-radius:8px;"></div>
            <div id="schedule-success" class="alert alert-success" style="display:none;margin-top:15px;padding:12px;background:#d4edda;color:#155724;border-radius:8px;"></div>
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
Close Schedule Viewing Modal
*/
function closeScheduleModal() {
    const modal = document.getElementById('schedule-viewing-modal');

    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

/**
Open Contact Agent (replaces phone call)
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
        // Get listing title from page
        const titleEl = document.querySelector('h1.je-spec-title');
        const listingTitle = titleEl ? titleEl.textContent.trim() : 'Listing';

        openContactAgentModal(listingId, listingType, agentId, agentName, false, division);
    } else {
        // Fallback: redirect to contact page
        window.location.href = `/contact.php?agent=${agentId}&listing=${listingId}`;
    }
}

/**
Save Listing to Favorites
*/
function jeSaveListing(type, id) {
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }

    // Find the button that was clicked
    const btn = document.querySelector(`button[onclick*="jeSaveListing('${type}', ${id})"]`);
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
                const icon = btn?.querySelector('i');

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
Check if user is logged in
*/
function isUserLoggedIn() {
    // Check meta tag for user data
    const meta = document.querySelector('meta[name="user-data"]');

    if (meta) {
        try {
            const data = JSON.parse(meta.content);
            return data.loggedIn === true;
        } catch (e) {
            return false;
        }
    }

    // Fallback: check for user-id meta
    return document.querySelector('meta[name="user-id"]')?.content ? true : false;
}

/**
Show login required message
*/
function showLoginRequired() {
    showToast('Please login to continue', 'warning');

    setTimeout(() => {
        window.location.href = '/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
    }, 1500);
}

/**
Show toast notification
*/
function showToast(message, type = 'info') {
    // Remove existing toasts
    const existing = document.querySelectorAll('.je-toast');
    existing.forEach(t => t.remove());

    const toast = document.createElement('div');

    toast.className = `je-toast toast-${type}`;
    toast.innerHTML = message;
    toast.style.cssText = `position:fixed;bottom:30px;right:30px;padding:14px 24px;border-radius:12px;background:${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#17a2b8'};color:${type === 'warning' ? '#1a1a2e' : 'white'};font-weight:500;z-index:99999;box-shadow:0 8px 30px rgba(0,0,0,0.2);max-width:400px;animation:slideInRight 0.3s ease;font-family:'Inter',sans-serif;font-size:14px;`;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';

        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Load user data from meta tags
document.addEventListener('DOMContentLoaded', function() {
    const meta = document.querySelector('meta[name="user-data"]');

    if (meta) {
        try {
            window.userData = JSON.parse(meta.content);
        } catch (e) {
            window.userData = null;
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

// ============================================================
// PRODUCT REVIEWS — FRONTEND BEHAVIOUR
// ============================================================

/**
Get CSRF token from page meta, hidden input, or cached global.
*/
function getKinasCSRFToken() {
    if (window.kinasCSRFToken) {
        return window.kinasCSRFToken;
    }

    const meta = document.querySelector('meta[name="csrf-token"]');

    if (meta && meta.content) {
        return meta.content;
    }

    const input = document.querySelector('input[name="csrf_token"]');

    if (input && input.value) {
        return input.value;
    }

    return '';
}

/**
Ensure a CSRF token is available. Attempts to fetch one from the auth
endpoint if the current page does not already expose one.
*/
async function ensureKinasCSRFToken() {
    let token = getKinasCSRFToken();

    if (token) {
        return token;
    }

    try {
        const response = await fetch('/api/auth/csrf-token.php', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        token = data.token || data.csrf_token || '';

        if (token) {
            window.kinasCSRFToken = token;
        }
    } catch (error) {
        token = '';
    }

    return token;
}

/**
Escape HTML for safe rendering.
*/
function escapeKinasHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

/**
Render star HTML for a rating.
*/
function renderProductReviewStars(rating) {
    rating = Math.max(0, Math.min(5, parseInt(rating || 0, 10)));

    let html = '<span class="kr-review-stars" aria-label="' + rating + ' out of 5 stars">';

    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            html += '<i class="fas fa-star"></i>';
        } else {
            html += '<i class="far fa-star"></i>';
        }
    }

    html += '</span>';

    return html;
}

/**
Open the product review modal.
*/
function openProductReviewModal(listingId, listingType, listingTitle) {
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }

    let modal = document.getElementById('product-review-modal');

    if (!modal) {
        modal = createProductReviewModal();
        document.body.appendChild(modal);
    }

    document.getElementById('review-listing-id').value = listingId;
    document.getElementById('review-listing-type').value = listingType;
    document.getElementById('review-listing-title').textContent = listingTitle || 'this product';

    const form = document.getElementById('review-form');

    if (form) {
        form.reset();
    }

    setProductReviewRating(0);

    document.getElementById('review-error').style.display = 'none';
    document.getElementById('review-success').style.display = 'none';

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

/**
Close the product review modal.
*/
function closeProductReviewModal() {
    const modal = document.getElementById('product-review-modal');

    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

/**
Set review rating state and star UI.
*/
function setProductReviewRating(rating) {
    const input = document.getElementById('review-rating');

    if (!input) {
        return;
    }

    input.value = rating || '';

    document.querySelectorAll('#review-stars .kr-review-star').forEach(function(button) {
        const buttonRating = parseInt(button.getAttribute('data-rating') || '0', 10);
        const icon = button.querySelector('i');

        if (buttonRating <= rating) {
            button.classList.add('active');

            if (icon) {
                icon.className = 'fas fa-star';
            }
        } else {
            button.classList.remove('active');

            if (icon) {
                icon.className = 'far fa-star';
            }
        }
    });
}

/**
Create product review modal.
*/
function createProductReviewModal() {
    const modal = document.createElement('div');

    modal.id = 'product-review-modal';
    modal.className = 'kr-review-modal';
    modal.style.display = 'none';

    modal.innerHTML = `
    <div class="kr-review-modal-content" style="max-width:520px;background:#fff;border-radius:16px;padding:30px;position:relative;margin:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
            <h3 style="font-family:'Prata',serif;font-size:22px;color:#0A0A0A;margin:0;">
                <i class="fas fa-star" style="color:#C6A43F;"></i> Write a Review
            </h3>
            <button type="button" onclick="closeProductReviewModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#888;padding:0 8px;">✕</button>
        </div>

        <div style="padding:12px 14px;background:#f9f9f9;border-radius:8px;margin-bottom:18px;font-size:14px;color:#333;">
            Reviewing: <strong id="review-listing-title">this product</strong>
        </div>

        <form id="review-form">
            <input type="hidden" name="listing_id" id="review-listing-id">
            <input type="hidden" name="listing_type" id="review-listing-type">
            <input type="hidden" name="rating" id="review-rating" value="">

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:8px;">Your Rating *</label>

                <div id="review-stars" style="display:flex;gap:8px;font-size:28px;color:#C6A43F;">
                    <button type="button" class="kr-review-star" data-rating="1" aria-label="1 star"><i class="far fa-star"></i></button>
                    <button type="button" class="kr-review-star" data-rating="2" aria-label="2 stars"><i class="far fa-star"></i></button>
                    <button type="button" class="kr-review-star" data-rating="3" aria-label="3 stars"><i class="far fa-star"></i></button>
                    <button type="button" class="kr-review-star" data-rating="4" aria-label="4 stars"><i class="far fa-star"></i></button>
                    <button type="button" class="kr-review-star" data-rating="5" aria-label="5 stars"><i class="far fa-star"></i></button>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label for="review-comment" style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Your Review *</label>
                <textarea id="review-comment" name="comment" rows="5" minlength="10" maxlength="2000" required placeholder="Share your honest experience with this product..." style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;resize:vertical;"></textarea>
            </div>

            <div style="background:#FFF8E1;border-radius:8px;padding:12px;margin-bottom:18px;border-left:3px solid #C6A43F;">
                <p style="font-size:12px;color:#7A5B00;margin:0;">
                    <strong>Moderation:</strong> Reviews are checked before appearing publicly. Approved reviews may show a verified purchase badge.
                </p>
            </div>

            <button type="submit" style="width:100%;padding:14px;background:#0A0A0A;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;">
                <i class="fas fa-paper-plane"></i> Submit Review
            </button>

            <div id="review-error" style="display:none;margin-top:15px;padding:12px;background:#f8d7da;color:#721c24;border-radius:8px;"></div>
            <div id="review-success" style="display:none;margin-top:15px;padding:12px;background:#d4edda;color:#155724;border-radius:8px;"></div>
        </form>
    </div>
    `;

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeProductReviewModal();
        }
    });

    modal.querySelectorAll('.kr-review-star').forEach(function(button) {
        button.addEventListener('click', function() {
            const rating = parseInt(this.getAttribute('data-rating') || '0', 10);
            setProductReviewRating(rating);
        });
    });

    modal.querySelector('#review-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = this.querySelector('button[type="submit"]');
        const errorDiv = document.getElementById('review-error');
        const successDiv = document.getElementById('review-success');
        const originalText = submitBtn.innerHTML;

        errorDiv.style.display = 'none';
        successDiv.style.display = 'none';

        const rating = document.getElementById('review-rating').value;

        if (!rating) {
            errorDiv.textContent = 'Please select a star rating.';
            errorDiv.style.display = 'block';
            return;
        }

        const comment = document.getElementById('review-comment').value.trim();

        if (comment.length < 10) {
            errorDiv.textContent = 'Please write at least 10 characters in your review.';
            errorDiv.style.display = 'block';
            return;
        }

        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        submitBtn.disabled = true;

        const formData = new FormData(this);

        const csrfToken = await ensureKinasCSRFToken();

        if (csrfToken) {
            formData.set('csrf_token', csrfToken);
        }

        try {
            const response = await fetch('/api/reviews/create.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                successDiv.innerHTML = data.message || '✅ Thank you. Your review has been submitted and is awaiting moderation.';
                successDiv.style.display = 'block';

                this.reset();
                setProductReviewRating(0);

                setTimeout(function() {
                    closeProductReviewModal();
                }, 2500);
            } else {
                errorDiv.textContent = data.error || 'Could not submit your review. Please try again.';
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
Load approved reviews for a listing into a target container.
*/
async function loadProductReviews(listingType, listingId, targetId) {
    const container = document.getElementById(targetId);

    if (!container) {
        return;
    }

    container.innerHTML = '<div class="kr-reviews-loading"><i class="fas fa-spinner fa-spin"></i> Loading reviews...</div>';

    try {
        const url = '/api/reviews/list.php?listing_type=' + encodeURIComponent(listingType) +
            '&listing_id=' + encodeURIComponent(listingId) +
            '&limit=10';

        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Unable to load reviews.');
        }

        renderProductReviews(container, data);
    } catch (error) {
        container.innerHTML = '<div class="kr-reviews-empty">Reviews are not available right now.</div>';
    }
}

/**
Render review list and summary.
*/
function renderProductReviews(container, data) {
    const summary = data.summary || data.data?.summary || {};
    const reviews = Array.isArray(data.reviews)
        ? data.reviews
        : Array.isArray(data.data?.reviews)
            ? data.data.reviews
            : [];

    let html = '';

    html += '<div class="kr-reviews-summary" style="margin-bottom:20px;">';

    if (parseInt(summary.count || 0, 10) > 0) {
        const average = parseFloat(summary.average || 0).toFixed(1);

        html += '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">';
        html += '<span style="font-size:34px;font-weight:700;color:#0A0A0A;">' + escapeKinasHtml(average) + '</span>';
        html += renderProductReviewStars(Math.round(summary.average || 0));
        html += '<span style="font-size:13px;color:#888;">' + escapeKinasHtml(summary.count || 0) + ' review(s)</span>';
        html += '</div>';
    } else {
        html += '<div class="kr-reviews-empty" style="color:#888;font-style:italic;">No approved reviews yet.</div>';
    }

    html += '</div>';

    if (reviews.length > 0) {
        html += '<div class="kr-reviews-list">';

        reviews.forEach(function(review) {
            const name = review.user_name || review.name || 'Customer';
            const rating = parseInt(review.rating || 0, 10);
            const comment = review.comment || '';
            const reviewId = parseInt(review.id || 0, 10);
            const verified = Boolean(review.verified_purchase);

            let createdAt = '';

            if (review.created_at) {
                const date = new Date(review.created_at);

                if (!isNaN(date.getTime())) {
                    createdAt = date.toLocaleDateString(undefined, {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                }
            }

            html += '<div class="kr-review-item" style="border-top:1px solid #eee;padding:16px 0;">';

            html += '<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:8px;">';
            html += '<div>';
            html += '<strong style="color:#0A0A0A;">' + escapeKinasHtml(name) + '</strong>';

            if (verified) {
                html += ' <span style="background:#E8F5E9;color:#2E7D32;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-left:6px;">Verified Purchase</span>';
            }

            if (createdAt) {
                html += '<div style="font-size:12px;color:#999;margin-top:3px;">' + escapeKinasHtml(createdAt) + '</div>';
            }

            html += '</div>';
            html += renderProductReviewStars(rating);
            html += '</div>';

            if (comment) {
                html += '<div style="color:#444;line-height:1.6;font-size:14px;white-space:pre-line;">' + escapeKinasHtml(comment) + '</div>';
            }

            if (reviewId > 0) {
                html += '<button type="button" onclick="reportProductReview(' + reviewId + ')" style="margin-top:10px;background:none;border:none;color:#999;font-size:12px;cursor:pointer;padding:0;">';
                html += '<i class="fas fa-flag"></i> Report';
                html += '</button>';
            }

            html += '</div>';
        });

        html += '</div>';
    }

    container.innerHTML = html;
}

/**
Report a review.
*/
async function reportProductReview(reviewId) {
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }

    const reason = prompt('Briefly explain why you are reporting this review:');

    if (!reason || reason.trim().length < 5) {
        showToast('Please provide a short reason.', 'warning');
        return;
    }

    const formData = new FormData();

    formData.append('review_id', reviewId);
    formData.append('reason', reason.trim());

    const csrfToken = await ensureKinasCSRFToken();

    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }

    try {
        const response = await fetch('/api/reviews/report.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.success) {
            showToast(data.message || 'Review reported. Thank you.', 'success');
        } else {
            showToast(data.error || 'Could not report this review.', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

/**
Inject review styles.
*/
function injectProductReviewStyles() {
    if (document.getElementById('kinas-review-styles')) {
        return;
    }

    const style = document.createElement('style');

    style.id = 'kinas-review-styles';
    style.textContent = `
        .kr-review-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .kr-review-modal-content {
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 90%;
        }

        .kr-review-star {
            background: none;
            border: none;
            color: #C6A43F;
            cursor: pointer;
            font-size: 28px;
            padding: 0;
            line-height: 1;
            transition: transform 0.15s ease;
        }

        .kr-review-star:hover {
            transform: scale(1.15);
        }

        .kr-review-stars {
            color: #C6A43F;
            display: inline-flex;
            gap: 2px;
        }

        .kr-reviews-loading,
        .kr-reviews-empty {
            padding: 20px;
            text-align: center;
            color: #888;
        }
    `;

    document.head.appendChild(style);
}

// Expose review functions globally for inline HTML handlers.
window.openProductReviewModal = openProductReviewModal;
window.closeProductReviewModal = closeProductReviewModal;
window.reportProductReview = reportProductReview;
window.loadProductReviews = loadProductReviews;
window.renderProductReviewStars = renderProductReviewStars;

// Convenience alias.
window.openReviewModal = openProductReviewModal;

document.addEventListener('DOMContentLoaded', function() {
    injectProductReviewStyles();
});
