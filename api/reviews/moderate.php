<?php
// /api/reviews/moderate.php
// Moderate Reviews (Admin Only)

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
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userData = validateToken($token);
if (!$userData || !isset($userData['is_admin']) || !$userData['is_admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$review_id = isset($input['review_id']) ? (int)$input['review_id'] : 0;
$action = isset($input['action']) ? $input['action'] : '';

if (!$review_id || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $query = "UPDATE product_reviews SET status = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$status, $review_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Review ' . $status
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Review not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update review']);
}