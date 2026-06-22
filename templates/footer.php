<?php
// KINAS GROUP footer — uses the JE luxury footer component
require_once __DIR__ . '/../includes/je-components.php';
je_render_footer('site');
?>

<!-- Font Awesome Icons - Ensures social icons display in footer -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared transparent-header scroll effect (hero pages only) -->
<script src="/assets/js/header-scroll.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Mobile menu
    var btn = document.getElementById('mobileMenuBtn');
    var drawer = document.getElementById('mobileNavDrawer');
    var overlay = document.getElementById('menuOverlay');
    var closeBtn = document.getElementById('closeMobileMenu');

    function openMenu() {
        if (drawer) drawer.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeMenu() {
        if (drawer) drawer.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); drawer.classList.contains('open') ? closeMenu() : openMenu(); });
    if (closeBtn) closeBtn.addEventListener('click', closeMenu);
    if (overlay) overlay.addEventListener('click', closeMenu);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });
    window.addEventListener('resize', function () { if (window.innerWidth > 768) closeMenu(); });
});

// Ensure X (Twitter) icon displays correctly in footer
document.addEventListener('DOMContentLoaded', function() {
    // Fix any Twitter icons to use X icon
    var socialLinks = document.querySelectorAll('.je-footer-social a');
    socialLinks.forEach(function(link) {
        var href = link.getAttribute('href') || '';
        // Check if it's an X/Twitter link
        if (href.includes('twitter.com') || href.includes('x.com')) {
            var icon = link.querySelector('i');
            if (icon) {
                // Replace with X icon if it's using old twitter icon
                if (icon.classList.contains('fa-twitter')) {
                    icon.classList.remove('fa-twitter');
                    icon.classList.add('fa-x-twitter');
                }
                // If it has no class or wrong class, set it properly
                if (!icon.classList.contains('fa-x-twitter') && !icon.classList.contains('fa-twitter')) {
                    icon.className = 'fab fa-x-twitter';
                }
            }
        }
        // Also check for any link that might be missing the X icon
        if (href.includes('x.com') && !href.includes('twitter.com')) {
            var icon = link.querySelector('i');
            if (icon) {
                icon.className = 'fab fa-x-twitter';
            }
        }
    });
    
    // If the footer is rendered dynamically, also fix after a short delay
    setTimeout(function() {
        var socialLinksDelayed = document.querySelectorAll('.je-footer-social a');
        socialLinksDelayed.forEach(function(link) {
            var href = link.getAttribute('href') || '';
            if (href.includes('twitter.com') || href.includes('x.com')) {
                var icon = link.querySelector('i');
                if (icon) {
                    if (icon.classList.contains('fa-twitter')) {
                        icon.classList.remove('fa-twitter');
                        icon.classList.add('fa-x-twitter');
                    }
                    if (!icon.classList.contains('fa-x-twitter') && !icon.classList.contains('fa-twitter')) {
                        icon.className = 'fab fa-x-twitter';
                    }
                }
            }
        });
    }, 300);
});
</script>
</body>
</html>
