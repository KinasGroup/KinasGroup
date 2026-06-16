<?php
/**
 * KINAS GROUP — Password Visibility Toggle (FINAL FIX)
 */
?>
<style>
.je-password-wrap {
    position: relative !important;
    display: block !important;
}

.je-password-wrap input {
    padding-right: 58px !important;
}

.je-password-toggle {
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
    color: #333 !important;
    z-index: 100 !important;
    font-size: 20px !important;
    box-shadow: 0 2px 5px rgba(0,0,0,0.15) !important;
}

.je-password-toggle:hover {
    color: #C6A43F !important;
    border-color: #C6A43F !important;
}

/* Fallback text if icon fails */
.je-password-toggle::after {
    content: "👁" !important;
    font-size: 18px !important;
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

            btn.addEventListener('click', () => {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                
                // Try Font Awesome first
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye', isPassword);
                    icon.classList.toggle('fa-eye-slash', !isPassword);
                } else {
                    // Emoji fallback
                    btn.style.fontSize = isPassword ? '22px' : '18px';
                }
            });

            // Initial icon (try FA or emoji)
            if (!btn.querySelector('i')) {
                btn.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
