<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$listingId = $data['listing_id'] ?? 0;
$listingType = $data['listing_type'] ?? 'car';
$reason = $data['reason'] ?? '';

try {
    $db = Database::getInstance()->getConnection();
    $tableMap = ['car' => 'car_listings', 'property' => 'property_listings', 'marketplace' => 'marketplace_listings'];
    $table = $tableMap[$listingType] ?? 'car_listings';
    
    $stmt = $db->prepare("UPDATE $table SET status = 'flagged' WHERE id = ?");
    $stmt->execute([$listingId]);
    
    $userId = $_SESSION['user_id'] ?? null;
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'listing_flagged', ?, NOW())");
    $stmt->execute([$userId, "Listing #{$listingId} flagged: {$reason}"]);
    
    echo json_encode(['success' => true, 'message' => 'Listing flagged for review']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to flag listing']);
}