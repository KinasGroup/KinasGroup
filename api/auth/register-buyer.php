<?php
header('Content-Type: application/json');

// Load environment variables from .env file
require_once __DIR__ . '/../../includes/dotenv.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/email.php';

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

$ip = Security::getClientIP();

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// ============================================
// CSRF TOKEN VALIDATION - ADDED
// ============================================
$csrfToken = $data['csrf_token'] ?? '';
if (empty($csrfToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Please refresh the page and try again.']);
    exit;
}

// Rotate CSRF token after successful validation
unset($_SESSION['csrf_token']);

// ============================================
// CAPTCHA VERIFICATION - ADDED
// ============================================
$captchaToken = $data['captcha_token'] ?? '';
$captchaSecretKey = $_ENV['CAPTCHA_SECRET_KEY'] ?? getenv('CAPTCHA_SECRET_KEY') ?? '';
$captchaEnabled = !empty($captchaSecretKey) && $captchaSecretKey !== '6LeXXXXXXXXXXXXXXXXXXXXXXXX';

if ($captchaEnabled && empty($captchaToken)) {
    http_response_code(422);
    echo json_encode(['error' => 'CAPTCHA verification required']);
    exit;
}

if ($captchaEnabled && !empty($captchaToken)) {
    $verifyResponse = @file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify?' . http_build_query([
            'secret' => $captchaSecretKey,
            'response' => $captchaToken,
            'remoteip' => $ip
        ])
    );
    if ($verifyResponse !== false) {
        $verifyData = json_decode($verifyResponse, true);
        if (!$verifyData || !$verifyData['success']) {
            http_response_code(422);
            echo json_encode(['error' => 'CAPTCHA verification failed. Please try again.']);
            exit;
        }
    }
}

// Extract and trim fields
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$password = $data['password'] ?? '';
$passwordConfirmation = $data['password_confirmation'] ?? '';

// Simple validation
$errors = [];

if (strlen($name) < 2) {
    $errors[] = 'Name must be at least 2 characters';
}
if (strlen($name) > 100) {
    $errors[] = 'Name must not exceed 100 characters';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address';
}
if (!preg_match('/^\+?[\d\s\-\(\)]{7,15}$/', $phone)) {
    $errors[] = 'Please enter a valid phone number';
}
if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters';
}
if (!preg_match('/[A-Z]/', $password)) {
    $errors[] = 'Password must contain at least one uppercase letter';
}
if (!preg_match('/[a-z]/', $password)) {
    $errors[] = 'Password must contain at least one lowercase letter';
}
if (!preg_match('/[0-9]/', $password)) {
    $errors[] = 'Password must contain at least one number';
}
if ($password !== $passwordConfirmation) {
    $errors[] = 'Passwords do not match';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['error' => 'Validation failed', 'errors' => $errors]);
    exit;
}

// DNS-based deliverability check: rejects addresses on domains that
// have no MX and no A record. PHP's mail() returns true even for
// undeliverable addresses (it hands off to the local MTA and lies), so
// without this check we were creating accounts for any string that
// passed filter_var(FILTER_VALIDATE_EMAIL) — the user could register
// with `fake@thisdomainreallydoesnotexist.com` and the rollback guard
// in the email-send path was never triggered.
$deliverableError = Security::checkEmailDeliverable($email);
if ($deliverableError !== null) {
    http_response_code(422);
    echo json_encode(['error' => $deliverableError]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    if (!$db) {
        error_log('Registration error: Database connection is null');
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }

    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([strtolower($email)]);
    if ($stmt->fetch()) {
        http_response_code(422);
        echo json_encode(['error' => 'An account with this email already exists']);
        exit;
    }

    // Generate verification code
    $verificationCode = Security::generateToken(32);
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $verificationExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $db->beginTransaction();

    // Insert user as buyer (role = 'user').
    // The 24h expiry is enforced server-side in auth/verify-email.php —
    // the email body advertises "this link will expire in 24 hours".
    $stmt = $db->prepare("
        INSERT INTO users
            (name, email, phone, password, role, status,
             verification_code, verification_code_expires, created_at)
        VALUES
            (?, ?, ?, ?, 'user', 'active',
             ?, ?, NOW())
    ");
    $stmt->execute([
        $name,
        strtolower($email),
        $phone,
        $passwordHash,
        $verificationCode,
        $verificationExpiry,
    ]);
    $userId = $db->lastInsertId();

    // Send verification email (REQUIRED — registration only succeeds if delivery succeeds)
    $emailSent = false;
    $emailError = 'Email service is unavailable. Please try again later.';
    try {
        $emailService = new EmailService();
        $emailSent = (bool) $emailService->sendVerificationEmail(strtolower($email), $name, $verificationCode);
        if (!$emailSent) {
            $emailError = 'We were unable to deliver the verification email to the address you provided. Please double-check the email and try again.';
        }
    } catch (Exception $e) {
        error_log('Email sending failed: ' . $e->getMessage());
        $emailSent = false;
        $emailError = 'We were unable to deliver the verification email. Please try again later.';
    }

    if (!$emailSent) {
        // Roll back the user insert so a fake/invalid email cannot create an account
        $db->rollBack();
        http_response_code(502);
        echo json_encode(['error' => $emailError]);
        exit;
    }

    // Log registration
    Security::logActivity($userId, 'buyer_registration', "New buyer registered: $email from $ip");

    $db->commit();

    // Generate new CSRF token
    $newCsrfToken = Security::generate_csrf_token();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'csrf_token' => $newCsrfToken,
        'message' => 'Account created successfully! Please login to continue.',
        'user_id' => $userId
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Registration PDO error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed. Please try again later.']);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Registration error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed. Please try again later.']);
}
?>
