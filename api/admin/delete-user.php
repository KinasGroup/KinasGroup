<?php
/**
 * Admin: soft-delete a user OR agent (by users.id).
 * Sets users.status = 'deleted' and (for agents) agent_profiles.
 * verification_status = 'suspended' so their listings stop showing
 * in public queries that filter on user status.
 *
 * Why soft delete: users has FKs from car_listings, property_listings,
 * solar_listings, marketplace_listings, messages, inquiries,
 * saved_listings, transactions, activity_logs, business_documents,
 * metamap_verifications, etc. A hard DELETE FROM users would either
 * cascade-wipe years of transaction history or throw FK errors. Soft
 * delete keeps the row for audit but makes the account inert.
 *
 * Accepts form POST (csrf_token, user_id) or JSON POST.
 * Redirects back to the referer on success; returns JSON if the
 * client sent Accept: application/json.
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) { header('Content-Type: application/json'); echo json_encode(['error' => 'Method not allowed']); }
    else echo 'Method not allowed';
    exit;
}

SessionManager::requireAdmin();

$contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $data = $_POST;
}

$token = $data['csrf_token'] ?? '';
if ($token !== '' && !Security::verifyCSRFToken($token)) {
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
    } else {
        $_SESSION['flash_error'] = 'Please refresh the page and try again.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/user-management.php'));
    }
    exit;
}

$userId = (int)($data['user_id'] ?? 0);

$redirectAfter = $_SERVER['HTTP_REFERER'] ?? '/admin/user-management.php';
if (!preg_match('#^/[a-zA-Z0-9_\-/]*(\.php)?(\?.*)?$#', $redirectAfter)) {
    $redirectAfter = '/admin/user-management.php';
}

if (!$userId) {
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['error' => 'Missing user_id']);
    } else {
        $_SESSION['flash_error'] = 'Missing user reference.';
        header('Location: ' . $redirectAfter);
        exit;
    }
    exit;
}

// Self-protection: an admin must NEVER be able to soft-delete their
// own account through the UI. If you really need to, do it via SQL.
if ($userId === (int)$_SESSION['user_id']) {
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'You cannot delete your own account from the admin UI']);
    } else {
        $_SESSION['flash_error'] = 'You cannot delete your own account.';
        header('Location: ' . $redirectAfter);
        exit;
    }
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Verify user exists
    $check = $db->prepare("SELECT id, role, name, email, status FROM users WHERE id = ?");
    $check->execute([$userId]);
    $u = $check->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
        if ($isJson) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        } else {
            $_SESSION['flash_error'] = 'User not found.';
            header('Location: ' . $redirectAfter);
            exit;
        }
        exit;
    }

    if ($u['status'] === 'deleted') {
        $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Already deleted']);
        } else {
            $_SESSION['flash_info'] = 'Account is already deleted.';
            header('Location: ' . $redirectAfter);
            exit;
        }
        exit;
    }

    $db->beginTransaction();

    // 1) Mark the user as deleted
    $db->prepare("UPDATE users SET status = 'deleted' WHERE id = ?")
       ->execute([$userId]);

    // 2) If this was an agent, also drop their verification + kill
    //    their active listings so they don't keep showing publicly.
    if ($u['role'] === 'agent') {
        $db->prepare("UPDATE agent_profiles SET verification_status = 'suspended' WHERE user_id = ?")
           ->execute([$userId]);

        $listingsTables = [
            'car'         => 'car_listings',
            'property'    => 'property_listings',
            'solar'       => 'solar_listings',
            'marketplace' => 'marketplace_listings',
        ];
        foreach ($listingsTables as $tbl) {
            // status='removed' is the soft-delete convention already
            // used by api/admin/remove-listing.php.
            $db->prepare("UPDATE {$tbl} SET status = 'removed' WHERE agent_id = ? AND status NOT IN ('sold','rented')")
               ->execute([$userId]);
        }
    }

    // 3) Invalidate any active session tokens for that user.
    //    The web UI uses PHP's native session, which the user can
    //    only invalidate by hitting /auth/logout.php; but the API
    //    'sessions' table holds remember-me / mobile tokens that
    //    we can hard-clear here.
    $db->prepare("DELETE FROM sessions WHERE user_id = ?")
       ->execute([$userId]);

    Security::logActivity($_SESSION['user_id'], 'user_deleted', "Soft-deleted user #$userId ({$u['email']}, role={$u['role']})");

    $db->commit();

    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'User deleted']);
    } else {
        $_SESSION['flash_success'] = 'User has been deleted.';
        header('Location: ' . $redirectAfter);
        exit;
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('admin delete-user error: ' . $e->getMessage());
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Delete failed']);
    } else {
        $_SESSION['flash_error'] = 'Delete failed. Please try again.';
        header('Location: ' . $redirectAfter);
        exit;
    }
}
