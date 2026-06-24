<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

// Get user ID
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    // Try FormData
    $listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
    $listingType = isset($_POST['listing_type']) ? trim($_POST['listing_type']) : 'car';
} else {
    $listingId = isset($data['listing_id']) ? (int)$data['listing_id'] : 0;
    $listingType = isset($data['listing_type']) ? trim($data['listing_type']) : 'car';
}

// Validate
if (!$listingId) {
    http_response_code(422);
    echo json_encode(['error' => 'Listing ID is required']);
    exit;
}

// Validate listing type
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
    error_log('Favorite error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Favorite error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update favorites: ' . $e->getMessage()]);
}
?>
