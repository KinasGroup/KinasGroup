<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please login to save favorites']);
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

// Get POST data (supports both FormData and JSON)
$data = $_POST;
if (empty($data)) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: [];
}

$listingId = (int)($data['listing_id'] ?? 0);
$listingType = trim($data['listing_type'] ?? 'car');

if (!$listingId) {
    http_response_code(422);
    echo json_encode(['error' => 'Listing ID is required']);
    exit;
}

$allowedTypes = ['car', 'property', 'solar', 'marketplace'];
if (!in_array($listingType, $allowedTypes)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid listing type']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if already favorited
    $stmt = $db->prepare("SELECT id FROM favorites WHERE user_id = ? AND listing_id = ? AND listing_type = ?");
    $stmt->execute([$userId, $listingId, $listingType]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Remove from favorites
        $stmt = $db->prepare("DELETE FROM favorites WHERE user_id = ? AND listing_id = ? AND listing_type = ?");
        $stmt->execute([$userId, $listingId, $listingType]);
        echo json_encode(['success' => true, 'action' => 'removed']);
    } else {
        // Add to favorites
        $stmt = $db->prepare("INSERT INTO favorites (user_id, listing_id, listing_type, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$userId, $listingId, $listingType]);
        echo json_encode(['success' => true, 'action' => 'added']);
    }
    
} catch (PDOException $e) {
    if ($e->getCode() == '42S02') {
        echo json_encode(['error' => 'Favorites table not found. Please run database migration.']);
    } else {
        error_log('Favorite error: ' . $e->getMessage());
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    error_log('Favorite error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to update favorites: ' . $e->getMessage()]);
}
?>
