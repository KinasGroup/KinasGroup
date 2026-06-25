<?php
/**
 * Delete an image from a listing
 * Accepts POST with image_id and csrf_token
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';A<?php
/**
 * Delete an image from a listing
 * Accepts POST with image_id and csrf_token
 * FIX: CSRF token is verified but NOT consumed, allowing multiple deletions without page refresh
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireAgent();

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

// ============================================================
// FIX: CSRF TOKEN VERIFICATION WITHOUT CONSUMPTION
// ============================================================
// Instead of using Security::verifyCSRFToken() which may consume the token,
// we verify it directly against the session token without clearing it.
// This allows multiple AJAX requests (e.g., deleting multiple images)
// to use the same token without needing to refresh the page.
// ============================================================
$token = $data['csrf_token'] ?? '';

if (empty($token)) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token missing']);
    exit;
}

// Verify token without consuming it
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Token is valid - proceed with deletion

$imageId = (int)($data['image_id'] ?? 0);

if (!$imageId) {
    header('Content-Type: application/json');
    http_response_code(422);
    echo json_encode(['error' => 'Invalid image ID']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get the image info to verify ownership
    $stmt = $db->prepare("
        SELECT li.id, li.url, li.listing_id, li.listing_type, 
               c.agent_id 
        FROM listing_images li
        JOIN car_listings c ON c.id = li.listing_id AND li.listing_type = 'car'
        WHERE li.id = ?
        UNION
        SELECT li.id, li.url, li.listing_id, li.listing_type,
               p.agent_id
        FROM listing_images li
        JOIN property_listings p ON p.id = li.listing_id AND li.listing_type = 'property'
        WHERE li.id = ?
        UNION
        SELECT li.id, li.url, li.listing_id, li.listing_type,
               s.agent_id
        FROM listing_images li
        JOIN solar_listings s ON s.id = li.listing_id AND li.listing_type = 'solar'
        WHERE li.id = ?
        UNION
        SELECT li.id, li.url, li.listing_id, li.listing_type,
               m.agent_id
        FROM listing_images li
        JOIN marketplace_listings m ON m.id = li.listing_id AND li.listing_type = 'marketplace'
        WHERE li.id = ?
    ");
    $stmt->execute([$imageId, $imageId, $imageId, $imageId]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$image) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'Image not found']);
        exit;
    }
    
    // Verify ownership
    if ((int)$image['agent_id'] !== (int)$_SESSION['user_id']) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    // Delete the image record
    $deleteStmt = $db->prepare("DELETE FROM listing_images WHERE id = ?");
    $deleteStmt->execute([$imageId]);
    
    // Delete the physical file (best effort)
    $filePath = __DIR__ . '/../../' . ltrim($image['url'], '/');
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
    
    Security::logActivity($_SESSION['user_id'], 'image_deleted', "Deleted image #$imageId from listing #{$image['listing_id']}");
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Image removed',
        // Optionally return the new token (if you want to refresh it)
        // 'csrf_token' => Security::generateCSRFToken()
    ]);
    
} catch (Exception $e) {
    error_log('delete-image error: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to remove image: ' . $e->getMessage()]);
}
?>

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireAgent();

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

// CSRF
$token = $data['csrf_token'] ?? '';
if (!Security::verifyCSRFToken($token)) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$imageId = (int)($data['image_id'] ?? 0);

if (!$imageId) {
    header('Content-Type: application/json');
    http_response_code(422);
    echo json_encode(['error' => 'Invalid image ID']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get the image info to verify ownership
    $stmt = $db->prepare("
        SELECT li.id, li.url, li.listing_id, li.listing_type, 
               c.agent_id 
        FROM listing_images li
        JOIN car_listings c ON c.id = li.listing_id AND li.listing_type = 'car'
        WHERE li.id = ?
        UNION
        SELECT li.id, li.url, li.listing_id, li.listing_type,
               p.agent_id
        FROM listing_images li
        JOIN property_listings p ON p.id = li.listing_id AND li.listing_type = 'property'
        WHERE li.id = ?
        UNION
        SELECT li.id, li.url, li.listing_id, li.listing_type,
               s.agent_id
        FROM listing_images li
        JOIN solar_listings s ON s.id = li.listing_id AND li.listing_type = 'solar'
        WHERE li.id = ?
        UNION
        SELECT li.id, li.url, li.listing_id, li.listing_type,
               m.agent_id
        FROM listing_images li
        JOIN marketplace_listings m ON m.id = li.listing_id AND li.listing_type = 'marketplace'
        WHERE li.id = ?
    ");
    $stmt->execute([$imageId, $imageId, $imageId, $imageId]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$image) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'Image not found']);
        exit;
    }
    
    // Verify ownership
    if ((int)$image['agent_id'] !== (int)$_SESSION['user_id']) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    // Delete the image record
    $deleteStmt = $db->prepare("DELETE FROM listing_images WHERE id = ?");
    $deleteStmt->execute([$imageId]);
    
    // Delete the physical file (best effort)
    $filePath = __DIR__ . '/../../' . ltrim($image['url'], '/');
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
    
    Security::logActivity($_SESSION['user_id'], 'image_deleted', "Deleted image #$imageId from listing #{$image['listing_id']}");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Image removed']);
    
} catch (Exception $e) {
    error_log('delete-image error: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to remove image: ' . $e->getMessage()]);
}
