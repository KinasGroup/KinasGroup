<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

SessionManager::requireLogin();

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT m.*, u.name AS sender_name, u.email AS sender_email
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.receiver_id = ?
        ORDER BY m.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (Exception $e) {
    error_log('inbox error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch inbox']);
}
