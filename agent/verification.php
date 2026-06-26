<?php
/**
 * KINAS GROUP — Agent Verification Wizard
 *
 * 3 steps:
 *   1. Phone verified? (handled at login time, but show status here)
 *   2. MetaMap identity verification
 *   3. Business document upload (CAC, TIN, etc.)
 *
 * State machine on agent_profiles.verification_status:
 *   pending → phone_verified → kyc_passed → documents_submitted → approved
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/metamap.php';

SessionManager::requireAgent();
$userId = (int)$_SESSION['user_id'];
$db     = Database::getInstance()->getConnection();
$csrf   = Security::generateCSRFToken();

// Load current state
$row = $db->prepare("
    SELECT ap.verification_status, ap.kyc_submitted_at, ap.kyc_decision_at,
           ap.kyc_provider, ap.kyc_verification_id,
           ap.cac_number, ap.tin, ap.company_legal_name, ap.business_doc_notes,
           mv.verification_id AS mv_id, mv.mati_status, mv.created_at AS mv_started,
           u.name, u.email, u.phone, u.phone_verified_at
    FROM users u
    JOIN agent_profiles ap ON ap.user_id = u.id
    LEFT JOIN metamap_verifications mv ON mv.user_id = u.id
    WHERE u.id = ?
    ORDER BY mv.id DESC LIMIT 1
");
$row->execute([$userId]);
$state = $row->fetch(PDO::FETCH_ASSOC) ?: [];

$status       = $state['verification_status'] ?? 'pending';
$phoneVerified = !empty($state['phone_verified_at']);
$kycPassed    = in_array($status, ['kyc_passed','documents_submitted','approved'], true);
$docsUploaded = in_array($status, ['documents_submitted','approved'], true);
$approved     = $status === 'approved';

$metamap = new MetaMapService();
$pageTitle = 'Verification - KINAS GROUP';
include __DIR__ . '/../templates/header.php';
?>

<style>
    :root {
        --kg-gold: #C6A43F; --kg-gold-deep: #A8882E;
        --kg-ink: #0A0A0A; --kg-bone: #F8F6F1; --kg-mist: #F5F7FA;
        --kg-line: #E0E0E0; --kg-line-2: #E8E8E8; --kg-line-3: #F0F0F0;
        --kg-mute: #888; --kg-green: #1B5E20;
    }
    body { font-family: 'Inter', sans-serif; background: var(--kg-mist); color: var(--kg-ink); }
    .verify-shell { max-width: 880px; margin: 0 auto; padding: 40px 24px 80px; }
    .verify-header { margin-bottom: 32px; }
    .verify-header h1 { font-family: 'Prata', serif; font-size: 32px; }
    .verify-header h1 i { color: var(--kg-gold); margin-right: 12px; }
    .verify-header p { color: var(--kg-mute-3, #666); margin-top: 6px; font-size: 14px; }

    .step-list { display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; }
    .step {
        background: #fff; border: 1px solid var(--kg-line-2);
        border-radius: 16px; padding: 28px;
        position: relative; overflow: hidden;
        transition: all 0.3s;
    }
    .step::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        background: var(--kg-line-2);
    }
    .step.is-done::before    { background: var(--kg-green); }
    .step.is-active::before  { background: var(--kg-gold); }
    .step.is-rejected::before{ background: #B71C1C; }
    .step.is-pending::before { background: var(--kg-mute); }

    .step-head { display: flex; align-items: center; gap: 18px; }
    .step-num {
        width: 48px; height: 48px; border-radius: 50%;
        background: var(--kg-mist); color: var(--kg-mute);
        display: inline-flex; align-items: center; justify-content: center;
        font-family: 'Prata', serif; font-size: 20px; font-weight: 700;
        flex-shrink: 0; transition: all 0.3s;
    }
    .step.is-done    .step-num { background: var(--kg-green); color: #fff; }
    .step.is-active  .step-num { background: var(--kg-gold); color: var(--kg-ink); }
    .step.is-rejected .step-num { background: #B71C1C; color: #fff; }

    .step-title { font-family: 'Prata', serif; font-size: 19px; }
    .step-status { font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--kg-mute); }
    .step.is-done    .step-status { color: var(--kg-green); }
    .step.is-active  .step-status { color: var(--kg-gold-deep); }
    .step.is-rejected .step-status { color: #B71C1C; }

    .step-body { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--kg-line-3); font-size: 14px; color: #555; line-height: 1.7; }
    .step.is-active .step-body { display: block; }
    .step.is-done .step-body,
    .step.is-pending .step-body { display: none; }
    .step.is-rejected .step-body { display: block; }

    .cta-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 16px; }
    .btn-primary {
        display: inline-flex; align-items: center; gap: 8px;
        background: var(--kg-ink); color: var(--kg-gold);
        border: 2px solid var(--kg-gold);
        padding: 14px 28px; border-radius: 999px;
        font-family: 'Inter', sans-serif; font-size: 14px; font-weight: 600;
        letter-spacing: 0.5px; cursor: pointer; text-transform: uppercase;
        text-decoration: none; transition: all 0.3s;
    }
    .btn-primary:hover { background: var(--kg-gold); color: var(--kg-ink); }
    .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
    .btn-secondary {
        display: inline-flex; align-items: center; gap: 8px;
        background: #fff; color: var(--kg-ink);
        border: 1px solid var(--kg-ink);
        padding: 13px 24px; border-radius: 999px;
        font-family: 'Inter', sans-serif; font-size: 14px; font-weight: 600;
        cursor: pointer; text-decoration: none; text-transform: uppercase;
    }
    .btn-secondary:hover { background: var(--kg-ink); color: #fff; }

    .meta-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-top: 14px; }
    .meta-card { background: var(--kg-bone); border-radius: 8px; padding: 10px 14px; }
    .meta-card .l { font-size: 10px; color: var(--kg-mute); text-transform: uppercase; letter-spacing: 0.5px; }
    .meta-card .v { font-size: 13px; color: var(--kg-ink); font-weight: 500; margin-top: 2px; word-break: break-all; }

    .upload-zone {
        border: 2px dashed var(--kg-line); border-radius: 12px;
        padding: 24px; text-align: center; cursor: pointer;
        background: #FAFAFA; transition: all 0.2s; margin-bottom: 16px;
    }
    .upload-zone:hover, .upload-zone.is-drag { border-color: var(--kg-gold); background: rgba(198,164,63,0.04); }
    .upload-zone i { font-size: 32px; color: var(--kg-gold); display: block; margin-bottom: 8px; }
    .upload-zone p { font-size: 14px; font-weight: 500; margin: 0; }
    .upload-zone span { font-size: 11px; color: var(--kg-mute); }

    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
    .form-row label, .form-group label {
        display: block; font-size: 11px; font-weight: 600; color: #333;
        text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;
    }
    .form-row input, .form-group input, .form-group select {
        width: 100%; padding: 11px 14px; border: 1px solid var(--kg-line); border-radius: 4px;
        font-family: 'Inter', sans-serif; font-size: 14px;
    }
    .form-row input:focus, .form-group input:focus, .form-group select:focus {
        outline: none; border-color: var(--kg-gold);
        box-shadow: 0 0 0 3px rgba(198,164,63,0.12);
    }

    .toast { position: fixed; bottom: 24px; right: 24px; z-index: 9999;
        padding: 16px 22px; border-radius: 12px; font-weight: 500; font-size: 14px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15); transform: translateY(20px); opacity: 0; transition: all 0.3s; max-width: 400px; }
    .toast.show { transform: translateY(0); opacity: 1; }
    .toast.success { background: var(--kg-green); color: white; }
    .toast.error   { background: #B71C1C; color: white; }

    .info-banner { background: linear-gradient(135deg,#FFF8E1,#FFF3E0); border: 1px solid #FFE0B2; border-radius: 12px; padding: 18px 24px; margin-bottom: 24px; display: flex; gap: 14px; align-items: flex-start; }
    .info-banner i { color: #BF360C; font-size: 18px; }
    .info-banner .text { font-size: 13px; color: #5D4037; line-height: 1.5; }

    .success-banner { background: linear-gradient(135deg,#E8F5E9,#F1F8E9); border: 1px solid #A7F3D0; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; display: flex; gap: 16px; align-items: center; }
    .success-banner i { color: var(--kg-green); font-size: 28px; }
    .success-banner h3 { font-family: 'Prata', serif; color: var(--kg-green); margin-bottom: 4px; font-size: 18px; }
    .success-banner p { color: #2E7D32; font-size: 13px; margin: 0; }

    @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
</style>

<div class="je-page">
<div class="je-dash-shell">
    <?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
    <main class="je-dash-main" style="padding:0; background:transparent;">
<div class="verify-shell">

    <div class="verify-header">
        <h1><i class="fas fa-shield-alt"></i> Verification</h1>
        <p>Complete all three steps to become a verified KINAS agent and unlock the ability to list inventory.</p>
    </div>

    <?php if ($approved): ?>
        <div class="success-banner">
            <i class="fas fa-check-circle"></i>
            <div>
                <h3>You're a verified agent 🎉</h3>
                <p>You have full access. <?= $state['kyc_decision_at'] ? 'Approved on ' . date('M j, Y', strtotime($state['kyc_decision_at'])) : '' ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="info-banner">
        <i class="fas fa-info-circle"></i>
        <div class="text">
            <strong>How verification works:</strong> MetaMap handles your personal ID (fast, automated). Our admin team reviews your business documents (CAC, etc.) for trust and safety. Your ID images never touch our servers — they're stored with MetaMap under their privacy policy.
        </div>
    </div>

    <div class="step-list">

        <!-- ── Step 1: Phone ── -->
        <div class="step <?= $phoneVerified ? 'is-done' : 'is-active' ?>">
            <div class="step-head">
                <div class="step-num"><?= $phoneVerified ? '<i class="fas fa-check"></i>' : '1' ?></div>
                <div style="flex:1; min-width:0;">
                    <div class="step-status"><?= $phoneVerified ? 'Phone Verified' : 'Step 1 of 3' ?></div>
                    <div class="step-title">Confirm your phone number</div>
                </div>
            </div>
            <div class="step-body">
                <?php if ($phoneVerified): ?>
                    <p style="color:var(--kg-green);">✓ Phone verified<?= $state['phone'] ? ' — ' . htmlspecialchars($state['phone']) : '' ?>.</p>
                <?php else: ?>
                    <p>We send a 6-digit code via SMS to make sure you control the device you're using. Required before you can list.</p>
                    <div class="cta-row">
                        <a href="/auth/verify-phone.php" class="btn-primary"><i class="fas fa-mobile-alt"></i> Verify phone number</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Step 2: MetaMap KYC ── -->
        <div class="step <?= $approved ? 'is-done' : ($kycPassed ? 'is-done' : (!$phoneVerified ? 'is-pending' : 'is-active')) ?>">
            <div class="step-head">
                <div class="step-num"><?= $kycPassed ? '<i class="fas fa-check"></i>' : '2' ?></div>
                <div style="flex:1; min-width:0;">
                    <div class="step-status">
                        <?php
                        if ($status === 'kyc_passed' || $status === 'documents_submitted' || $status === 'approved') echo 'Identity Verified';
                        elseif (!$phoneVerified) echo 'Step 2 of 3 (locked)';
                        elseif ($status === 'rejected') echo 'Identity needs re-do';
                        else echo 'Step 2 of 3';
                        ?>
                    </div>
                    <div class="step-title">Identity verification (MetaMap)</div>
                </div>
            </div>
            <div class="step-body">
                <?php if ($kycPassed): ?>
                    <p style="color:var(--kg-green);">✓ Personal identity verified via MetaMap. <?= $state['mv_started'] ? 'Started ' . date('M j, Y g:i A', strtotime($state['mv_started'])) : '' ?></p>
                <?php elseif (!$phoneVerified): ?>
                    <p style="color:var(--kg-mute);">Complete Step 1 first to unlock this step.</p>
                <?php else: ?>
                    <p>MetaMap will guide you through a quick, secure flow. You'll need a valid government ID and a few minutes.</p>
                    <div class="cta-row">
                        <button id="metamapBtn" class="btn-primary">
                            <i class="fas fa-shield-alt"></i> Start MetaMap Verification
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Step 3: Business docs ── -->
        <div class="step <?= $approved ? 'is-done' : ($docsUploaded ? 'is-active' : (!$kycPassed ? 'is-pending' : 'is-pending')) ?>">
            <div class="step-head">
                <div class="step-num"><?= $approved ? '<i class="fas fa-check"></i>' : '3' ?></div>
                <div style="flex:1; min-width:0;">
                    <div class="step-status">
                        <?php
                        if ($approved) echo 'Business Verified';
                        elseif ($status === 'documents_submitted') echo 'Under Admin Review';
                        elseif ($status === 'rejected') echo 'Resubmit Required';
                        elseif (!$kycPassed) echo 'Step 3 of 3 (locked)';
                        else echo 'Step 3 of 3';
                        ?>
                    </div>
                    <div class="step-title">Business document upload (CAC, TIN, etc.)</div>
                </div>
            </div>
            <div class="step-body">
                <?php if ($approved): ?>
                    <p style="color:var(--kg-green);">✓ Business documents approved. You're a fully verified agent.</p>
                <?php elseif ($status === 'documents_submitted'): ?>
                    <p>Your documents are being reviewed by our admin team. This usually takes 1–2 business days. We'll notify you by SMS once a decision is made.</p>
                    <?php if ($state['business_doc_notes']): ?>
                        <div class="meta-row" style="margin-top:14px;">
                            <div class="meta-card">
                                <div class="l">Latest Note</div>
                                <div class="v"><?= htmlspecialchars($state['business_doc_notes']) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php elseif ($status === 'rejected'): ?>
                    <p style="color:#B71C1C;">Your previous submission was not approved. Reason: <?= htmlspecialchars($state['business_doc_notes'] ?: 'Did not meet requirements.') ?></p>
                    <p>Please re-submit with corrections below.</p>
                    <?php $showUpload = true; ?>
                <?php elseif (!$kycPassed): ?>
                    <p style="color:var(--kg-mute);">Complete Steps 1 &amp; 2 first to unlock this step.</p>
                <?php else: ?>
                    <?php $showUpload = true; ?>
                <?php endif; ?>

                <?php if (!empty($showUpload)): ?>
                    <form id="bizDocForm" enctype="multipart/form-data" style="margin-top:8px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                        <div class="form-group" style="margin-bottom:14px;">
                            <label>Document Type</label>
                            <select name="document_type" required>
                                <option value="cac_certificate">CAC Certificate of Incorporation</option>
                                <option value="tin_certificate">Tax ID (TIN) Certificate</option>
                                <option value="utility_bill">Utility bill (must match CAC address)</option>
                                <option value="other">Other supporting document</option>
                            </select>
                        </div>

                        <div class="form-row">
                            <div>
                                <label>CAC / RC / BN Number <span style="font-weight:400; color:#999;">(optional)</span></label>
                                <input type="text" name="cac_number" maxlength="50" value="<?= htmlspecialchars($state['cac_number'] ?? '') ?>" placeholder="e.g. RC 1234567">
                            </div>
                            <div>
                                <label>Tax ID (TIN) <span style="font-weight:400; color:#999;">(optional)</span></label>
                                <input type="text" name="tin_number" maxlength="50" value="<?= htmlspecialchars($state['tin'] ?? '') ?>" placeholder="e.g. 20012345-0001">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:14px;">
                            <label>Company Legal Name (as on CAC)</label>
                            <input type="text" name="company_legal_name" maxlength="255" value="<?= htmlspecialchars($state['company_legal_name'] ?? '') ?>" placeholder="e.g. Smith Luxury Motors Ltd">
                        </div>

                        <label style="margin-top:10px;">Upload Document <span style="color:#B71C1C;">*</span></label>
                        <div class="upload-zone" id="uploadZone" onclick="document.getElementById('bizFile').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload or drag &amp; drop</p>
                            <span>PDF, JPG, PNG — max 10MB</span>
                        </div>
                        <input type="file" id="bizFile" name="file" accept="image/*,application/pdf" required style="display:none;">
                        <div id="bizFilePreview" style="font-size:12px; color:#1B5E20; margin-bottom:14px; display:none;"></div>

                        <div class="cta-row">
                            <button type="submit" id="bizDocBtn" class="btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit for Review
                            </button>
                            <a href="/pages/contact.php" class="btn-secondary"><i class="fas fa-question-circle"></i> Need help?</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="info-banner" style="background:linear-gradient(135deg,#E3F2FD,#E8EAF6); border-color:#90CAF9;">
        <i style="color:#1565C0;" class="fas fa-lock"></i>
        <div class="text" style="color:#1565C0;">
            <strong>Privacy:</strong> KINAS GROUP does not see or store your ID images — those are handled by MetaMap under their privacy policy. Your CAC document is reviewed by our admin team for business verification only and is not shared publicly.
        </div>
    </div>
</div>
</div>
    </main>
</div>

<div id="kycToast" class="toast"></div>

<script>
(function(){
    const csrf = '<?= htmlspecialchars($csrf, ENT_QUOTES) ?>';
    const toast = document.getElementById('kycToast');
    function showToast(msg, type) {
        toast.textContent = msg; toast.className = 'toast ' + type + ' show';
        setTimeout(() => toast.classList.remove('show'), 4500);
    }

    // ── MetaMap start
    const metamapBtn = document.getElementById('metamapBtn');
    if (metamapBtn) {
        metamapBtn.addEventListener('click', async () => {
            metamapBtn.disabled = true;
            metamapBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting…';
            try {
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('country', 'NG');
                const res = await fetch('/api/agent/kyc-start.php', { method:'POST', body: fd, credentials:'same-origin' });
                const data = await res.json().catch(()=>({}));
                if (res.ok && data.success && data.hostedUrl) {
                    window.open(data.hostedUrl, '_blank', 'noopener,noreferrer');
                    showToast('MetaMap opened in a new tab. Return here when you\'re done.', 'success');
                    pollForKycUpdate();
                } else {
                    showToast(data.error || 'Could not start MetaMap.', 'error');
                }
            } catch (e) { showToast('Network error.', 'error'); }
            finally {
                metamapBtn.disabled = false;
                metamapBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Start MetaMap Verification';
            }
        });
    }

    // Poll until MetaMap's webhook updates our state
    function pollForKycUpdate() {
        let attempts = 0;
        const t = setInterval(async () => {
            attempts++;
            if (attempts > 60) { clearInterval(t); return; }
            try {
                const r = await fetch('/api/agent/kyc-status.php', { credentials:'same-origin' });
                const d = await r.json();
                if (d.status === 'kyc_passed' || d.status === 'documents_submitted' || d.status === 'approved') {
                    clearInterval(t);
                    showToast('Identity verified!', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else if (d.status === 'rejected') {
                    clearInterval(t);
                    showToast('Identity verification was declined.', 'error');
                }
            } catch (_) {}
        }, 5000);
    }

    // ── Business doc upload
    const fileInput = document.getElementById('bizFile');
    const filePreview = document.getElementById('bizFilePreview');
    const uploadZone = document.getElementById('uploadZone');
    if (fileInput) {
        fileInput.addEventListener('change', () => {
            if (fileInput.files[0]) {
                filePreview.style.display = 'block';
                filePreview.innerHTML = '<i class="fas fa-check-circle"></i> ' + fileInput.files[0].name + ' (' + Math.round(fileInput.files[0].size/1024) + ' KB)';
            }
        });
        ['dragenter','dragover'].forEach(e => uploadZone.addEventListener(e, ev => { ev.preventDefault(); uploadZone.classList.add('is-drag'); }));
        ['dragleave','drop'].forEach(e => uploadZone.addEventListener(e, ev => { ev.preventDefault(); uploadZone.classList.remove('is-drag'); }));
        uploadZone.addEventListener('drop', ev => {
            if (ev.dataTransfer.files[0]) {
                fileInput.files = ev.dataTransfer.files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });
    }

    const bizDocForm = document.getElementById('bizDocForm');
    if (bizDocForm) {
        bizDocForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('bizDocBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
            try {
                const fd = new FormData(bizDocForm);
                const res = await fetch('/api/agent/upload-business-doc.php', { method:'POST', body: fd, credentials:'same-origin' });
                const data = await res.json().catch(()=>({}));
                if (res.ok && data.success) {
                    showToast('Submitted! Our team will review within 1–2 business days.', 'success');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showToast(data.error || 'Upload failed.', 'error');
                }
            } catch (err) { showToast('Network error.', 'error'); }
            finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit for Review';
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
