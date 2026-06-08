<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireLogin();
$data = json_decode(file_get_contents('php://input'), true);
$listingId = $_GET['id'] ?? 0;
$listingType = $data['listing_type'] ?? 'car';

try {
    $db = Database::getInstance()->getConnection();
    $tableMap = ['car' => 'car_listings', 'property' => 'property_listings', 'marketplace' => 'marketplace_listings'];
    $table = $tableMap[$listingType] ?? 'car_listings';
    
    $stmt = $db->prepare("UPDATE $table SET title = ?, price = ?, description = ?, updated_at = NOW() WHERE id = ? AND agent_id = ?");
    $stmt->execute([$data['title'], $data['price'], $data['description'] ?? '', $listingId, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Listing updated']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update listing']);
}