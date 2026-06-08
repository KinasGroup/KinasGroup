<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireLogin();

$data = json_decode(file_get_contents('php://input'), true);
$messageId = $data['message_id'] ?? 0;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND recipient_id = ?");
    $stmt->execute([$messageId, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to mark as read']);
}