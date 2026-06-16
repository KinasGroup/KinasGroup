<?php
/**
 * KINAS GROUP — Password Visibility Toggle (FIXED)
 */
?>
<style>
/* More robust password toggle — beats other .je-form-group rules */
.je-password-wrap {
    position: relative !important;
    display: block !important;
}

.je-password-wrap > input {
    padding-right: 52px !important; /* Extra room */
    width: 100% !important;
}

.je-password-toggle {
    position: absolute !important;
    top: 50% !important;
    right: 4px !important;
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
    z-index: 10 !important;
    padding: 0 !important;
    margin: 0 !important;
    font-size: 18px !important;
    transition: all 0.2s ease !important;
}

.je-password-toggle:hover,
.je-password-toggle:focus-visible {
    color: #C6A43F !important;
    background: rgba(198, 164, 63, 0.12) !important;
}

.je-password-toggle.is-visible {
    color: #C6A43F !important;
}

.je-password-toggle i {
    font-size: 18px !important;
    pointer-events: none !important;
}

/* Browser password reveal suppression */
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

    function ensureIcon(btn, visible) {
        const icon = btn.querySelector('i.fa-eye, i.fa-eye-slash');
        if (icon) {
            icon.classList.toggle('fa-eye', !visible);
            icon.classList.toggle('fa-eye-slash', visible);
        } else {
            // Text fallback
            btn.innerHTML = visible ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
        }
    }

    function init() {
        document.querySelectorAll('.je-password-wrap').forEach(wrap => {
            if (wrap.dataset.jeBound === '1') return;
            wrap.dataset.jeBound = '1';

            const input = wrap.querySelector('input[type="password"]');
            const btn = wrap.querySelector('.je-password-toggle');
            if (!input || !btn) return;

            btn.setAttribute('type', 'button');

            function toggle() {
                const isVisible = input.type === 'text';
                input.type = isVisible ? 'password' : 'text';
                
                const caret = input.selectionStart;
                input.focus();
                if (caret) input.setSelectionRange(caret, caret);

                btn.classList.toggle('is-visible', !isVisible);
                btn.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
                ensureIcon(btn, !isVisible);
            }

            btn.addEventListener('click', toggle);
            // Initial state
            ensureIcon(btn, false);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
