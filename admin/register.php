<?php
require_once __DIR__ . '/../includes/dotenv.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

if (SessionManager::isLoggedIn()) {
    $role = SessionManager::getUserRole();
    header('Location: ' . ($role === 'admin' ? '../admin/dashboard.php' : ($role === 'agent' ? '../agent/dashboard.php' : '../user/dashboard.php')));
    exit;
}

$csrfToken = Security::generateCSRFToken();
$errorMessage = SessionManager::getFlash('error');
$successMessage = SessionManager::getFlash('success');

$divisions = [
    'automobile'    => 'KINAS Automobile',
    'real_estate'   => 'Williams Connect Home',
    'marketplace'   => 'KINAS Marketplace',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Registration - KINAS GROUP | Luxury Marketplace</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="je-auth-shell">
    <aside class="je-auth-aside">
        <a href="../index.php" class="je-auth-brand">
            <img src="../assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP" onerror="this.style.display='none'">
            <span></span>
        </a>
        <div>
            <h1 class="je-auth-headline">Become a KINAS Agent.</h1>
            <p class="je-auth-sub"></p>
        </div>
        <blockquote class="je-auth-quote">
            <p>"KINAS made cross-border selling simple. Our Lagos dealership saw international buyers within the first week."</p>
            <cite>— M. Adebayo, Verified Dealer</cite>
        </blockquote>
    </aside>

    <main class="je-auth-main">
        <div class="je-auth-form" style="max-width:520px;">
            <h2>Create Agent Account</h2>
            <p class="je-auth-sub-form">After registration you'll complete MetaMap identity verification — usually under 2 minutes.</p>

            <?php if ($errorMessage): ?><div class="je-form-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>
            <?php if ($successMessage): ?><div class="je-form-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?></div><?php endif; ?>

            <form id="registerForm" method="POST" action="../api/auth/register.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="je-form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" placeholder="Your full name" required minlength="2" maxlength="100">
                </div>

                <div class="je-form-row">
                    <div class="je-form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="your@email.com" required autocomplete="email">
                    </div>
                    <div class="je-form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" placeholder="+234 800 000 0000" required>
                    </div>
                </div>

                <div class="je-form-group">
                    <label for="division">Select Your Division</label>
                    <select id="division" name="division" required>
                        <option value="">Choose your division</option>
                        <?php foreach ($divisions as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="je-form-row">
                    <div class="je-form-group">
                        <label for="password">Password</label>
                        <div class="je-password-wrap">
                            <input type="password" id="password" name="password" placeholder="Min. 8 characters" required minlength="8">
                            <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p style="font-size:11px; color:#888; margin-top:4px;">At least 8 characters with uppercase, lowercase, and numbers.</p>
                    </div>
                    <div class="je-form-group">
                        <label for="password_confirmation">Confirm Password</label>
                        <div class="je-password-wrap">
                            <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm password" required>
                            <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="je-form-group" id="captcha-group">
                    <div id="captcha-container"></div>
                    <input type="hidden" id="captcha-token" name="captcha_token">
                    <p style="font-size:11px; color:#888; margin-top:6px;">
                        <i class="fas fa-shield-alt"></i> Protected by reCAPTCHA. <a href="https://policies.google.com/privacy" target="_blank" style="color:#C6A43F;">Privacy</a> · <a href="https://policies.google.com/terms" target="_blank" style="color:#C6A43F;">Terms</a>
                    </p>
                </div>

                <p style="font-size:12px; color:#666; margin-bottom:20px; line-height:1.5;">
                    <i class="fas fa-shield-alt" style="color:#C6A43F;"></i> By registering, you agree to our
                    <a href="../pages/terms-of-use.php" style="color:#C6A43F;">Terms</a> and
                    <a href="../pages/privacy-policy.php" style="color:#C6A43F;">Privacy Policy</a>.
                    KYC verification is required before listing.
                </p>

                <button type="submit" id="submitBtn" class="je-btn je-btn-gold je-btn-block je-btn-lg">
                    Create Account &amp; Continue
                </button>
            </form>

            <div class="je-auth-switch">
                Already have an account? <a href="login.php">Sign in</a> · <a href="register-buyer.php">Register as buyer</a>
            </div>
        </div>
    </main>
</div>

<script>
const captchaSiteKey = '<?= htmlspecialchars($_ENV['CAPTCHA_SITE_KEY'] ?? '') ?>';
const isCaptchaConfigured = captchaSiteKey && captchaSiteKey !== '6LeXXXXXXXXXXXXXXXXXXXXXXXX' && captchaSiteKey.length > 30;
if (isCaptchaConfigured) {
    var s = document.createElement('script');
    s.src = 'https://www.google.com/recaptcha/api.js?onload=onCaptchaLoad&render=explicit';
    s.async = true; s.defer = true;
    document.head.appendChild(s);
} else {
    document.addEventListener('DOMContentLoaded', function() {
        const c = document.getElementById('captcha-group'); if (c) c.style.display = 'none';
    });
}
function onCaptchaLoad() {
    if (!isCaptchaConfigured) return;
    const c = document.getElementById('captcha-container');
    if (c) grecaptcha.render('captcha-container', {
        sitekey: captchaSiteKey,
        callback: r => document.getElementById('captcha-token').value = r,
        'expired-callback': () => document.getElementById('captcha-token').value = ''
    });
}
document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this, submitBtn = document.getElementById('submitBtn');
    const name = form.name.value.trim(), email = form.email.value.trim(), phone = form.phone.value.trim();
    const division = form.division.value, password = form.password.value, passwordConfirmation = form.password_confirmation.value;
    if (!name || !email || !phone || !division || !password || !passwordConfirmation) { alert('Please fill in all required fields'); return; }
    if (password !== passwordConfirmation) { alert('Passwords do not match'); return; }
    if (password.length < 8) { alert('Password must be at least 8 characters'); return; }
    if (isCaptchaConfigured && !document.getElementById('captcha-token').value) { alert('Please complete the CAPTCHA verification'); return; }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account…';

    try {
        const res = await fetch(form.action, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name, email, phone, division, password, password_confirmation: passwordConfirmation,
                csrf_token: form.csrf_token.value,
                captcha_token: isCaptchaConfigured ? document.getElementById('captcha-token').value : ''
            })
        });
        const data = await res.json();
        if (data.success) {
            const successMessage = data.message || 'Registration successful! Please login to continue.';
            window.location.href = 'login.php?registered=1&message=' + encodeURIComponent(successMessage);
        } else {
            let errorMsg = data.error || 'Registration failed';
            if (data.errors) errorMsg = Object.values(data.errors).join(', ');
            alert(errorMsg);
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Account &amp; Continue';
        }
    } catch (err) {
        console.error(err);
        alert('Network error. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Create Account &amp; Continue';
    }
});
</script>
<?php require_once __DIR__ . '/../includes/password-toggle.php'; ?>
</body>
</html>
