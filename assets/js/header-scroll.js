/* KINAS GROUP — Shared transparent-header scroll effect
 * --------------------------------------------------------------
 * Toggles `.transparent` / `.solid` on `#header` based on scroll
 * position relative to the page's hero section.
 *
 * Used by:
 *   - index.php  (home, id="heroSection")
 *   - divisions/kinas-automobile/index.php  (id="heroSection")
 *   - divisions/williams-connect-home/index.php
 *   - divisions/kinas-volt/index.php
 *   - divisions/kinas-marketplace/index.php
 *
 * The effect is opt-in: it only runs if BOTH the header and a hero
 * section exist on the page. Pages without a hero (e.g. detail,
 * search, dashboard) are no-ops — they keep the `solid` class that
 * templates/header.php already assigned.
 * --------------------------------------------------------------
 */
(function () {
    'use strict';

    var header      = document.getElementById('header');
    var heroSection = document.getElementById('heroSection');

    if (!header || !heroSection) return;        // not a hero page
    if (!header.classList.contains('transparent')) return; // already solid

    var ticking = false;

    function updateHeader() {
        var heroBottom = heroSection.getBoundingClientRect().bottom;
        // When the hero scrolls out of view (bottom <= 0), lock the header solid.
        if (heroBottom <= 60) {
            header.classList.remove('transparent');
            header.classList.add('solid');
        } else {
            header.classList.add('transparent');
            header.classList.remove('solid');
        }
        ticking = false;
    }

    function onScroll() {
        if (!ticking) {
            window.requestAnimationFrame(updateHeader);
            ticking = true;
        }
    }

    updateHeader();                  // run once on load (e.g. browser back-scroll)
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
})();
