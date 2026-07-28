<?php
/**
 * KINAS MARKETPLACE — Cart item count
 * GET /api/cart/count.php
 *
 * Backs the header's cart badge sync (templates/header.php), which runs
 * on every page load. Silent by design for logged-out visitors — the
 * badge just stays hidden rather than erroring.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';

if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => true, 'count' => 0]);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(ci.quantity), 0)
        FROM cart_items ci
        JOIN marketplace_listings m ON m.id = ci.listing_id
        WHERE ci.buyer_id = ? AND ci.listing_type = 'marketplace'
    ");
    $stmt->execute([$userId]);
    $count = (int)$stmt->fetchColumn();

    echo json_encode(['success' => true, 'count' => $count]);

} catch (Exception $e) {
    error_log('cart/count.php error: ' . $e->getMessage());
    // Fail quietly — this just syncs a header badge, not worth surfacing an error for.
    echo json_encode(['success' => true, 'count' => 0]);
}
