<?php
/**
* KINAS GROUP — Account Reactivation Endpoint
*
* Restores a self-deleted account back to active status.
* Requires the pending_reactivation_user_id session flag set by login.
*
* Restoration includes:
* - Setting users.status back to 'active'
* - Clearing deleted_at and deleted_by
* - Restoring agent profile verification status (if agent)
* - Restoring agent listings that were hidden during deletion
* - Creating a normal session
*/
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/dotenv.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
// Must have pending reactivation session flag
if (empty($_SESSION['pending_reactivation_user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No pending reactivation. Please log in first.']);
    exit;
}
$userId = (int)$_SESSION['pending_reactivation_user_id'];
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = $_POST;
}
// CSRF validation
$csrfToken = trim((string)($data['csrf_token'] ?? ''));
if ($csrfToken === '' || !Security::verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}
try {
    $db = Database::getInstance()->getConnection();
    if (!$db) {
        throw new RuntimeException('Database connection unavailable.');
    }
    // Verify the account is still in 'deleted' status
    $stmt = $db->prepare("SELECT id, name, username, email, role, status, deleted_by FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        unset($_SESSION['pending_reactivation_user_id']);
        http_response_code(404);
        echo json_encode(['error' => 'Account not found.']);
        exit;
    }
    if ((string)$user['status'] !== 'deleted') {
        unset($_SESSION['pending_reactivation_user_id']);
        http_response_code(409);
        echo json_encode(['error' => 'This account is not in a deleted state.']);
        exit;
    }
    // Only self-deleted accounts can be reactivated
    if (($user['deleted_by'] ?? 'self') === 'admin') {
        unset($_SESSION['pending_reactivation_user_id']);
        http_response_code(403);
        echo json_encode(['error' => 'This account was deactivated by an administrator. Please contact support.']);
        exit;
    }
    $db->beginTransaction();
    // 1) Restore user status
    $db->prepare("
        UPDATE users
        SET status = 'active',
            deleted_at = NULL,
            deleted_by = NULL,
            deletion_snapshot = NULL
        WHERE id = ?
    ")->execute([$userId]);
    // 2) If agent, restore profile and listings
    if ($user['role'] === 'agent') {
        // Restore agent profile verification status
        try {
            $db->prepare("
                UPDATE agent_profiles
                SET verification_status = 'approved'
                WHERE user_id = ?
                AND verification_status = 'suspended'
            ")->execute([$userId]);
        } catch (Throwable $e) {
            error_log('Reactivate: agent_profiles restore error: ' . $e->getMessage());
        }
        // Restore listings that were hidden during deletion
        $listingTables = [
            'car_listings',
            'property_listings',
            'solar_listings',
            'marketplace_listings',
        ];
        foreach ($listingTables as $tbl) {
            try {
                $db->prepare("
                    UPDATE {$tbl}
                    SET status = 'active'
                    WHERE agent_id = ?
                    AND status = 'removed'
                ")->execute([$userId]);
            } catch (Throwable $e) {
                error_log("Reactivate: {$tbl} restore error: " . $e->getMessage());
            }
        }
    }
    // 3) Invalidate any stale session tokens for this user
    try {
        $db->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$userId]);
    } catch (Throwable $e) {
        // sessions table may not exist
    }
    Security::logActivity($userId, 'account_reactivated', 'Account reactivated by user from ' . Security::getClientIP());
    $db->commit();
    // 4) Create a normal session
    SessionManager::regenerateSession();
    // Clear the pending reactivation flag
    unset($_SESSION['pending_reactivation_user_id']);
    unset($_SESSION['pending_reactivation_email']);
    unset($_SESSION['pending_reactivation_name']);
    // Set the user session
    SessionManager::setUser($user);
    // Generate new CSRF token
    unset($_SESSION['csrf_token']);
    $newCsrfToken = Security::generateCSRFToken();
    // Issue DB-persisted token for API clients
    $token = Security::generateToken(32);
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    $tokenIssued = false;
    try {
        $db->prepare("
            INSERT INTO sessions (user_id, token, expires_at, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $userId,
            $token,
            $expires,
            Security::getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
        $tokenIssued = true;
    } catch (\Throwable $e) {
        error_log('Reactivate: session token insert failed: ' . $e->getMessage());
    }
    $response = [
        'success' => true,
        'csrf_token' => $newCsrfToken,
        'message' => 'Your account has been reactivated successfully! Redirecting to dashboard…',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'username' => $user['username'] ?? null,
            'email' => $user['email'],
            'role' => $user['role'],
        ],
    ];
    if ($tokenIssued) {
        $response['token'] = $token;
    }
    echo json_encode($response);
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Reactivate error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to reactivate account. Please try again.']);
}
