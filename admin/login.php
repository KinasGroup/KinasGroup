<?php
// Load environment variables from .env file
require_once __DIR__ . '/../includes/dotenv.php';

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

// Redirect already-logged-in admins straight to the dashboard
if (SessionManager::isLoggedIn() && SessionManager::getUserRole() === 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Non-admin logged-in users get sent to their own dashboard
if (SessionManager::isLoggedIn()) {
    $role = SessionManager::getUserRole();
    header('Location: ' . ($role === 'agent' ? '../agent/dashboard.php' : '../user/dashboard.php'));
    exit;
}

$csrfToken     = Security::generateCSRFToken();
$errorMessage  = SessionManager::getFlash('error');
$successMessage = SessionManager::getFlash('success');
?>
<!DOCTYPE html>
<html lang="en" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="only light">
    <meta name="theme-color" content="#ffffff">
    <style>
        /* Force light mode immediately — see auth/login.php for the same
           pattern. This page was previously missing it entirely: no inline
           color-scheme on <html>, and no local override block, unlike
           every other auth page. */
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
    <?php require_once __DIR__ . '/../includes/favicon.php'; ?>
    <title>Admin Portal - KINAS GROUP | Luxury Marketplace</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
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
            <h1 class="je-auth-headline">Platform Administration.</h1>
            <p class="je-auth-sub"></p>
        </div>
        <blockquote class="je-auth-quote">
            <p>"Operational clarity and control — everything you need to keep the marketplace running at its best."</p>
            <cite>— KINAS GROUP Operations</cite>
        </blockquote>
    </aside>

    <!-- ── Right form ── -->
    <main class="je-auth-main">
        <div class="je-auth-form">
            <!-- Admin badge -->
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:28px;">
                <span style="display:inline-flex; align-items:center; gap:7px; background:#0A0A0A; color:#C6A43F; font-size:11px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; padding:6px 14px; border-radius:20px; border:1px solid #C6A43F;">
                    <i class="fas fa-shield-alt"></i> Admin Portal
                </span>
            </div>

            <h2>Welcome Back</h2>
            <p class="je-auth-sub-form">Sign in to access the administration dashboard.</p>

            <?php if ($errorMessage): ?>
                <div class="je-form-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>
            <?php if ($successMessage): ?>
                <div class="je-form-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="../api/auth/login.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="je-form-group">
                    <label for="email">Admin Email Address</label>
                    <input type="email" id="email" name="email" placeholder="admin@kinas-group.com" required autocomplete="email">
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

                <div class="je-form-group" id="captcha-group">
                    <div id="login-captcha-container"></div>
                    <input type="hidden" id="login-captcha-token" name="captcha_token">
                </div>

                <button type="submit" id="submitBtn" class="je-btn je-btn-gold je-btn-block je-btn-lg">
                    <i class="fas fa-sign-in-alt"></i> Sign In to Admin
                </button>
            </form>

            <div class="je-auth-switch">
                Not an admin? <a href="../auth/login.php">Agent / Buyer Login</a>
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

document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const submitBtn = document.getElementById('submitBtn');
    const email = form.email.value.trim();
    const password = form.password.value;

    if (!email || !password) { kinasToast('Please enter both email and password', 'error'); return; }

    const captchaToken = document.getElementById('login-captcha-token')?.value || '';
    if (isLoginCaptchaConfigured && !captchaToken) { kinasToast('Please complete the CAPTCHA verification', 'warning'); return; }

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
            if (data.user.role !== 'admin') {
                kinasToast('This account does not have admin privileges. Use the Agent/Buyer login instead.', 'warning');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In to Admin';
                return;
            }
            localStorage.setItem('kinas_token', data.token);
            window.location.href = 'dashboard.php';
        } else {
            kinasToast(data.error || 'Login failed. Admin access only.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In to Admin';
        }
    } catch (err) {
        console.error(err);
        kinasToast('Network error. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In to Admin';
    }
});
</script>
<?php require_once __DIR__ . '/../includes/kinas-ui.php'; ?>
<?php require_once __DIR__ . '/../includes/password-toggle.php'; ?>
</body>
</html>
