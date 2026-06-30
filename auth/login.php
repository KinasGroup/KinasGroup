<?php
// Load environment variables from .env file
require_once __DIR__ . '/../includes/dotenv.php';

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

// Redirect already-logged-in users away from auth pages
if (SessionManager::isLoggedIn()) {
    $role = SessionManager::getUserRole();
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ============================================================
         SUPPORT BOTH LIGHT & DARK THEME
         - Light theme: white form, dark aside, gold accents.
         - Dark  theme: dark form, dark aside, bright gold accents.
         Gold characters are preserved in both themes via the dedicated
         `prefers-color-scheme` block below (uses brighter gold on dark
         backgrounds and forces inline gold styles to follow theme).
         ============================================================ -->
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0A0A0A" media="(prefers-color-scheme: dark)">
    <style>
        html, body {
            color-scheme: light dark;
            background: #ffffff;
            color: #0A0A0A;
            transition: background-color 0.25s ease, color 0.25s ease;
        }

        /* ── DARK THEME ────────────────────────────────────────── */
        @media (prefers-color-scheme: dark) {
            html, body {
                background: #0A0A0A;
                color: rgba(255,255,255,0.92);
            }
            .je-auth-shell {
                background-color: #0A0A0A;
            }
            .je-auth-aside {
                background-color: #050505;
                color: rgba(255,255,255,0.72);
            }
            .je-auth-aside h1,
            .je-auth-aside .je-auth-headline {
                color: #ffffff;
            }
            .je-auth-aside .je-auth-quote p {
                color: rgba(255,255,255,0.78);
            }
            .je-auth-aside .je-auth-quote cite {
                color: rgba(255,255,255,0.5);
            }
            .je-auth-main,
            .je-auth-form {
                background-color: #0A0A0A;
                color: rgba(255,255,255,0.92);
            }
            .je-auth-form h2 {
                color: #ffffff;
            }
            .je-auth-form .je-auth-sub-form {
                color: rgba(255,255,255,0.6);
            }
            .je-form-group label {
                color: rgba(255,255,255,0.7) !important;
            }
            .je-form-group input,
            .je-form-group select,
            .je-form-group textarea {
                background-color: #141414 !important;
                color: rgba(255,255,255,0.92) !important;
                border-color: rgba(255,255,255,0.15) !important;
            }
            .je-form-group input::placeholder {
                color: rgba(255,255,255,0.38);
            }
            .je-form-group input:focus,
            .je-form-group select:focus,
            .je-form-group textarea:focus {
                border-color: var(--je-gold) !important;
                box-shadow: 0 0 0 3px rgba(212,185,106,0.22) !important;
            }
            .je-password-toggle {
                color: rgba(255,255,255,0.5);
            }
            .je-password-toggle:hover {
                color: var(--je-gold-soft);
            }
            /* Gold submit button — flip so gold fills the button on dark */
            .je-btn-gold {
                background: var(--je-gold) !important;
                color: var(--je-ink) !important;
                border-color: var(--je-gold) !important;
            }
            .je-btn-gold:hover {
                background: var(--je-gold-soft) !important;
                border-color: var(--je-gold-soft) !important;
            }
            /* All inline gold links/icons — brighten so they read on dark */
            a[style*="color: #C6A43F"],
            a[style*="color:#C6A43F"],
            i[style*="color: #C6A43F"],
            i[style*="color:#C6A43F"] {
                color: #D4B96A !important;
            }
            /* Switch links */
            .je-auth-switch a {
                color: var(--je-gold-soft);
            }
            /* Helper paragraphs (e.g. password rules) */
            .je-form-group p,
            .je-auth-form > p {
                color: rgba(255,255,255,0.55) !important;
            }
            /* Forgot-password / remember-me row label */
            label[style*="color:#666"],
            label[style*="color: #666"] {
                color: rgba(255,255,255,0.7) !important;
            }
        }

        /* ── LIGHT THEME — gold characters tuned for white bg ──── */
        @media (prefers-color-scheme: light) {
            /* Subtle tweaks so gold text on white has AA contrast */
            a[style*="color: #C6A43F"],
            a[style*="color:#C6A43F"],
            i[style*="color: #C6A43F"],
            i[style*="color:#C6A43F"] {
                color: #A8882E !important; /* var(--je-gold-deep) */
            }
        }
    </style>
    
    <title>Sign In - KINAS GROUP | Luxury Marketplace</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
    </style>
</head>
<body>

<!-- ============================================================
     CUSTOM NOTIFICATION SYSTEM - HIDDEN BY DEFAULT
     ============================================================ -->
<div id="jeNotification" class="je-notification" role="alert" aria-live="polite" style="display: none;">
    <div class="je-notification-content">
        <span class="je-notification-icon">
            <i class="fas fa-exclamation-circle" id="jeNotificationIcon"></i>
        </span>
        <div class="je-notification-body">
            <div class="je-notification-title" id="jeNotificationTitle">Attention</div>
            <div class="je-notification-message" id="jeNotificationMessage">Your message here</div>
        </div>
        <button class="je-notification-close" id="jeNotificationClose" aria-label="Close notification">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="je-notification-progress" id="jeNotificationProgress"></div>
</div>
<!-- ============================================================ -->

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

// ============================================================
// CUSTOM NOTIFICATION SYSTEM
// ============================================================
(function() {
    'use strict';

    var notification = document.getElementById('jeNotification');
    var messageEl = document.getElementById('jeNotificationMessage');
    var titleEl = document.getElementById('jeNotificationTitle');
    var iconEl = document.getElementById('jeNotificationIcon');
    var closeBtn = document.getElementById('jeNotificationClose');
    var progressBar = document.getElementById('jeNotificationProgress');
    var timeoutId = null;

    var icons = {
        error: 'fa-exclamation-circle',
        success: 'fa-check-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    var titles = {
        error: 'Error',
        success: 'Success',
        warning: 'Warning',
        info: 'Information'
    };

    window.showNotification = function(message, type, title) {
        if (timeoutId) {
            clearTimeout(timeoutId);
            timeoutId = null;
        }

        type = type || 'error';
        
        messageEl.textContent = message || 'An error occurred. Please try again.';
        titleEl.textContent = title || titles[type] || 'Attention';
        
        var iconClass = icons[type] || icons.error;
        iconEl.className = 'fas ' + iconClass;
        
        notification.className = 'je-notification ' + type + ' is-visible';
        notification.style.display = 'block';
        
        progressBar.style.animation = 'none';
        void progressBar.offsetWidth;
        progressBar.style.animation = 'jeNotificationProgress 5s linear forwards';
        
        timeoutId = setTimeout(function() {
            hideNotification();
        }, 5000);
    };

    window.hideNotification = function() {
        if (timeoutId) {
            clearTimeout(timeoutId);
            timeoutId = null;
        }
        notification.classList.remove('is-visible');
        notification.className = 'je-notification';
        notification.style.display = 'none';
    };

    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            hideNotification();
        });
    }

    document.addEventListener('click', function(e) {
        if (notification.style.display === 'block') {
            if (!notification.contains(e.target)) {
                hideNotification();
            }
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && notification.style.display === 'block') {
            hideNotification();
        }
    });

    console.log('Notification system initialized');
})();

// ============================================================
// LOGIN FORM HANDLER - NO ALERT POPUPS
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
            if (data.user.role === 'admin') {
                window.location.href = '/admin/dashboard.php';
            } else if (data.user.role === 'agent') {
                window.location.href = '/agent/dashboard.php';
            } else {
                window.location.href = '/user/dashboard.php';
            }
        } else {
            // Special case: email not verified
            if (data.error_code === 'email_not_verified') {
                // Use confirm() - this is a user decision, not an error alert
                if (confirm(
                    (data.error || 'Please verify your email.') +
                    '\n\nWould you like us to send a new verification link to ' + (data.email || email) + '?'
                )) {
                    try {
                        const r = await fetch('/api/auth/resend-verification.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ email: data.email || email })
                        });
                        const rd = await r.json();
                        showNotification(rd.message || 'A new verification link has been sent.', 'success');
                    } catch (err) {
                        showNotification('Could not resend verification email. Please try again later.', 'error');
                    }
                }
            } else {
                // REPLACED ALERT WITH CUSTOM NOTIFICATION
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
<?php require_once __DIR__ . '/../includes/password-toggle.php'; ?>
</body>
</html>
