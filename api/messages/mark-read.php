<?php
// /api/messages/mark-read.php
// Mark all messages as read

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');

// Use your secure SessionManager class instead of JWT tokens
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = SessionManager::getUserId();

try {
    $db = Database::getInstance()->getConnection();
    $query = "UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0";
    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
    $affectedRows = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'marked_count' => $affectedRows
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to mark messages as read']);
}
