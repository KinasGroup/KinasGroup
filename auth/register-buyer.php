<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

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
?>
<!DOCTYPE html>
<html lang="en" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- ============================================================
         FORCE LIGHT MODE - PERMANENT FIX
         ============================================================ -->
    <meta name="color-scheme" content="only light">
    <meta name="theme-color" content="#ffffff">
    <style>
        html, body { 
            color-scheme: light !important; 
            background: #ffffff !important;
        }
        @media (prefers-color-scheme: dark) {
            html, body {
                color-scheme: light !important;
                background: #ffffff !important;
                color: #0A0A0A !important;
            }
            .je-auth-shell,
            .je-auth-main,
            .je-auth-form {
                background-color: #ffffff !important;
                color: #0A0A0A !important;
            }
            .je-auth-aside {
                background-color: #0A0A0A !important;
                color: rgba(255,255,255,0.7) !important;
            }
            .je-auth-aside * {
                color: rgba(255,255,255,0.7) !important;
            }
            .je-auth-aside h1,
            .je-auth-aside .je-auth-headline {
                color: #ffffff !important;
            }
        }
    </style>
    <!-- ============================================================ -->
    
    <?php require_once __DIR__ . '/../includes/favicon.php'; ?>
    <title>Buyer Registration - KINAS GROUP | Luxury Marketplace</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- ============================================================
         MOBILE RESPONSIVENESS FIXES
         ============================================================ -->
    <style>
        .je-auth-shell {
            min-height: 100vh;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }
        @media (max-width: 992px) {
            .je-auth-shell {
                grid-template-columns: 1fr !important;
                grid-template-rows: auto 1fr !important;
            }
            .je-auth-aside {
                padding: 30px 24px 36px !important;
                min-height: auto !important;
                overflow: visible !important;
            }
            .je-auth-aside .je-auth-headline {
                font-size: 24px !important;
            }
            .je-auth-main {
                padding: 30px 20px !important;
            }
            .je-auth-form {
                max-width: 100% !important;
                padding: 0 10px !important;
            }
            .je-auth-form h2 {
                font-size: 24px !important;
            }
            .je-form-row {
                grid-template-columns: 1fr !important;
                gap: 0 !important;
            }
        }
        @media (max-width: 480px) {
            .je-auth-aside {
                padding: 20px 16px 24px !important;
                min-height: auto !important;
            }
            .je-auth-aside .je-auth-headline {
                font-size: 20px !important;
            }
            .je-auth-main {
                padding: 20px 14px !important;
            }
            .je-auth-form h2 {
                font-size: 20px !important;
            }
            .je-auth-form .je-auth-sub-form {
                font-size: 13px !important;
            }
            .je-form-group input {
                font-size: 14px !important;
                padding: 10px 12px !important;
            }
            .je-btn-lg {
                padding: 12px 20px !important;
                font-size: 13px !important;
            }
            .je-auth-switch {
                font-size: 12px !important;
            }
        }
        .je-password-wrap {
            display: flex;
            align-items: center;
            position: relative;
        }
        .je-password-wrap input {
            flex: 1;
            padding-right: 44px !important;
        }
        .je-password-toggle {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: #999;
            padding: 8px;
            font-size: 16px;
        }
        .je-password-toggle:hover {
            color: #666;
        }
        #captcha-group {
            min-height: 78px;
        }

        /* Restore gold accents - the site-wide dark-mode override
           (assets/css/style.css) blackens all text inside .je-auth-form,
           which was silently killing the gold CTA button and links here. */
        @media (prefers-color-scheme: dark) {
            #submitBtn.je-btn-gold,
            .je-auth-form .je-text-gold {
                color: #C6A43F !important;
            }
        }
    
/* ============================================================
   DARK MODE — force this page's own styling to stay identical
   to light mode. Auto-generated from every hardcoded
   background/color/border-color rule already on this page.
   ============================================================ */
@media (prefers-color-scheme: dark) {
    html, body { background: #ffffff !important; }
    .je-password-toggle { color: #999 !important; }
    .je-password-toggle:hover { color: #666 !important; }
}
</style>
</head>
<body>

<div class="je-auth-shell">
    <aside class="je-auth-aside">
        <a href="../index.php" class="je-auth-brand">
            <img src="../assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP" onerror="this.style.display='none'">
            <span></span>
        </a>
        <div>
            <h1 class="je-auth-headline">Discover. Acquire. Belong.</h1>
            <p class="je-auth-sub"></p>
        </div>
        <blockquote class="je-auth-quote">
            <p>"The saved-listing alerts and direct agent messaging made finding our home effortless."</p>
            <cite>— K. Mensah, Accra</cite>
        </blockquote>
    </aside>

    <main class="je-auth-main">
        <div class="je-auth-form">
            <h2>Create Buyer Account</h2>
            <p class="je-auth-sub-form">Free forever. No credit card required.</p>

            <?php if ($errorMessage): ?><div class="je-form-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>
            <?php if ($successMessage): ?><div class="je-form-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?></div><?php endif; ?>

            <form id="registerForm" method="POST" action="../api/auth/register-buyer.php" novalidate>
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
                        <i class="fas fa-shield-alt"></i> Protected by reCAPTCHA.
                    </p>
                </div>

                <p style="font-size:12px; color:#666; margin-bottom:20px; line-height:1.5;">
                    <i class="fas fa-shield-alt je-text-gold" style="color:#C6A43F;"></i> By registering, you agree to our
                    <a href="../pages/terms-of-use.php" class="je-text-gold" style="color:#C6A43F;">Terms</a> and
                    <a href="../pages/privacy-policy.php" class="je-text-gold" style="color:#C6A43F;">Privacy Policy</a>.
                </p>

                <button type="submit" id="submitBtn" class="je-btn je-btn-gold je-btn-block je-btn-lg">
                    Create Buyer Account
                </button>
            </form>

            <div class="je-auth-switch">
                Already have an account? <a href="login.php">Sign in</a>
                · Want to sell? <a href="register.php">Register as Agent</a>
            </div>
        </div>
    </main>
</div>

<script>
const captchaSiteKey = '<?= htmlspecialchars($_ENV['CAPTCHA_SITE_KEY'] ?? getenv('CAPTCHA_SITE_KEY') ?? '') ?>';
const isCaptchaConfigured = captchaSiteKey && captchaSiteKey !== '6LeXXXXXXXXXXXXXXXXXXXXXXXX' && captchaSiteKey.length > 30;
if (isCaptchaConfigured) {
    var s = document.createElement('script');
    s.src = 'https://www.google.com/recaptcha/api.js?onload=onCaptchaLoad&render=explicit';
    s.async = true; s.defer = true;
    document.head.appendChild(s);
} else {
    document.addEventListener('DOMContentLoaded', function() { const c = document.getElementById('captcha-group'); if (c) c.style.display = 'none'; });
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
    const password = form.password.value, passwordConfirmation = form.password_confirmation.value;
    const captchaToken = document.getElementById('captcha-token')?.value || '';
    if (!name || !email || !phone || !password || !passwordConfirmation) { kinasToast('Please fill in all required fields', 'error'); return; }
    if (password !== passwordConfirmation) { kinasToast('Passwords do not match', 'error'); return; }
    if (password.length < 8) { kinasToast('Password must be at least 8 characters', 'error'); return; }
    if (isCaptchaConfigured && !captchaToken) { kinasToast('Please complete the CAPTCHA verification', 'warning'); return; }
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account…';
    try {
        const res = await fetch(form.action, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name, email, phone, password, password_confirmation: passwordConfirmation,
                csrf_token: form.csrf_token.value, captcha_token: captchaToken
            })
        });
        const result = await res.json();
        if (res.ok && result.success) {
            const successMessage = encodeURIComponent(result.message || 'Registration successful! Please login to continue.');
            window.location.href = 'login.php?registered=1&message=' + successMessage;
        } else {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Buyer Account';
            if (result.errors && Array.isArray(result.errors)) kinasToast(result.errors.join(' · '), 'error');
            else if (result.error) kinasToast(result.error, 'error');
            else kinasToast('Registration failed', 'error');
        }
    } catch (err) {
        console.error(err);
        kinasToast('Network error. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Create Buyer Account';
    }
});
</script>
<?php require_once __DIR__ . '/../includes/kinas-ui.php'; ?>
<?php require_once __DIR__ . '/../includes/password-toggle.php'; ?>
</body>
</html>
