/**
 * KINAS GROUP — Newsletter subscribe handler
 *
 * Wires up every ".kinas-newsletter-form" on the page (footer widget,
 * blog index, blog post) to POST /api/newsletter/subscribe.php and show
 * an in-page success/error banner. Falls back gracefully if kinasToast()
 * (from includes/kinas-ui.php) isn't loaded on a given page.
 */
(function () {
    function notify(message, type) {
        if (typeof window.kinasToast === 'function') {
            window.kinasToast(message, type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'));
            return;
        }
        // Minimal responsive fallback banner if kinas-ui.js isn't present.
        var existing = document.getElementById('kinasNewsletterFallbackBanner');
        if (existing) existing.remove();

        var colors = type === 'error'
            ? { bg: '#FEF2F2', border: '#DC3545', text: '#721C24' }
            : { bg: '#E8F5E9', border: '#28A745', text: '#1B5E20' };

        var banner = document.createElement('div');
        banner.id = 'kinasNewsletterFallbackBanner';
        banner.setAttribute('role', 'status');
        banner.style.cssText =
            'position:fixed; top:16px; right:16px; left:16px; z-index:100000;' +
            'max-width:420px; margin-left:auto;' +
            'background:' + colors.bg + '; color:' + colors.text + ';' +
            'border-left:4px solid ' + colors.border + ';' +
            'padding:14px 20px; border-radius:6px; font-family:Inter,Arial,sans-serif;' +
            'font-size:14px; box-shadow:0 8px 30px rgba(0,0,0,0.15);';
        banner.textContent = message;
        document.body.appendChild(banner);

        setTimeout(function () {
            banner.style.transition = 'opacity .3s ease';
            banner.style.opacity = '0';
            setTimeout(function () { banner.remove(); }, 300);
        }, 4500);
    }

    function handleSubmit(form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var input = form.querySelector('input[type="email"], input[name="email"]');
            var email = input ? input.value.trim() : '';
            var source = form.getAttribute('data-source') || 'unknown';
            var button = form.querySelector('button[type="submit"], button');
            var originalLabel = button ? button.innerHTML : null;

            if (!email) {
                notify('Please enter your email address.', 'warning');
                if (input) input.focus();
                return;
            }

            if (button) {
                button.disabled = true;
                button.innerHTML = 'Subscribing&hellip;';
            }

            fetch('/api/newsletter/subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email, source: source })
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    if (result.ok && result.data && result.data.success) {
                        notify(result.data.message || 'Thank you for subscribing!', 'success');
                        form.reset();
                    } else {
                        notify((result.data && result.data.error) || 'Something went wrong. Please try again.', 'error');
                    }
                })
                .catch(function () {
                    notify('Network error — please try again.', 'error');
                })
                .finally(function () {
                    if (button) {
                        button.disabled = false;
                        button.innerHTML = originalLabel;
                    }
                });
        });
    }

    function init() {
        document.querySelectorAll('.kinas-newsletter-form').forEach(handleSubmit);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
