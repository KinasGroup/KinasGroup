<?php
/**
 * Delete a listing owned by the current user.
 * Accepts POST (form) with csrf_token, or DELETE (XHR) with id/type in query string.
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

SessionManager::requireLogin();

$method = $_SERVER['REQUEST_METHOD'];

// Accept POST (form submit) and DELETE (XHR)
if (!in_array($method, ['POST', 'DELETE'], true)) {
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    } else {
        http_response_code(405);
        echo 'Method not allowed';
    }
    exit;
}

// CSRF check for form POSTs
if ($method === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Security::verifyCSRFToken($token)) {
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Please refresh the page and try again.']);
        } else {
            $_SESSION['flash_error'] = 'Please refresh the page and try again.';
            header('Location: /agent/listings.php');
        }
        exit;
    }
}

// Read inputs
$listingId   = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$listingType = $_POST['type'] ?? $_GET['type'] ?? 'car';

$tableMap = [
    'car'         => 'car_listings',
    'property'    => 'property_listings',
    'solar'       => 'solar_listings',
    'marketplace' => 'marketplace_listings',
];
$table = $tableMap[$listingType] ?? null;

if (!$listingId || !$table) {
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['error' => 'Invalid listing reference.']);
    } else {
        $_SESSION['flash_error'] = 'Invalid listing reference.';
        header('Location: /agent/listings.php');
    }
    exit;
}

$redirectAfter = $_POST['redirect'] ?? '/agent/listings.php';
// Whitelist redirect to avoid open-redirect
if (!preg_match('#^/[a-zA-Z0-9_\-/]*(\.php)?(\?.*)?$#', $redirectAfter)) {
    $redirectAfter = '/agent/listings.php';
}

try {
    $db = Database::getInstance()->getConnection();

    // Verify ownership before delete
    $own = $db->prepare("SELECT id FROM $table WHERE id = ? AND agent_id = ?");
    $own->execute([$listingId, $_SESSION['user_id']]);
    if (!$own->fetch()) {
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'Listing not found.']);
        } else {
            $_SESSION['flash_error'] = 'Listing not found.';
            header('Location: ' . $redirectAfter);
        }
        exit;
    }

    // Remove images first (best-effort)
    try {
        $db->prepare("DELETE FROM listing_images WHERE listing_id = ? AND listing_type = ?")
           ->execute([$listingId, $listingType]);
    } catch (Exception $ignored) { /* table may not be reachable in all setups */ }

    // Remove any cart/favorites rows pointing at this listing — otherwise
    // they become orphaned: the cart badge (a bare COUNT) keeps counting
    // them, but the actual cart/saved-listings pages (which JOIN to the
    // listing table) silently exclude them, showing "1 item" in the
    // header and "empty" on the page itself.
    try {
        $db->prepare("DELETE FROM cart_items WHERE listing_id = ? AND listing_type = ?")
           ->execute([$listingId, $listingType]);
        $db->prepare("DELETE FROM favorites WHERE listing_id = ? AND listing_type = ?")
           ->execute([$listingId, $listingType]);
    } catch (Exception $ignored) { /* tables may not exist on older deployments mid-migration */ }

    // Delete the listing
    $db->prepare("DELETE FROM $table WHERE id = ? AND agent_id = ?")
       ->execute([$listingId, $_SESSION['user_id']]);

    // 🔧 FIX: Clear featured flag for this listing
    try {
        // Reset featured flag in the listing table
        $resetFeatured = $db->prepare("UPDATE $table SET featured = 0 WHERE id = ?");
        $resetFeatured->execute([$listingId]);
        
        // Clear from featured cache table if it exists
        $clearCache = $db->prepare("DELETE FROM featured_cache WHERE listing_id = ?");
        $clearCache->execute([$listingId]);
    } catch (Exception $e) {
        // Tables might not exist, continue
    }

    Security::logActivity($_SESSION['user_id'], 'listing_deleted', "Deleted $listingType listing $listingId");

    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Listing deleted']);
    } else {
        $_SESSION['flash_success'] = 'Listing deleted successfully.';
        header('Location: ' . $redirectAfter);
        exit;
    }
} catch (Exception $e) {
    error_log('listing delete error: ' . $e->getMessage());
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete listing']);
    } else {
        $_SESSION['flash_error'] = 'Failed to delete listing. Please try again.';
        header('Location: ' . $redirectAfter);
        exit;
    }
}
?>
