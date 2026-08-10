<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? null;
$userName = $_SESSION['user_name'] ?? '';

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
// Open Graph / Twitter Card / canonical URL data — this is what lets
// WhatsApp, Slack, Twitter/X, Telegram, Discord, iMessage, LinkedIn etc.
// build a link-preview card (title + description + thumbnail) when a
// page URL is pasted in. Any page can opt in to a specific title,
// description, or image by setting $pageTitle / $pageDescription /
// $pageImage / $pageType before including this template — division
// detail.php pages set $pageImage to the listing's first photo, for
// example. Every page still falls back to sane group-wide defaults
// below, so nothing shares with a blank/broken preview even if it was
// never updated.
//
// The origin is built from the CURRENT request's own host rather than
// the SITE_URL constant, because this single codebase serves five
// different domains (kinas-group.com, kinasauto.com,
// williamsconnecthome.com, kinasvolt.com, kinasstore.com) — hardcoding
// SITE_URL would put the wrong domain in og:url/canonical for four of
// the five divisions.
$ogScheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$ogHost = $_SERVER['HTTP_HOST'] ?? parse_url(SITE_URL, PHP_URL_HOST);
$ogOrigin = $ogScheme . '://' . $ogHost;

// BRANDING UPDATE: Replaced "The World's Luxury Marketplace" with "One Company, Multiple Solutions, One Trusted Ecosystem"
$ogTitle = $pageTitle ?? (SITE_NAME . ' | One Company, Multiple Solutions, One Trusted Ecosystem');
$ogDescription = $pageDescription ?? 'KINAS GROUP - One Company, Multiple Solutions, One Trusted Ecosystem';

// Default share image falls back to the group logo. Division detail
// pages pass their own listing's photo (already an absolute R2/CDN
// URL) via $pageImage; relative paths — like this fallback — get
// resolved against the current request's own origin above.
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
    
    <!-- ============================================================
         FAVICON - KINAS GROUP BRANDING
         ============================================================ -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
    <link rel="manifest" href="/assets/images/site.webmanifest">
    <meta name="theme-color" content="#0A0A0A">
    <!-- ============================================================ -->
    
    <!-- ============================================================
         FORCE LIGHT MODE - PERMANENT FIX
         ============================================================ -->
    <meta name="color-scheme" content="only light">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <style>
        /* Force light mode immediately - prevents flash of dark */
        html, body { 
            color-scheme: light !important; 
            background: #ffffff !important;
        }
        /* Override any dark mode preferences */
        @media (prefers-color-scheme: dark) {
            html, body {
                color-scheme: light !important;
                background: #ffffff !important;
                color: #0A0A0A !important;
            }
        }
    </style>
    <!-- ============================================================ -->
    
    <!-- BRANDING UPDATE: Replaced "The World's Luxury Marketplace" with "One Company, Multiple Solutions, One Trusted Ecosystem" -->
    <meta name="description" content="<?php echo $pageDescription ?? 'KINAS GROUP - One Company, Multiple Solutions, One Trusted Ecosystem'; ?>">
    <title><?php echo $pageTitle ?? 'KINAS GROUP | One Company, Multiple Solutions, One Trusted Ecosystem'; ?></title>

    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">

    <!-- ============================================================
         OPEN GRAPH / TWITTER CARD — link-preview thumbnails for
         WhatsApp, Facebook, Twitter/X, LinkedIn, Slack, Telegram,
         Discord, iMessage, etc. See the PHP block above for how
         $ogTitle/$ogDescription/$ogImage/$canonicalUrl are derived.
         ============================================================ -->
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
    <!-- ============================================================ -->

    <!-- ============================================================ -->
    <!-- USER DATA - Pass session data to JavaScript -->
    <!-- ============================================================ -->
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
    // Cache-bust every local stylesheet with its own file's last-modified
    // time. Without this, a browser or CDN (e.g. Cloudflare) can keep
    // serving a stale cached copy indefinitely after a CSS fix ships —
    // this exact class of bug already bit image-upload.js earlier.
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
    <!-- FONT AWESOME -->
    <!-- ============================================================ -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    
    <style>
        /* CRITICAL MOBILE MENU STYLES - DO NOT REMOVE */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #0A0A0A;
            padding: 10px;
            z-index: 1003;
            position: relative;
        }

        .mobile-nav-drawer {
            position: fixed;
            top: 0;
            right: -100%;
            width: 85%;
            max-width: 320px;
            height: 100%;
            background: #0A0A0A;
            z-index: 1002;
            transition: right 0.3s ease-in-out;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            gap: 5px;
            overflow-y: auto;
            box-shadow: -5px 0 25px rgba(0, 0, 0, 0.3);
        }

        .mobile-nav-drawer.open {
            right: 0;
        }

        .mobile-nav-drawer .close-menu {
            background: none;
            border: none;
            color: #C6A43F;
            font-size: 28px;
            cursor: pointer;
            align-self: flex-end;
            margin-bottom: 20px;
            padding: 5px;
            line-height: 1;
        }

        .mobile-nav-drawer a {
            color: #e0e0e0;
            text-decoration: none;
            font-family: 'Inter', sans-serif;
            padding: 14px 0;
            border-bottom: 1px solid #2a2a2a;
            font-size: 15px;
            letter-spacing: 0.5px;
            transition: color 0.3s;
        }

        .mobile-nav-drawer a:hover {
            color: #C6A43F;
        }

        .mobile-nav-drawer hr {
            border-color: #333;
            margin: 10px 0;
        }

        .menu-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1001;
        }

        .menu-overlay.active {
            display: block;
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block !important;
            }
            .header-nav {
                display: none !important;
            }
        }

        @media (min-width: 769px) {
            .mobile-nav-drawer {
                display: none !important;
            }
            .menu-overlay {
                display: none !important;
            }
        }

        /* ============================================================
           FIX: Mobile menu color on transparent header
           ============================================================ */
        .je3-header.transparent .mobile-menu-btn .menu-icon,
        .je3-header.transparent .mobile-menu-btn .menu-icon-close {
            color: #ffffff !important;
        }

        .je3-header.solid .mobile-menu-btn .menu-icon,
        .je3-header.solid .mobile-menu-btn .menu-icon-close {
            color: #0A0A0A !important;
        }

        /* ============================================================
           NOTIFICATION BADGE STYLES
           ============================================================ */
        .notification-container {
            position: relative;
            display: inline-flex;
            align-items: center;
            margin-right: 12px;
            vertical-align: middle;
        }
        .notification-icon {
            font-size: 20px;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 2px;
            color: #0A0A0A;
            transition: color 0.2s;
            position: relative;
        }
        .je3-header.transparent .notification-icon {
            color: #ffffff;
        }
        .je3-header.solid .notification-icon {
            color: #0A0A0A;
        }
        .notification-icon:hover {
            color: #C6A43F !important;
        }
        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #dc3545;
            color: #ffffff !important;
            border-radius: 50%;
            padding: 1px 6px;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            text-align: center;
            border: 2px solid #ffffff;
            z-index: 1000;
            line-height: 14px;
            display: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            font-family: 'Inter', Arial, sans-serif;
            pointer-events: none;
        }
        .notification-badge.show {
            display: inline-block;
            animation: notificationPulse 0.5s ease-in-out 2;
        }
        @keyframes notificationPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }

        /* Mobile notification badge */
        .mobile-nav-drawer .notification-mobile-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #e0e0e0;
            text-decoration: none;
            padding: 14px 0;
            border-bottom: 1px solid #2a2a2a;
            font-size: 15px;
            letter-spacing: 0.5px;
            transition: color 0.3s;
        }
        .mobile-nav-drawer .notification-mobile-link:hover {
            color: #C6A43F;
        }
        .mobile-nav-drawer .notification-mobile-badge {
            background: #dc3545;
            color: #ffffff;
            border-radius: 50%;
            padding: 1px 8px;
            font-size: 12px;
            font-weight: 700;
            min-width: 22px;
            height: 22px;
            text-align: center;
            line-height: 22px;
            display: none;
        }
        .mobile-nav-drawer .notification-mobile-badge.show {
            display: inline-block;
        }
    </style>
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
    <a href="/messages.php" class="notification-mobile-link">
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

<!-- HEADER - Transparent Overlay Effect -->
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
            
			<!-- ============================================================ -->
			<!-- NOTIFICATION BELL - Desktop -->
			<!-- ============================================================ -->
			<?php if ($isLoggedIn): ?>
			<div class="notification-container">
				<?php 
				// Route to the correct messages page based on user role
				$messagesLink = '/user/messages.php';
				if ($userRole === 'agent') {
					$messagesLink = '/agent/messages.php';
				} elseif ($userRole === 'admin') {
					$messagesLink = '/admin/messages.php';
				}
				?>
				<a href="<?php echo $messagesLink; ?>" class="notification-icon" aria-label="Messages">
					<i class="fas fa-envelope"></i>
					<span id="notificationBadge" class="notification-badge">0</span>
				</a>
			</div>
			<?php endif; ?>
			<!-- ============================================================ -->

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

<main>

<script>
// ============================================================
// CART BADGE — synced on every page load
// ============================================================
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
        .catch(function() { /* silent — badge just stays hidden */ });
})();

// ============================================================
// MOBILE MENU TOGGLE - SIMPLIFIED AND FIXED
// ============================================================
(function() {
    'use strict';
    
    // Get elements
    var menuBtn = document.getElementById('mobileMenuBtn');
    var drawer = document.getElementById('mobileNavDrawer');
    var overlay = document.getElementById('menuOverlay');
    var closeBtn = document.getElementById('closeMobileMenu');
    var body = document.body;

    // If any elements are missing, exit
    if (!menuBtn || !drawer) {
        console.warn('Mobile menu elements not found');
        return;
    }

    // Get the icon elements
    var menuIcon = menuBtn.querySelector('.menu-icon');
    var closeIcon = menuBtn.querySelector('.menu-icon-close');

    function openMenu() {
        drawer.classList.add('open');
        if (overlay) overlay.classList.add('active');
        menuBtn.setAttribute('aria-expanded', 'true');
        body.style.overflow = 'hidden';
        
        // Swap icons
        if (menuIcon) menuIcon.style.display = 'none';
        if (closeIcon) closeIcon.style.display = 'block';
    }

    function closeMenu() {
        drawer.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        menuBtn.setAttribute('aria-expanded', 'false');
        body.style.overflow = '';
        
        // Swap icons back
        if (menuIcon) menuIcon.style.display = 'block';
        if (closeIcon) closeIcon.style.display = 'none';
    }

    function toggleMenu(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (drawer.classList.contains('open')) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    // Remove any existing event listeners (by cloning and replacing)
    var newBtn = menuBtn.cloneNode(true);
    menuBtn.parentNode.replaceChild(newBtn, menuBtn);
    menuBtn = newBtn;

    // Re-get elements after clone
    menuIcon = menuBtn.querySelector('.menu-icon');
    closeIcon = menuBtn.querySelector('.menu-icon-close');

    // Add click event to the button
    menuBtn.addEventListener('click', toggleMenu);

    // Close button inside drawer
    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            closeMenu();
        });
    }

    // Overlay click to close
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            e.preventDefault();
            closeMenu();
        });
    }

    // Escape key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && drawer.classList.contains('open')) {
            closeMenu();
        }
    });

    // Ensure close icon is hidden initially
    if (closeIcon) closeIcon.style.display = 'none';
    if (menuIcon) menuIcon.style.display = 'block';

    console.log('Mobile menu initialized successfully');
})();

// ============================================================
// NOTIFICATION SYSTEM - Real-time unread message count
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

    function getAuthToken() {
        return localStorage.getItem('auth_token') || 
               localStorage.getItem('jwt_token') || 
               sessionStorage.getItem('auth_token') ||
               null;
    }

    function updateBadges() {
        var token = getAuthToken();
        if (!token) return;

        fetch(CONFIG.apiEndpoint, {
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) throw new Error('Failed to fetch');
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
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

                // Play sound if new messages arrived
                if (lastCount !== -1 && count > lastCount) {
                    playNotificationSound();
                }
                lastCount = count;
            }
        })
        .catch(function(error) {
            console.error('Notification error:', error);
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
        } catch (e) {
            // Silently fail if audio not available
        }
    }

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            updateBadges();
            timeout = setInterval(updateBadges, CONFIG.refreshInterval);
        });
    } else {
        updateBadges();
        timeout = setInterval(updateBadges, CONFIG.refreshInterval);
    }

    // Clean up
    window.addEventListener('beforeunload', function() {
        if (timeout) {
            clearInterval(timeout);
            timeout = null;
        }
    });

    // Update when tab becomes visible
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            updateBadges();
        }
    });

    console.log('Notification system initialized');
})();
</script>