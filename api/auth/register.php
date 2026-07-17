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
$__captcha = capt
