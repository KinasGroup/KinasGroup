<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../includes/dotenv.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/helpers.php';

if (SessionManager::isLoggedIn()) {
    $role = SessionManager::getUserRole();
    header('Location: ' . ($role === 'admin' ? '../admin/dashboard.php' : ($role === 'agent' ? '../agent/dashboard.php' : '../user/dashboard.php')));
    exit;
}

$csrfToken = Security::generateCSRFToken();
$errorMessage = SessionManager::getFlash('error');
$successMessage = SessionManager::getFlash('success');
$countries = kinas_countries();

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

    <title>Buyer Registration - KINAS GROUP | One Company, Multiple Solutions, One Trusted Ecosystem</title>

    <link rel="stylesheet" href="../assets/css/style.css?v=<?= authCssV('style.css') ?>">
    <link rel="stylesheet" href="../assets/css/james-edition.css?v=<?= authCssV('james-edition.css') ?>">
    <link rel="stylesheet" href="../assets/css/responsive.css?v=<?= authCssV('responsive.css') ?>">
    <link rel="stylesheet" href="../assets/css/auth.css?v=<?= authCssV('auth.css') ?>">

    <link rel="preload" as="image" href="../assets/images/hero/auth-hero-night.jpg">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .ka-phone-row {
            display: grid;
            grid-template-columns: minmax(230px, 280px) 1fr;
            gap: 10px;
            align-items: stretch;
        }

        .ka-phone-code-wrap .ka-select {
            padding-left: 14px;
        }

        @media (max-width: 560px) {
            .ka-phone-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                    <h1 class="ka-headline">Discover. Acquire.<br><span class="ka-accent">Belong.</span></h1>
                    <div class="ka-rule"></div>
                    <p class="ka-group"><span class="ka-accent">Kinas</span> Group</p>
                    <p class="ka-desc">One account to browse, save and enquire across Real Estate, Automobiles, Solar and Marketplace.</p>
                </div>
            </aside>

            <main class="ka-form-side">
                <div class="ka-card">
                    <p class="ka-eyebrow"><i class="fas fa-gem"></i> Buyer Account</p>
                    <h2>Create Buyer Account</h2>
                    <p class="ka-sub">Free forever. No credit card required.</p>

                    <?php if ($errorMessage): ?>
                        <div class="ka-alert error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?></div>
                    <?php endif; ?>

                    <?php if ($successMessage): ?>
                        <div class="ka-alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?></div>
                    <?php endif; ?>

                    <form id="registerForm" method="POST" action="../api/auth/register-buyer.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                        <div class="ka-field">
                            <label for="name">Full Name <span style="color:#888;font-weight:400;">(private — KYC only)</span></label>
                            <div class="ka-input-wrap">
                                <i class="fas fa-user ka-lead" aria-hidden="true"></i>
                                <input class="ka-input" type="text" id="name" name="name" placeholder="Your full legal name" required minlength="2" maxlength="100">
                            </div>
                        </div>

                        <div class="ka-field">
                            <label for="username">Username <span style="color:#888;font-weight:400;">(your public identity)</span></label>
                            <div class="ka-input-wrap">
                                <i class="fas fa-at ka-lead" aria-hidden="true"></i>
                                <input class="ka-input" type="text" id="username" name="username" placeholder="e.g. ade_luxury" required minlength="3" maxlength="20" autocomplete="username" autocapitalize="none" spellcheck="false" style="text-transform:lowercase;">
                            </div>
                            <p class="ka-hint" id="usernameHint">3–20 chars: letters, numbers, "_" and ".". This is how other members see you.</p>
                        </div>

                        <div class="ka-grid-2">
                            <div class="ka-field">
                                <label for="email">Email</label>
                                <div class="ka-input-wrap">
                                    <i class="fas fa-envelope ka-lead" aria-hidden="true"></i>
                                    <input class="ka-input" type="email" id="email" name="email" placeholder="your@email.com" required autocomplete="email">
                                </div>
                            </div>

                            <div class="ka-field">
                                <label for="phone">Phone</label>

                                <div class="ka-phone-row">
                                    <div class="ka-input-wrap ka-phone-code-wrap">
                                        <select class="ka-select" id="phone_country" name="phone_country" required aria-label="Country code">
                                            <?php foreach ($countries as $country): ?>
                                                <option
                                                    value="<?= htmlspecialchars($country['iso2']) ?>"
                                                    data-dial="+<?= htmlspecialchars($country['dial']) ?>"
                                                    <?= $country['iso2'] === 'NG' ? 'selected' : '' ?>
                                                >
                                                    <?= htmlspecialchars($country['flag'] . ' ' . $country['name'] . ' (+' . $country['dial'] . ')') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="ka-input-wrap">
                                        <i class="fas fa-phone ka-lead" aria-hidden="true"></i>
                                        <input class="ka-input" type="tel" id="phone" name="phone" placeholder="800 000 0000" required autocomplete="tel-national">
                                    </div>
                                </div>

                                <p class="ka-hint">Select your country, then enter your phone number without the country code.</p>
                            </div>
                        </div>

                        <div class="ka-grid-2">
                            <div class="ka-field">
                                <label for="password">Password</label>
                                <div class="ka-input-wrap je-password-wrap">
                                    <i class="fas fa-lock ka-lead" aria-hidden="true"></i>
                                    <input class="ka-input" type="password" id="password" name="password" placeholder="Min. 8 characters" required minlength="8">
                                    <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <p class="ka-hint">At least 8 characters with uppercase, lowercase, and numbers.</p>
                            </div>

                            <div class="ka-field">
                                <label for="password_confirmation">Confirm Password</label>
                                <div class="ka-input-wrap je-password-wrap">
                                    <i class="fas fa-lock ka-lead" aria-hidden="true"></i>
                                    <input class="ka-input" type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm password" required>
                                    <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="ka-field" id="captcha-group">
                            <div id="captcha-container"></div>
                            <input type="hidden" id="captcha-token" name="captcha_token">
                            <p class="ka-hint"><i class="fas fa-shield-alt je-text-gold" style="color:#C6A43F;"></i> Protected by reCAPTCHA.</p>
                        </div>

                        <p class="ka-terms">
                            <i class="fas fa-shield-alt je-text-gold" style="color:#C6A43F;"></i> By registering, you agree to our
                            <a href="../pages/terms-of-use.php" class="ka-link">Terms</a> and
                            <a href="../pages/privacy-policy.php" class="ka-link">Privacy Policy</a>.
                        </p>

                        <button type="submit" id="submitBtn" class="ka-btn-primary">Create Buyer Account</button>
                    </form>

                    <div class="ka-switch">
                        Already have an account? <a href="login.php" class="ka-link">Sign in</a>
                        <span class="ka-dot">·</span>
                        Want to sell? <a href="register.php" class="ka-link">Register as Agent</a>
                    </div>

                    <div class="ka-card-trust">
                        <span><i class="fas fa-lock"></i>256-bit SSL encrypted</span>
                        <span class="ka-dot">·</span>
                        <span><i class="fas fa-shield-alt"></i>Your data is protected</span>
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

    <script>
        const captchaSiteKey = '<?= htmlspecialchars(get_captcha_site_key()) ?>';
        const isCaptchaConfigured = captchaSiteKey && captchaSiteKey !== '6LeXXXXXXXXXXXXXXXXXXXXXXXX' && captchaSiteKey.length > 30;

        if (isCaptchaConfigured) {
            var s = document.createElement('script');
            s.src = 'https://www.google.com/recaptcha/api.js?onload=onCaptchaLoad&render=explicit';
            s.async = true;
            s.defer = true;
            document.head.appendChild(s);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                const c = document.getElementById('captcha-group');
                if (c) c.style.display = 'none';
            });
        }

        function onCaptchaLoad() {
            if (!isCaptchaConfigured) return;

            const c = document.getElementById('captcha-container');

            if (c) {
                grecaptcha.render('captcha-container', {
                    sitekey: captchaSiteKey,
                    callback: r => document.getElementById('captcha-token').value = r,
                    'expired-callback': () => document.getElementById('captcha-token').value = ''
                });
            }
        }

        (function () {
            const input = document.getElementById('username');
            const hint = document.getElementById('usernameHint');

            if (!input || !hint) return;

            const DEFAULT_HINT = hint.textContent;
            let t = null;

            input.addEventListener('input', function () {
                clearTimeout(t);

                const val = input.value.trim().replace(/^@/, '').toLowerCase();

                if (val.length < 3) {
                    hint.style.color = '';
                    hint.textContent = DEFAULT_HINT;
                    return;
                }

                t = setTimeout(async function () {
                    try {
                        const r = await fetch('/api/auth/check-username.php?username=' + encodeURIComponent(val), {
                            credentials: 'same-origin'
                        });

                        const d = await r.json();

                        hint.textContent = d.message || DEFAULT_HINT;
                        hint.style.color = d.available ? '#1B5E20' : '#C62828';
                    } catch (e) {}
                }, 350);
            });
        })();

        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const form = this;
            const submitBtn = document.getElementById('submitBtn');

            const name = form.name.value.trim();
            const email = form.email.value.trim();
            const phoneCountry = form.phone_country ? form.phone_country.value : '';
            const phone = form.phone.value.trim();
            const username = form.username.value.trim().replace(/^@/, '').toLowerCase();
            const password = form.password.value;
            const passwordConfirmation = form.password_confirmation.value;

            if (!name || !username || !email || !phoneCountry || !phone || !password || !passwordConfirmation) {
                kinasToast('Please fill in all required fields', 'error');
                return;
            }

            const phoneDigits = phone.replace(/\D+/g, '');

            if (phoneDigits.length < 5 || phoneDigits.length > 15) {
                kinasToast('Please enter a valid phone number', 'error');
                return;
            }

            if (password !== passwordConfirmation) {
                kinasToast('Passwords do not match', 'error');
                return;
            }

            if (password.length < 8) {
                kinasToast('Password must be at least 8 characters', 'error');
                return;
            }

            if (isCaptchaConfigured && !document.getElementById('captcha-token').value) {
                kinasToast('Please complete the CAPTCHA verification', 'warning');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account…';

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name,
                        username,
                        email,
                        phone_country: phoneCountry,
                        phone,
                        password,
                        password_confirmation: passwordConfirmation,
                        csrf_token: form.csrf_token.value,
                        captcha_token: isCaptchaConfigured ? document.getElementById('captcha-token').value : ''
                    })
                });

                const result = await res.json();

                if (res.ok && result.success) {
                    const successMessage = encodeURIComponent(result.message || 'Registration successful! Please login to continue.');
                    window.location.href = 'login.php?registered=1&message=' + successMessage;
                } else {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Create Buyer Account';

                    if (result.errors && Array.isArray(result.errors)) {
                        kinasToast(result.errors.join(' · '), 'error');
                    } else if (result.error) {
                        kinasToast(result.error, 'error');
                    } else {
                        kinasToast('Registration failed', 'error');
                    }
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
