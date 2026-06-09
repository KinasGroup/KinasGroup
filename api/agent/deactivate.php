<?php
/**
 * Agent: self-deactivate. Hides all listings and flags the account as suspended.
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireAgent();

$token = $_POST['csrf_token'] ?? '';
if ($token === '' || !Security::verifyCSRFToken($token)) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $db = Database::getInstance()->getConnection();
    $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$userId]);
    $db->prepare("UPDATE agent_profiles SET verification_status = 'suspended' WHERE user_id = ?")->execute([$userId]);

    // Soft-hide all listings
    $db->prepare("UPDATE car_listings         SET status = 'removed' WHERE agent_id = ?")->execute([$userId]);
    $db->prepare("UPDATE property_listings    SET status = 'removed' WHERE agent_id = ?")->execute([$userId]);
    $db->prepare("UPDATE solar_listings       SET status = 'removed' WHERE agent_id = ?")->execute([$userId]);
    $db->prepare("UPDATE marketplace_listings SET status = 'removed' WHERE agent_id = ?")->execute([$userId]);

    Security::logActivity($userId, 'account_deactivated', 'Agent self-deactivated');

    // Log the user out and redirect home with a message
    $_SESSION = [];
    session_destroy();
    session_start();
    $_SESSION['flash_error'] = 'Your account has been deactivated. Please contact support to reactivate.';
    header('Location: /');
    exit;
} catch (Exception $e) {
    error_log('deactivate error: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to deactivate account']);
}
