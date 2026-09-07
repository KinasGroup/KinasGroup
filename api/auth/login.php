<?php
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

$ip = Security::getClientIP();
Security::rateLimitDB('login_' . $ip, MAX_LOGIN_ATTEMPTS, LOGIN_TIMEOUT);

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    login_json_error(400, 'Invalid JSON data');
}

$identifier = strtolower(trim((string)($data['email'] ?? $data['username'] ?? '')));
$password = (string)($data['password'] ?? '');

$headerCsrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$csrfToken = trim((string)($data['csrf_token'] ?? $headerCsrf));

if ($csrfToken === '' || !Security::verifyCSRFToken($csrfToken)) {
    login_json_error(403, 'Please refresh the page and try again.');
}

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
        'http' => ['timeout' => 5, 'method' => 'GET'],
        'socket' => ['timeout' => 5],
    ]);

    $verifyResponse = @file_get_contents($captchaUrl, false, $captchaContext);

    if ($verifyResponse === false) {
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

$isEmail = strpos($identifier, '@') !== false;

if ($isEmail && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
    login_json_error(422, 'Please enter a valid email address.');
}

try {
    $db = Database::getInstance()->getConnection();

    if (!$db) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $stmt = $db->prepare(
        "SELECT id, name, username, email, password, role, verified, status, email_verified_at,
                deleted_at, deleted_by
         FROM users
         WHERE " . ($isEmail ? "email = ?" : "username = ?")
    );

    $stmt->execute([$identifier]);
    $user = $stmt->fetch();

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

    $status = (string)($user['status'] ?? 'active');

    // ============================================================
    // DELETED ACCOUNT — ALLOW LOGIN FOR REACTIVATION
    // ============================================================
    // If the account was self-deleted, allow the user to authenticate
    // but do NOT create a normal session. Instead, set a temporary
    // "pending reactivation" flag so the frontend can redirect to
    // the reactivation page.
    // ============================================================
    if ($status === 'deleted') {
        $deletedBy = (string)($user['deleted_by'] ?? 'self');

        // Only self-deleted accounts can be reactivated.
        // Admin-deleted accounts must contact support.
        if ($deletedBy === 'admin') {
            login_json_error(403, 'Your account was deactivated by an administrator. Please contact support to restore it.');
        }

        // Set a temporary session flag for reactivation.
        // Do NOT call SessionManager::setUser() — the account is not active yet.
        $_SESSION['pending_reactivation_user_id'] = (int)$user['id'];
        $_SESSION['pending_reactivation_email'] = (string)$user['email'];
        $_SESSION['pending_reactivation_name'] = (string)$user['name'];

        Security::logActivity(
            (int)$user['id'],
            'login_deleted_account',
            "Deleted account login for reactivation: $identifier from $ip"
        );

        echo json_encode([
            'success' => true,
            'requires_reactivation' => true,
            'csrf_token' => Security::generateCSRFToken(),
            'message' => 'Your account was deleted. You can reactivate it.',
        ]);

        exit;
    }

    if ($status !== 'active') {
        $statusMessages = [
            'suspended' => 'Your account has been suspended. Please contact support.',
            'inactive' => 'Your account is inactive. Please contact support.',
        ];

        $statusMessage = $statusMessages[$status] ?? 'Your account is ' . $status . '. Please contact support.';

        login_json_error(403, $statusMessage);
    }

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

    SessionManager::regenerateSession();

    $token = Security::generateToken(32);
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

    $tokenIssued = false;

    try {
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
        error_log('Session row insert failed (non-fatal, web auth will still work): ' . $sessionErr->getMessage());
    }

    SessionManager::setUser($user);

    Security::logActivity(
        (int)$user['id'],
        'login',
        'Successful login from ' . $ip
    );

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
