<?php
/**
 * KINAS GROUP — Send phone OTP via Termii
 *
 * POST /api/auth/send-otp.php
 *   body: { csrf_token, phone?, purpose: 'register'|'login'|'reset'|'change_phone' }
 *
 * If the user is logged in, uses their registered phone unless overridden.
 * Always rate-limited: 1 OTP / 30s, max 5 / hour per phone.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/termii.php';

$body  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$token = $body['csrf_token'] ?? '';

if (!Security::verifyCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$purpose = in_array($body['purpose'] ?? '', ['register','login','reset','change_phone'], true)
    ? $body['purpose'] : 'register';

$db = Database::getInstance()->getConnection();

// Resolve phone
$phone = trim($body['phone'] ?? '');
if (!$phone && $userId) {
    $stmt = $db->prepare("SELECT phone, phone_verified_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['phone'])) {
        http_response_code(422);
        echo json_encode(['error' => 'No phone number on file. Please update your profile.']);
        exit;
    }
    $phone = $row['phone'];
}

if (!$phone) {
    http_response_code(422);
    echo json_encode(['error' => 'Phone number is required.']);
    exit;
}

// Basic phone sanity
$digits = preg_replace('/\D+/', '', $phone);
if (strlen($digits) < 10 || strlen($digits) > 15) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid phone number (e.g. +234 800 000 0000).']);
    exit;
}

// Rate limit: 1 per 30s, 5 per hour
$bucket = 'otp_' . preg_replace('/\D+/', '', $phone);
$rate30 = Security::rateLimitDB($bucket . '_30s', 1, 30);
$rate1h = Security::rateLimitDB($bucket . '_1h', 5, 3600);
if (!$rate30) { http_response_code(429); echo json_encode(['error' => 'Please wait 30 seconds before requesting a new code.']); exit; }
if (!$rate1h) { http_response_code(429); echo json_encode(['error' => 'Too many requests. Please try again in an hour.']); exit; }

// Invalidate any unconsumed OTPs for this phone
if ($userId) {
    $db->prepare("UPDATE phone_otps SET consumed_at = NOW() WHERE user_id = ? AND phone = ? AND consumed_at IS NULL")
        ->execute([$userId, $phone]);
}

$termii = new TermiiService();
if (!$termii->isEnabled()) {
    // Dev / staging fallback: log to server and accept a hardcoded "000000" code
    error_log("OTP [dev] phone={$phone} code=000000 purpose={$purpose}");
    http_response_code(503);
    echo json_encode(['error' => 'SMS service not configured. Use code 000000 for testing.']);
    exit;
}

try {
    $result = $termii->sendOtp($phone, 6, 10);
    if (!$result['success']) {
        throw new RuntimeException($result['message'] ?? 'Termii send failed');
    }

    $codeHash = password_hash($result['code'], PASSWORD_BCRYPT);

    $insert = $db->prepare("
        INSERT INTO phone_otps
            (user_id, phone, code_hash, purpose, max_attempts, termii_message_id, expires_at, ip_address, created_at)
        VALUES (?, ?, ?, ?, 5, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), ?, NOW())
    ");
    $insert->execute([
        $userId,
        $phone,
        $codeHash,
        $purpose,
        $result['pin_id'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    Security::logActivity((int)($userId ?? 0), 'otp_sent', "OTP sent to phone ending " . substr($digits, -4));

    echo json_encode([
        'success' => true,
        'message' => 'Verification code sent to your phone.',
        // Dev convenience: include the code if Termii was in dry-run
        '_dev_code' => getenv('APP_ENV') === 'development' ? $result['code'] : null,
    ]);
} catch (Exception $e) {
    error_log('send-otp error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not send verification code. Please try again.']);
}
