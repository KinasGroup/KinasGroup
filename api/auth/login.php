<?php
header('Content-Type: application/json');
require_once '../config/env.php';
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

// CORS headers for API access (adjust origin in production)
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

// Validate CSRF token — require it to be present and correct
if (empty($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Please refresh the page and try again.']);
    exit;
}

if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Please refresh the page and try again.']);
    exit;
}

// Rotate CSRF token after successful validation (only now, after it passed)
unset($_SESSION['csrf_token']);

// CAPTCHA verification (skip if not configured)
$captchaToken = $data['captcha_token'] ?? '';
$captchaSecretKey = $_ENV['CAPTCHA_SECRET_KEY'] ?? getenv('CAPTCHA_SECRET_KEY') ?? '';
$captchaEnabled = !empty($captchaSecretKey) && $captchaSecretKey !== '6LeXXXXXXXXXXXXXXXXXXXXXXXX';

if ($captchaEnabled) {
    if (empty($captchaToken)) {
        http_response_code(422);
        echo json_encode(['error' => 'Please complete the CAPTCHA verification.']);
        exit;
    }
    // Verify with Google reCAPTCHA
    $verifyResponse = @file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify?' . http_build_query([
            'secret' => $captchaSecretKey,
            'response' => $captchaToken,
            'remoteip' => $ip
        ])
    );
    if ($verifyResponse === false) {
        // Network failure reaching Google — fail open with a log entry
        error_log('reCAPTCHA verification network failure for IP: ' . $ip);
    } else {
        $verifyData = json_decode($verifyResponse, true);
        if (!$verifyData || !$verifyData['success']) {
            http_response_code(422);
            echo json_encode(['error' => 'CAPTCHA verification failed. Please try again.']);
            exit;
        }
    }
}

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Always use consistent timing to prevent user enumeration timing attacks
    $stmt = $db->prepare(
        "SELECT id, name, email, password, role, verified, status FROM users WHERE email = ?"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Use constant-time password verification
    $passwordHash = $user['password'] ?? '';
    $passwordValid = password_verify($password, $passwordHash);

    // Always perform a dummy hash to maintain consistent timing
    if (!$user) {
        password_hash('dummy_password_for_timing', PASSWORD_BCRYPT, ['cost' => 12]);
    }

    if (!$user || !password_verify($password, $user['password'])) {
        Security::logActivity($user['id'] ?? null, 'login_failed', "Failed login attempt for: $email from $ip");
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    // Check user status
    if ($user['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['error' => 'Account is ' . $user['status']]);
        exit;
    }

    // Rotate session ID on privilege change (login)
    SessionManager::regenerateSession();

    // Populate session via SessionManager
    SessionManager::setUser($user);

    // Issue DB-persisted token for API clients
    $token = Security::generateToken(32);
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

    // Clean up old sessions for this user
    $db->prepare("DELETE FROM sessions WHERE user_id = ? AND expires_at < NOW()")->execute([$user['id']]);
    $db->prepare("INSERT INTO sessions (user_id, token, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)")
       ->execute([$user['id'], $token, $expires, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '']);

    Security::logActivity($user['id'], 'login', 'Successful login from ' . $ip);

    // Generate new CSRF token for next request
    $newCsrfToken = Security::generate_csrf_token();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'token' => $token,
        'csrf_token' => $newCsrfToken,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'verified' => (bool)$user['verified'],
        ],
    ]);

} catch (\Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Login failed']);
}
