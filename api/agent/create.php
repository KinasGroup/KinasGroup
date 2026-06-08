<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireAdmin();
Security::validateCSRFToken($_POST['csrf_token'] ?? '');

$data = json_decode(file_get_contents('php://input'), true);

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("INSERT INTO agent_profiles (user_id, division, company, license_number, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$data['user_id'], $data['division'], $data['company'] ?? '', $data['license_number'] ?? '']);
    
    Security::logActivity($_SESSION['user_id'], 'agent_created', "Agent profile created for user {$data['user_id']}");
    
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create agent']);
}