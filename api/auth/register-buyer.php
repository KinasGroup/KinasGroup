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

    // Insert user as buyer (role = 'user')
    // Using 'verification_code' column (matches your table, not 'email_verification_code')
    $stmt = $db->prepare("
        INSERT INTO users (name, email, phone, password, role, status, verification_code, created_at)
        VALUES (?, ?, ?, ?, 'user', 'active', ?, NOW())
    ");
    $stmt->execute([$name, strtolower($email), $phone, $passwordHash, $verificationCode]);
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
    error_log('Registration PDO error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed. Please try again later.']);
} catch (Exception $e) {
    error_log('Registration error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed. Please try again later.']);
}
?>
