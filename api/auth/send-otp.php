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
    echo json_encode(['error' => 'Please enter a valid phone number (e.g., 08012345678).']);
    exit;
}

// Format phone for display
$displayPhone = $digits;
if (strlen($displayPhone) === 11) {
    $displayPhone = substr($displayPhone, 0, 4) . '***' . substr($displayPhone, -4);
} else {
    $displayPhone = substr($displayPhone, 0, 3) . '***' . substr($displayPhone, -4);
}

// Rate limit: 1 per 30s, 5 per hour
$bucket = 'otp_' . $digits;
$rate30 = Security::rateLimitDB($bucket . '_30s', 1, 30);
$rate1h = Security::rateLimitDB($bucket . '_1h', 5, 3600);

if (!$rate30) { 
    http_response_code(429); 
    echo json_encode(['error' => 'Please wait 30 seconds before requesting a new code.']); 
    exit; 
}
if (!$rate1h) { 
    http_response_code(429); 
    echo json_encode(['error' => 'Too many requests. Please try again in an hour.']); 
    exit; 
}

// Invalidate any unconsumed OTPs for this phone
if ($userId) {
    $db->prepare("UPDATE phone_otps SET consumed_at = NOW() WHERE user_id = ? AND phone = ? AND consumed_at IS NULL")
        ->execute([$userId, $phone]);
}

$termii = new TermiiService();

// Generate a random OTP
$otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

try {
    // Try to send via Termii if enabled
    if ($termii->isEnabled()) {
        $result = $termii->sendOtp($phone, 6, 15);
        if (!$result['success']) {
            throw new RuntimeException($result['message'] ?? 'Termii send failed');
        }
        $pinId = $result['pin_id'] ?? null;
        // IMPORTANT: Termii generates and texts its own PIN internally — it
        // does not use our local $otpCode at all. The code below still
        // hashes $otpCode and stores it (so the NOT NULL code_hash column
        // is satisfied and the dev/local fallback path below still works),
        // but the authoritative check for this OTP happens in
        // verify-otp.php via Termii's own /api/sms/otp/verify using
        // $pinId, not by comparing against $otpCode.
    } else {
        // Termii not enabled - log the OTP for development
        error_log("OTP [dev] phone={$phone} code={$otpCode} purpose={$purpose}");
        $pinId = null;
    }

    $codeHash = password_hash($otpCode, PASSWORD_BCRYPT);

    $insert = $db->prepare("
        INSERT INTO phone_otps
            (user_id, phone, code_hash, purpose, max_attempts, termii_message_id, expires_at, ip_address, created_at)
        VALUES (?, ?, ?, ?, 5, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), ?, NOW())
    ");
    $insert->execute([
        $userId,
        $phone,
        $codeHash,
        $purpose,
        $pinId,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    Security::logActivity((int)($userId ?? 0), 'otp_sent', "OTP sent to phone ending {$displayPhone}");

    $response = [
        'success' => true,
        'message' => 'Verification code sent to your phone.',
    ];

    // Include the OTP in development mode for testing
    $appEnv = getenv('APP_ENV') ?: 'production';
    if ($appEnv === 'development' || $appEnv === 'local') {
        $response['_dev_code'] = $otpCode;
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log('send-otp error: ' . $e->getMessage());
    
    // Even if SMS fails, store the OTP for testing purposes
    $codeHash = password_hash($otpCode, PASSWORD_BCRYPT);
    $insert = $db->prepare("
        INSERT INTO phone_otps
            (user_id, phone, code_hash, purpose, max_attempts, expires_at, ip_address, created_at)
        VALUES (?, ?, ?, ?, 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), ?, NOW())
    ");
    $insert->execute([
        $userId,
        $phone,
        $codeHash,
        $purpose,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $appEnv = getenv('APP_ENV') ?: 'production';
    if ($appEnv === 'development' || $appEnv === 'local') {
        echo json_encode([
            'success' => true,
            'message' => 'SMS service not available. Use code: ' . $otpCode,
            '_dev_code' => $otpCode
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Could not send verification code. Please try again.']);
    }
}
