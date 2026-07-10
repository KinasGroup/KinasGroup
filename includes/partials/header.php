<?php
// $headerStyle = 'solid' | 'transparent' (default solid for protected pages)
// $headerDepth = '../' for 1 level deep, '../../' for 2 levels, '' for root
$headerStyle = $headerStyle ?? 'solid';
$depth       = $headerDepth ?? '../';
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
    
    <title>KINAS GROUP</title>
    <!-- ... rest of your header content ... -->
    
    <header class="je3-header <?= $headerStyle ?>" id="header" style="position:fixed;">
        <div class="container header-inner">
            <a href="<?= $depth ?>index.php" class="header-logo">
                <img src="<?= $depth ?>assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP">
            </a>
            <button class="mobile-menu-btn" onclick="toggleMenu()">☰</button>
            <nav class="header-nav" id="mainNav">
                <a href="<?= $depth ?>divisions/kinas-automobile/index.php">AUTOMOBILE</a>
                <a href="<?= $depth ?>divisions/williams-connect-home/index.php">HOMES</a>
                <a href="<?= $depth ?>divisions/kinas-volt/index.php">VOLT</a>
                <a href="<?= $depth ?>divisions/kinas-marketplace/index.php">MARKETPLACE</a>
                <?php if (SessionManager::isLoggedIn()): ?>
                    <a href="<?= $depth ?>auth/logout.php" class="je2-button nav-btn-outline">Sign Out</a>
                <?php else: ?>
                    <a href="<?= $depth ?>auth/login.php" class="je2-button nav-btn-outline">Sign In</a>
                    <a href="<?= $depth ?>auth/register.php" class="je2-button nav-btn-filled">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Font Awesome Icons - CRITICAL for ALL icons on dashboard pages -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <script>
    function toggleMenu(){var n=document.getElementById('mainNav'),b=document.querySelector('.mobile-menu-btn');n.classList.toggle('active');b.textContent=n.classList.contains('active')?'✕':'☰';}
    document.addEventListener('click',function(e){var n=document.getElementById('mainNav'),b=document.querySelector('.mobile-menu-btn');if(n&&n.classList.contains('active')&&!n.contains(e.target)&&e.target!==b){n.classList.remove('active');b.textContent='☰';}});
    </script>
