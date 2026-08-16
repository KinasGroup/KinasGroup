<?php
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// FIX: Accept both JSON and standard Form submissions
$contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$isJson = stripos($contentType, 'application/json') !== false;
if ($isJson) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $data = $_POST;
}

$token = $data['token'] ?? '';
$password = $data['password'] ?? '';
$passwordConfirmation = $data['password_confirmation'] ?? '';

if (strlen($password) < 8) {
    if ($isJson) {
        http_response_code(422);
        echo json_encode(['error' => 'Password must be at least 8 characters']);
    } else {
        SessionManager::setFlash('error', 'Password must be at least 8 characters.');
        header('Location: /auth/reset-password.php?token=' . urlencode($token));
    }
    exit;
}

if ($password !== $passwordConfirmation) {
    if ($isJson) {
        http_response_code(422);
        echo json_encode(['error' => 'Passwords do not match']);
    } else {
        SessionManager::setFlash('error', 'Passwords do not match.');
        header('Location: /auth/reset-password.php?token=' . urlencode($token));
    }
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // FIX: Added reset_token_used = 0 check
    $stmt = $db->prepare("
        SELECT id FROM users
        WHERE reset_token = ? AND reset_token_expiry > NOW() AND reset_token_used = 0 AND status = 'active'
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        if ($isJson) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or expired reset token']);
        } else {
            SessionManager::setFlash('error', 'Invalid or expired reset token. Please request a new one.');
            header('Location: /auth/forgot-password.php');
        }
        exit;
    }

    $hashedPassword = Security::hashPassword($password);
    
    // FIX: Mark token as used
    $stmt = $db->prepare("
        UPDATE users
        SET password = ?, reset_token = NULL, reset_token_expiry = NULL, reset_token_used = 1
        WHERE id = ?
    ");
    $stmt->execute([$hashedPassword, $user['id']]);

    // Revoke all existing sessions
    $stmt = $db->prepare("DELETE FROM sessions WHERE user_id = ?");
    $stmt->execute([$user['id']]);

    Security::logActivity($user['id'], 'password_reset', 'Password reset completed');

    if ($isJson) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully. Please log in with your new password.'
        ]);
    } else {
        SessionManager::setFlash('success', 'Password reset successfully. Please log in.');
        header('Location: /auth/reset-password.php?token=' . urlencode($token) . '&success=1');
    }
    exit;

} catch (Exception $e) {
    error_log('Reset password error: ' . $e->getMessage());
    if ($isJson) {
        http_response_code(500);
        echo json_encode(['error' => 'Password reset failed']);
    } else {
        SessionManager::setFlash('error', 'An error occurred. Please try again.');
        header('Location: /auth/reset-password.php?token=' . urlencode($token));
    }
    exit;
}
