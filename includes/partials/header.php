<?php
// $headerStyle = 'solid' | 'transparent' (default solid for protected pages)
// $headerDepth = '../' for 1 level deep, '../../' for 2 levels, '' for root
$headerStyle = $headerStyle ?? 'solid';
$depth       = $headerDepth ?? '../';
?>
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
<script>
function toggleMenu(){var n=document.getElementById('mainNav'),b=document.querySelector('.mobile-menu-btn');n.classList.toggle('active');b.textContent=n.classList.contains('active')?'✕':'☰';}
document.addEventListener('click',function(e){var n=document.getElementById('mainNav'),b=document.querySelector('.mobile-menu-btn');if(n&&n.classList.contains('active')&&!n.contains(e.target)&&e.target!==b){n.classList.remove('active');b.textContent='☰';}});
</script>
