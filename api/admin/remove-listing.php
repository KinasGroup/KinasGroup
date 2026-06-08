<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireAdmin();

$data = json_decode(file_get_contents('php://input'), true);
$listingId = $data['listing_id'] ?? 0;
$listingType = $data['listing_type'] ?? 'car';

try {
    $db = Database::getInstance()->getConnection();
    $tableMap = ['car' => 'car_listings', 'property' => 'property_listings', 'marketplace' => 'marketplace_listings'];
    $table = $tableMap[$listingType] ?? 'car_listings';
    
    $stmt = $db->prepare("UPDATE $table SET status = 'removed' WHERE id = ?");
    $stmt->execute([$listingId]);
    
    echo json_encode(['success' => true, 'message' => 'Listing removed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Removal failed']);
}