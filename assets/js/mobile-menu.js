// KINAS GROUP — Mobile Menu
//
// Division pages: drives #mobileNavDrawer (uses .open class + right:0 CSS)
// Other pages:    drives #mainNav          (uses .active class + display:flex CSS)
// Button:         always #mobileMenuBtn in templates/header.php
// Overlay:        always #menuOverlay in templates/header.php
//
// Safety: calls closeMenu() on init so the menu always starts fully closed.

(function () {
    'use strict';

    function init() {
        var btn      = document.getElementById('mobileMenuBtn');
        var overlay  = document.getElementById('menuOverlay');
        var drawer   = document.getElementById('mobileNavDrawer');
        var nav      = document.getElementById('mainNav');
        var closeBtn = document.getElementById('closeMobileMenu');

        if (!btn) return;

        var usingDrawer = !!drawer;
        var target      = drawer || nav;
        if (!target) return;

        function isOpen() {
            if (usingDrawer) return drawer.classList.contains('open');
            return nav.classList.contains('active');
        }

        function setIcon(open) {
            var iconOpen  = btn.querySelector('.menu-icon');
            var iconClose = btn.querySelector('.menu-icon-close');
            if (iconOpen)  iconOpen.style.display  = open ? 'none'   : 'inline';
            if (iconClose) iconClose.style.display = open ? 'inline' : 'none';
            btn.setAttribute('aria-expanded', String(open));
        }

        function openMenu() {
            if (usingDrawer) {
                drawer.classList.add('open');
            } else {
                nav.classList.add('active');
            }
            if (overlay) {
                overlay.style.display = 'block';
                overlay.classList.add('active');
            }
            document.body.style.overflow = 'hidden';
            setIcon(true);
        }

        function closeMenu() {
            if (usingDrawer) {
                drawer.classList.remove('open');
            } else {
                nav.classList.remove('active');
            }
            if (overlay) {
                overlay.classList.remove('active');
                overlay.style.display = 'none';
            }
            document.body.style.overflow = '';
            setIcon(false);
        }

        // Always start closed
        closeMenu();

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            isOpen() ? closeMenu() : openMenu();
        });

        if (closeBtn) closeBtn.addEventListener('click', closeMenu);
        if (overlay)  overlay.addEventListener('click', closeMenu);

        target.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', closeMenu);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) closeMenu();
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 768 && isOpen()) closeMenu();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
