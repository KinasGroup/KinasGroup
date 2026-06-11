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
</head>
<body>

<div class="je-auth-shell" style="grid-template-columns: 1fr;">
    <main class="je-auth-main">
        <div class="je-auth-form" style="max-width:520px; text-align:center;">
            <a href="../index.php" class="je-auth-brand" style="margin-bottom:32px; justify-content:center; color:#0A0A0A;">
                <img src="../assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP" onerror="this.style.display='none'" style="height:30px;">
                <span style="color:#0A0A0A; font-family:'Prata',serif;">KINAS GROUP</span>
            </a>

            <?php if ($verified): ?>
                <div style="width:80px; height:80px; margin:0 auto 24px; border-radius:50%; background:#E8F5E9; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-check-circle" style="font-size:2.4rem; color:#1B5E20;"></i>
                </div>
                <h2 style="color:#1B5E20;">Email Verified</h2>
                <p class="je-auth-sub-form">Your email has been successfully verified. You can now sign in to your KINAS account.</p>
                <a href="login.php" class="je-btn je-btn-gold je-btn-block je-btn-lg" style="margin-top:8px;">Sign In Now</a>
            <?php else: ?>
                <div style="width:80px; height:80px; margin:0 auto 24px; border-radius:50%; background:#FEF2F2; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-times-circle" style="font-size:2.4rem; color:#B71C1C;"></i>
                </div>
                <h2 style="color:#B71C1C;">Verification Failed</h2>
                <p class="je-auth-sub-form"><?= htmlspecialchars($error ?: 'The verification link is invalid or has expired.') ?></p>
                <a href="login.php" class="je-btn je-btn-outline je-btn-block je-btn-lg" style="margin-top:8px;">Back to Sign In</a>
                <a href="#" class="je-auth-switch" id="resend-link" style="display:block; margin-top:14px;">
                    <i class="fas fa-envelope"></i> Resend Verification Email
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
    this.textContent = 'Sending…';
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
    this.innerHTML = '<i class="fas fa-envelope"></i> Resend Verification Email';
});
</script>
</body>
</html>
