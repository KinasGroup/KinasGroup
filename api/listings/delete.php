<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireLogin();
$listingId = $_GET['id'] ?? 0;
$listingType = $_GET['type'] ?? 'car';

try {
    $db = Database::getInstance()->getConnection();
    $tableMap = ['car' => 'car_listings', 'property' => 'property_listings', 'marketplace' => 'marketplace_listings'];
    $table = $tableMap[$listingType] ?? 'car_listings';
    
    $stmt = $db->prepare("DELETE FROM $table WHERE id = ? AND agent_id = ?");
    $stmt->execute([$listingId, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Listing deleted']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete listing']);
}