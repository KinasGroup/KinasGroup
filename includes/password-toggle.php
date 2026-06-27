<?php
/**
 * KINAS GROUP — Password Visibility Toggle
 */
?>
<style>
.je-password-wrap {
    position: relative !important;
    display: block !important;
}

.je-password-wrap input {
    padding-right: 38px !important;
}

.je-password-toggle {
    position: absolute !important;
    top: 50% !important;
    right: 10px !important;
    transform: translateY(-50%) !important;
    width: auto !important;
    height: auto !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: none !important;
    border: none !important;
    border-radius: 0 !important;
    cursor: pointer !important;
    color: #888 !important;
    z-index: 100 !important;
    font-size: 15px !important;
    box-shadow: none !important;
    padding: 4px !important;
}

.je-password-toggle:hover {
    color: #C6A43F !important;
}
</style>

<script>
(function() {
    if (window.passwordToggleDone) return;
    window.passwordToggleDone = true;

    function init() {
        document.querySelectorAll('.je-password-wrap').forEach(wrap => {
            if (wrap.dataset.init) return;
            wrap.dataset.init = '1';

            const input = wrap.querySelector('input[type="password"]');
            const btn = wrap.querySelector('.je-password-toggle');
            if (!input || !btn) return;

            btn.type = 'button';

            if (!btn.querySelector('i')) {
                btn.innerHTML = '<i class="fas fa-eye"></i>';
            }

            btn.addEventListener('click', () => {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';

                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye', isPassword);
                    icon.classList.toggle('fa-eye-slash', !isPassword);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
