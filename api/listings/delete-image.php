<?php
/**
 * API: Delete Single Image from Listing
 * POST /api/listings/delete-image.php
 * body: { image_id, csrf_token }
 */

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in and is an agent
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized. Please log in as an agent.']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate CSRF token
if (!Security::verifyCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$imageId = (int)($input['image_id'] ?? 0);
$agentId = (int)$_SESSION['user_id'];

if (empty($imageId)) {
    http_response_code(422);
    echo json_encode(['error' => 'Image ID is required']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Get the image details to find the listing_id and listing_type
    $stmt = $db->prepare("SELECT id, listing_id, listing_type, url FROM listing_images WHERE id = ?");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        http_response_code(404);
        echo json_encode(['error' => 'Image not found']);
        exit;
    }

    // Verify the listing belongs to this agent
    $tableMap = [
        'car' => 'car_listings',
        'property' => 'property_listings',
        'solar' => 'solar_listings',
        'marketplace' => 'marketplace_listings'
    ];

    $listingType = $image['listing_type'];
    if (!isset($tableMap[$listingType])) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid listing type']);
        exit;
    }

    $tableName = $tableMap[$listingType];
    $stmt = $db->prepare("SELECT agent_id FROM $tableName WHERE id = ?");
    $stmt->execute([$image['listing_id']]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing || (int)$listing['agent_id'] !== $agentId) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to delete this image']);
        exit;
    }

    // Delete the physical file from server
    $fileDeleted = false;
    $imageUrl = $image['url'] ?? '';
    if (!empty($imageUrl)) {
        // Try different path variations
        $pathsToTry = [
            $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($imageUrl, '/'),
            $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . basename($imageUrl),
            $_SERVER['DOCUMENT_ROOT'] . '/assets/uploads/' . basename($imageUrl),
            __DIR__ . '/../../uploads/' . basename($imageUrl),
        ];
        
        foreach ($pathsToTry as $filePath) {
            if (file_exists($filePath) && is_file($filePath)) {
                $fileDeleted = unlink($filePath);
                break;
            }
        }
        
        // Also check for thumbnails
        $pathinfo = pathinfo($imageUrl);
        $thumbPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($pathinfo['dirname'], '/') . '/' . $pathinfo['filename'] . '_thumb.' . $pathinfo['extension'];
        if (file_exists($thumbPath) && is_file($thumbPath)) {
            unlink($thumbPath);
        }
    }

    // Delete from database
    $stmt = $db->prepare("DELETE FROM listing_images WHERE id = ?");
    $result = $stmt->execute([$imageId]);

    if ($result) {
        Security::logActivity($agentId, 'image_deleted', "Deleted image ID $imageId from {$listingType} listing {$image['listing_id']}");
        echo json_encode([
            'success' => true,
            'message' => 'Image deleted successfully',
            'file_deleted' => $fileDeleted
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete image from database']);
    }

} catch (PDOException $e) {
    error_log('Delete image error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error. Please try again.']);
} catch (Exception $e) {
    error_log('Delete image error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred. Please try again.']);
}
