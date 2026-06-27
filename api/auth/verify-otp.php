<?php
/**
 * KINAS GROUP — Verify phone OTP
 *
 * POST /api/auth/verify-otp.php
 *   body: { csrf_token, phone?, code, purpose }
 *
 * Verifies locally against the bcrypt hash in phone_otps.
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

$body  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$token = $body['csrf_token'] ?? '';

if (!Security::verifyCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$code = preg_replace('/\D+/', '', (string)($body['code'] ?? ''));
if (strlen($code) !== 6) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter the 6-digit code we sent.']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$purpose = in_array($body['purpose'] ?? '', ['register','login','reset','change_phone'], true)
    ? $body['purpose'] : 'register';
$phone = trim($body['phone'] ?? '');

$db = Database::getInstance()->getConnection();

// Find the most recent unconsumed, unexpired OTP for this user+phone
$sql = "SELECT id, code_hash, attempts, max_attempts, expires_at, phone
        FROM phone_otps
        WHERE consumed_at IS NULL
          AND expires_at > NOW()
          " . ($userId ? "AND user_id = ?" : "AND user_id IS NULL") . "
          " . ($phone ? "AND phone = ?" : "") . "
        ORDER BY id DESC LIMIT 1";
$stmt = $db->prepare($sql);
$params = [];
if ($userId) $params[] = $userId;
if ($phone)  $params[] = $phone;
$stmt->execute($params);
$otp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$otp) {
    http_response_code(400);
    echo json_encode(['error' => 'No active verification code. Please request a new one.']);
    exit;
}

if ((int)$otp['attempts'] >= (int)$otp['max_attempts']) {
    $db->prepare("UPDATE phone_otps SET consumed_at = NOW() WHERE id = ?")->execute([$otp['id']]);
    http_response_code(429);
    echo json_encode(['error' => 'Too many attempts. Please request a new code.']);
    exit;
}

if (!password_verify($code, $otp['code_hash'])) {
    $db->prepare("UPDATE phone_otps SET attempts = attempts + 1 WHERE id = ?")->execute([$otp['id']]);
    $left = (int)$otp['max_attempts'] - ((int)$otp['attempts'] + 1);
    http_response_code(400);
    echo json_encode(['error' => "Incorrect code. {$left} attempt" . ($left === 1 ? '' : 's') . ' left.']);
    exit;
}

// Mark consumed
$db->prepare("UPDATE phone_otps SET consumed_at = NOW() WHERE id = ?")->execute([$otp['id']]);

// Mark phone verified on the user
if ($userId) {
    if ($phone && $phone !== $otp['phone']) {
        $db->prepare("UPDATE users SET phone = ?, phone_verified_at = NOW() WHERE id = ?")
            ->execute([$phone, $userId]);
    } else {
        $db->prepare("UPDATE users SET phone_verified_at = COALESCE(phone_verified_at, NOW()) WHERE id = ?")
            ->execute([$userId]);
    }

    $db->prepare("UPDATE agent_profiles
                  SET verification_status = 'phone_verified'
                  WHERE user_id = ? AND verification_status = 'pending'")
        ->execute([$userId]);

    Security::logActivity($userId, 'phone_verified', "Phone verified ending " . substr(preg_replace('/\D+/', '', $otp['phone']), -4));
}

echo json_encode([
    'success' => true,
    'message' => 'Phone number verified successfully.',
]);
