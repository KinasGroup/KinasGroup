<?php
/**
* KINAS GROUP — Self-service account deletion (soft delete)
*
* Sets users.status = 'deleted', deleted_by = 'self', deleted_at = NOW().
* For agents: suspends agent_profiles and hides ONLY active listings.
* Listings in other states (pending, draft, sold, rented) are untouched.
* Logs the user out after successful deletion.
*
* Called by: /user/delete-account.php
* Accepts: POST JSON with csrf_token + password confirmation
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

    if ($user['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin accounts cannot be deleted from this page.']);
        exit;
    }

    $db->beginTransaction();

    // 1) Soft-delete the user account
    $db->prepare("
        UPDATE users
        SET status = 'deleted',
            deleted_by = 'self',
            deleted_at = NOW()
        WHERE id = ?
    ")->execute([$userId]);

    // 2) If agent: suspend profile + hide ONLY active listings
    if ($user['role'] === 'agent') {
        try {
            $db->prepare("UPDATE agent_profiles SET verification_status = 'suspended' WHERE user_id = ?")
               ->execute([$userId]);
        } catch (Throwable $e) {
            // agent_profiles may not exist
        }

        $listingTables = [
            'car_listings',
            'property_listings',
            'solar_listings',
            'marketplace_listings',
        ];

        foreach ($listingTables as $tbl) {
            try {
                // FIX: Only hide ACTIVE listings.
                // Pending, draft, sold, rented listings are untouched.
                $db->prepare("UPDATE {$tbl} SET status = 'removed' WHERE agent_id = ? AND status = 'active'")
                   ->execute([$userId]);
            } catch (Throwable $e) {
                // Table may not exist
            }
        }
    }

    // 3) Invalidate session tokens
    try {
        $db->prepare("DELETE FROM sessions WHERE user_id = ?")
           ->execute([$userId]);
    } catch (Throwable $e) {
        // sessions table may not exist
    }

    Security::logActivity($userId, 'account_self_deleted', "User self-deleted account ({$user['role']})");

    $db->commit();

    // 4) Log the user out
    SessionManager::logout();

    echo json_encode([
        'success' => true,
        'message' => 'Your account has been deleted. You can sign in again later to reactivate it.',
        'redirect' => '/auth/login.php?deleted=1',
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('delete-account error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete account. Please try again.']);
}
