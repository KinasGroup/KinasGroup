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

$userId = SessionManager::getUserId();

try {
    // Revoke all sessions for user
    if ($userId) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM sessions WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Log activity
        Security::logActivity($userId, 'logout', 'User logged out');
    }
    
    // Destroy session
    SessionManager::logout();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Logout failed']);
}
?>