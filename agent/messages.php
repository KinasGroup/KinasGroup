<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (new messages, read state,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * KINAS GROUP - Agent Messages (Rebuilt Chat Interface)
 *
 * This page is now a lightweight shell. All conversation fetching,
 * rendering, sending, media (images + voice notes), read receipts and
 * polling are driven by assets/js/chat.js against the /api/messages/
 * endpoints (send.php, thread.php, conversations.php, mark-read.php).
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

// Quick lookup to determine the listing type (car, property, solar,
// marketplace) so the JS client knows exactly which table to reference
// when sending messages.
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

        <!--
          The Chat Root Node.
          chat.js reads these data attributes to initialize the UI,
          authenticate API calls, and open the correct thread on load.
        -->
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

<!-- Load the new Chat JS engine (deferred so it runs after the DOM is ready) -->
<script src="/assets/js/chat.js?v=<?= time() ?>" defer></script>

<?php include '../templates/footer.php'; ?>
