<?php
/**
 * KINAS MARKETPLACE — Add a listing to the buyer's cart
 * POST /api/cart/add.php   { listing_id }
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
    echo json_encode(['error' => 'Please login to add items to your cart']);
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

    $stmt = $db->prepare("SELECT id, agent_id, status FROM marketplace_listings WHERE id = ?");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch();

    if (!$listing) {
        http_response_code(404);
        echo json_encode(['error' => 'Listing not found']);
        exit;
    }
    if ($listing['status'] !== 'active') {
        http_response_code(409);
        echo json_encode(['error' => 'This item is no longer available']);
        exit;
    }
    if ((int)$listing['agent_id'] === $userId) {
        http_response_code(422);
        echo json_encode(['error' => "You can't add your own listing to your cart"]);
        exit;
    }

    $db->prepare("INSERT IGNORE INTO cart_items (buyer_id, listing_id, listing_type) VALUES (?, ?, 'marketplace')")
       ->execute([$userId, $listingId]);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM cart_items WHERE buyer_id = ?");
    $countStmt->execute([$userId]);
    $count = (int)$countStmt->fetchColumn();

    echo json_encode(['success' => true, 'cart_count' => $count]);

} catch (Exception $e) {
    error_log('cart/add.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong. Please try again.']);
}
