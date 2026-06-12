<?php
// Load environment variables from .env file
require_once __DIR__ . '/../includes/dotenv.php';

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

// Redirect already-logged-in users away from auth pages
if (SessionManager::isLoggedIn()) {
    $role = SessionManager::getUserRole();
    header('Location: ' . ($role === 'admin' ? '../admin/dashboard.php' : ($role === 'agent' ? '../agent/dashboard.php' : '../user/dashboard.php')));
    exit;
}

$csrfToken = Security::generateCSRFToken();
$errorMessage = SessionManager::getFlash('error');
$successMessage = SessionManager::getFlash('success');

$registrationSuccess = isset($_GET['registered']) && $_GET['registered'] == 1;
if ($registrationSuccess) {
    $successMessage = isset($_GET['message']) ? urldecode($_GET['message']) : 'Registration successful! Please sign in below.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - KINAS GROUP | Luxury Marketplace</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="je-auth-shell">
    <!-- ── Left aside ── -->
    <aside class="je-auth-aside">
        <a href="../index.php" class="je-auth-brand">
            <img src="../assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP" onerror="this.style.display='none'">
            <span></span>
        </a>
        <div>
            <h1 class="je-auth-headline">A Luxurious Marketplace.</h1>
            <p class="je-auth-sub"></p>
        </div>
        <blockquote class="je-auth-quote">
            <p>"We bought our Lagos penthouse through KINAS. The verification process gave us total confidence in the agent."</p>
            <cite>— A. Okonkwo, Lagos</cite>
        </blockquote>
    </aside>

    <!-- ── Right form ── -->
    <main class="je-auth-main">
        <div class="je-auth-form">
            <h2>Welcome back</h2>
            <p class="je-auth-sub-form">Sign in to access your dashboard, saved listings and messages.</p>

            <?php if ($errorMessage): ?>
                <div class="je-form-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>
            <?php if ($successMessage): ?>
                <div class="je-form-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="../api/auth/login.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="je-form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com" required autocomplete="email">
                </div>
                <div class="je-form-group">
                    <label for="password">Password</label>
                    <div class="je-password-wrap">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; font-size:13px;">
                    <label style="display:flex; align-items:center; gap:6px; color:#666; cursor:pointer;">
                        <input type="checkbox" name="remember" value="1" style="accent-color:#C6A43F;"> Remember me
                    </label>
                    <a href="forgot-password.php" style="color:#C6A43F; text-decoration:none; font-weight:500;">Forgot password?</a>
                </div>

                <div class="je-form-group" id="captcha-group">
                    <div id="login-captcha-container"></div>
                    <input type="hidden" id="login-captcha-token" name="captcha_token">
                </div>

                <button type="submit" id="submitBtn" class="je-btn je-btn-gold je-btn-block je-btn-lg">
                    Sign In
                </button>
            </form>

            <div class="je-auth-switch">
                Don't have an account? <a href="register.php">Register as Agent</a> · <a href="register-buyer.php">Register as Buyer</a>
            </div>
        </div>
    </main>
</div>

<script>
const loginCaptchaSiteKey = '<?= htmlspecialchars($_ENV['CAPTCHA_SITE_KEY'] ?? getenv('CAPTCHA_SITE_KEY') ?? '') ?>';
const isLoginCaptchaConfigured = loginCaptchaSiteKey && loginCaptchaSiteKey !== '6LeXXXXXXXXXXXXXXXXXXXXXXXX' && loginCaptchaSiteKey.length > 30;

if (isLoginCaptchaConfigured) {
    var s = document.createElement('script');
    s.src = 'https://www.google.com/recaptcha/api.js?onload=onLoginCaptchaLoad&render=explicit';
    s.async = true; s.defer = true;
    document.head.appendChild(s);
} else {
    const g = document.getElementById('captcha-group');
    if (g) g.style.display = 'none';
}

function onLoginCaptchaLoad() {
    if (!isLoginCaptchaConfigured) return;
    const c = document.getElementById('login-captcha-container');
    if (c) {
        grecaptcha.render('login-captcha-container', {
            sitekey: loginCaptchaSiteKey,
            callback: r => document.getElementById('login-captcha-token').value = r,
            'expired-callback': () => document.getElementById('login-captcha-token').value = ''
        });
    }
}

if (window.location.search.includes('registered=1')) {
    const url = new URL(window.location.href);
    url.searchParams.delete('registered');
    url.searchParams.delete('message');
    window.history.replaceState({}, document.title, url.pathname);
}

document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const submitBtn = document.getElementById('submitBtn');
    const email = form.email.value.trim();
    const password = form.password.value;
    if (!email || !password) { alert('Please enter both email and password'); return; }
    const captchaToken = document.getElementById('login-captcha-token')?.value || '';
    if (isLoginCaptchaConfigured && !captchaToken) { alert('Please complete the CAPTCHA verification.'); return; }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in…';

    try {
        const res = await fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password, csrf_token: form.csrf_token.value, captcha_token: captchaToken })
        });
        const data = await res.json();
        if (data.success) {
            localStorage.setItem('kinas_token', data.token);
            window.location.href = data.user.role === 'admin' ? '../admin/dashboard.php' :
                                   (data.user.role === 'agent' ? '../agent/dashboard.php' : '../user/dashboard.php');
        } else {
            // Special case: the account exists but the email hasn't been
            // verified. Show a clear message and offer a "resend the
            // verification link" action. The API tells us the user's email
            // so we can re-issue the code without them re-typing it.
            if (data.error_code === 'email_not_verified') {
                const wantResend = confirm(
                    (data.error || 'Please verify your email.') +
                    '\n\nWould you like us to send a new verification link to ' + (data.email || email) + '?'
                );
                if (wantResend) {
                    try {
                        const r = await fetch('/api/auth/resend-verification.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ email: data.email || email })
                        });
                        const rd = await r.json();
                        alert(rd.message || 'If that email is registered and unverified, a new link has been sent.');
                    } catch (err) {
                        alert('Could not resend the verification email. Please try again later.');
                    }
                }
            } else {
                alert(data.error || 'Login failed');
            }
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Sign In';
        }
    } catch (err) {
        console.error(err);
        alert('Network error. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Sign In';
    }
});
</script>
<?php require_once __DIR__ . '/../includes/password-toggle.php'; ?>
</body>
</html>
