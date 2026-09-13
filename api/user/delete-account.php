<?php
/**
* KINAS GROUP — Self-service account deletion (soft delete)
*
* Sets users.status = 'deleted' so the account can be reactivated later.
* For agents: also suspends agent_profiles and hides listings.
* Logs the user out after successful deletion.
*
* Called by: /user/delete-account.php
* Accepts: POST with csrf_token + password confirmation
*/
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireLogin();

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// CSRF validation
$token = $data['csrf_token'] ?? '';
if ($token === '' || !Security::verifyCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Password confirmation
$password = $data['password'] ?? '';
if ($password === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter your password to confirm deletion.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Verify password
    $stmt = $db->prepare("SELECT password, role, status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Account not found.']);
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Incorrect password. Deletion cancelled.']);
        exit;
    }

    if ($user['status'] === 'deleted') {
        echo json_encode(['success' => true, 'message' => 'Account is already deleted.']);
        exit;
    }

    // Prevent admins from self-deleting via this endpoint
    if ($user['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin accounts cannot be deleted from this page.']);
        exit;
    }

    $db->beginTransaction();

    // 1) Soft-delete the user account
    $db->prepare("UPDATE users SET status = 'deleted' WHERE id = ?")
       ->execute([$userId]);

    // 2) If agent: suspend profile + hide listings
    if ($user['role'] === 'agent') {
        $db->prepare("UPDATE agent_profiles SET verification_status = 'suspended' WHERE user_id = ?")
           ->execute([$userId]);

        $listingTables = [
            'car_listings',
            'property_listings',
            'solar_listings',
            'marketplace_listings',
        ];

        foreach ($listingTables as $tbl) {
            $db->prepare("UPDATE {$tbl} SET status = 'removed' WHERE agent_id = ? AND status NOT IN ('sold','rented')")
               ->execute([$userId]);
        }
    }

    // 3) Invalidate session tokens
    $db->prepare("DELETE FROM sessions WHERE user_id = ?")
       ->execute([$userId]);

    Security::logActivity($userId, 'account_self_deleted', "User self-deleted account ({$user['role']})");

    $db->commit();

    // 4) Log the user out
    SessionManager::logout();

    echo json_encode([
        'success' => true,
        'message' => 'Your account has been deleted. You can sign in again later to reactivate it.',
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('delete-account error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete account. Please try again.']);
}
