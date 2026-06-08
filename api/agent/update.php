<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireLogin();
$data = json_decode(file_get_contents('php://input'), true);
$agentId = $_GET['id'] ?? 0;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE agent_profiles SET company = ?, license_number = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$data['company'] ?? '', $data['license_number'] ?? '', $agentId, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update agent']);
}