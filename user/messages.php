<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';
require_once '../includes/security.php';
require_once '../api/config/database.php';

if (!SessionManager::isLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}

// ============================================================
// KINAS BUILD CACHE BUSTER
// ============================================================
// Bump this version on every new frontend build/release.
// Example:
// 2026.08.15.01
// 2026.08.15.02
// 2026.08.16.01
// ============================================================
$kinasBuildVersion = '2026.08.15.04';

$chatCssFile = __DIR__ . '/../assets/css/chat.css';
$chatJsFile  = __DIR__ . '/../assets/js/chat.js';

$chatCssMtime = @filemtime($chatCssFile);
$chatJsMtime  = @filemtime($chatJsFile);

$chatCssVersion = ($chatCssMtime ? $chatCssMtime : $kinasBuildVersion) . '.' . $kinasBuildVersion;
$chatJsVersion  = ($chatJsMtime ? $chatJsMtime : $kinasBuildVersion) . '.' . $kinasBuildVersion;

$userId = SessionManager::getUserId();

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
            'marketplace' => 'marketplace_listings',
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
        // Keep empty listing type.
    }
}

$pageTitle = 'Messages - My Dashboard';

include '../templates/header.php';
?>

<link rel="stylesheet" href="/assets/css/chat.css?v=<?= htmlspecialchars($chatCssVersion, ENT_QUOTES, 'UTF-8') ?>">

<div class="je-dash-shell">
    <?php include __DIR__ . '/../includes/partials/user-sidebar.php'; ?>

    <main class="je-dash-main" style="padding: 20px; background: #f4f5f7; min-height: calc(100vh - 100px); overflow: hidden;">
        <div id="chatRoot"
             data-csrf="<?= htmlspecialchars(Security::generateCSRFToken()) ?>"
             data-user-id="<?= (int)$userId ?>"
             data-role="user"
             data-open-other="<?= (int)$otherUserId ?>"
             data-open-listing="<?= (int)$listingId ?>"
             data-open-type="<?= htmlspecialchars($listingType) ?>">
        </div>
    </main>
</div>

<script>
window.__kinasChatBoot = false;
window.__kinasChatError = null;

function escHtmlSafe(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[m];
    });
}

window.addEventListener('error', function (e) {
    if (window.__kinasChatBoot) return;

    window.__kinasChatError = (e.message || 'Unknown script error') + ' @ ' + ((e.filename || '').split('/').pop() || 'script') + ':' + (e.lineno || '?');

    var root = document.getElementById('chatRoot');

    if (root && !root.querySelector('.chat-app')) {
        root.innerHTML = '<div style="margin:24px auto;max-width:600px;background:#FEF2F2;border:1px solid #FECACA;color:#B71C1C;border-radius:12px;padding:18px 22px;font:13px/1.6 Inter,Arial,sans-serif;"><strong>Messenger hit a script error.</strong><br>' + escHtmlSafe(window.__kinasChatError) + '<br><small>Send this text to support — the chat.js copy on the server is likely corrupted.</small></div>';
    }
}, true);
</script>

<script src="/assets/js/chat.js?v=<?= htmlspecialchars($chatJsVersion, ENT_QUOTES, 'UTF-8') ?>" defer></script>

<script>
window.addEventListener('load', function () {
    setTimeout(function () {
        var root = document.getElementById('chatRoot');

        if (!root || root.querySelector('.chat-app')) return;
        if (window.__kinasChatError) return;

        fetch('/assets/js/chat.js?v=<?= htmlspecialchars($chatJsVersion, ENT_QUOTES, 'UTF-8') ?>', {
            method: 'HEAD'
        }).then(function (r) {
            var hint = r.status === 200
                ? (window.__kinasChatJsLoaded
                    ? 'The file loaded but stopped early — screenshot this card for support.'
                    : 'The file returned 200 but never parsed — the copy on the server is truncated/corrupted. Re-upload the COMPLETE assets/js/chat.js exactly as provided.')
                : 'The file is missing or blocked on the server (HTTP ' + r.status + '). Upload assets/js/chat.js.';

            root.innerHTML = '<div style="margin:24px auto;max-width:600px;background:#FFF8E1;border:1px solid #FFE082;color:#5d4a00;border-radius:12px;padding:18px 22px;font:13px/1.6 Inter,Arial,sans-serif;"><strong>Messenger script did not initialise.</strong><br>chat.js HTTP status: ' + r.status + '<br>' + escHtmlSafe(hint) + '</div>';
        }).catch(function () {
            root.innerHTML = '<div style="margin:24px auto;max-width:600px;background:#FFF8E1;border:1px solid #FFE082;color:#5d4a00;border-radius:12px;padding:18px 22px;font:13px/1.6 Inter,Arial,sans-serif;"><strong>Messenger script did not initialise.</strong><br>Could not request /assets/js/chat.js (network error).</div>';
        });
    }, 300);
});
</script>

<?php include '../templates/footer.php'; ?>
