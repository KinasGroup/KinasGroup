<?php
// KINAS GROUP footer — uses the JE luxury footer component
//
// AMENDED FOR WHATSAPP CUSTOMER COMMUNICATION (LOGGED-IN ONLY):
// - Loads WhatsApp configuration safely.
// - Loads WhatsApp CSS if available.
// - Adds a global floating WhatsApp contact button  (LOGGED-IN ONLY, FA ICON).
// - Loads whatsapp-button.js for product/listing WhatsApp enquiry buttons (LOGGED-IN ONLY).
require_once __DIR__ . '/../api/config/constants.php';
require_once __DIR__ . '/../includes/je-components.php';
require_once __DIR__ . '/../includes/kinas-ui.php';
// Ensure a session is available so we can gate WhatsApp by login state.
if (session_status() === PHP_SESSION_NONE) {
@session_start();
}
$kinasWhatsAppLoggedIn = isset($_SESSION['user_id']);
// ============================================================
// WHATSAPP CONFIGURATION
// ============================================================
$kinasWhatsAppNumber = defined('WHATSAPP_NUMBER')
? preg_replace('/\D+/', '', (string)WHATSAPP_NUMBER)
: '';
$kinasWhatsAppGeneralMessage = 'Hello KINAS GROUP, I would like to make an enquiry.';
$kinasWhatsAppLink = ($kinasWhatsAppNumber !== '' && $kinasWhatsAppLoggedIn)
? 'https://wa.me/' . $kinasWhatsAppNumber . '?text=' . rawurlencode($kinasWhatsAppGeneralMessage)
: '';
$kinasWhatsAppCssPath = __DIR__ . '/../assets/css/whatsapp-button.css';
$kinasWhatsAppJsPath = __DIR__ . '/../assets/js/whatsapp-button.js';
je_render_footer('site');
?>
<?php if (file_exists($kinasWhatsAppCssPath)): ?>
<link rel="stylesheet" href="/assets/css/whatsapp-button.css?v=<?= (int)(@filemtime($kinasWhatsAppCssPath) ?: time()) ?>">
<?php else: ?>
<style>
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
.kinas-whatsapp-float .kinas-wa-icon {
font-size: 28px;
line-height: 1;
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
.kinas-whatsapp-float .kinas-wa-icon { font-size: 25px; }
.kinas-whatsapp-tooltip {
display: none;
}
}
</style>
<?php endif; ?>
<?php if ($kinasWhatsAppLink !== '' && $kinasWhatsAppLoggedIn): ?>
<a href="<?= htmlspecialchars($kinasWhatsAppLink, ENT_QUOTES, 'UTF-8') ?>"
class="kinas-whatsapp-float"
target="_blank"
rel="noopener noreferrer"
aria-label="Chat with KINAS GROUP on WhatsApp"
data-kinas-whatsapp-global="1">
<i class="fab fa-whatsapp kinas-wa-icon" aria-hidden="true"></i>
<span class="kinas-whatsapp-tooltip">Chat with us on WhatsApp</span>
</a>
<?php endif; ?>
<?php if ($kinasWhatsAppNumber !== '' && $kinasWhatsAppLoggedIn): ?>
<script>
window.SITE_CONSTANTS = window.SITE_CONSTANTS || {};
window.SITE_CONSTANTS.WHATSAPP_NUMBER = <?= json_encode(
$kinasWhatsAppNumber,
JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;
window.SITE_CONSTANTS.WHATSAPP_LOGGED_IN = true;
</script>
<?php if (file_exists($kinasWhatsAppJsPath)): ?>
<script src="/assets/js/whatsapp-button.js?v=<?= (int)(@filemtime($kinasWhatsAppJsPath) ?: time()) ?>"></script>
<?php endif; ?>
<?php endif; ?>
<script src="/assets/js/header-scroll.js"></script>
<script src="/assets/js/newsletter.js"></script>
</body>
</html>
