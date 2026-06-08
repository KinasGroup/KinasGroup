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

// SECURED: Use DB-backed rate limiting instead of session-based
$ip = Security::getClientIP();
Security::rateLimitDB('forgot_password_' . $ip, MAX_LOGIN_ATTEMPTS, LOGIN_TIMEOUT);

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

$email = trim($data['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid email address']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Look up user by email (case-insensitive)
    $stmt = $db->prepare("SELECT id, name, email, status FROM users WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['status'] === 'active') {
        // Generate reset token
        $resetToken = Security::generateToken(32);
        $tokenExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store token with expiry
        $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ?, reset_token_used = 0 WHERE id = ?");
        $stmt->execute([password_hash($resetToken, PASSWORD_BCRYPT), $tokenExpiry, $user['id']]);

        // Log activity
        Security::logActivity($user['id'], 'password_reset_request', 'Password reset requested from ' . $ip);

        // Send reset email (in production, this would use SMTP)
        try {
            $emailService = new EmailService();
            $emailService->sendPasswordReset($user['email'], $user['name'], $resetToken);
        } catch (Exception $e) {
            error_log('Email send failed: ' . $e->getMessage());
            // Continue - don't expose email failure
        }
    }

    // Always return success to prevent email enumeration
    // This is intentional - don't reveal whether email exists
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'If an account exists with this email, you will receive password reset instructions.'
    ]);

} catch (Exception $e) {
    error_log('Password reset error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process request']);
}
