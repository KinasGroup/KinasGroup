<?php
header('Content-Type: application/json');

// Load environment variables from .env file
require_once __DIR__ . '/../../includes/dotenv.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/validation.php';
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

// IP-based rate limiting (DB-backed)
$ip = Security::getClientIP();
Security::rateLimitDB('register_' . $ip, 3, 3600);

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// CSRF token validation
$csrfToken = $data['csrf_token'] ?? '';
if (empty($csrfToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Please refresh the page and try again.']);
    exit;
}

// Rotate CSRF token after successful validation
unset($_SESSION['csrf_token']);

// api/auth/login.php, api/auth/register.php, api/auth/register-buyer.php
// REPLACE the captcha verification block (the part starting at
// "// CAPTCHA verification..." through the if ($captchaEnabled && !empty...)
// block) with this:

require_once __DIR__ . '/../../includes/captcha.php';
$__captcha = captcha_get_keys();
$captchaSecretKey = $__captcha['secret_key'];
$captchaSiteKey   = $__captcha['site_key'];
$captchaEnabled   = $__captcha['is_configured'];
$captchaHost      = $__captcha['host']; // for logging

if ($captchaEnabled) {
    $captchaToken = $data['captcha_token'] ?? '';
    if (empty($captchaToken)) {
        http_response_code(422);
        echo json_encode(['error' => 'CAPTCHA verification required']);
        exit;
    }
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

// Map frontend division values to database values
$divisionMap = [
    'automobile' => 'kinas-automobile',
    'real_estate' => 'williams-connect-home',
    'solar' => 'kinas-volt',
    'marketplace' => 'kinas-marketplace'
];

// Convert division if needed
$originalDivision = $data['division'] ?? '';
$data['division'] = $divisionMap[$originalDivision] ?? $originalDivision;

// Validate input
$validator = new Validator();
$rules = [
    'name' => 'required|min:2|max:100',
    'email' => 'required|email',
    'phone' => 'required',
    'password' => 'required|min:8',
    'password_confirmation' => 'required',
    'division' => 'required'
];

if (!$validator->validate($data, $rules)) {
    http_response_code(422);
    echo json_encode([
        'error' => 'Validation failed',
        'errors' => $validator->getErrors()
    ]);
    exit;
}

// Verify password confirmation
if ($data['password'] !== $data['password_confirmation']) {
    http_response_code(422);
    echo json_encode(['error' => 'Passwords do not match']);
    exit;
}

// Validate division
$validDivisions = ['kinas-automobile', 'williams-connect-home', 'kinas-volt', 'kinas-marketplace'];
if (!in_array($data['division'], $validDivisions, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid division selected']);
    exit;
}

// Password strength validation
if (!preg_match('/[A-Z]/', $data['password'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Password must contain at least one uppercase letter']);
    exit;
}

if (!preg_match('/[a-z]/', $data['password'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Password must contain at least one lowercase letter']);
    exit;
}

if (!preg_match('/[0-9]/', $data['password'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Password must contain at least one number']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([strtolower(trim($data['email']))]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'An account with this email already exists']);
        exit;
    }

    // DNS-based deliverability check on the email domain. PHP's mail()
    // returns true even for undeliverable addresses, so without this we
    // would happily create agent accounts for fake domains. Reject
    // before opening the transaction so the rollback path doesn't need
    // to fire.
    $deliverableError = Security::checkEmailDeliverable($data['email'] ?? '');
    if ($deliverableError !== null) {
        http_response_code(422);
        echo json_encode(['error' => $deliverableError]);
        exit;
    }

    $db->beginTransaction();

    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    $verificationCode = bin2hex(random_bytes(32));
    $verificationExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Insert user — division is stored in agent_profiles, not the users table.
    // verification_code + verification_code_expires MUST be persisted here;
    // otherwise the link emailed to the user can never resolve (the
    // verify-email lookup matches on verification_code alone).
    $stmt = $db->prepare("
        INSERT INTO users
            (name, email, password, phone, role, status,
             verification_code, verification_code_expires, created_at)
        VALUES
            (?, ?, ?, ?, 'agent', 'active',
             ?, ?, NOW())
    ");
    $stmt->execute([
        Security::sanitizeInput($data['name']),
        strtolower(trim($data['email'])),
        $hashedPassword,
        Security::sanitizeInput($data['phone']),
        $verificationCode,
        $verificationExpiry,
    ]);

    $userId = $db->lastInsertId();

    // Create agent profile
    $stmt = $db->prepare("
        INSERT INTO agent_profiles (user_id, division, verification_status, created_at)
        VALUES (?, ?, 'pending', NOW())
    ");
    $stmt->execute([$userId, $data['division']]);

    // Send verification email (REQUIRED — registration only succeeds if delivery succeeds)
    $emailSent = false;
    $emailError = 'Email service is not configured.';
    try {
        if (class_exists('EmailService')) {
            $emailService = new EmailService();
            $emailSent = (bool) $emailService->sendVerificationEmail($data['email'], $data['name'], $verificationCode);
            if (!$emailSent) {
                $emailError = 'We were unable to deliver the verification email to the address you provided. Please double-check the email and try again.';
            }
        } else {
            $emailSent = false;
            $emailError = 'Email service is unavailable. Please try again later.';
        }
    } catch (Exception $e) {
        error_log('Email sending failed: ' . $e->getMessage());
        $emailSent = false;
        $emailError = 'We were unable to deliver the verification email. Please try again later.';
    }

    if (!$emailSent) {
        // Roll back the user + agent profile so a fake/invalid email cannot create an account
        $db->rollBack();
        http_response_code(502);
        echo json_encode(['error' => $emailError]);
        exit;
    }

    // Log activity
    Security::logActivity($userId, 'agent_registration', "New agent registration for {$data['division']} from $ip");

    $db->commit();

    // Generate new CSRF token for next request
    $newCsrfToken = Security::generate_csrf_token();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'csrf_token' => $newCsrfToken,
        'message' => 'Registration successful! Please login to continue.',
        'user_id' => $userId
    ]);

} catch (PDOException $e) {
    if (isset($db)) $db->rollBack();
    error_log('Registration error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed. Please try again.']);
} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    error_log('Registration error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed. Please try again.']);
}
?>
