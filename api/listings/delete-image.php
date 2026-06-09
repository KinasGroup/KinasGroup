<?php
/**
 * Delete a single listing image (agent must own the parent listing).
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

SessionManager::requireLogin();

$contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $data = $_POST;
}

$token = $data['csrf_token'] ?? '';
if ($token === '' || !Security::verifyCSRFToken($token)) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

$imageId = (int)($data['image_id'] ?? 0);
if (!$imageId) {
    header('Content-Type: application/json');
    http_response_code(422);
    echo json_encode(['error' => 'Missing image_id']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, listing_id, listing_type, url FROM listing_images WHERE id = ?");
    $stmt->execute([$imageId]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$img) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'Image not found']);
        exit;
    }

    // Verify the current user owns the parent listing
    $tableMap = [
        'car'         => 'car_listings',
        'property'    => 'property_listings',
        'solar'       => 'solar_listings',
        'marketplace' => 'marketplace_listings',
    ];
    $table = $tableMap[$img['listing_type']] ?? null;
    if (!$table) {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['error' => 'Unknown listing type']);
        exit;
    }
    $own = $db->prepare("SELECT id FROM $table WHERE id = ? AND agent_id = ?");
    $own->execute([$img['listing_id'], $_SESSION['user_id']]);
    if (!$own->fetch()) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to delete this image']);
        exit;
    }

    // Delete DB row
    $db->prepare("DELETE FROM listing_images WHERE id = ?")->execute([$imageId]);

    // Best-effort: remove the actual file (only if it's a local path)
    if (!empty($img['url']) && str_starts_with($img['url'], '/uploads/')) {
        $filePath = __DIR__ . '/../../' . ltrim($img['url'], '/');
        if (is_file($filePath)) @unlink($filePath);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Image removed']);
} catch (Exception $e) {
    error_log('delete-image error: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to remove image']);
}
