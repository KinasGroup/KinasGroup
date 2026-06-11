<?php
// KINAS GROUP footer — uses the JE luxury footer component
require_once __DIR__ . '/../includes/je-components.php';
je_render_footer('site');
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Header transparent → solid on scroll.
    //
    // BUG FIX: the previous version called update() on page load AND on
    // every scroll event, forcing 'transparent' on the header whenever
    // scrollY <= 50. That's correct for the homepage (server renders
    // class="je3-header transparent") but broken for EVERY other page
    // (server renders class="je3-header solid" for dashboards, login,
    // division pages, etc.) — the JS would clobber the server's choice
    // and produce an invisible white-on-light header at the top of the
    // page until the user scrolled past 50px.
    //
    // Fix: only ADD 'solid' on scroll-down. Never force 'transparent'
    // — the server's PHP in templates/header.php has already picked the
    // correct initial class based on whether we're on the homepage.
    var header = document.getElementById('header');
    if (header) {
        var onScroll = function () {
            if (window.scrollY > 50) {
                header.classList.add('solid');
            }
        };
        window.addEventListener('scroll', onScroll, { passive: true });
    }

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
</script>
</body>
</html>
