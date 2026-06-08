<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$listingId = $_GET['id'] ?? 0;
$listingType = $_GET['type'] ?? 'car';

try {
    $db = Database::getInstance()->getConnection();
    $tableMap = ['car' => 'car_listings', 'property' => 'property_listings', 'marketplace' => 'marketplace_listings'];
    $table = $tableMap[$listingType] ?? 'car_listings';
    
    $stmt = $db->prepare("SELECT l.*, u.name as agent_name, u.email as agent_email, u.verified as agent_verified FROM $table l JOIN users u ON l.agent_id = u.id WHERE l.id = ? AND l.status = 'active'");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch();
    
    if ($listing) {
        $stmt = $db->prepare("UPDATE $table SET views = views + 1 WHERE id = ?");
        $stmt->execute([$listingId]);
        
        $imgStmt = $db->prepare("SELECT * FROM listing_images WHERE listing_id = ? AND listing_type = ? ORDER BY sort_order");
        $imgStmt->execute([$listingId, $listingType]);
        $listing['images'] = $imgStmt->fetchAll();
        
        echo json_encode(['success' => true, 'listing' => $listing]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Listing not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch listing']);
}