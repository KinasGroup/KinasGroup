<?php
// Authenticated, per-session content — never cache this page.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

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

// Google OAuth slot — shipped disabled; flip in .env when ready.
$googleOAuthEnabled = filter_var(getenv('GOOGLE_OAUTH_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN);

// AUTO CACHE-BUST: version = file modified time (never serve stale CSS).
function authCssV($file)
{
    return @filemtime(__DIR__ . '/../assets/css/' . $file) ?: 1;
}
?>
<!DOCTYPE html>
<html lang="en" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="only light">
    <meta name="theme-color" content="#050505">

    <?php require_once __DIR__ . '/../includes/favicon.php'; ?>

    <title>Sign In - KINAS GROUP | One Company, Multiple Solutions, One Trusted Ecosystem</title>

    <link rel="stylesheet" href="../assets/css/style.css?v=<?= authCssV('style.css') ?>">
    <link rel="stylesheet" href="../assets/css/james-edition.css?v=<?= authCssV('james-edition.css') ?>">
    <link rel="stylesheet" href="../assets/css/responsive.css?v=<?= authCssV('responsive.css') ?>">
    <link rel="stylesheet" href="../assets/css/auth.css?v=<?= authCssV('auth.css') ?>">

    <link rel="preload" as="image" href="../assets/images/hero/auth-hero-night.jpg">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="ka-shell">
        <div class="ka-main">
            <div class="ka-hero" aria-hidden="true"></div>

            <!-- ── Brand panel ── -->
            <aside class="ka-brand">
                <a href="../index.php" class="ka-logo">
                    <img src="../assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP" onerror="this.style.display='none'">
                </a>

                <div class="ka-brand-copy">
                    <h1 class="ka-headline">Welcome<br><span class="ka-accent">Back!</span></h1>
                    <div class="ka-rule"></div>
                    <p class="ka-group"><span class="ka-accent">Kinas</span> Group</p>
                    <p class="ka-desc">One Company. Multiple Solutions. One Trusted Ecosystem. Delivering excellence across Real Estate, Automobiles, Solar Solutions, Hospitality, Global Trade and Commerce.</p>
                </div>
            </aside>

            <!-- ── Form card ── -->
            <main class="ka-form-side">
                <div class="ka-card">
                    <h2>Log In</h2>
                    <p class="ka-sub">Enter your details to access your account.</p>

                    <?php if ($errorMessage): ?>
                        <div class="ka-alert error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?></div>
                    <?php endif; ?>

                    <?php if ($successMessage): ?>
                        <div class="ka-alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?></div>
                    <?php endif; ?>

                    <form id="loginForm" method="POST" action="../api/auth/login.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                        <div class="ka-field">
                            <label for="email">Email or Username</label>
                            <div class="ka-input-wrap">
                                <i class="fas fa-user ka-lead" aria-hidden="true"></i>
                                <input class="ka-input" type="text" id="email" name="email" placeholder="Enter your email or @username" required autocomplete="username">
                            </div>
                        </div>

                        <div class="ka-field">
                            <label for="password">Password</label>
                            <div class="ka-input-wrap je-password-wrap">
                                <i class="fas fa-lock ka-lead" aria-hidden="true"></i>
                                <input class="ka-input" type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                                <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="ka-row">
                            <label class="ka-remember">
                                <input type="checkbox" name="remember" value="1"> Remember me
                            </label>
                            <a href="forgot-password.php" class="ka-link">Forgot password?</a>
                        </div>

                        <div class="ka-field" id="captcha-group">
                            <div id="login-captcha-container"></div>
                            <input type="hidden" id="login-captcha-token" name="captcha_token">
                        </div>

                        <button type="submit" id="submitBtn" class="ka-btn-primary">Log in</button>

                        <?php if ($googleOAuthEnabled): ?>
                            <div class="ka-divider-or">or</div>
                            <button type="button" class="ka-btn-google" id="googleBtn">
                                <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                                Continue with Google
                            </button>
                        <?php endif; ?>
                    </form>

                    <div class="ka-switch">
                        Don't have an account? <a href="register.php" class="ka-link">Register Now</a>
                        <span class="ka-dot">·</span>
                        <a href="register-buyer.php" class="ka-link">Register as Buyer</a>
                    </div>

                    <div class="ka-card-trust">
                        <span><i class="fas fa-lock"></i>256-bit SSL encrypted</span>
                        <span class="ka-dot">·</span>
                        <span><i class="fas fa-shield-alt"></i>Your data is protected</span>
                    </div>
                </div>
            </main>
        </div>

        <!-- ── Trust bar ── -->
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
        const loginCaptchaSiteKey = '<?= htmlspecialchars(get_captcha_site_key()) ?>';
        const isLoginCaptchaConfigured = loginCaptchaSiteKey && loginCaptchaSiteKey !== '6LeXXXXXXXXXXXXXXXXXXXXXXXX' && loginCaptchaSiteKey.length > 30;

        if (isLoginCaptchaConfigured) {
            var s = document.createElement('script');
            s.src = 'https://www.google.com/recaptcha/api.js?onload=onLoginCaptchaLoad&render=explicit';
            s.async = true;
            s.defer = true;
            document.head.appendChild(s);
        } else {
            const g = document.getElementById('captcha-group');
            if (g) g.style.display = 'none';
        }

        function onLoginCaptchaLoad() {
            if (!isLoginCaptchaConfigured) return;

            const c = document.getElementById('login-captcha-container');

            if (c && typeof grecaptcha !== 'undefined') {
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

        window.showNotification = function(message, type) {
            try {
                const typeMap = {
                    error: 'error',
                    success: 'success',
                    warning: 'warning',
                    info: 'info'
                };

                if (typeof window.kinasToast === 'function') {
                    window.kinasToast(message, typeMap[type] || 'error', 5000);
                } else {
                    alert(message);
                }
            } catch (err) {
                console.error('Notification error:', err);
                alert(message);
            }
        };

        function updateLoginCsrfToken(token) {
            if (!token) return;

            const field = document.querySelector('#loginForm input[name="csrf_token"]');

            if (field) {
                field.value = token;
            }
        }

        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const form = this;
            const submitBtn = document.getElementById('submitBtn');
            const originalBtnHtml = submitBtn.innerHTML;

            const email = form.email.value.trim();
            const password = form.password.value;

            if (!email || !password) {
                window.showNotification('Please enter both email/username and password.', 'warning');
                return;
            }

            const captchaToken = document.getElementById('login-captcha-token')?.value || '';

            if (isLoginCaptchaConfigured && !captchaToken) {
                window.showNotification('Please complete the CAPTCHA verification.', 'warning');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in…';

            let willRedirect = false;

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email,
                        password,
                        csrf_token: form.elements.csrf_token ? form.elements.csrf_token.value : '',
                        captcha_token: captchaToken
                    })
                });

                const rawText = await res.text();

                let data = {};

                try {
                    data = JSON.parse(rawText);
                } catch (parseError) {
                    console.error('Login response was not valid JSON:', rawText);
                    throw new Error('Invalid login response');
                }

                if (data.csrf_token) {
                    updateLoginCsrfToken(data.csrf_token);
                }

                if (data.success) {
                    willRedirect = true;

                    if (data.token) {
                        localStorage.setItem('kinas_token', data.token);
                    }

                    const role = data?.user?.role || 'user';

                    if (role === 'admin') {
                        window.location.href = '/admin/dashboard.php';
                    } else if (role === 'agent') {
                        window.location.href = '/agent/dashboard.php';
                    } else {
                        window.location.href = '/user/dashboard.php';
                    }

                    return;
                }

                if (data.error_code === 'email_not_verified') {
                    const message =
                        (data.error || 'Please verify your email.') +
                        ' Would you like us to send a new verification link to ' +
                        (data.email || email) + '?';

                    if (typeof window.kinasConfirm === 'function') {
                        window.kinasConfirm(
                            message,
                            async function() {
                                try {
                                    const r = await fetch('/api/auth/resend-verification.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json'
                                        },
                                        body: JSON.stringify({
                                            email: data.email || email
                                        })
                                    });

                                    const rd = await r.json();

                                    window.showNotification(
                                        rd.message || 'If that email is registered and unverified, a new link has been sent.',
                                        'success'
                                    );
                                } catch (err) {
                                    window.showNotification('Could not resend the verification email. Please try again later.', 'error');
                                }
                            },
                            {
                                title: 'Resend Verification',
                                confirm: 'Send Link',
                                variant: 'gold',
                                icon: 'fa-envelope',
                                subtitle: 'We\'ll send a new verification link to your email.'
                            }
                        );
                    } else {
                        window.showNotification(data.error || 'Please verify your email before signing in.', 'error');
                    }
                } else {
                    window.showNotification(data.error || 'Invalid email/username or password. Please try again.', 'error');
                }
            } catch (err) {
                console.error('Login request failed:', err);
                window.showNotification('Unable to complete login. Please check your connection and try again.', 'error');
            } finally {
                if (!willRedirect) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml || 'Log in';
                }
            }
        });
    </script>

    <?php require_once __DIR__ . '/../includes/password-toggle.php'; ?>
</body>
</html>
