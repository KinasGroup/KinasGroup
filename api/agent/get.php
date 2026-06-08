<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

$agentId = $_GET['id'] ?? 0;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT a.*, u.name, u.email, u.phone, u.verified FROM agent_profiles a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch();
    
    if ($agent) {
        echo json_encode(['success' => true, 'agent' => $agent]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Agent not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch agent']);
}