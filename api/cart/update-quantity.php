<?php
/**
 * KINAS MARKETPLACE — Update a cart item's quantity
 * POST /api/cart/update-quantity.php   { listing_id, quantity }
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please login to update your cart']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

$data = $_POST;
if (empty($data)) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
}

$listingId = (int)($data['listing_id'] ?? 0);
$quantity = (int)($data['quantity'] ?? 0);

if (!$listingId) {
    http_response_code(422);
    echo json_encode(['error' => 'Listing ID is required']);
    exit;
}

// Quantity 0 or below removes the item entirely — same semantics as
// most cart UIs (dragging the stepper down to 0 = remove).
$maxQtyPerItem = 20;
$quantity = max(0, min($quantity, $maxQtyPerItem));

try {
    $db = Database::getInstance()->getConnection();

    if ($quantity === 0) {
        $db->prepare("DELETE FROM cart_items WHERE buyer_id = ? AND listing_id = ? AND listing_type = 'marketplace'")
           ->execute([$userId, $listingId]);
    } else {
        $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE buyer_id = ? AND listing_id = ? AND listing_type = 'marketplace'");
        $stmt->execute([$quantity, $userId, $listingId]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found in your cart']);
            exit;
        }
    }

    echo json_encode(['success' => true, 'quantity' => $quantity]);

} catch (Exception $e) {
    error_log('cart/update-quantity.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong. Please try again.']);
}
