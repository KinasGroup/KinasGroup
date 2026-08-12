<?php
/**
 * KINAS GROUP — Global Site Header
 *
 * Includes:
 * - WhatsApp global floating button & product integration constants
 * - Open Graph / Twitter Card meta tags
 * - Mobile navigation drawer & overlay
 * - Desktop navigation with notification bell & cart badge
 * - Real-time session-based notification polling
 */

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure session is active before checking user data
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? null;
$userName = $_SESSION['user_name'] ?? '';

// ============================================================
// WHATSAPP CONFIGURATION
// ============================================================
$whatsappNumber = defined('WHATSAPP_NUMBER')
    ? preg_replace('/\D+/', '', (string)WHATSAPP_NUMBER)
    : '';

$whatsappEnabled = $whatsappNumber !== '';

$whatsappGeneralMessage = 'Hello KINAS GROUP, I would like to make an enquiry.';
$whatsappGeneralLink = $whatsappEnabled
    ? 'https://wa.me/' . $whatsappNumber . '?text=' . rawurlencode($whatsappGeneralMessage)
    : '';

// Check if this is a "hero page" (for transparent header overlay effect).
$isHeroPage = false;
$scriptName = basename($_SERVER['PHP_SELF']);
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Homepage: / and /index.php
if ($scriptName === 'index.php' && in_array($requestUri, ['/', '/index.php'], true)) {
    $isHeroPage = true;
}

// Division landings: /divisions/*/index.php
if ($scriptName === 'index.php'
    && preg_match('#^/divisions/[^/]+/?$#', $requestUri)) {
    $isHeroPage = true;
}

// About page
if ($scriptName === 'about.php'
    && preg_match('#^/pages/about\.php$#', $requestUri)) {
    $isHeroPage = true;
}

$transparentClass = $isHeroPage ? 'transparent' : 'solid';

// ---------------------------------------------------------------------
// Open Graph / Twitter Card / canonical URL data
// ---------------------------------------------------------------------
$ogScheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';

$ogHost = $_SERVER['HTTP_HOST'] ?? parse_url(SITE_URL, PHP_URL_HOST);
$ogOrigin = $ogScheme . '://' . $ogHost;

$ogTitle = $pageTitle ?? (SITE_NAME . ' | One Company, Multiple Solutions, One Trusted Ecosystem');
$ogDescription = $pageDescription ?? 'KINAS GROUP - One Company, Multiple Solutions, One Trusted Ecosystem';

$ogImageRaw = $pageImage ?? '/assets/images/logos/kinas-group-logo.jpg';
$ogImage = (stripos($ogImageRaw, 'http://') === 0 || stripos($ogImageRaw, 'https://') === 0)
    ? $ogImageRaw
    : $ogOrigin . $ogImageRaw;

$canonicalUrl = $pageUrl ?? ($ogOrigin . ($_SERVER['REQUEST_URI'] ?? '/'));
?>
<!DOCTYPE html>
<html lang="en" style="color-scheme: light;">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">

<!-- FAVICON -->
<link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
<link rel="manifest" href="/assets/images/site.webmanifest">
<meta name="theme-color" content="#0A0A0A">

<!-- FORCE LIGHT MODE -->
<meta name="color-scheme" content="only light">
<meta name="theme-color" content="#ffffff">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<style>
html, body { color-scheme: light !important; background: #ffffff !important; }
@media (prefers-color-scheme: dark) {
    html, body { color-scheme: light !important; background: #ffffff !important; color: #0A0A0A !important; }
}
</style>

<meta name="description" content="<?php echo htmlspecialchars($ogDescription); ?>">
<title><?php echo htmlspecialchars($ogTitle); ?></title>
<link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">

<!-- OPEN GRAPH / TWITTER CARD -->
<meta property="og:type" content="<?php echo htmlspecialchars($pageType ?? 'website'); ?>">
<meta property="og:site_name" content="<?php echo htmlspecialchars(SITE_NAME); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
<meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>">
<meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
<meta name="twitter:image" content="<?php echo htmlspecialchars($ogImage); ?>">

<!-- USER DATA -->
<meta name="user-data" content='<?php
$userData = [
    'id' => $_SESSION['user_id'] ?? null,
    'name' => $_SESSION['user_name'] ?? '',
    'email' => $_SESSION['user_email'] ?? '',
    'phone' => $_SESSION['user_phone'] ?? '',
    'role' => $_SESSION['user_role'] ?? '',
    'loggedIn' => isset($_SESSION['user_id'])
];
echo json_encode($userData);
?>'>
<meta name="user-id" content="<?php echo $_SESSION['user_id'] ?? ''; ?>">

<!-- Stylesheets -->
<?php
$__cssVer = function ($relPath) {
    $abs = __DIR__ . '/..' . $relPath;
    return $relPath . '?v=' . (@filemtime($abs) ?: time());
};
?>
<link rel="stylesheet" href="<?= $__cssVer('/assets/css/footer-social.css') ?>">
<link rel="stylesheet" href="<?= $__cssVer('/assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= $__cssVer('/assets/css/james-edition.css') ?>">
<link rel="stylesheet" href="<?= $__cssVer('/assets/css/responsive.css') ?>">

<?php if ($userRole === 'admin'): ?>
<link rel="stylesheet" href="<?= $__cssVer('/assets/css/admin.css') ?>">
<?php endif; ?>

<!-- ============================================================ -->
<!-- WHATSAPP CSS -->
<!-- ============================================================ -->
<?php if (file_exists(__DIR__ . '/../assets/css/whatsapp-button.css')): ?>
<link rel="stylesheet" href="<?= $__cssVer('/assets/css/whatsapp-button.css') ?>">
<?php else: ?>
<style>
/* Minimal fallback WhatsApp styles if whatsapp-button.css is missing */
.kinas-whatsapp-float {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 99999;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #25D366;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
    transition: background 0.2s ease, transform 0.2s ease;
}

.kinas-whatsapp-float:hover {
    background: #128C7E;
    transform: translateY(-2px);
}

.kinas-whatsapp-float svg {
    width: 28px;
    height: 28px;
    fill: currentColor;
    display: block;
}

.kinas-whatsapp-tooltip {
    display: none;
}
</style>
<?php endif; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">

<!-- ============================================================ -->
<!-- CRITICAL MOBILE MENU & NOTIFICATION STYLES (RESTORED) -->
<!-- ============================================================ -->
<style>
/* CRITICAL MOBILE MENU STYLES */
.mobile-menu-btn { display: none; background: none; border: none; font-size: 28px; cursor: pointer; color: #0A0A0A; padding: 10px; z-index: 1003; position: relative; }
.mobile-nav-drawer { position: fixed; top: 0; right: -100%; width: 85%; max-width: 320px; height: 100%; background: #0A0A0A; z-index: 1002; transition: right 0.3s ease-in-out; padding: 30px 20px; display: flex; flex-direction: column; gap: 5px; overflow-y: auto; box-shadow: -5px 0 25px rgba(0, 0, 0, 0.3); }
.mobile-nav-drawer.open { right: 0; }
.mobile-nav-drawer .close-menu { background: none; border: none; color: #C6A43F; font-size: 28px; cursor: pointer; align-self: flex-end; margin-bottom: 20px; padding: 5px; line-height: 1; }
.mobile-nav-drawer a { color: #e0e0e0; text-decoration: none; font-family: 'Inter', sans-serif; padding: 14px 0; border-bottom: 1px solid #2a2a2a; font-size: 15px; letter-spacing: 0.5px; transition: color 0.3s; }
.mobile-nav-drawer a:hover { color: #C6A43F; }
.mobile-nav-drawer hr { border-color: #333; margin: 10px 0; }
.menu-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 1001; }
.menu-overlay.active { display: block; }
@media (max-width: 768px) {
.mobile-menu-btn { display: block !important; }
.header-nav { display: none !important; }
}
@media (min-width: 769px) {
.mobile-nav-drawer { display: none !important; }
.menu-overlay { display: none !important; }
}
.je3-header.transparent .mobile-menu-btn .menu-icon,
.je3-header.transparent .mobile-menu-btn .menu-icon-close { color: #ffffff !important; }
.je3-header.solid .mobile-menu-btn .menu-icon,
.je3-header.solid .mobile-menu-btn .menu-icon-close { color: #0A0A0A !important; }
/* NOTIFICATION BADGE STYLES */
.notification-container { position: relative; display: inline-flex; align-items: center; margin-right: 12px; vertical-align: middle; }
.notification-icon { font-size: 20px; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; padding: 4px 2px; color: #0A0A0A; transition: color 0.2s; position: relative; }
.je3-header.transparent .notification-icon { color: #ffffff; }
.je3-header.solid .notification-icon { color: #0A0A0A; }
.notification-icon:hover { color: #C6A43F !important; }
.notification-badge { position: absolute; top: -6px; right: -6px; background: #dc3545; color: #ffffff !important; border-radius: 50%; padding: 1px 6px; font-size: 10px; font-weight: 700; min-width: 18px; height: 18px; text-align: center; border: 2px solid #ffffff; z-index: 1000; line-height: 14px; display: none; box-shadow: 0 2px 4px rgba(0,0,0,0.2); font-family: 'Inter', Arial, sans-serif; pointer-events: none; }
.notification-badge.show { display: inline-block; animation: notificationPulse 0.5s ease-in-out 2; }
@keyframes notificationPulse { 0% { transform: scale(1); } 50% { transform: scale(1.3); } 100% { transform: scale(1); } }
.mobile-nav-drawer .notification-mobile-link { display: flex; align-items: center; justify-content: space-between; color: #e0e0e0; text-decoration: none; padding: 14px 0; border-bottom: 1px solid #2a2a2a; font-size: 15px; letter-spacing: 0.5px; transition: color 0.3s; }
.mobile-nav-drawer .notification-mobile-link:hover { color: #C6A43F; }
.mobile-nav-drawer .notification-mobile-badge { background: #dc3545; color: #ffffff; border-radius: 50%; padding: 1px 8px; font-size: 12px; font-weight: 700; min-width: 22px; height: 22px; text-align: center; line-height: 22px; display: none; }
.mobile-nav-drawer .notification-mobile-badge.show { display: inline-block; }
</style>

<!-- ============================================================ -->
<!-- WHATSAPP SITE CONSTANTS + SCRIPT -->
<!-- ============================================================ -->
<?php if ($whatsappEnabled): ?>
<script>
window.SITE_CONSTANTS = window.SITE_CONSTANTS || {};
window.SITE_CONSTANTS.WHATSAPP_NUMBER = <?= json_encode(
    $whatsappNumber,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;
window.SITE_CONSTANTS.WHATSAPP_GENERAL_MESSAGE = <?= json_encode(
    $whatsappGeneralMessage,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;
</script>

<?php if (file_exists(__DIR__ . '/../assets/js/whatsapp-button.js')): ?>
<script src="<?= $__cssVer('/assets/js/whatsapp-button.js') ?>" defer></script>
<?php endif; ?>
<?php endif; ?>

</head>
<body>

<!-- Mobile Menu Overlay -->
<div id="menuOverlay" class="menu-overlay"></div>

<!-- Mobile Navigation Drawer -->
<div id="mobileNavDrawer" class="mobile-nav-drawer">
<button class="close-menu" id="closeMobileMenu">✕</button>

<a href="/divisions/kinas-automobile/">KINAS AUTOMOBILE</a>
<a href="/divisions/williams-connect-home/">WILLIAMS CONNECT HOME</a>
<a href="/divisions/kinas-volt/">KINAS VOLT</a>
<a href="/divisions/kinas-marketplace/">KINAS MARKETPLACE</a>
<a href="/pages/about.php">ABOUT US</a>

<a href="/divisions/kinas-marketplace/cart.php">
<i class="fas fa-cart-shopping" style="margin-right:8px;"></i>Cart
<span id="jeCartBadgeMobile" style="display:none;background:#C6A43F;color:#0A0A0A;font-size:11px;font-weight:700;min-width:18px;height:18px;border-radius:999px;padding:0 5px;line-height:18px;text-align:center;margin-left:8px;"></span>
</a>

<!-- NOTIFICATION LINK IN MOBILE MENU -->
<?php if ($isLoggedIn): ?>
<?php
$messagesLink = '/user/messages.php';
if ($userRole === 'agent') { $messagesLink = '/agent/messages.php'; }
elseif ($userRole === 'admin') { $messagesLink = '/admin/messages.php'; }
?>
<a href="<?php echo htmlspecialchars($messagesLink); ?>" class="notification-mobile-link">
<span><i class="fas fa-envelope" style="margin-right:8px;"></i>Messages</span>
<span id="notificationMobileBadge" class="notification-mobile-badge">0</span>
</a>
<?php endif; ?>

<hr>

<?php if ($isLoggedIn): ?>
<?php if ($userRole === 'admin'): ?>
<a href="/admin/dashboard.php">Admin Dashboard</a>
<?php elseif ($userRole === 'agent'): ?>
<a href="/agent/dashboard.php">Agent Dashboard</a>
<?php else: ?>
<a href="/user/dashboard.php">My Dashboard</a>
<?php endif; ?>
<a href="/auth/logout.php">Sign Out</a>
<?php else: ?>
<a href="/auth/login.php">Sign In</a>
<a href="/auth/register.php">Register as Agent</a>
<a href="/auth/register-buyer.php">Register as Buyer</a>
<?php endif; ?>
</div>

<!-- HEADER -->
<header class="je3-header <?php echo $transparentClass; ?>" id="header">
<div class="container header-inner">

<a href="/" class="header-logo">
<img src="/assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP">
</a>

<button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu" aria-expanded="false">
<span class="menu-icon" aria-hidden="true">☰</span>
<span class="menu-icon-close" style="display:none;" aria-hidden="true">✕</span>
</button>

<nav class="header-nav" id="mainNav">
<a href="/divisions/kinas-automobile/">KINAS AUTOMOBILE</a>
<a href="/divisions/williams-connect-home/">WILLIAMS CONNECT HOME</a>
<a href="/divisions/kinas-volt/">KINAS VOLT</a>
<a href="/divisions/kinas-marketplace/">KINAS MARKETPLACE</a>
<a href="/pages/about.php">ABOUT US</a>

<a href="/divisions/kinas-marketplace/cart.php" id="jeHeaderCartLink" class="header-cart-link" aria-label="Cart">
<i class="fas fa-cart-shopping"></i>
<span id="jeCartBadge" class="header-cart-badge"></span>
</a>

<!-- NOTIFICATION BELL - Desktop -->
<?php if ($isLoggedIn): ?>
<?php
$messagesLink = '/user/messages.php';
if ($userRole === 'agent') { $messagesLink = '/agent/messages.php'; }
elseif ($userRole === 'admin') { $messagesLink = '/admin/messages.php'; }
?>
<div class="notification-container">
<a href="<?php echo htmlspecialchars($messagesLink); ?>" class="notification-icon" aria-label="Messages" title="View Messages">
<i class="fas fa-envelope"></i>
<span id="notificationBadge" class="notification-badge">0</span>
</a>
</div>
<?php endif; ?>

<?php if ($isLoggedIn): ?>
<?php if ($userRole === 'admin'): ?>
<a href="/admin/dashboard.php" class="je2-button nav-btn-outline">Admin Panel</a>
<?php elseif ($userRole === 'agent'): ?>
<a href="/agent/dashboard.php" class="je2-button nav-btn-outline">Dashboard</a>
<?php else: ?>
<a href="/user/dashboard.php" class="je2-button nav-btn-outline">Dashboard</a>
<?php endif; ?>
<a href="/auth/logout.php" class="je2-button nav-btn-outline">Sign Out</a>
<?php else: ?>
<a href="/auth/login.php" class="je2-button nav-btn-outline">Sign In</a>
<a href="/auth/register.php" class="je2-button nav-btn-filled">Register</a>
<?php endif; ?>
</nav>
</div>
</header>

<!-- ============================================================ -->
<!-- GLOBAL FLOATING WHATSAPP BUTTON -->
<!-- ============================================================ -->
<?php if ($whatsappEnabled): ?>
<a href="<?= htmlspecialchars($whatsappGeneralLink, ENT_QUOTES, 'UTF-8') ?>"
   class="kinas-whatsapp-float"
   target="_blank"
   rel="noopener noreferrer"
   aria-label="Chat with KINAS GROUP on WhatsApp"
   data-kinas-whatsapp-global="1">
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
    <span class="kinas-whatsapp-tooltip">Chat with us on WhatsApp</span>
</a>
<?php endif; ?>

<main>

<script>
// CART BADGE
(function() {
    var badge = document.getElementById('jeCartBadge');
    var badgeMobile = document.getElementById('jeCartBadgeMobile');

    if (!badge && !badgeMobile) return;

    fetch('/api/cart/count.php', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.count > 0) {
                if (badge) { badge.textContent = data.count; badge.style.display = 'flex'; }
                if (badgeMobile) { badgeMobile.textContent = data.count; badgeMobile.style.display = 'inline-block'; }
            }
        })
        .catch(function() { /* silent */ });
})();

// MOBILE MENU TOGGLE
(function() {
    'use strict';

    var menuBtn = document.getElementById('mobileMenuBtn');
    var drawer = document.getElementById('mobileNavDrawer');
    var overlay = document.getElementById('menuOverlay');
    var closeBtn = document.getElementById('closeMobileMenu');
    var body = document.body;

    if (!menuBtn || !drawer) return;

    var menuIcon = menuBtn.querySelector('.menu-icon');
    var closeIcon = menuBtn.querySelector('.menu-icon-close');

    function openMenu() {
        drawer.classList.add('open');
        if (overlay) overlay.classList.add('active');
        menuBtn.setAttribute('aria-expanded', 'true');
        body.style.overflow = 'hidden';

        if (menuIcon) menuIcon.style.display = 'none';
        if (closeIcon) closeIcon.style.display = 'block';
    }

    function closeMenu() {
        drawer.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        menuBtn.setAttribute('aria-expanded', 'false');
        body.style.overflow = '';

        if (menuIcon) menuIcon.style.display = 'block';
        if (closeIcon) closeIcon.style.display = 'none';
    }

    function toggleMenu(e) {
        e.preventDefault(); e.stopPropagation();
        if (drawer.classList.contains('open')) { closeMenu(); } else { openMenu(); }
    }

    var newBtn = menuBtn.cloneNode(true);
    menuBtn.parentNode.replaceChild(newBtn, menuBtn);
    menuBtn = newBtn;
    menuIcon = menuBtn.querySelector('.menu-icon');
    closeIcon = menuBtn.querySelector('.menu-icon-close');

    menuBtn.addEventListener('click', toggleMenu);

    if (closeBtn) closeBtn.addEventListener('click', function(e) { e.preventDefault(); closeMenu(); });
    if (overlay) overlay.addEventListener('click', function(e) { e.preventDefault(); closeMenu(); });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && drawer.classList.contains('open')) closeMenu();
    });

    if (closeIcon) closeIcon.style.display = 'none';
    if (menuIcon) menuIcon.style.display = 'block';
})();

// ============================================================
// NOTIFICATION SYSTEM - Real-time unread message count (Session-based)
// ============================================================
(function() {
    'use strict';

    var isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
    if (!isLoggedIn) return;

    var CONFIG = {
        refreshInterval: 30000,
        apiEndpoint: '/api/messages/unread-count.php',
    };

    var timeout = null;
    var lastCount = -1;

    function updateBadges() {
        fetch(CONFIG.apiEndpoint, {
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) {
            if (response.status === 401 || response.status === 403) {
                console.log('Notification: Not authenticated');
                return;
            }
            if (!response.ok) throw new Error('Failed to fetch');
            return response.json();
        })
        .then(function(data) {
            if (data && data.success) {
                var count = data.unread_count || 0;

                var badge = document.getElementById('notificationBadge');
                var mobileBadge = document.getElementById('notificationMobileBadge');

                if (badge) {
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.style.display = 'inline-block';
                        badge.classList.add('show');
                    } else {
                        badge.style.display = 'none';
                        badge.classList.remove('show');
                    }
                }

                if (mobileBadge) {
                    if (count > 0) {
                        mobileBadge.textContent = count > 99 ? '99+' : count;
                        mobileBadge.style.display = 'inline-block';
                        mobileBadge.classList.add('show');
                    } else {
                        mobileBadge.style.display = 'none';
                        mobileBadge.classList.remove('show');
                    }
                }

                if (lastCount !== -1 && count > lastCount) {
                    playNotificationSound();
                }

                lastCount = count;
            }
        })
        .catch(function(error) {
            // Silently fail
        });
    }

    function playNotificationSound() {
        try {
            var audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            var oscillator = audioCtx.createOscillator();
            var gainNode = audioCtx.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);

            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            gainNode.gain.value = 0.1;

            oscillator.start();
            setTimeout(function() { oscillator.stop(); }, 200);
        } catch (e) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            updateBadges();
            timeout = setInterval(updateBadges, CONFIG.refreshInterval);
        });
    } else {
        updateBadges();
        timeout = setInterval(updateBadges, CONFIG.refreshInterval);
    }

    window.addEventListener('beforeunload', function() {
        if (timeout) { clearInterval(timeout); timeout = null; }
    });

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) { updateBadges(); }
    });

    console.log('Notification system initialized (session-based)');
})();
</script>
