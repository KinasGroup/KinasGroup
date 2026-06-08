<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/file-upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireLogin();

$subDir = $_POST['type'] ?? 'general';
$listingId = $_POST['listing_id'] ?? 0;
$listingType = $_POST['listing_type'] ?? 'car';

try {
    $uploader = new FileUpload($subDir);
    $result = $uploader->upload($_FILES['image'], ['maxWidth' => 1920, 'maxHeight' => 1080]);
    
    if ($result['success'] && $listingId) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO listing_images (listing_id, listing_type, url, thumbnail_url, sort_order, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$listingId, $listingType, $result['filename'], $result['thumbnail'], 0]);
    }
    
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Upload failed']);
}