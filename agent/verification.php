<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN could keep serving a stale snapshot after a
// status change, making the page look like it isn't updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * KINAS GROUP — Agent Verification
 *
 * Simplified flow (manual business-document upload removed — Didit KYB
 * fully replaces it):
 *   1. Email (always shown as complete — informational only)
 *   2. Phone verification
 *   3. Didit KYC — personal identity verification
 *   4. Didit KYB — automated business verification (registry, UBOs, AML)
 *
 * NOTE: the old manual-document-upload code path (agent_profiles
 * .business_doc_notes / documents_submitted state /
 * api/agent/upload-business-doc.php) still exists in the codebase for
 * historical records, but is intentionally not linked from this page
 * anymore.
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/didit.php';

SessionManager::requireAgent();
$userId = (int)$_SESSION['user_id'];
$db     = Database::getInstance()->getConnection();
$csrf   = Security::generateCSRFToken();

// Load current state (KYC + KYB + phone)
$row = $db->prepare("
    SELECT ap.verification_status, ap.kyc_submitted_at, ap.kyc_decision_at,
           ap.kyb_status, ap.company_name,
           u.phone, u.phone_verified_at
    FROM users u
    JOIN agent_profiles ap ON ap.user_id = u.id
    WHERE u.id = ?
");
$row->execute([$userId]);
$state = $row->fetch(PDO::FETCH_ASSOC) ?: [];

$status        = $state['verification_status'] ?? 'pending';
$phoneVerified = !empty($state['phone_verified_at']);
// 'kyc_passed' now means "identity confirmed" for BOTH individuals and
// businesses — for a business it's an intermediate state (KYB still
// pending), not yet a full pass. See api/webhooks/didit.php.
$kycPassed     = in_array($status, ['kyc_passed', 'documents_submitted', 'approved'], true);
$approved      = $status === 'approved';
$isBusiness    = trim((string)($state['company_name'] ?? '')) !== '';

$kybStatus   = $state['kyb_status'] ?? 'not_started';
$kybApproved = $kybStatus === 'approved';

// Step state machine for the timeline UI. 'locked' = previous step not
// done yet, 'rejected' = Didit declined it, 'pending' = actionable now.
$steps = [
    1 => [
        'title' => 'Email Verified',
        'icon'  => 'fa-envelope',
        'state' => 'completed',
    ],
    2 => [
        'title' => 'Phone Verified',
        'icon'  => 'fa-phone',
        'state' => $phoneVerified ? 'completed' : 'pending',
    ],
    3 => [
        'title' => 'Identity Verification (KYC)',
        'icon'  => 'fa-id-card',
        'state' => $kycPassed ? 'completed' : (!$phoneVerified ? 'locked' : 'pending'),
    ],
    4 => [
        'title' => $isBusiness
            ? 'Business Verification (KYB via Didit) — Required'
            : 'Business Verification (KYB via Didit) — Optional',
        'icon'  => 'fa-building',
        'state' => $kybApproved
            ? 'completed'
            : ($kybStatus === 'rejected'
                ? 'rejected'
                : (!$phoneVerified ? 'locked' : 'pending')),
    ],
];

$pageTitle = 'Agent Verification - KINAS GROUP';
include __DIR__ . '/../templates/header.php';
?>

<style>
.verification-timeline {
    max-width: 800px;
    margin: 30px auto;
}
.step {
    display: flex;
    gap: 16px;
    margin-bottom: 28px;
    position: relative;
}
.step:last-child { margin-bottom: 0; }
.step::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 42px;
    bottom: -22px;
    width: 2px;
    background: #e0e0e0;
}
.step:last-child::before { display: none; }
.step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
    z-index: 1;
}
.step.completed .step-icon { background: #2E7D32; color: white; }
.step.pending .step-icon   { background: #C6A43F; color: white; }
.step.locked .step-icon    { background: #999; color: white; }
.step.rejected .step-icon  { background: #B71C1C; color: white; }
.step-content {
    flex: 1;
    padding-top: 4px;
}
.step h3 {
    margin: 0 0 4px 0;
    font-size: 15px;
    font-weight: 600;
}
.step p {
    color: #666;
    margin: 0;
    font-size: 13px;
}
.step.rejected p { color: #B71C1C; }
.btn-start {
    background: #C6A43F;
    color: #0A0A0A;
    font-size: 13px;
    padding: 6px 16px;
    border-radius: 4px;
    display: inline-block;
    text-decoration: none;
    margin-top: 6px;
    border: none;
    cursor: pointer;
    font-family: inherit;
}
.btn-start:hover { background: #b3942e; color: #0A0A0A; }
.btn-start:disabled { opacity: 0.6; cursor: not-allowed; }
/* Page header styles */
.page-header h1 { font-size: 22px; margin-bottom: 4px; }
.page-header p { font-size: 14px; color: #666; }
/* Alert styles */
.alert-success h3 { font-size: 18px; margin-bottom: 8px; }
.alert-success p { font-size: 14px; }
.alert-success .btn-gold { font-size: 14px; padding: 8px 24px; }

/* Toast (needed for the Didit start/resume/retry buttons below) */
.toast {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    padding: 14px 20px; border-radius: 10px; font-weight: 500; font-size: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15); transform: translateY(20px);
    opacity: 0; transition: all 0.3s; max-width: 380px;
}
.toast.show { transform: translateY(0); opacity: 1; }
.toast.success { background: #2E7D32; color: #fff; }
.toast.error   { background: #B71C1C; color: #fff; }
.toast.info    { background: #0A0A0A; color: #fff; }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>

<main class="je-dash-main">
    <div class="page-header">
        <h1><i class="fas fa-user-check" style="color:#C6A43F; font-size:20px;"></i> Account Verification</h1>
        <p style="font-size:14px; color:#666; margin-top:4px;">Complete these steps to activate your agent account</p>
    </div>

    <?php if ($isBusiness && !$approved): ?>
    <div style="background:#FFF8E1;border:1px solid #FFE082;border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#5d4a00;">
        <i class="fas fa-building"></i> Your account has a business name on file, so both identity verification (KYC)
        <strong>and</strong> business verification (KYB) are required before you're fully verified — KYC alone isn't
        enough for a business account. If this should be an individual account instead, remove the company name from your profile.
    </div>
    <?php endif; ?>

    <div class="verification-timeline">
        <?php foreach ($steps as $num => $step): ?>
        <div class="step <?= $step['state'] ?>">
            <div class="step-icon">
                <i class="fas <?= $step['state'] === 'completed' ? 'fa-check' : $step['icon'] ?>"></i>
            </div>
            <div class="step-content">
                <h3>Step <?= $num ?>: <?= htmlspecialchars($step['title']) ?></h3>

                <?php if ($step['state'] === 'completed'): ?>
                    <p>✓ Completed<?= ($num === 2 && $state['phone']) ? ' — ' . htmlspecialchars($state['phone']) : '' ?></p>

                <?php elseif ($step['state'] === 'locked'): ?>
                    <p>Complete phone verification first to unlock this step.</p>

                <?php elseif ($num === 2): ?>
                    <p>We text a 6-digit code to confirm you control this device.</p>
                    <a href="/auth/verify-phone.php" class="btn-start">Verify Phone</a>

                <?php elseif ($num === 3): ?>
                    <p>Quick, secure ID check via Didit. Takes a few minutes.</p>
                    <button type="button" id="diditKycBtn" class="btn-start">
                        <i class="fas fa-shield-alt"></i> Start Identity Verification
                    </button>

                <?php elseif ($num === 4 && $step['state'] === 'rejected'): ?>
                    <p>Business verification was declined. You can try again.</p>
                    <button type="button" id="diditKybBtn" class="btn-start">
                        <i class="fas fa-building"></i> Retry Business Verification
                    </button>

                <?php elseif ($num === 4 && $kybStatus === 'review_needed'): ?>
                    <p>Under Didit review — no action needed, usually resolves within a day.</p>

                <?php elseif ($num === 4 && $kybStatus === 'in_progress'): ?>
                    <p>In progress. If you closed the Didit tab, resume below.</p>
                    <button type="button" id="diditKybBtn" class="btn-start">
                        <i class="fas fa-building"></i> Resume Business Verification
                    </button>

                <?php elseif ($num === 4): ?>
                    <p>Didit automatically checks your business registry, ownership, and sanctions status.</p>
                    <button type="button" id="diditKybBtn" class="btn-start">
                        <i class="fas fa-building"></i> Start Business Verification (Didit)
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($approved): ?>
    <div class="alert alert-success" style="text-align:center; padding:24px 20px; margin-top:20px;">
        <h3 style="font-size:18px; margin-bottom:6px;">✅ Your account is fully verified!</h3>
        <p style="font-size:14px; margin-bottom:12px;">You can now create listings and use all agent features.</p>
        <a href="/agent/dashboard.php" class="btn btn-gold" style="font-size:14px; padding:8px 28px; display:inline-block; background:#C6A43F; color:#0A0A0A; text-decoration:none; border-radius:4px;">Go to Dashboard</a>
    </div>
    <?php endif; ?>
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

    // ── Didit KYC start ──
    const diditKycBtn = document.getElementById('diditKycBtn');
    if (diditKycBtn) {
        diditKycBtn.addEventListener('click', async () => {
            diditKycBtn.disabled = true;
            const originalHtml = diditKycBtn.innerHTML;
            diditKycBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting…';
            try {
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                const res = await fetch('/api/agent/didit-kyc-start.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success && data.url) {
                    window.open(data.url, '_blank', 'noopener,noreferrer');
                    showToast("Didit opened in a new tab. Return here when you're done.", 'success');
                    pollForKycUpdate();
                } else {
                    showToast(data.error || 'Could not start identity verification.', 'error');
                }
            } catch (e) { showToast('Network error.', 'error'); }
            finally {
                diditKycBtn.disabled = false;
                diditKycBtn.innerHTML = originalHtml;
            }
        });
    }

    function pollForKycUpdate() {
        let attempts = 0;
        const t = setInterval(async () => {
            attempts++;
            if (attempts > 60) { clearInterval(t); return; }
            try {
                const r = await fetch('/api/agent/didit-kyc-status.php', { credentials: 'same-origin' });
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

    // ── Didit KYB start ──
    const diditKybBtn = document.getElementById('diditKybBtn');
    if (diditKybBtn) {
        diditKybBtn.addEventListener('click', async () => {
            diditKybBtn.disabled = true;
            const originalHtml = diditKybBtn.innerHTML;
            diditKybBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting…';
            try {
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                const res = await fetch('/api/agent/didit-kyb-start.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success && data.url) {
                    window.open(data.url, '_blank', 'noopener,noreferrer');
                    showToast("Didit opened in a new tab. Return here when you're done.", 'success');
                    pollForKybUpdate();
                } else {
                    showToast(data.error || 'Could not start business verification.', 'error');
                }
            } catch (e) { showToast('Network error.', 'error'); }
            finally {
                diditKybBtn.disabled = false;
                diditKybBtn.innerHTML = originalHtml;
            }
        });
    }

    function pollForKybUpdate() {
        let attempts = 0;
        const t = setInterval(async () => {
            attempts++;
            if (attempts > 60) { clearInterval(t); return; }
            try {
                const r = await fetch('/api/agent/didit-kyb-status.php', { credentials: 'same-origin' });
                const d = await r.json();
                if (d.status === 'approved') {
                    clearInterval(t);
                    showToast('Business verified!', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else if (d.status === 'rejected') {
                    clearInterval(t);
                    showToast('Business verification was declined.', 'error');
                } else if (d.status === 'review_needed') {
                    clearInterval(t);
                    showToast('Business verification is under review.', 'info');
                }
            } catch (_) {}
        }, 5000);
    }
})();
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
