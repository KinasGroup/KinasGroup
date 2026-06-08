<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

SessionManager::requireAdmin();

$limit = $_GET['limit'] ?? 50;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT a.*, u.name as user_name FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    $logs = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'logs' => $logs]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch logs']);
}