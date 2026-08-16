<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once '../config/env.php';
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

// CORS headers for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Always return JSON and include a fresh CSRF token where possible.
 * This prevents the frontend from getting stuck with an invalid/expired token.
 */
function login_json_error(int $status, string $error, ?array $extra = null): void
{
    http_response_code($status);

    $payload = [
        'error' => $error,
        'csrf_token' => Security::generateCSRFToken(),
    ];

    if ($extra !== null) {
        $payload = array_merge($payload, $extra);
    }

    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    login_json_error(405, 'Method not allowed');
}

// IP-based rate limiting
$ip = Security::getClientIP();
Security::rateLimitDB('login_' . $ip, MAX_LOGIN_ATTEMPTS, LOGIN_TIMEOUT);

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    login_json_error(400, 'Invalid JSON data');
}

// The identifier may be an email address OR a username.
$identifier = strtolower(trim((string)($data['email'] ?? $data['username'] ?? '')));
$password = (string)($data['password'] ?? '');

// Allow CSRF token from JSON body or X-CSRF-Token header.
$headerCsrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$csrfToken = trim((string)($data['csrf_token'] ?? $headerCsrf));

// Validate CSRF token without destroying it before login is complete.
if ($csrfToken === '' || !Security::verifyCSRFToken($csrfToken)) {
    login_json_error(403, 'Please refresh the page and try again.');
}

// CAPTCHA verification
$captchaToken = (string)($data['captcha_token'] ?? '');
$captchaSecretKey = get_captcha_secret_key();
$captchaEnabled = !empty($captchaSecretKey) && $captchaSecretKey !== '6LeXXXXXXXXXXXXXXXXXXXXXXXX';

if ($captchaEnabled) {
    if ($captchaToken === '') {
        login_json_error(422, 'Please complete the CAPTCHA verification.');
    }

    $captchaUrl = 'https://www.google.com/recaptcha/api/siteverify?' . http_build_query([
        'secret' => $captchaSecretKey,
        'response' => $captchaToken,
        'remoteip' => $ip,
    ]);

    $captchaContext = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'GET',
        ],
        'socket' => [
            'timeout' => 5,
        ],
    ]);

    $verifyResponse = @file_get_contents($captchaUrl, false, $captchaContext);

    if ($verifyResponse === false) {
        // Network failure reaching Google — fail open with a log entry.
        error_log('reCAPTCHA verification network failure for IP: ' . $ip);
    } else {
        $verifyData = json_decode($verifyResponse, true);

        if (!$verifyData || empty($verifyData['success'])) {
            login_json_error(422, 'CAPTCHA verification failed. Kindly refresh the page and try again.');
        }
    }
}

if ($identifier === '' || $password === '') {
    login_json_error(400, 'Please enter both email/username and password.');
}

// Only validate email format when the identifier actually looks like an email.
$isEmail = strpos($identifier, '@') !== false;

if ($isEmail && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
    login_json_error(422, 'Please enter a valid email address.');
}

try {
    $db = Database::getInstance()->getConnection();

    if (!$db) {
        throw new RuntimeException('Database connection unavailable.');
    }

    // Lookup by email OR username.
    $stmt = $db->prepare(
        "SELECT id, name, username, email, password, role, verified, status, email_verified_at
         FROM users
         WHERE " . ($isEmail ? "email = ?" : "username = ?")
    );

    $stmt->execute([$identifier]);
    $user = $stmt->fetch();

    // Use consistent timing to reduce user enumeration timing differences.
    $passwordHash = $user['password'] ?? '';
    $passwordValid = password_verify($password, $passwordHash);

    if (!$user) {
        password_hash('dummy_password_for_timing', PASSWORD_BCRYPT, ['cost' => 12]);
    }

    if (!$user || !$passwordValid) {
        Security::logActivity(
            $user['id'] ?? null,
            'login_failed',
            "Failed login attempt for: $identifier from $ip"
        );

        login_json_error(401, 'Invalid email/username or password. Please try again.');
    }

    // Check user status
    $status = (string)($user['status'] ?? 'active');

    if ($status !== 'active') {
        $statusMessages = [
            'suspended' => 'Your account has been suspended. Please contact support.',
            'inactive' => 'Your account is inactive. Please contact support.',
            'deleted' => 'Your account has been deleted. Please contact support.',
        ];

        $statusMessage = $statusMessages[$status] ?? 'Your account is ' . $status . '. Please contact support.';

        login_json_error(403, $statusMessage);
    }

    // Block login if the email has not been verified.
    // Admins are seeded with email_verified_at already set.
    if (($user['role'] ?? '') !== 'admin' && empty($user['email_verified_at'])) {
        Security::logActivity(
            (int)$user['id'],
            'login_blocked_unverified',
            "Login blocked — email not verified. identifier=$identifier from $ip"
        );

        login_json_error(
            403,
            'Please verify your email before signing in. Check your inbox (and spam folder) for the verification link, or request a new one below.',
            [
                'error_code' => 'email_not_verified',
                'email' => $user['email'],
            ]
        );
    }

    // Rotate session ID on privilege change (login)
    SessionManager::regenerateSession();

    // Issue DB-persisted token for API clients
    $token = Security::generateToken(32);
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

    $tokenIssued = false;

    try {
        // Clean up expired sessions for this user (best-effort)
        $db->prepare("DELETE FROM sessions WHERE user_id = ? AND expires_at < NOW()")
            ->execute([$user['id']]);

        $db->prepare(
            "INSERT INTO sessions (user_id, token, expires_at, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([
            $user['id'],
            $token,
            $expires,
            $ip,
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);

        $tokenIssued = true;
    } catch (\Throwable $sessionErr) {
        // Most likely the `sessions` table is missing on this deploy.
        // Don't block login — web auth uses the PHP session.
        error_log('Session row insert failed (non-fatal, web auth will still work): ' . $sessionErr->getMessage());
    }

    // Populate session via SessionManager AFTER any DB writes.
    SessionManager::setUser($user);

    Security::logActivity(
        (int)$user['id'],
        'login',
        'Successful login from ' . $ip
    );

    // Rotate CSRF token after successful login.
    unset($_SESSION['csrf_token']);
    $newCsrfToken = Security::generateCSRFToken();

    $response = [
        'success' => true,
        'csrf_token' => $newCsrfToken,
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
    error_log('Login error: ' . $e->getMessage());

    login_json_error(500, 'Unable to sign in. Please try again later.');
}
