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
 * OPTION 1 KYC NAME RULE:
 * - Didit approved + name matches => Step 3 completed
 * - Didit approved + name mismatch => Step 3 rejected (with reason)
 * - Didit approved + name unreadable => Step 3 under review
 *
 * REVAMP: on page load we run an on-demand Didit sync (self-heal) so a
 * missed/rejected webhook can never leave an approved agent showing as
 * "unverified". Business rules preserved: KYC pass => orange verified
 * badge + can list except car rentals; full/green only via KYB or admin.
 *
 * AMENDED:
 * - Step 3 now properly reflects in_progress, review_needed, rejected,
 *   and expired states instead of always showing the "Start" button.
 * - Step 4 also handles in_progress and review_needed properly.
 * - Rejection reason is shown when available.
 */

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/didit.php';
require_once __DIR__ . '/../includes/didit-sync.php';

SessionManager::requireAgent();

$userId = (int)$_SESSION['user_id'];
$db     = Database::getInstance()->getConnection();
$csrf   = Security::generateCSRFToken();

// ── SELF-HEAL: sync KYC + KYB from Didit BEFORE rendering ──
try { didit_sync_kyc($db, $userId); } catch (Throwable $e) { error_log('verification.php kyc sync error: ' . $e->getMessage()); }
try { didit_sync_kyb($db, $userId); } catch (Throwable $e) { error_log('verification.php kyb sync error: ' . $e->getMessage()); }

// ── Schema helpers ──
function verification_page_column_exists(PDO $db, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    try {
        $st = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
        ");
        $st->execute([$table, $column]);
        $cache[$key] = ((int)$st->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

// ── Load current state (KYC + KYB + phone + name-match info) ──
$apSelects = [
    'ap.verification_status',
    'ap.kyb_status',
    'ap.company_name',
];

if (verification_page_column_exists($db, 'agent_profiles', 'kyc_submitted_at')) {
    $apSelects[] = 'ap.kyc_submitted_at';
} else {
    $apSelects[] = 'NULL AS kyc_submitted_at';
}

if (verification_page_column_exists($db, 'agent_profiles', 'kyc_decision_at')) {
    $apSelects[] = 'ap.kyc_decision_at';
} else {
    $apSelects[] = 'NULL AS kyc_decision_at';
}

if (verification_page_column_exists($db, 'agent_profiles', 'kyc_rejection_reason')) {
    $apSelects[] = 'ap.kyc_rejection_reason';
} else {
    $apSelects[] = 'NULL AS kyc_rejection_reason';
}

if (verification_page_column_exists($db, 'agent_profiles', 'kyc_name_match')) {
    $apSelects[] = 'ap.kyc_name_match';
} else {
    $apSelects[] = 'NULL AS kyc_name_match';
}

$row = $db->prepare("
    SELECT " . implode(', ', $apSelects) . ",
           u.phone, u.phone_verified_at, u.name
    FROM users u
    JOIN agent_profiles ap ON ap.user_id = u.id
    WHERE u.id = ?
");
$row->execute([$userId]);
$state = $row->fetch(PDO::FETCH_ASSOC) ?: [];

$status            = $state['verification_status'] ?? 'pending';
$phoneVerified     = !empty($state['phone_verified_at']);
$userFullName      = trim($state['name'] ?? '');
$kycRejectionReason = trim((string)($state['kyc_rejection_reason'] ?? ''));
$kycNameMatch      = $state['kyc_name_match'] ?? null;

// ── KYC step state ──
// 'kyc_passed' means "identity confirmed" for BOTH individuals and
// businesses — for a business it's an intermediate state (KYB still
// pending), not yet a full pass.
$kycPassed  = in_array($status, ['kyc_passed', 'documents_submitted', 'approved'], true);
$approved   = $status === 'approved';
$isBusiness = trim((string)($state['company_name'] ?? '')) !== '';

// ── KYB step state ──
$kybStatus   = $state['kyb_status'] ?? 'not_started';
$kybApproved = $kybStatus === 'approved';

// ── Determine Step 3 state ──
if ($kycPassed) {
    $step3State = 'completed';
} elseif ($status === 'rejected') {
    $step3State = 'rejected';
} elseif ($status === 'review_needed') {
    $step3State = 'review';
} elseif ($status === 'in_progress') {
    $step3State = 'in_progress';
} elseif ($status === 'expired') {
    $step3State = 'expired';
} elseif (!$phoneVerified) {
    $step3State = 'locked';
} else {
    $step3State = 'pending';
}

// ── Determine Step 4 state ──
if ($kybApproved) {
    $step4State = 'completed';
} elseif ($kybStatus === 'rejected') {
    $step4State = 'rejected';
} elseif ($kybStatus === 'review_needed') {
    $step4State = 'review';
} elseif ($kybStatus === 'in_progress') {
    $step4State = 'in_progress';
} elseif ($kybStatus === 'expired') {
    $step4State = 'expired';
} elseif (!$phoneVerified) {
    $step4State = 'locked';
} else {
    $step4State = 'pending';
}

// ── Step definitions ──
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
        'state' => $step3State,
    ],
    4 => [
        'title' => $isBusiness
            ? 'Business Verification (KYB via Didit) — Required'
            : 'Business Verification (KYB via Didit) — Optional',
        'icon'  => 'fa-building',
        'state' => $step4State,
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
.step.review .step-icon    { background: #E65100; color: white; }
.step.in_progress .step-icon { background: #1565C0; color: white; }
.step.expired .step-icon   { background: #6D4C41; color: white; }
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
.step.review p { color: #E65100; }
.step.in_progress p { color: #1565C0; }
.step.expired p { color: #6D4C41; }
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
.btn-retry {
    background: #B71C1C;
    color: #fff;
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
.btn-retry:hover { background: #9a1717; color: #fff; }
.btn-resume {
    background: #1565C0;
    color: #fff;
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
.btn-resume:hover { background: #0d47a1; color: #fff; }
.rejection-reason {
    background: #FFEBEE;
    border: 1px solid #FFCDD2;
    border-radius: 4px;
    padding: 8px 12px;
    margin-top: 8px;
    font-size: 12px;
    color: #B71C1C;
}
/* Page header styles */
.page-header h1 { font-size: 22px; margin-bottom: 4px; }
.page-header p { font-size: 14px; color: #666; }
/* Alert styles */
.alert-success h3 { font-size: 18px; margin-bottom: 8px; }
.alert-success p { font-size: 14px; }
.alert-success .btn-gold { font-size: 14px; padding: 8px 24px; }
/* KYC NAME VALIDATION NOTICE */
.alert-warning {
    background: #fff3cd;
    border: 1px solid #ffeeba;
    color: #856404;
    padding: 15px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}
.alert-warning strong {
    font-weight: 700;
}
.alert-warning .registered-name {
    color: #0A0A0A;
    font-weight: 600;
    background: #f8f0d0;
    padding: 2px 10px;
    border-radius: 4px;
    display: inline-block;
}
/* Toast */
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

        <!-- KYC NAME VALIDATION NOTICE -->
        <?php if ($userFullName && !$kycPassed && $step3State !== 'review'): ?>
        <div class="alert-warning">
            <strong>⚠️ Important:</strong> The legal name on your government-issued ID must <strong>exactly match</strong> the full name registered on your account:
            <span class="registered-name"><?php echo htmlspecialchars($userFullName); ?></span><br>
            <small style="display:block;margin-top:6px;color:#6c5a00;">
                <i class="fas fa-info-circle"></i> If the names do not match, your KYC verification will be rejected. Please ensure your ID reflects this exact name before proceeding.
            </small>
        </div>
        <?php endif; ?>

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
                    <?php if ($step['state'] === 'completed'): ?>
                        <i class="fas fa-check"></i>
                    <?php elseif ($step['state'] === 'rejected'): ?>
                        <i class="fas fa-times"></i>
                    <?php elseif ($step['state'] === 'review'): ?>
                        <i class="fas fa-hourglass-half"></i>
                    <?php elseif ($step['state'] === 'in_progress'): ?>
                        <i class="fas fa-spinner fa-spin"></i>
                    <?php elseif ($step['state'] === 'expired'): ?>
                        <i class="fas fa-clock"></i>
                    <?php else: ?>
                        <i class="fas <?= $step['icon'] ?>"></i>
                    <?php endif; ?>
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

                    <?php elseif ($num === 3 && $step['state'] === 'in_progress'): ?>
                        <p>Your identity verification is in progress. If you closed the Didit tab, you can resume below.</p>
                        <button type="button" id="diditKycBtn" class="btn-resume">
                            <i class="fas fa-play-circle"></i> Resume Identity Verification
                        </button>

                    <?php elseif ($num === 3 && $step['state'] === 'review'): ?>
                        <p>Your identity verification is under manual review by our team. This usually resolves within 24–48 hours.</p>
                        <?php if ($kycNameMatch === 'unreadable'): ?>
                            <div class="rejection-reason" style="background:#FFF3E0;border-color:#FFE0B2;color:#E65100;">
                                <i class="fas fa-info-circle"></i> The name on your submitted ID document could not be read clearly. Our team is reviewing it manually.
                            </div>
                        <?php endif; ?>

                    <?php elseif ($num === 3 && $step['state'] === 'rejected'): ?>
                        <p>Your identity verification was declined.</p>
                        <?php if ($kycRejectionReason !== ''): ?>
                            <div class="rejection-reason">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($kycRejectionReason) ?>
                            </div>
                        <?php endif; ?>
                        <button type="button" id="diditKycBtn" class="btn-retry">
                            <i class="fas fa-redo"></i> Retry Identity Verification
                        </button>

                    <?php elseif ($num === 3 && $step['state'] === 'expired'): ?>
                        <p>Your previous identity verification session expired. Please start a new one.</p>
                        <button type="button" id="diditKycBtn" class="btn-start">
                            <i class="fas fa-shield-alt"></i> Restart Identity Verification
                        </button>

                    <?php elseif ($num === 3): ?>
                        <p>Quick, secure ID check via Didit. Takes a few minutes.</p>
                        <button type="button" id="diditKycBtn" class="btn-start">
                            <i class="fas fa-shield-alt"></i> Start Identity Verification
                        </button>

                    <?php elseif ($num === 4 && $step['state'] === 'in_progress'): ?>
                        <p>Business verification is in progress. If you closed the Didit tab, resume below.</p>
                        <button type="button" id="diditKybBtn" class="btn-resume">
                            <i class="fas fa-play-circle"></i> Resume Business Verification
                        </button>

                    <?php elseif ($num === 4 && $step['state'] === 'review'): ?>
                        <p>Under Didit review — no action needed, usually resolves within a day.</p>

                    <?php elseif ($num === 4 && $step['state'] === 'rejected'): ?>
                        <p>Business verification was declined. You can try again.</p>
                        <button type="button" id="diditKybBtn" class="btn-retry">
                            <i class="fas fa-redo"></i> Retry Business Verification
                        </button>

                    <?php elseif ($num === 4 && $step['state'] === 'expired'): ?>
                        <p>Your previous business verification session expired. Please start a new one.</p>
                        <button type="button" id="diditKybBtn" class="btn-start">
                            <i class="fas fa-building"></i> Restart Business Verification
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
        toast.textContent = msg;
        toast.className = 'toast ' + type + ' show';
        setTimeout(() => toast.classList.remove('show'), 4500);
    }

    // ── Didit KYC start / resume / retry ──
    const diditKycBtn = document.getElementById('diditKycBtn');
    if (diditKycBtn) {
        diditKycBtn.addEventListener('click', async () => {
            diditKycBtn.disabled = true;
            const originalHtml = diditKycBtn.innerHTML;
            diditKycBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting…';

            try {
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                const res = await fetch('/api/agent/didit-kyc-start.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                });
                const data = await res.json().catch(() => ({}));

                if (res.ok && data.success && data.url) {
                    window.open(data.url, '_blank', 'noopener,noreferrer');
                    showToast("Didit opened in a new tab. Return here when you're done.", 'success');
                    pollForKycUpdate();
                } else {
                    showToast(data.error || 'Could not start identity verification.', 'error');
                }
            } catch (e) {
                showToast('Network error.', 'error');
            } finally {
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
                    setTimeout(() => window.location.reload(), 1500);
                } else if (d.status === 'review_needed') {
                    clearInterval(t);
                    showToast('Identity verification is under review.', 'info');
                    setTimeout(() => window.location.reload(), 1500);
                }
            } catch (_) {}
        }, 5000);
    }

    // ── Didit KYB start / resume / retry ──
    const diditKybBtn = document.getElementById('diditKybBtn');
    if (diditKybBtn) {
        diditKybBtn.addEventListener('click', async () => {
            diditKybBtn.disabled = true;
            const originalHtml = diditKybBtn.innerHTML;
            diditKybBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting…';

            try {
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                const res = await fetch('/api/agent/didit-kyb-start.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                });
                const data = await res.json().catch(() => ({}));

                if (res.ok && data.success && data.url) {
                    window.open(data.url, '_blank', 'noopener,noreferrer');
                    showToast("Didit opened in a new tab. Return here when you're done.", 'success');
                    pollForKybUpdate();
                } else {
                    showToast(data.error || 'Could not start business verification.', 'error');
                }
            } catch (e) {
                showToast('Network error.', 'error');
            } finally {
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
                    setTimeout(() => window.location.reload(), 1500);
                } else if (d.status === 'review_needed') {
                    clearInterval(t);
                    showToast('Business verification is under review.', 'info');
                    setTimeout(() => window.location.reload(), 1500);
                }
            } catch (_) {}
        }, 5000);
    }
})();
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
