<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireAdmin();
$agentId = $_GET['id'] ?? 0;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("DELETE FROM agent_profiles WHERE id = ?");
    $stmt->execute([$agentId]);
    
    echo json_encode(['success' => true, 'message' => 'Agent deleted']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete agent']);
}