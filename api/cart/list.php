<?php
/**
 * KINAS MARKETPLACE — List the buyer's cart
 * GET /api/cart/list.php
 *
 * Backs the fetch('/api/cart/list.php') call in
 * divisions/kinas-marketplace/cart.php. That page already existed and
 * expected this exact contract; the endpoint itself was simply never
 * built, so the cart page could never show anything.
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please login to view your cart']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT m.id AS listing_id, m.title, m.price, m.status, m.agent_id,
               u.name AS agent_name,
               (SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
        FROM cart_items ci
        JOIN marketplace_listings m ON m.id = ci.listing_id
        LEFT JOIN users u ON u.id = m.agent_id
        WHERE ci.buyer_id = ? AND ci.listing_type = 'marketplace'
        ORDER BY ci.created_at DESC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $items = [];
    $subtotal = 0.0;
    $hasUnavailable = false;

    foreach ($rows as $row) {
        $available = $row['status'] === 'active';
        if (!$available) {
            $hasUnavailable = true;
        } else {
            $subtotal += marketplaceBuyerPrice((float)$row['price']);
        }

        $items[] = [
            'listing_id'  => (int)$row['listing_id'],
            'title'       => $row['title'],
            'thumbnail'   => $row['thumbnail'] ?: null,
            'agent_name'  => $row['agent_name'] ?: 'Seller',
            'price_label' => formatPrice(marketplaceBuyerPrice((float)$row['price'])),
            'available'   => $available,
            'detail_url'  => '/divisions/kinas-marketplace/detail.php?id=' . (int)$row['listing_id'],
        ];
    }

    echo json_encode([
        'success'         => true,
        'items'           => $items,
        'count'           => count($items),
        'subtotal_label'  => formatPrice($subtotal),
        'has_unavailable' => $hasUnavailable,
    ]);

} catch (Exception $e) {
    error_log('cart/list.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load your cart. Please try again.']);
}
