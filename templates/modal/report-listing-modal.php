<!-- Report Listing Modal -->
<div id="report-listing-modal" class="admin-modal" style="display: none;">
    <div class="admin-modal-content" style="max-width: 500px;">
        <div class="admin-modal-header">
            <h3>🚩 Report Listing</h3>
            <button onclick="closeReportModal()" class="modal-close">✕</button>
        </div>
        
        <p style="color: var(--tertiary); margin-bottom: 20px;">
            We take listing accuracy seriously. Please let us know if there's an issue with this listing.
        </p>
        
        <form id="report-listing-form">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="listing_id" id="report-listing-id">
            <input type="hidden" name="listing_type" id="report-listing-type">
            
            <div class="form-group">
                <label>Reason for Report *</label>
                <select name="report_reason" id="report-reason" required>
                    <option value="">Select a reason...</option>
                    <option value="inaccurate">Inaccurate Information</option>
                    <option value="fake">Fake or Scam Listing</option>
                    <option value="sold">Already Sold/Rented</option>
                    <option value="duplicate">Duplicate Listing</option>
                    <option value="inappropriate">Inappropriate Content</option>
                    <option value="spam">Spam</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Additional Details</label>
                <textarea name="report_details" id="report-details" rows="4" 
                          placeholder="Please provide specific details about the issue..."></textarea>
                <p style="font-size: 11px; color: var(--tertiary); margin-top: 5px;">
                    Providing details helps our team review the report faster.
                </p>
            </div>
            
            <div class="form-group">
                <label>Your Email (optional)</label>
                <input type="email" name="reporter_email" id="reporter-email" 
                       placeholder="We may contact you for more information"
                       value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>">
            </div>
            
            <button type="submit" class="je2-button" style="width: 100%; padding: 14px; color: #e74c3c; border-color: #e74c3c;">
                🚩 Submit Report
            </button>
            
            <div id="report-form-error" class="alert alert-danger" style="display: none; margin-top: 15px;"></div>
            <div id="report-form-success" class="alert alert-success" style="display: none; margin-top: 15px;"></div>
        </form>
        
        <p style="font-size: 12px; color: var(--tertiary); margin-top: 20px; text-align: center;">
            False reports may result in account restrictions. 
            <a href="/pages/terms-of-use.php" style="color: var(--accent);">Learn more</a>
        </p>
    </div>
</div>

<script>
function openReportModal(listingId, listingType) {
    document.getElementById('report-listing-id').value = listingId;
    document.getElementById('report-listing-type').value = listingType;
    document.getElementById('report-listing-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeReportModal() {
    document.getElementById('report-listing-modal').style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('report-form-error').style.display = 'none';
    document.getElementById('report-form-success').style.display = 'none';
}

// Close when clicking overlay
document.getElementById('report-listing-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeReportModal();
    }
});

// Handle report submission
document.getElementById('report-listing-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const errorDiv = document.getElementById('report-form-error');
    const successDiv = document.getElementById('report-form-success');
    
    submitBtn.textContent = 'Submitting...';
    submitBtn.disabled = true;
    errorDiv.style.display = 'none';
    successDiv.style.display = 'none';
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('/api/listings/flag.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            successDiv.innerHTML = '✅ Report submitted successfully. Our team will review this listing shortly.';
            successDiv.style.display = 'block';
            
            setTimeout(() => {
                closeReportModal();
                this.reset();
            }, 2500);
        } else {
            errorDiv.textContent = data.error || 'Failed to submit report. Please try again.';
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        errorDiv.textContent = 'Network error. Please try again.';
        errorDiv.style.display = 'block';
    } finally {
        submitBtn.textContent = '🚩 Submit Report';
        submitBtn.disabled = false;
    }
});
</script>