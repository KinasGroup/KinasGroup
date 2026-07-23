<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/constants.php';
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

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("UPDATE agent_profiles SET verification_status = 'approved', verified_at = NOW() WHERE id = ?");
    $stmt->execute([$agentId]);
    
    $stmt = $db->prepare("UPDATE users SET verified = 1 WHERE id = (SELECT user_id FROM agent_profiles WHERE id = ?)");
    $stmt->execute([$agentId]);
    
    $stmt = $db->prepare("SELECT u.email, u.name FROM agent_profiles a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch();
    
    $svc = new EmailService();
    $approveBody = "Hi {$agent['name']},\n\nGreat news — your KINAS GROUP agent account has been approved. You can now log in and start listing.\n\n" . $svc->getSiteUrl() . "/auth/login.php";
    $svc->send($agent['email'], $agent['name'], 'Your KINAS GROUP agent account has been approved', nl2br(htmlspecialchars($approveBody)), $approveBody, INFO_EMAIL, 'KINAS GROUP');
    
    echo json_encode(['success' => true, 'message' => 'Agent approved']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Approval failed']);
}