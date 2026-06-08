<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireAdmin();

$data = json_decode(file_get_contents('php://input'), true);
$agentId = $data['agent_id'] ?? 0;
$reason = $data['reason'] ?? 'Not specified';

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("UPDATE agent_profiles SET verification_status = 'rejected', admin_notes = ? WHERE id = ?");
    $stmt->execute([$reason, $agentId]);
    
    $stmt = $db->prepare("SELECT u.email, u.name FROM agent_profiles a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch();
    
    $emailService = new EmailService();
    $emailService->sendAgentRejected($agent['email'], $agent['name'], $reason);
    
    echo json_encode(['success' => true, 'message' => 'Agent rejected']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Rejection failed']);
}