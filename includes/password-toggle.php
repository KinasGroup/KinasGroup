<?php
/**
 * KINAS GROUP — Password Visibility Toggle
 * --------------------------------------------------
 * Include this file in any page that has password fields.
 * It (a) prints a small CSS block, and (b) auto-wires up
 * any <div class="je-password-wrap"> on the page so the
 * eye icon toggles between type="password" and type="text".
 *
 * Usage in a form:
 *   <div class="je-password-wrap">
 *       <input type="password" name="password" id="password" ...>
 *       <button type="button" class="je-password-toggle"
 *               aria-label="Show password" aria-pressed="false">
 *           <i class="fas fa-eye" aria-hidden="true"></i>
 *       </button>
 *   </div>
 *
 * The icon automatically swaps between fa-eye and fa-eye-slash.
 * Safe to include multiple times — IIFE-guarded.
 */
?>
<style>
.je-password-wrap { position: relative; }
.je-password-wrap > input {
    /* Leave room for the eye icon on the right */
    padding-right: 42px !important;
}
.je-password-toggle {
    position: absolute;
    top: 50%;
    right: 6px;
    transform: translateY(-50%);
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 0;
    padding: 0;
    margin: 0;
    cursor: pointer;
    color: #888;
    border-radius: 4px;
    transition: color 0.15s ease, background-color 0.15s ease;
}
.je-password-toggle:hover,
.je-password-toggle:focus-visible {
    color: var(--je-gold, #C6A43F);
    background: rgba(198, 164, 63, 0.08);
    outline: none;
}
.je-password-toggle.is-visible { color: var(--je-gold, #C6A43F); }
.je-password-toggle i { font-size: 15px; line-height: 1; pointer-events: none; }
</style>
<script>
(function () {
    'use strict';
    if (window.__jePasswordToggleLoaded) return;
    window.__jePasswordToggleLoaded = true;

    function init() {
        var wraps = document.querySelectorAll('.je-password-wrap');
        wraps.forEach(function (wrap) {
            if (wrap.dataset.jeBound === '1') return;
            wrap.dataset.jeBound = '1';

            var input = wrap.querySelector('input[type="password"], input[data-password-toggle]');
            var btn   = wrap.querySelector('.je-password-toggle');
            if (!input || !btn) return;

            // Make sure the button is never a submit, even inside a form
            btn.setAttribute('type', 'button');
            btn.setAttribute('aria-label', 'Show password');
            btn.setAttribute('aria-pressed', 'false');

            function sync() {
                var visible = input.type === 'text';
                btn.classList.toggle('is-visible', visible);
                btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
                btn.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
                var icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye', !visible);
                    icon.classList.toggle('fa-eye-slash', visible);
                }
            }

            btn.addEventListener('click', function (e) {
                e.preventDefault();
                input.type = (input.type === 'password') ? 'text' : 'password';
                // Keep focus + caret position stable
                var caret = input.selectionStart;
                input.focus();
                try { input.setSelectionRange(caret, caret); } catch (_) {}
                sync();
            });

            // Initialize icon to "eye" (password hidden by default)
            sync();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
