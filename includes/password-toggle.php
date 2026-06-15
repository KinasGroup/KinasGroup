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
 * If FontAwesome is not loaded, a plain text fallback ("Show")
 * is shown so the toggle is still functional.
 *
 * Safe to include multiple times — IIFE-guarded.
 */
?>
<style>
.je-password-wrap { position: relative; display: block; }
.je-password-wrap > input {
    /* Leave room for the eye icon on the right.
       !important so we beat .je-form-group input specificity. */
    padding-right: 48px !important;
}
.je-password-toggle {
    position: absolute !important;
    top: 0;
    right: 0;
    height: 100%;
    width: 42px;
    display: flex !important;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 0;
    border-left: 1px solid transparent;
    padding: 0;
    margin: 0;
    cursor: pointer;
    color: #555;
    border-radius: 0 3px 3px 0;
    transition: color 0.15s ease, background-color 0.15s ease;
    z-index: 2;
    font: inherit;
    line-height: 1;
    -webkit-appearance: none;
    appearance: none;
}
.je-password-toggle:hover,
.je-password-toggle:focus-visible {
    color: #C6A43F;
    background: rgba(198, 164, 63, 0.10);
    outline: none;
}
.je-password-toggle.is-visible { color: #C6A43F; }
.je-password-toggle i,
.je-password-toggle svg {
    font-size: 16px;
    line-height: 1;
    pointer-events: none;
}
/* Suppress the browser's built-in password reveal button so it
   doesn't fight with our custom one. */
.je-password-wrap > input::-ms-reveal,
.je-password-wrap > input::-ms-clear { display: none; }
</style>
<script>
(function () {
    'use strict';
    if (window.__jePasswordToggleLoaded) return;
    window.__jePasswordToggleLoaded = true;

    function ensureIcon(btn, visible) {
        var icon = btn.querySelector('i, svg');
        if (!icon) {
            // Fallback text label if no icon element exists
            btn.textContent = visible ? '🙈' : '👁';
            return;
        }
        if (icon.tagName === 'I') {
            // FontAwesome path: toggle fa-eye / fa-eye-slash
            icon.classList.toggle('fa-eye',       !visible);
            icon.classList.toggle('fa-eye-slash',  visible);
        }
    }

    function bindPasswordToggles() {
        var wraps = document.querySelectorAll('.je-password-wrap');
        var bound = 0;
        
        wraps.forEach(function (wrap) {
            // Skip if already bound
            if (wrap.dataset.jeBound === '1') return;
            
            var input = wrap.querySelector('input[type="password"]');
            var btn   = wrap.querySelector('.je-password-toggle');
            
            if (!input || !btn) {
                // Debug: log missing elements (remove in production)
                if (window.console && window.location.hostname === 'localhost') {
                    console.warn('Password toggle: missing input or button', {wrap: wrap, input: input, btn: btn});
                }
                return;
            }
            
            // Mark as bound
            wrap.dataset.jeBound = '1';
            bound++;

            // Force the button type so it never submits the form
            if (btn.tagName === 'BUTTON' && (!btn.type || btn.type === 'submit')) {
                btn.setAttribute('type', 'button');
            }
            btn.setAttribute('aria-label', 'Show password');
            btn.setAttribute('aria-pressed', 'false');

            function sync() {
                var visible = input.type === 'text';
                btn.classList.toggle('is-visible', visible);
                btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
                btn.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
                btn.title = visible ? 'Hide password' : 'Show password';
                ensureIcon(btn, visible);
            }

            // Remove any existing listeners to prevent duplicates
            var newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
            
            newBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                input.type = (input.type === 'password') ? 'text' : 'password';
                // Keep focus + caret position stable across the toggle
                var caret = input.selectionStart;
                input.focus();
                try { input.setSelectionRange(caret, caret); } catch (_) {}
                sync();
            });

            // Initial paint
            sync();
        });
        
        return bound;
    }

    function init() {
        // First attempt immediately
        var bound = bindPasswordToggles();
        
        // If nothing was bound, retry after a short delay (DOM might still be loading)
        if (bound === 0) {
            setTimeout(function() {
                bindPasswordToggles();
            }, 100);
        }
        
        // Also set up a MutationObserver to catch dynamically added password fields
        if (window.MutationObserver && typeof MutationObserver === 'function') {
            var observer = new MutationObserver(function(mutations) {
                // Check if any new password-wrap elements were added
                var needsBinding = document.querySelectorAll('.je-password-wrap:not([data-je-bound="1"])').length > 0;
                if (needsBinding) {
                    bindPasswordToggles();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
