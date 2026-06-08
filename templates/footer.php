<?php
// KINAS GROUP footer — uses the JE luxury footer component
require_once __DIR__ . '/../includes/je-components.php';
je_render_footer('site');
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Header transparent → solid on scroll
    var header = document.getElementById('header');
    if (header) {
        var update = function () {
            if (window.scrollY > 50) {
                header.classList.remove('transparent');
                header.classList.add('solid');
            } else {
                header.classList.add('transparent');
                header.classList.remove('solid');
            }
        };
        update();
        window.addEventListener('scroll', update, { passive: true });
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
