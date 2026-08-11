<?php
// KINAS GROUP footer — uses the JE luxury footer component
//
// AMENDED FOR WHATSAPP CUSTOMER COMMUNICATION:
// - Loads WhatsApp configuration safely.
// - Loads WhatsApp CSS if available.
// - Adds a global floating WhatsApp contact button.
// - Loads whatsapp-button.js for product/listing WhatsApp enquiry buttons.

require_once __DIR__ . '/../api/config/constants.php';
require_once __DIR__ . '/../includes/je-components.php';

// Site-wide toast/confirm modal system (kinasToast / kinasConfirm) — this
// replaces native browser alert()/confirm()/prompt() popups everywhere.
// Pages that also require_once this directly are unaffected: require_once
// resolves by realpath, so it is never loaded twice.
require_once __DIR__ . '/../includes/kinas-ui.php';

// ============================================================
// WHATSAPP CONFIGURATION
// ============================================================

$kinasWhatsAppNumber = defined('WHATSAPP_NUMBER')
    ? preg_replace('/\D+/', '', (string)WHATSAPP_NUMBER)
    : '';

$kinasWhatsAppGeneralMessage = 'Hello KINAS GROUP, I would like to make an enquiry.';

$kinasWhatsAppLink = $kinasWhatsAppNumber !== ''
    ? 'https://wa.me/' . $kinasWhatsAppNumber . '?text=' . rawurlencode($kinasWhatsAppGeneralMessage)
    : '';

$kinasWhatsAppCssPath = __DIR__ . '/../assets/css/whatsapp-button.css';
$kinasWhatsAppJsPath = __DIR__ . '/../assets/js/whatsapp-button.js';

je_render_footer('site');
?>

<!-- ============================================================ -->
<!-- WHATSAPP GLOBAL STYLES -->
<!-- ============================================================ -->
<?php if (file_exists($kinasWhatsAppCssPath)): ?>
<link rel="stylesheet" href="/assets/css/whatsapp-button.css?v=<?= (int)(@filemtime($kinasWhatsAppCssPath) ?: time()) ?>">
<?php else: ?>
<style>
/* Fallback WhatsApp floating button styles */
.kinas-whatsapp-float {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 99999;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #25D366;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
    transition: background 0.2s ease, transform 0.2s ease;
}

.kinas-whatsapp-float:hover {
    background: #128C7E;
    transform: translateY(-2px);
}

.kinas-whatsapp-float svg {
    width: 28px;
    height: 28px;
    fill: currentColor;
    display: block;
}

.kinas-whatsapp-tooltip {
    position: absolute;
    right: 66px;
    top: 50%;
    transform: translateY(-50%);
    background: #0A0A0A;
    color: #ffffff;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
}

.kinas-whatsapp-float:hover .kinas-whatsapp-tooltip {
    opacity: 1;
}

@media (max-width: 768px) {
    .kinas-whatsapp-float {
        bottom: 18px;
        right: 18px;
        width: 52px;
        height: 52px;
    }

    .kinas-whatsapp-tooltip {
        display: none;
    }
}
</style>
<?php endif; ?>

<!-- ============================================================ -->
<!-- WHATSAPP GLOBAL FLOATING BUTTON -->
<!-- ============================================================ -->
<?php if ($kinasWhatsAppLink !== ''): ?>
<a href="<?= htmlspecialchars($kinasWhatsAppLink, ENT_QUOTES, 'UTF-8') ?>"
   class="kinas-whatsapp-float"
   target="_blank"
   rel="noopener noreferrer"
   aria-label="Chat with KINAS GROUP on WhatsApp"
   data-kinas-whatsapp-global="1">
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
    <span class="kinas-whatsapp-tooltip">Chat with us on WhatsApp</span>
</a>
<?php endif; ?>

<!-- ============================================================ -->
<!-- WHATSAPP SITE CONSTANTS + SCRIPT -->
<!-- ============================================================ -->
<script>
window.SITE_CONSTANTS = window.SITE_CONSTANTS || {};
window.SITE_CONSTANTS.WHATSAPP_NUMBER = <?= json_encode(
    $kinasWhatsAppNumber,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;
</script>

<?php if (file_exists($kinasWhatsAppJsPath)): ?>
<script src="/assets/js/whatsapp-button.js?v=<?= (int)(@filemtime($kinasWhatsAppJsPath) ?: time()) ?>"></script>
<?php endif; ?>

<!-- Shared transparent-header scroll effect (hero pages only) -->
<script src="/assets/js/header-scroll.js"></script>

<!-- Newsletter subscribe forms (footer widget + blog pages) -->
<script src="/assets/js/newsletter.js"></script>

<!-- NOTE: Mobile menu open/close logic lives ONLY in templates/header.php.
Do not re-add a second listener on #mobileMenuBtn here — having two
handlers toggle the same drawer causes it to open and instantly
close again on every tap (looked like the button "did nothing"). -->
</body>
</html>
