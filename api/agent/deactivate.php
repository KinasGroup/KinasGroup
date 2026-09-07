<?php
/**
* Agent: self-delete account (soft delete with reactivation support).
*
* AMENDED:
* - Sets users.status = 'deleted' (was 'suspended')
* - Sets users.deleted_by = 'self'
* - Sets users.deleted_at = NOW()
* - Stores a deletion snapshot for full restoration on reactivation
* - Hides all listings (sets to 'removed')
* - Suspends agent profile verification
* - Logs the user out
*
* The user can later log in and reactivate their account.
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

    // Get current agent profile status for the snapshot
    $agentVerificationStatus = 'pending';
    try {
        $profStmt = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
        $profStmt->execute([$userId]);
        $profRow = $profStmt->fetch(PDO::FETCH_ASSOC);
        if ($profRow) {
            $agentVerificationStatus = $profRow['verification_status'] ?? 'pending';
        }
    } catch (Throwable $e) {
        // agent_profiles might not exist
    }

    // Build listing snapshot
    $listingTables = [
        'car_listings',
        'property_listings',
        'solar_listings',
        'marketplace_listings',
    ];

    $snapshot = [
        'agent_verification_status' => $agentVerificationStatus,
        'listings' => [],
    ];

    foreach ($listingTables as $table) {
        $snapshot['listings'][$table] = [];
        try {
            $stmt = $db->prepare(
                "SELECT id, status FROM {$table}
                 WHERE agent_id = ?
                 AND status NOT IN ('removed','sold','rented')"
            );
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $snapshot['listings'][$table][] = [
                    'id'     => (int)$row['id'],
                    'status' => $row['status'],
                ];
            }
        } catch (Throwable $e) {
            // Table might not exist
        }
    }

    $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE);

    // Update user record
    $db->prepare("
        UPDATE users
        SET status = 'deleted',
            deleted_by = 'self',
            deleted_at = NOW(),
            deletion_snapshot = ?
        WHERE id = ?
    ")->execute([$snapshotJson, $userId]);

    // Suspend agent profile verification
    try {
        $db->prepare("UPDATE agent_profiles SET verification_status = 'suspended' WHERE user_id = ?")
           ->execute([$userId]);
    } catch (Throwable $e) {
        // agent_profiles might not exist
    }

    // Hide all listings (except sold/rented/already removed)
    foreach ($listingTables as $table) {
        try {
            $db->prepare(
                "UPDATE {$table}
                 SET status = 'removed'
                 WHERE agent_id = ?
                 AND status NOT IN ('removed','sold','rented')"
            )->execute([$userId]);
        } catch (Throwable $e) {
            // Table might not exist
        }
    }

    Security::logActivity($userId, 'account_deleted_self', 'Agent self-deleted account (reactivatable)');

    // Log out and redirect to login with reactivation message
    $_SESSION = [];
    session_destroy();
    session_start();
    $_SESSION['flash_success'] = 'Your account has been deleted. You can sign in again to reactivate it.';

    header('Location: /auth/login.php');
    exit;

} catch (Exception $e) {
    error_log('Agent delete-account error: ' . $e->getMessage());

    $_SESSION['flash_error'] = 'Failed to delete account. Please try again.';
    header('Location: /agent/profile.php');
    exit;
}
