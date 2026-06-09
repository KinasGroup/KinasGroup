<?php
/**
 * Admin: suspend or reactivate an agent (by users.id).
 * Accepts form POST (csrf_token, user_id, action) or JSON POST.
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
        $_SESSION['flash_error'] = 'Invalid security token.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/agent-management.php'));
    }
    exit;
}

$userId = (int)($data['user_id'] ?? $data['agent_id'] ?? 0);
$action = $data['action'] ?? 'suspend';

$newStatus = $action === 'activate' ? 'active' : 'suspended';
$newKyc    = $action === 'activate' ? 'approved' : 'suspended';

$redirectAfter = $_SERVER['HTTP_REFERER'] ?? '/admin/agent-management.php';
if (!preg_match('#^/[a-zA-Z0-9_\-/]*(\.php)?(\?.*)?$#', $redirectAfter)) {
    $redirectAfter = '/admin/agent-management.php';
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
    }
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Verify user exists and is an agent
    $check = $db->prepare("SELECT id, role, status FROM users WHERE id = ?");
    $check->execute([$userId]);
    $u = $check->fetch(PDO::FETCH_ASSOC);
    if (!$u || $u['role'] !== 'agent') {
        $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
        if ($isJson) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'Agent not found']);
        } else {
            $_SESSION['flash_error'] = 'Agent not found.';
            header('Location: ' . $redirectAfter);
        }
        exit;
    }

    $db->prepare("UPDATE users SET status = ? WHERE id = ?")
       ->execute([$newStatus, $userId]);

    // Update agent_profiles if exists
    $db->prepare("UPDATE agent_profiles SET verification_status = ? WHERE user_id = ?")
       ->execute([$newKyc, $userId]);

    Security::logActivity($_SESSION['user_id'], 'agent_' . $action, "Agent user_id=$userId set to $newStatus");

    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Agent ' . ($action === 'activate' ? 'reactivated' : 'suspended')]);
    } else {
        $_SESSION['flash_success'] = 'Agent has been ' . ($action === 'activate' ? 'reactivated' : 'suspended') . '.';
        header('Location: ' . $redirectAfter);
        exit;
    }
} catch (Exception $e) {
    error_log('admin suspend-agent error: ' . $e->getMessage());
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Action failed']);
    } else {
        $_SESSION['flash_error'] = 'Action failed. Please try again.';
        header('Location: ' . $redirectAfter);
        exit;
    }
}
