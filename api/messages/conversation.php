<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

SessionManager::requireLogin();

$userId = $_GET['user_id'] ?? 0;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE (m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?) ORDER BY m.created_at ASC");
    $stmt->execute([$_SESSION['user_id'], $userId, $userId, $_SESSION['user_id']]);
    $conversation = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'messages' => $conversation]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch conversation']);
}