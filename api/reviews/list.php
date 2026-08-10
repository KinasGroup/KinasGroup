<?php
// /api/reviews/list.php
// Get Reviews for a Listing

require_once '../config/database.php';

header('Content-Type: application/json');

$listing_type = isset($_GET['type']) ? $_GET['type'] : '';
$listing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($listing_type) || !$listing_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing listing type or ID']);
    exit;
}

$validTypes = ['car', 'property', 'marketplace'];
if (!in_array($listing_type, $validTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid listing type']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $query = "SELECT r.*, u.name as user_name, u.email 
              FROM product_reviews r 
              JOIN users u ON r.user_id = u.id 
              WHERE r.listing_type = ? AND r.listing_id = ? AND r.status = 'approved'
              ORDER BY r.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$listing_type, $listing_id]);
    $reviews = $stmt->fetchAll();
    
    // Get average rating
    $avgQuery = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
                 FROM product_reviews 
                 WHERE listing_type = ? AND listing_id = ? AND status = 'approved'";
    $avgStmt = $db->prepare($avgQuery);
    $avgStmt->execute([$listing_type, $listing_id]);
    $avgData = $avgStmt->fetch();
    
    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'average_rating' => round($avgData['avg_rating'] ?? 0, 1),
        'total_reviews' => (int)($avgData['total_reviews'] ?? 0)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch reviews']);
}