<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../includes/dotenv.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
// User must have the pending_reactivation flag set by login
if (empty($_SESSION['pending_reactivation_user_id'])) {
    header('Location: /auth/login.php');
    exit;
}
// If somehow already logged in normally, redirect to dashboard
if (SessionManager::isLoggedIn()) {
    $role = SessionManager::getUserRole();
    header('Location: ' . ($role === 'admin' ? '/admin/dashboard.php' : ($role === 'agent' ? '/agent/dashboard.php' : '/user/dashboard.php')));
    exit;
}
$csrfToken = Security::generateCSRFToken();
$pendingName = $_SESSION['pending_reactivation_name'] ?? 'User';
$pendingEmail = $_SESSION['pending_reactivation_email'] ?? '';
function authCssV($file) { return @filemtime(__DIR__ . '/../assets/css/' . $file) ?: 1; }
?>
<!DOCTYPE html>
<html lang="en" style="color-scheme: light;">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="only light">
<meta name="theme-color" content="#050505">
<?php require_once __DIR__ . '/../includes/favicon.php'; ?>
<title>Reactivate Account - KINAS GROUP</title>
<link rel="stylesheet" href="../assets/css/style.css?v=<?= authCssV('style.css') ?>">
<link rel="stylesheet" href="../assets/css/james-edition.css?v=<?= authCssV('james-edition.css') ?>">
<link rel="stylesheet" href="../assets/css/responsive.css?v=<?= authCssV('responsive.css') ?>">
<link rel="stylesheet" href="../assets/css/auth.css?v=<?= authCssV('auth.css') ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="ka-shell">
<div class="ka-main">
<div class="ka-hero" aria-hidden="true"></div>
<aside class="ka-brand">
<a href="../index.php" class="ka-logo">
<img src="../assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP" onerror="this.style.display='none'">
</a>
<div class="ka-brand-copy">
<h1 class="ka-headline">Welcome<br><span class="ka-accent">Back!</span></h1>
<div class="ka-rule"></div>
<p class="ka-group"><span class="ka-accent">Kinas</span> Group</p>
<p class="ka-desc">We're glad you're back. Reactivate your account to continue where you left off.</p>
</div>
</aside>
<main class="ka-form-side">
<div class="ka-card">
<p class="ka-eyebrow"><i class="fas fa-redo-alt"></i> Account Reactivation</p>
<h2>Reactivate Your Account</h2>
<p class="ka-sub">
Your account was previously deleted. You can reactivate it now to
regain access to your profile, saved items, and messages.
</p>
<div style="padding:12px 14px;background:#f9f9f9;border-radius:8px;margin-bottom:18px;font-size:14px;color:#333;">
<strong>Account:</strong> <?= htmlspecialchars($pendingEmail) ?><br>
<strong>Name:</strong> <?= htmlspecialchars($pendingName) ?>
</div>
<div style="background:#FFF8E1;border-radius:8px;padding:12px;margin-bottom:18px;border-left:3px solid #C6A43F;">
<p style="font-size:12px;color:#7A5B00;margin:0;">
<strong>What happens when you reactivate?</strong><br>
• Your account status is restored to active<br>
• Your profile, saved items, and messages remain intact<br>
• If you were an agent, your listings will be restored<br>
• You can delete your account again at any time
</p>
</div>
<form id="reactivateForm" method="POST" action="../api/auth/reactivate-account.php" novalidate>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
<button type="submit" id="reactivateBtn" class="ka-btn-primary">
<i class="fas fa-redo-alt"></i> Reactivate My Account
</button>
</form>
<div id="reactivate-error" style="display:none;margin-top:15px;padding:12px;background:#f8d7da;color:#721c24;border-radius:8px;"></div>
<div id="reactivate-success" style="display:none;margin-top:15px;padding:12px;background:#d4edda;color:#155724;border-radius:8px;"></div>
<div style="text-align:center;margin-top:20px;">
<a href="../auth/login.php" class="ka-link" style="font-size:13px;">
<i class="fas fa-arrow-left"></i> Back to Login
</a>
</div>
</div>
</main>
</div>
<section class="ka-trust">
<div class="ka-trust-item">
<img src="../assets/images/trust/secure-transactions-icon-60.png" alt="" onerror="this.style.display='none'">
<div><strong>Secure &amp; Protected</strong><span>Your data is safe with us</span></div>
</div>
<div class="ka-trust-item">
<img src="../assets/images/trust/concierge-service-icon-60.png" alt="" onerror="this.style.display='none'">
<div><strong>24/7 Support</strong><span>We're here to help you anytime</span></div>
</div>
<div class="ka-trust-item">
<img src="../assets/images/trust/verified-dealers-icon-60.png" alt="" onerror="this.style.display='none'">
<div><strong>Trusted &amp; Reliable</strong><span>Excellence you can trust</span></div>
</div>
</section>
<footer class="ka-footer">
© <?= date('Y') ?> Kinas Group. All rights reserved.
<span class="ka-sep">|</span> <a href="../pages/privacy-policy.php">Privacy Policy</a>
<span class="ka-sep">|</span> <a href="../pages/terms-of-use.php">Terms of Use</a>
</footer>
</div>
<?php require_once __DIR__ . '/../includes/kinas-ui.php'; ?>
<script>
document.getElementById('reactivateForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const btn = document.getElementById('reactivateBtn');
    const errorDiv = document.getElementById('reactivate-error');
    const successDiv = document.getElementById('reactivate-success');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reactivating…';
    btn.disabled = true;
    errorDiv.style.display = 'none';
    successDiv.style.display = 'none';
    try {
        const res = await fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: form.csrf_token.value })
        });
        const data = await res.json();
        if (data.success) {
            successDiv.innerHTML = data.message || '✅ Account reactivated successfully! Redirecting…';
            successDiv.style.display = 'block';
            if (data.token) {
                localStorage.setItem('kinas_token', data.token);
            }
            setTimeout(function() {
                const role = data.user?.role || 'user';
                if (role === 'admin') {
                    window.location.href = '/admin/dashboard.php';
                } else if (role === 'agent') {
                    window.location.href = '/agent/dashboard.php';
                } else {
                    window.location.href = '/user/dashboard.php';
                }
            }, 2000);
        } else {
            errorDiv.textContent = data.error || 'Failed to reactivate account. Please try again.';
            errorDiv.style.display = 'block';
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch (err) {
        console.error(err);
        errorDiv.textContent = 'Network error. Please try again.';
        errorDiv.style.display = 'block';
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
});
</script>
</body>
</html>
