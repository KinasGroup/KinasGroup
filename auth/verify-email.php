<?php
/**
 * KINAS GROUP — Email Verification
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/dotenv.php';
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/security.php';

$code     = trim($_GET['code'] ?? '');
$verified = false;
$error    = '';

if (!empty($code)) {
    if (!ctype_xdigit($code) || strlen($code) > 128) {
        $error = 'Invalid verification link.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            // Match on the code itself — NOT on users.status. New
            // registrations are inserted as 'active' (so they can log in
            // immediately for buyers) which made the old
            // "AND status='pending'" condition fail for every legitimate
            // link. Email verification is now a soft signal: it sets
            // email_verified_at and the user_verified flag, but does
            // not gate login.
            $stmt = $db->prepare(
                "SELECT id, name, role, verification_code_expires, email_verified_at
                 FROM users
                 WHERE verification_code = ?
                 LIMIT 1"
            );
            $stmt->execute([$code]);
            $user = $stmt->fetch();
            if (!$user) {
                $error = 'This verification link is invalid or has already been used.';
            } elseif (!empty($user['email_verified_at'])) {
                // Already verified — treat as success and just direct to login.
                $verified = true;
                SessionManager::setFlash('success', 'Your email is already verified. You can sign in.');
            } elseif (!empty($user['verification_code_expires']) && strtotime((string)$user['verification_code_expires']) < time()) {
                $error = 'This verification link has expired. Please request a new one below.';
            } else {
                $db->prepare(
                    "UPDATE users
                        SET verified=1,
                            verification_code=NULL,
                            verification_code_expires=NULL,
                            email_verified_at=NOW()
                      WHERE id=?"
                )->execute([$user['id']]);
                Security::logActivity($user['id'], 'email_verified', 'Email verified via link');
                $verified = true;
                // Agents should go through phone verification next, then MetaMap.
                // Buyers/admins go straight to login.
                if ($user['role'] === 'agent') {
                    SessionManager::setUser([
                        'id'       => (int)$user['id'],
                        'name'     => $user['name'],
                        'email'    => '',
                        'role'     => 'agent',
                        'verified' => 1,
                    ]);
                    SessionManager::setFlash('success', 'Email verified! Now let\'s confirm your phone number.');
                    header('Location: /auth/verify-phone.php');
                    exit;
                }
                SessionManager::setFlash('success', 'Email verified! You can now sign in.');
            }
        } catch (Exception $e) {
            error_log('Email verification error: ' . $e->getMessage());
            $error = 'Verification failed. Please try again or contact support.';
        }
    }
} else {
    $error = 'No verification code provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Per-page polish on top of the JE auth shell */
        .verify-icon-wrap {
            width: 96px; height: 96px; border-radius: 50%;
            margin: 0 auto 28px; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 12px 32px rgba(0,0,0,0.08);
        }
        .verify-icon-wrap.success { background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%); }
        .verify-icon-wrap.error   { background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%); }
        .verify-icon-wrap i { font-size: 2.6rem; }
        .verify-icon-wrap.success i { color: #1B5E20; }
        .verify-icon-wrap.error   i { color: #B71C1C; }
        .verify-step-list {
            background: rgba(255,255,255,0.05); border-radius: 12px;
            padding: 22px; border: 1px solid rgba(255,255,255,0.08);
        }
        .verify-step-list .step-label {
            font-size: 11px; letter-spacing: 2px; text-transform: uppercase;
            color: #C6A43F; margin-bottom: 12px; font-weight: 700;
        }
        .verify-step-list ul { list-style: none; padding: 0; margin: 0; color: rgba(255,255,255,0.75); font-size: 14px; line-height: 2; }
        .verify-step-list i { color: #C6A43F; width: 22px; text-align: center; margin-right: 8px; }
    </style>
</head>
<body>

<div class="je-auth-shell">
    <!-- ── Left aside: trust / verification themed ── -->
    <aside class="je-auth-aside">
        <a href="../index.php" class="je-auth-brand">
            <img src="../assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP" onerror="this.style.display='none'">
            <span>KINAS GROUP</span>
        </a>
        <div>
            <h1 class="je-auth-headline">Verified. Trusted. Yours.</h1>
            <p class="je-auth-sub">Email verification is the first step in keeping KINAS GROUP a marketplace where buyers, sellers, and renters can transact with total confidence.</p>
        </div>
        <div class="verify-step-list">
            <div class="step-label">Your Verification Path</div>
            <ul>
                <li><i class="fas fa-envelope-open-text"></i> Email verified — you are here</li>
                <li><i class="fas fa-mobile-alt"></i> Phone number confirmed (agents)</li>
                <li><i class="fas fa-id-card"></i> MetaMap identity check (agents)</li>
                <li><i class="fas fa-building"></i> CAC / TIN document review (agents)</li>
                <li><i class="fas fa-check-circle"></i> Approved &amp; ready to list</li>
            </ul>
        </div>
    </aside>

    <!-- ── Right form: status + next steps ── -->
    <main class="je-auth-main">
        <div class="je-auth-form" style="max-width: 520px; text-align: center;">
            <?php if ($verified): ?>
                <div class="verify-icon-wrap success">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                </div>
                <h2>Email Verified</h2>
                <p class="je-auth-sub-form">Your email has been successfully confirmed. You can now sign in to your KINAS GROUP account and pick up where you left off.</p>
                <a href="login.php" class="je-btn je-btn-gold je-btn-block je-btn-lg" style="margin-top:12px;">
                    <i class="fas fa-sign-in-alt"></i>&nbsp; Sign In Now
                </a>
            <?php else: ?>
                <div class="verify-icon-wrap error">
                    <i class="fas fa-times-circle" aria-hidden="true"></i>
                </div>
                <h2>Verification Failed</h2>
                <p class="je-auth-sub-form"><?= htmlspecialchars($error ?: 'The verification link is invalid or has expired.') ?></p>
                <a href="login.php" class="je-btn je-btn-outline je-btn-block je-btn-lg" style="margin-top:12px;">
                    <i class="fas fa-arrow-left"></i>&nbsp; Back to Sign In
                </a>
                <a href="#" class="je-auth-switch" id="resend-link" style="display:block; margin-top:18px;">
                    <i class="fas fa-envelope"></i>&nbsp; Resend Verification Email
                </a>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
document.getElementById('resend-link')?.addEventListener('click', async function(e) {
    e.preventDefault();
    const email = prompt('Enter your registered email address:');
    if (!email) return;
    const original = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
    try {
        // Dedicated resend endpoint — the old /api/auth/forgot-password.php
        // only handles password resets and silently no-ops for fresh
        // registrations, which is why users got "no more email" before.
        const res = await fetch('/api/auth/resend-verification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
        const data = await res.json();
        alert(data.message || 'If that email is registered and unverified, a new link has been sent.');
    } catch (err) {
        alert('Request failed. Please try again.');
    }
    this.innerHTML = original;
});
</script>
</body>
</html>
