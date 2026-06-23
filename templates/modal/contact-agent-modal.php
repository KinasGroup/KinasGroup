<!-- Contact Agent Modal -->
<?php
// Ensure Security class is loaded
require_once __DIR__ . '/../../includes/security.php';
?>
<div id="contact-agent-modal" class="admin-modal" style="display: none;">
    <div class="admin-modal-content" style="max-width: 520px;">
        <div class="admin-modal-header">
            <h3>Contact Agent</h3>
            <button onclick="closeContactAgentModal()" class="modal-close">✕</button>
        </div>
        
        <div id="agent-info-preview" style="display: flex; align-items: center; gap: 15px; padding: 15px; background: #f9f9f9; border-radius: 8px; margin-bottom: 20px;">
            <div id="agent-avatar-preview" style="width: 50px; height: 50px; border-radius: 50%; background: #e0e0e0; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 600;">
                A
            </div>
            <div>
                <strong id="agent-name-preview">Agent Name</strong>
                <p style="color: var(--tertiary); font-size: 12px; margin: 2px 0 0;">
                    <span id="agent-verified-preview"></span>
                    <span id="agent-division-preview"></span>
                </p>
            </div>
        </div>
        
        <form id="contact-agent-form">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="listing_id" id="contact-listing-id">
            <input type="hidden" name="listing_type" id="contact-listing-type">
            <input type="hidden" name="agent_id" id="contact-agent-id">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Your Name *</label>
                    <input type="text" name="name" id="contact-name" required placeholder="John Doe"
                           value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Your Email *</label>
                    <input type="email" name="email" id="contact-email" required placeholder="john@example.com"
                           value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Your Phone</label>
                <input type="tel" name="phone" id="contact-phone" placeholder="+1 (555) 000-0000">
            </div>
            
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" id="contact-subject" placeholder="Inquiry about your listing">
            </div>
            
            <div class="form-group">
                <label>Message *</label>
                <textarea name="message" id="contact-message" rows="5" required 
                          placeholder="Hi, I'm interested in your listing. Is it still available? I would like to know more about..."></textarea>
                <p style="font-size: 11px; color: var(--tertiary); margin-top: 5px;">
                    Be specific about your inquiry to get a faster response.
                </p>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: start; gap: 8px; cursor: pointer; font-size: 13px; color: var(--tertiary);">
                    <input type="checkbox" name="copy_self" value="1" checked style="margin-top: 2px;">
                    Send a copy of this inquiry to my email
                </label>
            </div>
            
            <div style="background: #f0f9ff; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                <p style="font-size: 12px; color: var(--accent); margin: 0;">
                    🔒 Your contact information is only shared with the agent for this specific inquiry.
                </p>
            </div>
            
            <button type="submit" class="je2-button black" style="width: 100%; padding: 14px; font-size: 16px;">
                📧 Send Inquiry
            </button>
            
            <div id="contact-form-error" class="alert alert-danger" style="display: none; margin-top: 15px;"></div>
            <div id="contact-form-success" class="alert alert-success" style="display: none; margin-top: 15px;"></div>
        </form>
    </div>
</div>

<script>
function openContactAgentModal(listingId, listingType, agentId, agentName, agentVerified, agentDivision) {
    document.getElementById('contact-listing-id').value = listingId;
    document.getElementById('contact-listing-type').value = listingType;
    document.getElementById('contact-agent-id').value = agentId;
    document.getElementById('agent-name-preview').textContent = agentName;
    
    if (agentVerified) {
        document.getElementById('agent-verified-preview').innerHTML = '<span class="verified-badge" style="display: inline-block; margin-right: 8px;">✓ Verified</span>';
    } else {
        document.getElementById('agent-verified-preview').innerHTML = '';
    }
    
    document.getElementById('agent-division-preview').textContent = agentDivision;
    document.getElementById('agent-avatar-preview').textContent = agentName.charAt(0).toUpperCase();
    document.getElementById('contact-subject').value = 'Inquiry about ' + agentDivision + ' listing';
    
    document.getElementById('contact-agent-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeContactAgentModal() {
    document.getElementById('contact-agent-modal').style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('contact-form-error').style.display = 'none';
    document.getElementById('contact-form-success').style.display = 'none';
}

// Close modal when clicking overlay
document.getElementById('contact-agent-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeContactAgentModal();
    }
});

// Handle contact form submission
document.getElementById('contact-agent-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const errorDiv = document.getElementById('contact-form-error');
    const successDiv = document.getElementById('contact-form-success');
    const originalText = submitBtn.textContent;
    
    submitBtn.textContent = 'Sending...';
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
            successDiv.textContent = '✅ Inquiry sent successfully! The agent will contact you shortly.';
            successDiv.style.display = 'block';
            
            // Reset form after 2 seconds
            setTimeout(() => {
                closeContactAgentModal();
                this.reset();
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
</script>
