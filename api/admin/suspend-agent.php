<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireAdmin();

$data = json_decode(file_get_contents('php://input'), true);
$agentId = $data['agent_id'] ?? 0;

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("UPDATE agent_profiles SET verification_status = 'suspended' WHERE id = ?");
    $stmt->execute([$agentId]);
    
    $stmt = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = (SELECT user_id FROM agent_profiles WHERE id = ?)");
    $stmt->execute([$agentId]);
    
    echo json_encode(['success' => true, 'message' => 'Agent suspended']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Suspension failed']);
}