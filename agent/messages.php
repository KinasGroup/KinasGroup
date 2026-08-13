<?php
// Authenticated, per-session content — never cache this page.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
/**
* KINAS GROUP - Agent Messages (Rebuilt Chat Interface)
*
* This page is now a lightweight shell. All conversation fetching,
* rendering, sending, media (images + voice notes), read receipts and
* polling are driven by assets/js/chat.js against the /api/messages/
* endpoints (send.php, thread.php, conversations.php, mark-read.php).
*
* WATCHDOG: if chat.js ever fails to build the UI, a visible diagnostic
* card is shown inside #chatRoot instead of a blank white area.
*/
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';
require_once '../includes/security.php';
require_once '../api/config/database.php';
// Redirect if not logged in as an agent
if (!SessionManager::isLoggedIn() || $_SESSION['user_role'] !== 'agent') {
header('Location: /auth/login.php');
exit;
}
$userId = SessionManager::getUserId();
// Capture URL parameters for deep-linking into a specific thread
$otherUserId = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$listingId   = isset($_GET['listing']) ? (int)$_GET['listing'] : 0;
$listingType = '';
if ($listingId > 0) {
try {
$db = Database::getInstance()->getConnection();
$tables = [
'car'         => 'car_listings',
'property'    => 'property_listings',
'solar'       => 'solar_listings',
'marketplace' => 'marketplace_listings'
];
foreach ($tables as $type => $table) {
$stmt = $db->prepare("SELECT id FROM {$table} WHERE id = ? LIMIT 1");
$stmt->execute([$listingId]);
if ($stmt->fetchColumn()) {
$listingType = $type;
break;
}
}
} catch (Throwable $e) {
// Ignore DB errors here; the JS/API will handle missing listings gracefully.
}
}
$pageTitle = 'Messages - Agent Dashboard';
include '../templates/header.php';
?>
<!-- Load the new Chat CSS (cache-busted for immediate deployment) -->
<link rel="stylesheet" href="/assets/css/chat.css?v=<?= time() ?>">
<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main" style="padding: 20px; background: #f4f5f7; min-height: calc(100vh - 100px); overflow: hidden;">
<div id="chatRoot"
data-csrf="<?= htmlspecialchars(Security::generateCSRFToken()) ?>"
data-user-id="<?= (int)$userId ?>"
data-role="agent"
data-open-other="<?= (int)$otherUserId ?>"
data-open-listing="<?= (int)$listingId ?>"
data-open-type="<?= htmlspecialchars($listingType) ?>">
</div>
</main>
</div>
<!-- WATCHDOG (part 1): catch script errors BEFORE chat.js runs and
surface them visibly instead of a blank page. -->
<script>
window.__kinasChatBoot = false;
window.addEventListener('error', function (e) {
    if (window.__kinasChatBoot) return;
    var root = document.getElementById('chatRoot');
    if (root && !root.querySelector('.chat-app')) {
        var file = (e.filename || '').split('/').pop() || 'script';
        root.innerHTML =
            '<div style="margin:24px auto;max-width:600px;background:#FEF2F2;border:1px solid #FECACA;color:#B71C1C;border-radius:12px;padding:18px 22px;font:13px/1.6 Inter,Arial,sans-serif;">' +
            '<strong style="font-size:14px;">Messenger could not start.</strong><br>' +
            escHtmlSafe(e.message || 'Unknown script error') + ' <small>(' + escHtmlSafe(file) + ':' + (e.lineno || '?') + ')</small><br>' +
            '<small style="color:#7f1d1d;">Please screenshot this and send it to support.</small></div>';
    }
}, true);
function escHtmlSafe(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
}
</script>
<!-- Load the new Chat JS engine (deferred so it runs after the DOM is ready) -->
<script src="/assets/js/chat.js?v=<?= time() ?>" defer></script>
<!-- WATCHDOG (part 2): after full load, if chat.js never built the UI
(missing file, 404, blocked), show a diagnostic card, never blank. -->
<script>
window.addEventListener('load', function () {
    setTimeout(function () {
        var root = document.getElementById('chatRoot');
        if (root && !root.querySelector('.chat-app') && !window.__kinasChatBoot) {
            root.innerHTML =
                '<div style="margin:24px auto;max-width:600px;background:#FFF8E1;border:1px solid #FFE082;color:#5d4a00;border-radius:12px;padding:18px 22px;font:13px/1.6 Inter,Arial,sans-serif;">' +
                '<strong style="font-size:14px;">Messenger script did not initialise.</strong><br>' +
                'The file /assets/js/chat.js did not run. Check the Network tab that it returns 200, and that no ad-blocker or plugin is blocking it, then reload.</div>';
        }
    }, 300);
});
</script>
<?php include '../templates/footer.php'; ?>
