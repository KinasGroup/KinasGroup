<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

SessionManager::requireLogin();

$userId = (int)($_GET['user_id'] ?? 0);
$myId   = (int)$_SESSION['user_id'];

if (!$userId) {
    http_response_code(422);
    echo json_encode(['error' => 'Missing user_id']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT m.*, u.name AS sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$myId, $userId, $userId, $myId]);
    $conversation = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark messages as read (those sent to me by this user)
    $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")
       ->execute([$userId, $myId]);

    echo json_encode(['success' => true, 'messages' => $conversation]);
} catch (Exception $e) {
    error_log('conversation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch conversation']);
}
