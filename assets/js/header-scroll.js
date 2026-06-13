/* KINAS GROUP — Shared transparent-header scroll effect
 * --------------------------------------------------------------
 * Toggles `.transparent` / `.solid` on `#header` based on scroll
 * position. The header is transparent when the user is at the very
 * top of the page (and therefore sitting over a full-bleed hero
 * image) and switches to solid as soon as the user scrolls past
 * the header's own height — so the menu text never overlaps the
 * hero content during scroll.
 *
 * Used by:
 *   - index.php                       (home)
 *   - divisions/kinas-automobile/index.php
 *   - divisions/williams-connect-home/index.php
 *   - divisions/kinas-volt/index.php
 *   - divisions/kinas-marketplace/index.php
 *
 * Self-disables (no-op) on pages without a `.je3-header` element
 * or where the header wasn't given the `transparent` class by
 * templates/header.php. Pages like detail.php, search.php, and
 * the dashboards keep their server-rendered `solid` class and
 * the scroll listener never fires a class change.
 * --------------------------------------------------------------
 */
(function () {
    'use strict';

    var header = document.getElementById('header');
    if (!header) return;
    if (!header.classList.contains('transparent')) return; // already solid

    // The header's own height is the right threshold: as soon as the
    // top of the page has scrolled out from under the header, the
    // header is no longer sitting over the hero — flip to solid so
    // its text stays readable.
    function getThreshold() {
        return header.offsetHeight || 60;
    }

    var ticking = false;

    function updateHeader() {
        if (window.scrollY > getThreshold()) {
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
