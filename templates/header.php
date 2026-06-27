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
        /* ============================================================
           MOBILE MENU STYLES - RESTORED + FIXED
           ============================================================ */
        
        /* Mobile menu button - base styles */
        .mobile-menu-btn {
            display: none;
            background: none !important;
            background-color: transparent !important;
            border: none !important;
            font-size: 28px;
            cursor: pointer;
            padding: 8px 12px;
            z-index: 1003;
            position: relative;
            min-width: 48px;
            min-height: 48px;
            align-items: center;
            justify-content: center;
            line-height: 1;
            color: #0A0A0A !important;
        }

        /* Force hamburger icon */
        .mobile-menu-btn .menu-icon {
            display: block !important;
            font-size: 28px !important;
            line-height: 1 !important;
        }

        /* Force close icon - hidden by default */
        .mobile-menu-btn .menu-icon-close {
            display: none !important;
            font-size: 28px !important;
            line-height: 1 !important;
        }

        /* Transparent header - white icons */
        .je3-header.transparent .mobile-menu-btn {
            color: #ffffff !important;
        }
        .je3-header.transparent .mobile-menu-btn .menu-icon,
        .je3-header.transparent .mobile-menu-btn .menu-icon-close {
            color: #ffffff !important;
        }

        /* Solid header - dark icons */
        .je3-header.solid .mobile-menu-btn {
            color: #0A0A0A !important;
        }
        .je3-header.solid .mobile-menu-btn .menu-icon,
        .je3-header.solid .mobile-menu-btn .menu-icon-close {
            color: #0A0A0A !important;
        }

        /* Mobile navigation drawer */
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

        /* Show hamburger on mobile */
        @media (max-width: 1200px) {
            .mobile-menu-btn {
                display: flex !important;
            }
            .header-nav {
                display: none !important;
            }
        }

        @media (min-width: 1201px) {
            .mobile-nav-drawer {
                display: none !important;
            }
            .menu-overlay {
                display: none !important;
            }
        }

        /* Dark mode override - keep menu visible and transparent */
        @media (prefers-color-scheme: dark) {
            .mobile-menu-btn {
                display: flex !important;
                background: transparent !important;
                background-color: transparent !important;
                color: #0A0A0A !important;
            }
            .je3-header.transparent .mobile-menu-btn {
                color: #ffffff !important;
            }
            .je3-header.transparent .mobile-menu-btn .menu-icon,
            .je3-header.transparent .mobile-menu-btn .menu-icon-close {
                color: #ffffff !important;
            }
            .je3-header.solid .mobile-menu-btn {
                color: #0A0A0A !important;
            }
            .je3-header.solid .mobile-menu-btn .menu-icon,
            .je3-header.solid .mobile-menu-btn .menu-icon-close {
                color: #0A0A0A !important;
            }
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
