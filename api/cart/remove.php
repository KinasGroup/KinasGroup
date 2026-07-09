<?php
/**
 * KINAS MARKETPLACE — Remove a listing from the buyer's cart
 * POST /api/cart/remove.php   { listing_id }
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
    echo json_encode(['error' => 'Please login to manage your cart']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

$data = $_POST;
if (empty($data)) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
}

$listingId = (int)($data['listing_id'] ?? 0);
if (!$listingId) {
    http_response_code(422);
    echo json_encode(['error' => 'Listing ID is required']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("DELETE FROM cart_items WHERE buyer_id = ? AND listing_id = ? AND listing_type = 'marketplace'");
    $stmt->execute([$userId, $listingId]);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM cart_items WHERE buyer_id = ?");
    $countStmt->execute([$userId]);
    $count = (int)$countStmt->fetchColumn();

    echo json_encode(['success' => true, 'cart_count' => $count]);

} catch (Exception $e) {
    error_log('cart/remove.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not remove item. Please try again.']);
}
