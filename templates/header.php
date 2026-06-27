<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuBtn = document.getElementById('mobileMenuBtn');
    const drawer = document.getElementById('mobileNavDrawer');
    const overlay = document.getElementById('menuOverlay');
    const closeBtn = document.getElementById('closeMobileMenu');

    function openMenu() {
        drawer.classList.add('open');
        overlay.classList.add('active');
        menuBtn.classList.add('open');
        menuBtn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
        drawer.classList.remove('open');
        overlay.classList.remove('active');
        menuBtn.classList.remove('open');
        menuBtn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    function toggleMenu() {
        if (drawer.classList.contains('open')) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    if (menuBtn) {
        menuBtn.addEventListener('click', toggleMenu);
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeMenu);
    }

    if (overlay) {
        overlay.addEventListener('click', closeMenu);
    }

    // Close menu on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && drawer.classList.contains('open')) {
            closeMenu();
        }
    });
});
</script>
