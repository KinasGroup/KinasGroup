<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/email.php';

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// IP-based rate limit (DB-backed). 5 attempts / hour / IP is enough for
// a legit user who lost the email and a few re-sends, while still
// blocking brute-force enumeration.
$ip = Security::getClientIP();
Security::rateLimitDB('resend_verification_' . $ip, 5, 3600);

$data = json_decode(file_get_contents('php://input'), true);
$email = trim(strtolower($data['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid email address']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id, name, email, role, email_verified_at FROM users WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $message = 'If an account exists with that email and is not yet verified, a new verification link has been sent.';

    // Only actually re-issue a code if:
    //   - the user exists
    //   - email has not been verified yet
    // Otherwise, do nothing — and DON'T leak the user state to the caller
    // (the success message stays the same either way).
    if ($user && empty($user['email_verified_at'])) {
        $newCode  = bin2hex(random_bytes(32));
        $expiry   = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $db->prepare(
            "UPDATE users
                SET verification_code = ?,
                    verification_code_expires = ?
              WHERE id = ?"
        )->execute([$newCode, $expiry, $user['id']]);

        try {
            $svc = new EmailService();
            $svc->sendVerificationEmail($user['email'], $user['name'], $newCode);
        } catch (\Throwable $e) {
            error_log('Resend verification email send failed for user ' . $user['id'] . ': ' . $e->getMessage());
            // Continue — we never want the user to know the email failed
            // for security reasons, but we log it.
        }

        Security::logActivity($user['id'], 'verification_resent', "Verification email resent to {$user['email']} from $ip");
    }

    // Always 200 with the same message so a caller cannot enumerate users.
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => $message]);

} catch (\Throwable $e) {
    error_log('Resend verification error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process request']);
}
