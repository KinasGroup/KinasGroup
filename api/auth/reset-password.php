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
$token = $data['token'] ?? '';
$password = $data['password'] ?? '';
$passwordConfirmation = $data['password_confirmation'] ?? '';

if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['error' => 'Password must be at least 8 characters']);
    exit;
}

if ($password !== $passwordConfirmation) {
    http_response_code(422);
    echo json_encode(['error' => 'Passwords do not match']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT id FROM users 
        WHERE reset_token = ? AND reset_token_expiry > NOW() AND status = 'active'
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired reset token']);
        exit;
    }
    
    // Update password
    $hashedPassword = Security::hashPassword($password);
    $stmt = $db->prepare("
        UPDATE users 
        SET password = ?, reset_token = NULL, reset_token_expiry = NULL 
        WHERE id = ?
    ");
    $stmt->execute([$hashedPassword, $user['id']]);
    
    // Revoke all existing sessions
    $stmt = $db->prepare("DELETE FROM sessions WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    
    Security::logActivity($user['id'], 'password_reset', 'Password reset completed');
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully. Please log in with your new password.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Password reset failed']);
}
?>