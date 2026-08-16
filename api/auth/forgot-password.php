<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// SECURED: Use DB-backed rate limiting
$ip = Security::getClientIP();
Security::rateLimitDB('forgot_password_' . $ip, MAX_LOGIN_ATTEMPTS, LOGIN_TIMEOUT);

// FIX: Accept both JSON and standard Form submissions
$contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$isJson = stripos($contentType, 'application/json') !== false;
if ($isJson) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $data = $_POST;
}

if (!$data) {
    if ($isJson) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
    } else {
        SessionManager::setFlash('error', 'Invalid request data.');
        header('Location: /auth/forgot-password.php');
    }
    exit;
}

$email = trim($data['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    if ($isJson) {
        http_response_code(422);
        echo json_encode(['error' => 'Please enter a valid email address']);
    } else {
        SessionManager::setFlash('error', 'Please enter a valid email address.');
        header('Location: /auth/forgot-password.php');
    }
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, name, email, status FROM users WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['status'] === 'active') {
        $resetToken = Security::generateToken(32);
        $tokenExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // FIX: Store the raw token (64-char hex) so it can be searched in SQL later.
        $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ?, reset_token_used = 0 WHERE id = ?");
        $stmt->execute([$resetToken, $tokenExpiry, $user['id']]);

        Security::logActivity($user['id'], 'password_reset_request', 'Password reset requested from ' . $ip);

        try {
            $svc = new EmailService();
            $resetLink = $svc->getSiteUrl() . '/auth/reset-password.php?token=' . urlencode($resetToken);
            $body = "Hi {$user['name']},\n\nWe received a request to reset your KINAS GROUP password. Click the link below to choose a new one:\n{$resetLink}\n\nThis link expires in 1 hour. If you didn't request this, you can safely ignore this email.";
            
            $svc->send($user['email'], $user['name'], 'Reset your KINAS GROUP password', nl2br(htmlspecialchars($body)), $body, INFO_EMAIL, 'KINAS GROUP');
        } catch (Exception $e) {
            error_log('Email send failed: ' . $e->getMessage());
        }
    }

    // Always return success to prevent email enumeration
    if ($isJson) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'If an account exists with this email, you will receive password reset instructions.'
        ]);
    } else {
        SessionManager::setFlash('success', 'If an account exists with this email, you will receive password reset instructions.');
        header('Location: /auth/forgot-password.php');
    }
    exit;

} catch (Exception $e) {
    error_log('Password reset error: ' . $e->getMessage());
    if ($isJson) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to process request']);
    } else {
        SessionManager::setFlash('error', 'An error occurred. Please try again.');
        header('Location: /auth/forgot-password.php');
    }
    exit;
}
