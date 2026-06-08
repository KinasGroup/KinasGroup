<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireLogin();

$data = json_decode(file_get_contents('php://input'), true);
$listingId = $data['listing_id'] ?? 0;
$listingType = $data['listing_type'] ?? 'car';
$userId = $_SESSION['user_id'];

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT id FROM favorites WHERE user_id = ? AND listing_id = ? AND listing_type = ?");
    $stmt->execute([$userId, $listingId, $listingType]);
    
    if ($stmt->fetch()) {
        $stmt = $db->prepare("DELETE FROM favorites WHERE user_id = ? AND listing_id = ? AND listing_type = ?");
        $stmt->execute([$userId, $listingId, $listingType]);
        echo json_encode(['success' => true, 'action' => 'removed']);
    } else {
        $stmt = $db->prepare("INSERT INTO favorites (user_id, listing_id, listing_type, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$userId, $listingId, $listingType]);
        echo json_encode(['success' => true, 'action' => 'added']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update favorites']);
}