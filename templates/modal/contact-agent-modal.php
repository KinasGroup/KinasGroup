<!-- ============================================================
KINAS BUILD: 2026.08.15.06
FILE: templates/modal/contact-agent-modal.php

COMPLETE SELF-CONTAINED CONTACT AGENT POPUP MODAL

Fixes:
- Modal no longer depends on admin/dashboard CSS.
- Modal is styled as a proper centered popup overlay.
- Works on public listing detail pages.
- Keeps existing openContactAgentModal() function signature.
- Keeps Option A inquiry permissions:
  customer, agent and admin may inquire, except on own listing.
============================================================ -->

<?php
$kaModalUid  = (class_exists('SessionManager') && SessionManager::isLoggedIn())
    ? (int)SessionManager::getUserId()
    : 0;

$kaModalRole = (string)($_SESSION['user_role'] ?? '');
?>

<style>
/* ============================================================
   KINAS CONTACT AGENT MODAL — SELF-CONTAINED POPUP STYLES
   ============================================================ */

#contact-agent-modal.kinas-contact-modal {
    position: fixed;
    inset: 0;
    z-index: 1000001;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(2px);
}

#contact-agent-modal.kinas-contact-modal * {
    box-sizing: border-box;
}

.kcm-card {
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    overflow-y: auto;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.25);
    padding: 24px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.kcm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.kcm-header h3 {
    margin: 0;
    font-family: 'Prata', serif;
    font-size: 22px;
    color: #0A0A0A;
}

.kcm-close {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 50%;
    background: #f1f3f4;
    color: #0A0A0A;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.2s ease, color 0.2s ease;
}

.kcm-close:hover {
    background: #C6A43F;
    color: #ffffff;
}

.kcm-agent {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 10px;
    margin-bottom: 20px;
}

.kcm-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #C6A43F;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
    flex-shrink: 0;
}

.kcm-agent-name {
    display: block;
    font-size: 16px;
    font-weight: 700;
    color: #0A0A0A;
}

.kcm-agent-meta {
    display: block;
    margin-top: 3px;
    font-size: 12px;
    color: #666666;
}

#contact-agent-form .kcm-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.kcm-field {
    margin-bottom: 14px;
}

.kcm-field label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #333333;
}

.kcm-field input,
.kcm-field textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #dddddd;
    border-radius: 8px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    color: #0A0A0A;
    background: #ffffff;
}

.kcm-field input:focus,
.kcm-field textarea:focus {
    outline: none;
    border-color: #C6A43F;
    box-shadow: 0 0 0 2px rgba(198, 164, 63, 0.12);
}

.kcm-field textarea {
    resize: vertical;
    min-height: 120px;
}

.kcm-hint {
    margin-top: 6px;
    font-size: 11px;
    color: #777777;
}

.kcm-checkbox {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    cursor: pointer;
    font-size: 13px;
    color: #555555;
}

.kcm-checkbox input {
    margin-top: 2px;
}

.kcm-privacy {
    background: #f0f9ff;
    border-left: 3px solid #17a2b8;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 18px;
}

.kcm-privacy p {
    margin: 0;
    font-size: 12px;
    color: #0c5460;
}

.kcm-submit {
    width: 100%;
    padding: 14px 18px;
    border: none;
    border-radius: 10px;
    background: #0A0A0A;
    color: #ffffff;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s ease, opacity 0.2s ease;
}

.kcm-submit:hover {
    background: #C6A43F;
    color: #0A0A0A;
}

.kcm-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.kcm-alert {
    display: none;
    margin-top: 15px;
    padding: 12px 14px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.5;
}

.kcm-alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.kcm-alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

@media (max-width: 600px) {
    .kcm-card {
        padding: 18px;
    }

    #contact-agent-form .kcm-grid {
        grid-template-columns: 1fr;
    }

    .kcm-header h3 {
        font-size: 20px;
    }
}
</style>

<div id="contact-agent-modal" class="kinas-contact-modal" style="display: none;">
    <div class="kcm-card">
        <div class="kcm-header">
            <h3>Contact Agent</h3>
            <button type="button" class="kcm-close" onclick="closeContactAgentModal()">✕</button>
        </div>

        <div class="kcm-agent">
            <div class="kcm-avatar" id="agent-avatar-preview">A</div>
            <div>
                <strong class="kcm-agent-name" id="agent-name-preview">Agent Name</strong>
                <span class="kcm-agent-meta">
                    <span id="agent-verified-preview"></span>
                    <span id="agent-division-preview"></span>
                </span>
            </div>
        </div>

        <form id="contact-agent-form">
            <?php
            if (class_exists('Security') && method_exists('Security', 'csrfField')) {
                echo Security::csrfField();
            }
            ?>

            <input type="hidden" name="listing_id" id="contact-listing-id">
            <input type="hidden" name="listing_type" id="contact-listing-type">
            <input type="hidden" name="agent_id" id="contact-agent-id">

            <div class="kcm-grid">
                <div class="kcm-field">
                    <label>Your Name *</label>
                    <input type="text"
                           name="name"
                           id="contact-name"
                           required
                           placeholder="John Doe"
                           value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>">
                </div>

                <div class="kcm-field">
                    <label>Your Email *</label>
                    <input type="email"
                           name="email"
                           id="contact-email"
                           required
                           placeholder="john@example.com"
                           value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>">
                </div>
            </div>

            <div class="kcm-field">
                <label>Your Phone</label>
                <input type="tel"
                       name="phone"
                       id="contact-phone"
                       placeholder="+234 800 000 0000">
            </div>

            <div class="kcm-field">
                <label>Subject</label>
                <input type="text"
                       name="subject"
                       id="contact-subject"
                       placeholder="Inquiry about your listing">
            </div>

            <div class="kcm-field">
                <label>Message *</label>
                <textarea name="message"
                          id="contact-message"
                          rows="5"
                          required
                          placeholder="Hi, I'm interested in your listing. Is it still available? I would like to know more about..."></textarea>
                <div class="kcm-hint">
                    Be specific about your inquiry to get a faster response.
                </div>
            </div>

            <div class="kcm-field">
                <label class="kcm-checkbox">
                    <input type="checkbox" name="copy_self" value="1" checked>
                    Send a copy of this inquiry to my email
                </label>
            </div>

            <div class="kcm-privacy">
                <p>
                    🔒 Your contact information is only shared with the agent for this specific inquiry.
                </p>
            </div>

            <button type="submit" class="kcm-submit">
                📧 Send Inquiry
            </button>

            <div id="contact-form-error" class="kcm-alert kcm-alert-danger"></div>
            <div id="contact-form-success" class="kcm-alert kcm-alert-success"></div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    // Server-side guard data.
    var KA_CURRENT_USER_ID = <?= (int)$kaModalUid ?>;
    var KA_CURRENT_USER_ROLE = <?= json_encode($kaModalRole) ?>;

    // Option A:
    // Customers, agents and admins may send listing inquiries.
    // Self-contact remains blocked.
    var KA_ALLOWED_INQUIRY_ROLES = ['user', 'agent', 'admin'];

    function getModal() {
        return document.getElementById('contact-agent-modal');
    }

    function kcmToast(message, type) {
        if (typeof kinasToast === 'function') {
            kinasToast(message, type || 'warning');
            return;
        }

        if (typeof window.showSuccessBanner === 'function') {
            window.showSuccessBanner(message, type === 'error');
            return;
        }

        alert(message);
    }

    function _openContactAgentModal(listingId, listingType, agentId, agentName, agentVerified, agentDivision) {
        var modal = getModal();
        if (!modal) return;

        // Guard 1: own listing.
        if (KA_CURRENT_USER_ID && parseInt(agentId, 10) === KA_CURRENT_USER_ID) {
            kcmToast('This is your own listing — inquiries from yourself are not allowed.', 'warning');
            return;
        }

        // Guard 2: allowed roles only.
        if (KA_CURRENT_USER_ID && KA_ALLOWED_INQUIRY_ROLES.indexOf(KA_CURRENT_USER_ROLE) === -1) {
            kcmToast('Your account type cannot send listing inquiries.', 'warning');
            return;
        }

        document.getElementById('contact-listing-id').value = listingId || '';
        document.getElementById('contact-listing-type').value = listingType || '';
        document.getElementById('contact-agent-id').value = agentId || '';

        document.getElementById('agent-name-preview').textContent = agentName || 'Agent';

        var verifiedEl = document.getElementById('agent-verified-preview');
        if (agentVerified) {
            verifiedEl.innerHTML = '✓ Verified · ';
        } else {
            verifiedEl.innerHTML = '';
        }

        document.getElementById('agent-division-preview').textContent = agentDivision || '';
        document.getElementById('agent-avatar-preview').textContent = String(agentName || 'A').charAt(0).toUpperCase();
        document.getElementById('contact-subject').value = 'Inquiry about ' + (agentDivision || 'listing');

        document.getElementById('contact-form-error').style.display = 'none';
        document.getElementById('contact-form-success').style.display = 'none';

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function _closeContactAgentModal() {
        var modal = getModal();
        if (!modal) return;

        modal.style.display = 'none';
        document.body.style.overflow = '';

        var errorDiv = document.getElementById('contact-form-error');
        var successDiv = document.getElementById('contact-form-success');

        if (errorDiv) errorDiv.style.display = 'none';
        if (successDiv) successDiv.style.display = 'none';
    }

    // Expose globally for inline onclick handlers.
    window.openContactAgentModal = window.openContactAgentModal || _openContactAgentModal;
    window.closeContactAgentModal = window.closeContactAgentModal || _closeContactAgentModal;

    function bindModal() {
        var modal = getModal();
        if (!modal) return;

        // Close when clicking outside card.
        if (!modal.dataset.kinasOverlayBound) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    window.closeContactAgentModal();
                }
            });

            modal.dataset.kinasOverlayBound = '1';
        }

        // Close with Escape key.
        if (!document.body.dataset.kinasContactEscBound) {
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    window.closeContactAgentModal();
                }
            });

            document.body.dataset.kinasContactEscBound = '1';
        }

        var form = document.getElementById('contact-agent-form');
        if (!form || form.dataset.kinasBound === '1') return;

        form.dataset.kinasBound = '1';

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            var agentId = document.getElementById('contact-agent-id').value;

            if (KA_CURRENT_USER_ID && parseInt(agentId, 10) === KA_CURRENT_USER_ID) {
                window.closeContactAgentModal();
                kcmToast('This is your own listing — inquiries from yourself are not allowed.', 'warning');
                return;
            }

            if (KA_CURRENT_USER_ID && KA_ALLOWED_INQUIRY_ROLES.indexOf(KA_CURRENT_USER_ROLE) === -1) {
                window.closeContactAgentModal();
                kcmToast('Your account type cannot send listing inquiries.', 'warning');
                return;
            }

            var submitBtn = form.querySelector('button[type="submit"]');
            var errorDiv = document.getElementById('contact-form-error');
            var successDiv = document.getElementById('contact-form-success');
            var originalText = submitBtn.textContent;

            submitBtn.textContent = 'Sending...';
            submitBtn.disabled = true;
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';

            var formData = new FormData(form);

            try {
                var response = await fetch('/api/messages/send-inquiry.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                var data = await response.json();

                if (data.success) {
                    successDiv.textContent = '✅ Inquiry sent successfully! The agent will contact you shortly.';
                    successDiv.style.display = 'block';

                    setTimeout(function () {
                        window.closeContactAgentModal();
                        form.reset();
                    }, 2000);
                } else {
                    errorDiv.textContent = data.error || 'Failed to send inquiry. Please try again.';
                    errorDiv.style.display = 'block';
                }
            } catch (error) {
                errorDiv.textContent = 'Network error. Please try again.';
                errorDiv.style.display = 'block';
            } finally {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindModal);
    } else {
        bindModal();
    }
})();
</script>
