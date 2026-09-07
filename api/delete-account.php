<?php
/**
* KINAS GROUP — Self-service account deletion endpoint
*
* Soft-deletes the logged-in user's account.
* The account can later be reactivated by logging in again.
*
* For agents:
* - Stores a restoration snapshot.
* - Hides listings by setting them to removed.
* - Suspends agent profile verification.
*
* For buyers:
* - Simply marks the user account as deleted.
*/

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in.']);
    exit;
}

$userId = (int)SessionManager::getUserId();

Security::rateLimitDB('delete_account_' . $userId, 3, 600);

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    $data = $_POST;
}

$csrfToken = trim((string)($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
$password = (string)($data['password'] ?? '');

if ($csrfToken === '' || !Security::verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Please refresh the page and try again.']);
    exit;
}

if ($password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter your password to confirm account deletion.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    if (!$db) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $stmt = $db->prepare(
        "SELECT id, password, role, status
         FROM users
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Account not found.']);
        exit;
    }

    if (($user['role'] ?? '') === 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin accounts cannot be deleted from this page.']);
        exit;
    }

    if (($user['status'] ?? '') === 'deleted') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'This account has already been deleted.']);
        exit;
    }

    if (!password_verify($password, (string)($user['password'] ?? ''))) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'The password you entered is incorrect.']);
        exit;
    }

    $snapshot = [
        'deleted_from_role' => (string)($user['role'] ?? 'user'),
        'agent_profile' => null,
        'listings' => [],
    ];

    $role = (string)($user['role'] ?? 'user');

    $db->beginTransaction();

    // If this is an agent, preserve listing states before hiding them.
    if ($role === 'agent') {
        // Store agent profile verification state.
        try {
            $profileStmt = $db->prepare(
                "SELECT verification_status, kyb_status
                 FROM agent_profiles
                 WHERE user_id = ?
                 LIMIT 1"
            );

            $profileStmt->execute([$userId]);
            $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

            if ($profile) {
                $snapshot['agent_profile'] = [
                    'verification_status' => (string)($profile['verification_status'] ?? 'pending'),
                    'kyb_status' => (string)($profile['kyb_status'] ?? 'not_started'),
                ];

                $db->prepare(
                    "UPDATE agent_profiles
                     SET verification_status = 'suspended'
                     WHERE user_id = ?"
                )->execute([$userId]);
            }
        } catch (Throwable $e) {
            error_log('Account deletion agent profile snapshot error: ' . $e->getMessage());
        }

        // Hide all listings and store their previous status.
        $listingTables = [
            'car_listings',
            'property_listings',
            'solar_listings',
            'marketplace_listings',
        ];

        foreach ($listingTables as $table) {
            try {
                $rowsStmt = $db->prepare(
                    "SELECT id, status
                     FROM {$table}
                     WHERE agent_id = ?
                       AND status <> 'removed'"
                );

                $rowsStmt->execute([$userId]);
                $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $statuses = [];

                    foreach ($rows as $row) {
                        $statuses[(string)$row['id']] = (string)$row['status'];
                    }

                    $snapshot['listings'][$table] = $statuses;

                    $db->prepare(
                        "UPDATE {$table}
                         SET status = 'removed'
                         WHERE agent_id = ?
                           AND status <> 'removed'"
                    )->execute([$userId]);
                }
            } catch (Throwable $e) {
                error_log("Account deletion listing snapshot error for {$table}: " . $e->getMessage());
            }
        }
    }

    $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE);

    $db->prepare(
        "UPDATE users
         SET status = 'deleted',
             deleted_at = NOW(),
             deleted_by = 'self',
             deletion_snapshot = ?
         WHERE id = ?"
    )->execute([$snapshotJson, $userId]);

    $db->commit();

    Security::logActivity(
        $userId,
        'account_deleted_self',
        'User self-deleted account. Account can be reactivated.'
    );

    // Log the user out after successful soft deletion.
    SessionManager::logout();

    echo json_encode([
        'success' => true,
        'message' => 'Your account has been deleted. You can sign in again to reactivate it.',
        'redirect' => '/auth/login.php?deleted=1',
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Account deletion error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to delete account. Please try again.']);
}
