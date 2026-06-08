<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/file-upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireLogin();

$data = json_decode(file_get_contents('php://input'), true);
$imageId = $data['image_id'] ?? 0;
$subDir = $data['type'] ?? 'general';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT url, thumbnail_url FROM listing_images WHERE id = ?");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch();
    
    if ($image) {
        $uploader = new FileUpload($subDir);
        $uploader->delete($image['url']);
        if ($image['thumbnail_url']) {
            $uploader->delete($image['thumbnail_url']);
        }
        
        $stmt = $db->prepare("DELETE FROM listing_images WHERE id = ?");
        $stmt->execute([$imageId]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Image deleted']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete image']);
}