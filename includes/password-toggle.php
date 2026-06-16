<?php
/**
 * KINAS GROUP — Password Visibility Toggle (ULTIMATE FIX)
 */
?>
<style>
/* === ULTIMATE OVERRIDE - beats everything === */
.je-password-wrap,
.je-form-group .je-password-wrap {
    position: relative !important;
    display: block !important;
    z-index: 999 !important;
}

.je-password-wrap > input,
.je-form-group input[type="password"],
.je-form-group .je-password-wrap input {
    padding-right: 58px !important;
    box-sizing: border-box !important;
    position: relative !important;
    z-index: 1 !important;
}

/* The eye button */
.je-password-toggle,
.je-form-group .je-password-toggle {
    position: absolute !important;
    top: 50% !important;
    right: 12px !important;
    transform: translateY(-50%) !important;
    width: 42px !important;
    height: 42px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: #fff !important;
    border: 1px solid #ddd !important;
    border-radius: 50% !important;
    cursor: pointer !important;
    color: #555 !important;
    z-index: 100 !important;
    font-size: 20px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
    transition: all 0.2s ease !important;
}

.je-password-toggle:hover,
.je-password-toggle:focus {
    color: #C6A43F !important;
    background: #fff !important;
    border-color: #C6A43F !important;
    box-shadow: 0 2px 6px rgba(198,164,63,0.3) !important;
}

.je-password-toggle i {
    font-size: 20px !important;
    pointer-events: none !important;
}

/* Browser built-in password eye suppression */
input::-ms-reveal,
input::-ms-clear,
.je-password-wrap input::-ms-reveal,
.je-password-wrap input::-ms-clear {
    display: none !important;
}
</style>

<script>
(function() {
    'use strict';
    if (window.__passwordToggleInit) return;
    window.__passwordToggleInit = true;

    function initToggles() {
        document.querySelectorAll('.je-password-wrap').forEach(wrap => {
            if (wrap.dataset.bound) return;
            wrap.dataset.bound = 'true';

            const input = wrap.querySelector('input[type="password"]');
            const btn = wrap.querySelector('.je-password-toggle');
            if (!input || !btn) return;

            btn.type = 'button';

            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye', !isHidden);
                    icon.classList.toggle('fa-eye-slash', isHidden);
                }
            });

            // Initial icon
            const icon = btn.querySelector('i');
            if (icon) icon.classList.add('fa-eye');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initToggles);
    } else {
        initToggles();
    }
})();
</script>
