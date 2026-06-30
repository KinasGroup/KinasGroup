<?php
// KINAS GROUP footer — uses the JE luxury footer component
require_once __DIR__ . '/../includes/je-components.php';
je_render_footer('site');
?>

<!-- Shared transparent-header scroll effect (hero pages only) -->
<script src="/assets/js/header-scroll.js"></script>

<!-- NOTE: Mobile menu open/close logic lives ONLY in templates/header.php.
     Do not re-add a second listener on #mobileMenuBtn here — having two
     handlers toggle the same drawer causes it to open and instantly
     close again on every tap (looked like the button "did nothing"). -->

<!-- ============================================================
     CUSTOM CONFIRMATION MODAL - ALTERNATIVE IN CASE HEADER MISSES
     ============================================================ -->
<script>
// Ensure jeConfirm is available even if header didn't load it
(function() {
    'use strict';
    
    // Only initialize if not already defined
    if (typeof window.jeConfirm !== 'undefined') {
        return;
    }
    
    var modal = document.getElementById('jeConfirmModal');
    if (!modal) {
        console.warn('jeConfirmModal not found in DOM');
        return;
    }
    
    var overlay = modal.querySelector('.je-confirm-overlay');
    var titleEl = document.getElementById('jeConfirmTitle');
    var messageEl = document.getElementById('jeConfirmMessage');
    var confirmBtn = document.getElementById('jeConfirmConfirm');
    var cancelBtn = document.getElementById('jeConfirmCancel');
    var iconEl = modal.querySelector('.je-confirm-icon');

    var currentResolve = null;

    // Show confirmation modal
    window.jeConfirm = function(message, title, type) {
        return new Promise(function(resolve) {
            titleEl.textContent = title || 'Confirm Action';
            messageEl.textContent = message || 'Are you sure you want to proceed?';
            
            type = type || 'warning';
            iconEl.className = 'je-confirm-icon ' + type;
            
            var iconMap = {
                'warning': 'fa-exclamation-circle',
                'danger': 'fa-exclamation-triangle',
                'success': 'fa-check-circle',
                'info': 'fa-info-circle'
            };
            var iconClass = iconMap[type] || 'fa-exclamation-circle';
            iconEl.innerHTML = '<i class="fas ' + iconClass + '"></i>';
            
            if (type === 'danger') {
                confirmBtn.className = 'je-confirm-btn je-confirm-btn-confirm danger';
            } else {
                confirmBtn.className = 'je-confirm-btn je-confirm-btn-confirm';
            }
            
            modal.classList.add('is-visible');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            currentResolve = resolve;
        });
    };

    function hideConfirm() {
        modal.classList.remove('is-visible');
        modal.style.display = 'none';
        document.body.style.overflow = '';
        currentResolve = null;
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            if (currentResolve) currentResolve(true);
            hideConfirm();
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            if (currentResolve) currentResolve(false);
            hideConfirm();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            if (currentResolve) currentResolve(false);
            hideConfirm();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            if (currentResolve) currentResolve(false);
            hideConfirm();
        }
    });

    console.log('Confirmation modal initialized (footer fallback)');
})();
</script>

</body>
</html>
