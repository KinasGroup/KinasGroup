<?php
// /api/reviews/create.php
// Submit a Product Review

require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

$token = null;
if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Please login to leave a review']);
    exit;
}

$userData = validateToken($token);
if (!$userData) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

$userId = $userData['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data']);
    exit;
}

$required = ['listing_type', 'listing_id', 'rating'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $field"]);
        exit;
    }
}

$listing_type = $input['listing_type'];
$listing_id = (int)$input['listing_id'];
$rating = (int)$input['rating'];
$comment = isset($input['comment']) ? trim($input['comment']) : '';

$validTypes = ['car', 'property', 'marketplace'];
if (!in_array($listing_type, $validTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid listing type']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['error' => 'Rating must be between 1 and 5']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if already reviewed
    $checkQuery = "SELECT id FROM product_reviews WHERE user_id = ? AND listing_type = ? AND listing_id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$userId, $listing_type, $listing_id]);
    
    if ($checkStmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'You have already reviewed this product']);
        exit;
    }
    
    // Insert review
    $insertQuery = "INSERT INTO product_reviews (user_id, listing_type, listing_id, rating, comment, status) 
                    VALUES (?, ?, ?, ?, ?, 'pending')";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->execute([$userId, $listing_type, $listing_id, $rating, $comment]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Review submitted for moderation',
        'review_id' => $db->lastInsertId()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to submit review']);
}