<?php
/**
* KINAS GROUP — Reactivate Account Endpoint
*
* Restores a self-deleted account back to active status.
* Requires a pending_reactivation_user_id session flag (set by login.php).
*
* Restoration includes:
* - Setting users.status back to 'active'
* - Clearing deleted_at and deleted_by
* - Restoring agent listings (if applicable)
* - Restoring agent profile verification status
* - Creating a normal session
*/
declare(strict_types=1);

header('Content-Type: application/json');

require_once '../config/env.php';
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function reactivate_json_error(int $status, string $error): void
{
    http_response_code($status);
    echo json_encode([
        'error' => $error,
        'csrf_token' => Security::generateCSRFToken(),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reactivate_json_error(405, 'Method not allowed');
}

// Must have a pending reactivation session flag
if (empty($_SESSION['pending_reactivation_user_id'])) {
    reactivate_json_error(401, 'No pending reactivation. Please log in first.');
}

$userId = (int)$_SESSION['pending_reactivation_user_id'];

// Rate limit reactivation attempts
Security::rateLimitDB('reactivate_' . $userId, 3, 600);

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    reactivate_json_error(400, 'Invalid JSON data');
}

// CSRF validation
$csrfToken = trim((string)($data['csrf_token'] ?? ''));

if ($csrfToken === '' || !Security::verifyCSRFToken($csrfToken)) {
    reactivate_json_error(403, 'Please refresh the page and try again.');
}

try {
    $db = Database::getInstance()->getConnection();

    if (!$db) {
        throw new RuntimeException('Database connection unavailable.');
    }

    // Fetch the user
    $stmt = $db->prepare("
        SELECT id, name, username, email, password, role, verified, status, email_verified_at,
               deleted_at, deleted_by
        FROM users
        WHERE id = ?
    ");

    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        reactivate_json_error(404, 'Account not found.');
    }

    if ((string)$user['status'] !== 'deleted') {
        reactivate_json_error(409, 'This account is not in a deleted state.');
    }

    if ((string)($user['deleted_by'] ?? 'self') === 'admin') {
        reactivate_json_error(403, 'This account was deactivated by an administrator and cannot be self-reactivated.');
    }

    // ============================================================
    // RESTORE THE ACCOUNT
    // ============================================================

    $db->beginTransaction();

    // 1. Restore user status
    $db->prepare("
        UPDATE users
        SET status = 'active',
            deleted_at = NULL,
            deleted_by = NULL,
            updated_at = NOW()
        WHERE id = ?
    ")->execute([$userId]);

    // 2. If the user is an agent, restore their listings and profile
    if ((string)$user['role'] === 'agent') {
        // Restore agent profile verification status
        // If they were approved before deletion, restore that.
        // Otherwise, set back to pending.
        try {
            $profileStmt = $db->prepare("
                SELECT verification_status
                FROM agent_profiles
                WHERE user_id = ?
            ");

            $profileStmt->execute([$userId]);
            $profile = $profileStmt->fetch();

            if ($profile) {
                // If verification was 'suspended' due to deletion, restore to 'approved'
                // if the user was verified, otherwise keep as is.
                if ((string)$profile['verification_status'] === 'suspended' && (bool)$user['verified']) {
                    $db->prepare("
                        UPDATE agent_profiles
                        SET verification_status = 'approved'
                        WHERE user_id = ?
                    ")->execute([$userId]);
                }
            }
        } catch (\Throwable $e) {
            error_log('Agent profile restore error: ' . $e->getMessage());
        }

        // Restore agent listings (set 'removed' back to 'active')
        $listingTables = [
            'car_listings',
            'property_listings',
            'solar_listings',
            'marketplace_listings',
        ];

        foreach ($listingTables as $table) {
            try {
                $db->prepare("
                    UPDATE {$table}
                    SET status = 'active'
                    WHERE agent_id = ?
                      AND status = 'removed'
                ")->execute([$userId]);
            } catch (\Throwable $e) {
                error_log("Listing restore error for {$table}: " . $e->getMessage());
            }
        }
    }

    $db->commit();

    // ============================================================
    // CREATE A NORMAL SESSION
    // ============================================================

    // Clear the pending reactivation flag
    unset($_SESSION['pending_reactivation_user_id']);
    unset($_SESSION['pending_reactivation_email']);
    unset($_SESSION['pending_reactivation_name']);

    // Regenerate session ID
    SessionManager::regenerateSession();

    // Create a DB session token
    $token = Security::generateToken(32);
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    $tokenIssued = false;

    try {
        $db->prepare("DELETE FROM sessions WHERE user_id = ? AND expires_at < NOW()")
           ->execute([$userId]);

        $db->prepare(
            "INSERT INTO sessions (user_id, token, expires_at, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([
            $userId,
            $token,
            $expires,
            Security::getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);

        $tokenIssued = true;
    } catch (\Throwable $e) {
        error_log('Session row insert failed during reactivation: ' . $e->getMessage());
    }

    // Set the user session
    SessionManager::setUser($user);

    // Log the reactivation
    Security::logActivity(
        $userId,
        'account_reactivated',
        'Account reactivated by user from ' . Security::getClientIP()
    );

    // Generate new CSRF token
    unset($_SESSION['csrf_token']);
    $newCsrfToken = Security::generateCSRFToken();

    $response = [
        'success' => true,
        'csrf_token' => $newCsrfToken,
        'message' => 'Your account has been reactivated successfully!',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'username' => $user['username'] ?? null,
            'email' => $user['email'],
            'role' => $user['role'],
            'verified' => (bool)$user['verified'],
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

    error_log('Reactivation error: ' . $e->getMessage());

    reactivate_json_error(500, 'Unable to reactivate account. Please try again later.');
}
