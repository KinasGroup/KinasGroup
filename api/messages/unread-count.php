<?php
// /api/messages/unread-count.php
// Get Unread Message Count

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';

header('Content-Type: application/json');

// Use your secure SessionManager class instead of JWT tokens
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = SessionManager::getUserId();

try {
    $db = Database::getInstance()->getConnection();
    $query = "SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0";
    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'unread_count' => (int)($row['unread_count'] ?? 0)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch unread count']);
}
