<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Load environment variables from .env file
require_once __DIR__ . '/../includes/dotenv.php';

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

// Redirect already-logged-in users away from auth pages
if (SessionManager::isLoggedIn()) {
    $role = SessionManager::getUserRole();
    // FIXED: Use correct paths for all roles
    if ($role === 'admin') {
        header('Location: /admin/dashboard.php');
    } elseif ($role === 'agent') {
        header('Location: /agent/dashboard.php');
    } else {
        header('Location: /user/dashboard.php');
    }
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
        /* Force light mode immediately */
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
    <title>Sign In - KINAS GROUP | One Company, Multiple Solutions, One Trusted Ecosystem</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- ============================================================
         MOBILE RESPONSIVENESS FIXES
         ============================================================ -->
    <style>
        /* Ensure auth shell is responsive */
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
            .je-form-group input,
            .je-form-group select {
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
        
        /* Fix password toggle on mobile */
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
    <!-- ── Left aside ── -->
    <aside class="je-auth-aside">
        <a href="../index.php" class="je-auth-brand">
            <img src="../assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP" onerror="this.style.display='none'">
            <span></span>
        </a>
        <div>
            <h1 class="je-auth-headline">One Company, Multiple Solutions, One Trusted Ecosystem.</h1>
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
            <h2>Welcome Back</h2>
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

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; font-size:13px; flex-wrap:wrap; gap:8px;">
                    <label style="display:flex; align-items:center; gap:6px; color:#666; cursor:pointer;">
                        <input type="checkbox" name="remember" value="1" style="accent-color:#C6A43F;"> Remember me
                    </label>
                    <a href="forgot-password.php" class="je-text-gold" style="color:#C6A43F; text-decoration:none; font-weight:500;">Forgot password?</a>
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
const loginCaptchaSiteKey = '<?= htmlspecialchars(get_captcha_site_key()) ?>';
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

// ============================================================
// NOTIFICATION — delegates to kinasToast (defined in kinas-ui.php)
// ============================================================
window.showNotification = function(message, type) {
    var typeMap = { error: 'error', success: 'success', warning: 'warning', info: 'info' };
    kinasToast(message, typeMap[type] || 'error', 5000);
};

// ============================================================
// LOGIN FORM HANDLER
// ============================================================
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const submitBtn = document.getElementById('submitBtn');
    const email = form.email.value.trim();
    const password = form.password.value;
    
    if (!email || !password) {
        showNotification('Please enter both email and password.', 'warning');
        return;
    }
    
    const captchaToken = document.getElementById('login-captcha-token')?.value || '';
    if (isLoginCaptchaConfigured && !captchaToken) {
        showNotification('Please complete the CAPTCHA verification.', 'warning');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in…';

    try {
        const res = await fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                email, 
                password, 
                csrf_token: form.csrf_token.value, 
                captcha_token: captchaToken 
            })
        });
        const data = await res.json();
        
        if (data.success) {
            localStorage.setItem('kinas_token', data.token);
            // FIXED: Use absolute paths for redirects
            if (data.user.role === 'admin') {
                window.location.href = '/admin/dashboard.php';
            } else if (data.user.role === 'agent') {
                window.location.href = '/agent/dashboard.php';
            } else {
                window.location.href = '/user/dashboard.php';
            }
        } else {
            // Special case: the account exists but the email hasn't been
            // verified. Show a clear message and offer a "resend the
            // verification link" action. The API tells us the user's email
            // so we can re-issue the code without them re-typing it.
            if (data.error_code === 'email_not_verified') {
                kinasConfirm(
                    (data.error || 'Please verify your email.') +
                    ' Would you like us to send a new verification link to ' + (data.email || email) + '?',
                    async function() {
                        try {
                            const r = await fetch('/api/auth/resend-verification.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ email: data.email || email })
                            });
                            const rd = await r.json();
                            showNotification(rd.message || 'If that email is registered and unverified, a new link has been sent.', 'success');
                        } catch (err) {
                            showNotification('Could not resend the verification email. Please try again later.', 'error');
                        }
                    },
                    { title: 'Resend Verification', confirm: 'Send Link', variant: 'gold', icon: 'fa-envelope', subtitle: 'We\'ll send a new verification link to your email.' }
                );
            } else {
                // REPLACED BROWSER ALERT WITH CUSTOM NOTIFICATION
                showNotification(data.error || 'Login failed. Please check your credentials.', 'error');
            }
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Sign In';
        }
    } catch (err) {
        console.error(err);
        showNotification('Network error. Please check your internet connection and try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Sign In';
    }
});
</script>
<?php require_once __DIR__ . '/../includes/kinas-ui.php'; ?>
<?php require_once __DIR__ . '/../includes/password-toggle.php'; ?>
</body>
</html>
