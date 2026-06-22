<?php
// KINAS GROUP footer — uses the JE luxury footer component
require_once __DIR__ . '/../includes/je-components.php';
je_render_footer('site');
?>

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

// Fix for X (Twitter) social icon in footer
document.addEventListener('DOMContentLoaded', function() {
    // Find all social links in footer
    var socialLinks = document.querySelectorAll('.je-footer-social a, .footer-social a, .social-links a');
    var xIconFound = false;
    
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
                // Ensure it has the X icon
                if (!icon.classList.contains('fa-x-twitter') && !icon.classList.contains('fa-twitter')) {
                    icon.className = 'fab fa-x-twitter';
                }
                xIconFound = true;
            }
        }
    });
    
    // If no X/Twitter link found, we need to add it
    // This assumes the footer has a container for social icons
    if (!xIconFound) {
        var socialContainer = document.querySelector('.je-footer-social, .footer-social, .social-links');
        if (socialContainer) {
            // Check if there's already an X link in the footer
            var existingXLink = socialContainer.querySelector('a[href*="x.com"], a[href*="twitter.com"]');
            if (!existingXLink) {
                // Get the X URL from settings or use default
                var xUrl = 'https://x.com/kinasgroup';
                
                // Try to get from settings via AJAX or use default
                var xLink = document.createElement('a');
                xLink.href = xUrl;
                xLink.target = '_blank';
                xLink.rel = 'noopener noreferrer';
                xLink.setAttribute('aria-label', 'X (Twitter)');
                
                var xIcon = document.createElement('i');
                xIcon.className = 'fab fa-x-twitter';
                xLink.appendChild(xIcon);
                
                // Insert as second item if possible
                var children = socialContainer.children;
                if (children.length > 0) {
                    socialContainer.insertBefore(xLink, children[1]);
                } else {
                    socialContainer.appendChild(xLink);
                }
            }
        }
    }
});

// Also handle dynamic footer rendering from je-components
document.addEventListener('DOMContentLoaded', function() {
    // If the footer is rendered via JavaScript, wait a bit and then fix
    setTimeout(function() {
        var socialLinks = document.querySelectorAll('.je-footer-social a, .footer-social a, .social-links a');
        socialLinks.forEach(function(link) {
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
    }, 500);
});
</script>
</body>
</html>
