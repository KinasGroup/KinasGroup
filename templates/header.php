<?php
require_once __DIR__ . '/../api/config/database.php';
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
    <meta name="color-scheme" content="light only">
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
    
    <meta name="description" content="<?php echo $pageDescription ?? 'KINAS GROUP - The World\'s Luxury Marketplace: Homes, Cars, Solar & Products for Sale'; ?>">
    <title><?php echo $pageTitle ?? 'KINAS GROUP | The World\'s Luxury Marketplace'; ?></title>

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
    <link rel="stylesheet" href="/assets/css/footer-social.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/james-edition.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <?php if ($userRole === 'admin'): ?>
    <link rel="stylesheet" href="/assets/css/admin.css">
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
        <i class="fas fa-shopping-bag" style="margin-right:8px;"></i>Cart
        <span id="jeCartBadgeMobile" style="display:none;background:#C6A43F;color:#0A0A0A;font-size:11px;font-weight:700;min-width:18px;height:18px;border-radius:999px;padding:0 5px;line-height:18px;text-align:center;margin-left:8px;"></span>
    </a>
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
            <div class="header-nav-links">
                <a href="/divisions/kinas-automobile/">KINAS AUTOMOBILE</a>
                <a href="/divisions/williams-connect-home/">WILLIAMS CONNECT HOME</a>
                <a href="/divisions/kinas-volt/">KINAS VOLT</a>
                <a href="/divisions/kinas-marketplace/">KINAS MARKETPLACE</a>
                <a href="/pages/about.php">ABOUT US</a>
            </div>
            <div class="header-nav-actions">
                <a href="/divisions/kinas-marketplace/cart.php" id="jeHeaderCartLink" class="header-cart-link" aria-label="Cart">
                    <i class="fas fa-shopping-bag"></i>
                    <span id="jeCartBadge" class="header-cart-badge"></span>
                </a>
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
            </div>
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
</script>
