<?php
header('Content-Type: application/json');
require_once '../config/env.php';
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/recaptcha.php'; // ← ADDED

// CORS headers for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// IP-based rate limiting
$ip = Security::getClientIP();
Security::rateLimitDB('login_' . $ip, MAX_LOGIN_ATTEMPTS, LOGIN_TIMEOUT);

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$csrfToken = $data['csrf_token'] ?? '';

// Validate CSRF token
if (empty($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Please refresh the page and try again.']);
    exit;
}

if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Please refresh the page and try again.']);
    exit;
}

// Rotate CSRF token after successful validation
unset($_SESSION['csrf_token']);

// ============================================
// CAPTCHA VERIFICATION - FIXED
// ============================================
$captchaToken = $data['captcha_token'] ?? '';

// Skip verification if no secret key is configured (development mode)
$keys = getRecaptchaKeys();
if (!empty($keys['secret']) && $keys['secret'] !== '6LeXXXXXXXXXXXXXXXXXXXXXXXX') {
    if (!verifyRecaptcha($captchaToken)) {
        http_response_code(422);
        echo json_encode(['error' => 'CAPTCHA verification failed. Please try again.']);
        exit;
    }
}

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter both email and password.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare(
        "SELECT id, name, email, password, role, verified, status, email_verified_at FROM users WHERE email = ?"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $passwordValid = password_verify($password, $user['password'] ?? '');

    if (!$user || !$passwordValid) {
        Security::logActivity($user['id'] ?? null, 'login_failed', "Failed login attempt for: $email from $ip");
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email address or password. Please try again.']);
        exit;
    }

    // Check user status
    if ($user['status'] !== 'active') {
        $statusMessages = [
            'suspended' => 'Your account has been suspended. Please contact support.',
            'inactive' => 'Your account is inactive. Please contact support.',
            'deleted' => 'Your account has been deleted. Please contact support.'
        ];
        $statusMessage = $statusMessages[$user['status']] ?? 'Your account is ' . $user['status'] . '. Please contact support.';
        http_response_code(403);
        echo json_encode(['error' => $statusMessage]);
        exit;
    }

    // Block login if email not verified (admins bypass)
    if (($user['role'] ?? '') !== 'admin' && empty($user['email_verified_at'])) {
        Security::logActivity($user['id'], 'login_blocked_unverified', "Login blocked — email not verified. email=$email from $ip");
        http_response_code(403);
        echo json_encode([
            'error' => 'Please verify your email before signing in. Check your inbox (and spam folder) for the verification link, or request a new one below.',
            'error_code' => 'email_not_verified',
            'email' => $user['email'],
        ]);
        exit;
    }

    SessionManager::regenerateSession();

    $token = Security::generateToken(32);
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

    $tokenIssued = false;
    try {
        $db->prepare("DELETE FROM sessions WHERE user_id = ? AND expires_at < NOW()")->execute([$user['id']]);
        $db->prepare("INSERT INTO sessions (user_id, token, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)")
           ->execute([$user['id'], $token, $expires, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '']);
        $tokenIssued = true;
    } catch (\Throwable $sessionErr) {
        error_log('Session row insert failed (non-fatal): ' . $sessionErr->getMessage());
    }

    SessionManager::setUser($user);
    Security::logActivity($user['id'], 'login', 'Successful login from ' . $ip);

    $newCsrfToken = Security::generate_csrf_token();

    http_response_code(200);
    $response = [
        'success' => true,
        'csrf_token' => $newCsrfToken,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
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
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to sign in. Please try again later.']);
}
?>
