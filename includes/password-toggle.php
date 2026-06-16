<?php
/**
 * KINAS GROUP — Password Visibility Toggle (STRONG FIX)
 */
?>
<style>
/* === STRONG OVERRIDE FOR PASSWORD TOGGLE === */
.je-password-wrap { 
    position: relative !important; 
    display: block !important; 
}

.je-password-wrap > input {
    padding-right: 54px !important;
    box-sizing: border-box !important;
}

/* Button positioning & visibility */
.je-password-toggle {
    position: absolute !important;
    top: 50% !important;
    right: 8px !important;
    transform: translateY(-50%) !important;
    height: 38px !important;
    width: 42px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: transparent !important;
    border: none !important;
    cursor: pointer !important;
    color: #555 !important;
    z-index: 30 !important;
    font-size: 20px !important;
    padding: 0 !important;
    margin: 0 !important;
    border-radius: 50% !important;
    transition: all 0.2s ease !important;
}

.je-password-toggle:hover,
.je-password-toggle:focus-visible {
    color: #C6A43F !important;
    background: rgba(198, 164, 63, 0.2) !important;
}

/* Icon */
.je-password-toggle i,
.je-password-toggle svg {
    font-size: 20px !important;
    pointer-events: none !important;
}

/* Suppress browser built-in reveal */
.je-password-wrap input::-ms-reveal,
.je-password-wrap input::-ms-clear { 
    display: none !important; 
}
</style>

<script>
(function () {
    'use strict';
    if (window.__jePasswordToggleLoaded) return;
    window.__jePasswordToggleLoaded = true;

    function init() {
        document.querySelectorAll('.je-password-wrap').forEach(wrap => {
            if (wrap.dataset.jeBound) return;
            wrap.dataset.jeBound = '1';

            const input = wrap.querySelector('input[type="password"]');
            const btn = wrap.querySelector('.je-password-toggle');
            if (!input || !btn) return;

            btn.type = 'button';

            function toggle() {
                const isVisible = input.type === 'text';
                input.type = isVisible ? 'password' : 'text';
                btn.classList.toggle('is-visible', !isVisible);
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye', isVisible);
                    icon.classList.toggle('fa-eye-slash', !isVisible);
                }
            }

            btn.addEventListener('click', toggle);
            // Initial state
            const icon = btn.querySelector('i');
            if (icon) icon.classList.add('fa-eye');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
