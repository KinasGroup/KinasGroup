<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$code = $data['code'] ?? '';

if (empty($code)) {
    http_response_code(422);
    echo json_encode(['error' => 'Verification code is required']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT id, name FROM users WHERE verification_code = ? AND status = 'pending'");
    $stmt->execute([$code]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid verification code']);
        exit;
    }
    
    // Activate user
    $stmt = $db->prepare("UPDATE users SET status = 'active', verification_code = NULL, email_verified_at = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    Security::logActivity($user['id'], 'email_verified', 'Email verified successfully');
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Email verified successfully. You can now log in.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Verification failed']);
}
?>