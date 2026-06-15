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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - KINAS GROUP | Luxury Marketplace</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
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
            <h1 class="je-auth-headline">Platform Administration.</h1>
            <p class="je-auth-sub"></p>
        </div>
        <blockquote class="je-auth-quote">
            <p>"Operational clarity and control — everything you need to keep the marketplace running at its best."</p>
            <cite>— KINAS GROUP Operations</cite>
        </blockquote>
    </aside>

    <main class="je-auth-main">
        <div class="je-auth-form">
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
                    <div class="je-password-wrap" id="password-wrap">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="je-password-toggle" id="password-toggle-btn" aria-label="Show password" aria-pressed="false" tabindex="0">
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
            if (data.user.role !== 'admin') {
                alert('This account does not have admin privileges. Use the Agent/Buyer login instead.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In to Admin';
                return;
            }
            localStorage.setItem('kinas_token', data.token);
            window.location.href = 'dashboard.php';
        } else {
            alert(data.error || 'Login failed. Admin access only.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In to Admin';
        }
    } catch (err) {
        console.error(err);
        alert('Network error. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In to Admin';
    }
});

// ============================================
// PASSWORD TOGGLE FIX (Embedded)
// ============================================
(function() {
    'use strict';
    
    function initPasswordToggle() {
        var passwordInput = document.getElementById('password');
        var toggleBtn = document.getElementById('password-toggle-btn');
        var wrapper = document.getElementById('password-wrap');
        
        if (!passwordInput || !toggleBtn || !wrapper) {
            console.log('Password toggle elements not found');
            return;
        }
        
        // Force inline styles on wrapper
        wrapper.style.position = 'relative';
        wrapper.style.display = 'block';
        
        // Force inline styles on input
        passwordInput.style.paddingRight = '48px';
        passwordInput.style.width = '100%';
        
        // Force inline styles on button
        toggleBtn.style.position = 'absolute';
        toggleBtn.style.right = '0';
        toggleBtn.style.top = '0';
        toggleBtn.style.height = '100%';
        toggleBtn.style.width = '44px';
        toggleBtn.style.display = 'flex';
        toggleBtn.style.alignItems = 'center';
        toggleBtn.style.justifyContent = 'center';
        toggleBtn.style.background = 'transparent';
        toggleBtn.style.border = 'none';
        toggleBtn.style.cursor = 'pointer';
        toggleBtn.style.color = '#888';
        toggleBtn.style.fontSize = '18px';
        toggleBtn.style.zIndex = '10';
        
        // Remove any existing listeners and add fresh one
        var newBtn = toggleBtn.cloneNode(true);
        toggleBtn.parentNode.replaceChild(newBtn, toggleBtn);
        toggleBtn = newBtn;
        
        // Get the icon
        var icon = toggleBtn.querySelector('i');
        
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                if (icon) {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
                toggleBtn.setAttribute('aria-label', 'Hide password');
                toggleBtn.setAttribute('aria-pressed', 'true');
            } else {
                passwordInput.type = 'password';
                if (icon) {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
                toggleBtn.setAttribute('aria-label', 'Show password');
                toggleBtn.setAttribute('aria-pressed', 'false');
            }
            passwordInput.focus();
        });
        
        console.log('✅ Password toggle is active');
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPasswordToggle);
    } else {
        initPasswordToggle();
    }
})();
</script>
</body>
</html>
