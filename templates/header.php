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

        /* ============================================================
           CUSTOM CONFIRMATION MODAL STYLES
           ============================================================ */
        .je-confirm-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            animation: jeConfirmFadeIn 0.2s ease;
        }

        .je-confirm-modal.is-visible {
            display: flex;
        }

        .je-confirm-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .je-confirm-content {
            position: relative;
            background: #ffffff;
            border-radius: 16px;
            padding: 40px 48px 32px;
            max-width: 440px;
            width: calc(100% - 32px);
            text-align: center;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.25);
            animation: jeConfirmSlideUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 1;
        }

        .je-confirm-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
        }

        .je-confirm-icon.warning {
            background: #FFF3E0;
            color: #E65100;
        }

        .je-confirm-icon.danger {
            background: #FFEBEE;
            color: #C62828;
        }

        .je-confirm-icon.success {
            background: #E8F5E9;
            color: #2E7D32;
        }

        .je-confirm-icon.info {
            background: #E3F2FD;
            color: #0D47A1;
        }

        .je-confirm-title {
            font-family: 'Prata', serif;
            font-size: 20px;
            color: #0A0A0A;
            margin-bottom: 8px;
        }

        .je-confirm-message {
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .je-confirm-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .je-confirm-btn {
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 100px;
        }

        .je-confirm-btn-cancel {
            background: #f5f5f5;
            color: #555;
        }

        .je-confirm-btn-cancel:hover {
            background: #e8e8e8;
        }

        .je-confirm-btn-confirm {
            background: #C6A43F;
            color: #0A0A0A;
        }

        .je-confirm-btn-confirm:hover {
            background: #A8882E;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(198, 164, 63, 0.3);
        }

        .je-confirm-btn-confirm.danger {
            background: #C62828;
            color: #ffffff;
        }

        .je-confirm-btn-confirm.danger:hover {
            background: #B71C1C;
            box-shadow: 0 4px 16px rgba(198, 40, 40, 0.3);
        }

        @keyframes jeConfirmFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes jeConfirmSlideUp {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .je-confirm-content {
                padding: 28px 20px 24px;
            }
            
            .je-confirm-icon {
                width: 48px;
                height: 48px;
                font-size: 20px;
            }
            
            .je-confirm-title {
                font-size: 18px;
            }
            
            .je-confirm-message {
                font-size: 13px;
            }
            
            .je-confirm-btn {
                padding: 10px 20px;
                font-size: 13px;
                min-width: 80px;
            }
        }
    </style>
</head>
<body>

<!-- ============================================================
     CUSTOM CONFIRMATION MODAL
     ============================================================ -->
<div id="jeConfirmModal" class="je-confirm-modal" style="display: none;">
    <div class="je-confirm-overlay"></div>
    <div class="je-confirm-content">
        <div class="je-confirm-icon warning">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <h3 class="je-confirm-title" id="jeConfirmTitle">Confirm Action</h3>
        <p class="je-confirm-message" id="jeConfirmMessage">Are you sure you want to proceed?</p>
        <div class="je-confirm-buttons">
            <button class="je-confirm-btn je-confirm-btn-cancel" id="jeConfirmCancel">Cancel</button>
            <button class="je-confirm-btn je-confirm-btn-confirm" id="jeConfirmConfirm">Confirm</button>
        </div>
    </div>
</div>
<!-- ============================================================ -->

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
// CUSTOM CONFIRMATION MODAL
// ============================================================
(function() {
    'use strict';

    var modal = document.getElementById('jeConfirmModal');
    var overlay = modal ? modal.querySelector('.je-confirm-overlay') : null;
    var titleEl = document.getElementById('jeConfirmTitle');
    var messageEl = document.getElementById('jeConfirmMessage');
    var confirmBtn = document.getElementById('jeConfirmConfirm');
    var cancelBtn = document.getElementById('jeConfirmCancel');
    var iconEl = modal ? modal.querySelector('.je-confirm-icon') : null;

    var currentResolve = null;

    // Show confirmation modal
    window.jeConfirm = function(message, title, type) {
        return new Promise(function(resolve, reject) {
            // Set content
            titleEl.textContent = title || 'Confirm Action';
            messageEl.textContent = message || 'Are you sure you want to proceed?';
            
            // Set icon type
            type = type || 'warning';
            iconEl.className = 'je-confirm-icon ' + type;
            
            // Set icon based on type
            var iconMap = {
                'warning': 'fa-exclamation-circle',
                'danger': 'fa-exclamation-triangle',
                'success': 'fa-check-circle',
                'info': 'fa-info-circle'
            };
            var iconClass = iconMap[type] || 'fa-exclamation-circle';
            iconEl.innerHTML = '<i class="fas ' + iconClass + '"></i>';
            
            // Set button styles
            if (type === 'danger') {
                confirmBtn.className = 'je-confirm-btn je-confirm-btn-confirm danger';
            } else {
                confirmBtn.className = 'je-confirm-btn je-confirm-btn-confirm';
            }
            
            // Show modal
            modal.classList.add('is-visible');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Store resolve function
            currentResolve = resolve;
        });
    };

    // Hide modal
    function hideConfirm() {
        modal.classList.remove('is-visible');
        modal.style.display = 'none';
        document.body.style.overflow = '';
        currentResolve = null;
    }

    // Confirm button
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            if (currentResolve) {
                currentResolve(true);
            }
            hideConfirm();
        });
    }

    // Cancel button
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            if (currentResolve) {
                currentResolve(false);
            }
            hideConfirm();
        });
    }

    // Click overlay to cancel
    if (overlay) {
        overlay.addEventListener('click', function() {
            if (currentResolve) {
                currentResolve(false);
            }
            hideConfirm();
        });
    }

    // Escape key to cancel
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            if (currentResolve) {
                currentResolve(false);
            }
            hideConfirm();
        }
    });

    console.log('Confirmation modal initialized');
})();
</script>
