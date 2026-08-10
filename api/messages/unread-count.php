<?php
// /api/messages/unread-count.php
// Get Unread Message Count

require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

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
if (!$userData) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

$userId = $userData['user_id'];

try {
    $db = Database::getInstance()->getConnection();
    $query = "SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0";
    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'unread_count' => (int)($row['unread_count'] ?? 0)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch unread count']);
}