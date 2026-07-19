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

$db->beginTransaction();

try {
    // Find the most recent unconsumed, unexpired OTP for this user+phone.
    // FOR UPDATE locks the row so a second, near-simultaneous verify
    // request (e.g. triggered by both the paste auto-submit and a manual
    // click/Enter) has to wait for this transaction to finish instead of
    // racing it — see the note above for why that race mattered.
    $sql = "SELECT id, code_hash, attempts, max_attempts, expires_at, phone, termii_message_id, consumed_at
            FROM phone_otps
            WHERE expires_at > NOW()
              " . ($userId ? "AND user_id = ?" : "AND user_id IS NULL") . "
              " . ($phone ? "AND phone = ?" : "") . "
            ORDER BY id DESC LIMIT 1
            FOR UPDATE";
    $stmt = $db->prepare($sql);
    $params = [];
    if ($userId) $params[] = $userId;
    if ($phone)  $params[] = $phone;
    $stmt->execute($params);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$otp) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'No active verification code. Please request a new one.']);
        exit;
    }

    // Already consumed by an earlier request. Could be a genuine success
    // (the race described above — a duplicate request for the same
    // already-verified code should look like success, not "incorrect")
    // or it could have been consumed because attempts ran out. Tell them
    // apart by checking whether verification actually completed.
    if ($otp['consumed_at'] !== null) {
        $alreadyVerified = false;
        if ($userId) {
            $checkStmt = $db->prepare("SELECT phone_verified_at FROM users WHERE id = ?");
            $checkStmt->execute([$userId]);
            $alreadyVerified = (bool)$checkStmt->fetchColumn();
        }

        $db->commit();
        if ($alreadyVerified) {
            echo json_encode([
                'success' => true,
                'message' => 'Phone number verified successfully.',
            ]);
        } else {
            http_response_code(429);
            echo json_encode(['error' => 'Too many attempts. Please request a new code.']);
        }
        exit;
    }

    if ((int)$otp['attempts'] >= (int)$otp['max_attempts']) {
        $db->prepare("UPDATE phone_otps SET consumed_at = NOW() WHERE id = ?")->execute([$otp['id']]);
        $db->commit();
        http_response_code(429);
        echo json_encode(['error' => 'Too many attempts. Please request a new code.']);
        exit;
    }

    // Verify the code. If this OTP went out via Termii, Termii itself
    // generated the actual PIN that was texted — our code_hash is not the
    // real value the user received, so we must check with Termii directly.
    // If Termii wasn't used (disabled/dev mode) or its API call errors out,
    // fall back to the local hash so an outage doesn't lock the user out.
    $codeIsValid = false;

    if (!empty($otp['termii_message_id'])) {
        require_once '../../includes/termii.php';
        $termii = new TermiiService();
        $tv = $termii->verifyOtp($otp['termii_message_id'], $code);

        if ($tv['success']) {
            $codeIsValid = (bool)$tv['verified'];
        } else {
            // Termii's verify API itself failed (e.g. network/outage) — don't
            // lock the user out; fall back to the local record.
            $codeIsValid = password_verify($code, $otp['code_hash']);
        }
    } else {
        $codeIsValid = password_verify($code, $otp['code_hash']);
    }

    if (!$codeIsValid) {
        $db->prepare("UPDATE phone_otps SET attempts = attempts + 1 WHERE id = ?")->execute([$otp['id']]);
        $left = (int)$otp['max_attempts'] - ((int)$otp['attempts'] + 1);
        $db->commit();
        http_response_code(400);
        echo json_encode(['error' => "Incorrect code. {$left} attempt" . ($left === 1 ? '' : 's') . ' left.']);
        exit;
    }

    // Mark consumed
    $db->prepare("UPDATE phone_otps SET consumed_at = NOW() WHERE id = ?")->execute([$otp['id']]);

    // Mark phone verified on the user
    if ($userId) {
        // $otp['phone'] is the ground truth — it's the exact number Termii
        // just cryptographically verified a code against. The old logic
        // here trusted whatever the client's request body claimed ($phone)
        // instead, and silently fell back to leaving users.phone untouched
        // whenever that body param was omitted — meaning an account could
        // end up "phone verified" while users.phone still showed a totally
        // different, NEVER-verified number. Always sync to $otp['phone'].
        $currentPhoneStmt = $db->prepare("SELECT phone FROM users WHERE id = ? FOR UPDATE");
        $currentPhoneStmt->execute([$userId]);
        $currentPhone = $currentPhoneStmt->fetchColumn();
        $currentPhoneDigits = $currentPhone ? preg_replace('/\D+/', '', $currentPhone) : '';
        $otpPhoneDigits = preg_replace('/\D+/', '', $otp['phone']);

        if ($currentPhoneDigits !== '' && $currentPhoneDigits !== $otpPhoneDigits && $purpose !== 'change_phone') {
            // Red flag: this OTP was requested for a DIFFERENT number than
            // what's currently on the account, and this isn't an explicit
            // "change my number" request from account settings. Reject —
            // don't silently let phone verification double as an
            // unauthorized number swap.
            $db->rollBack();
            http_response_code(409);
            echo json_encode([
                'error' => 'This code was sent to a different number than the one on your account. '
                    . 'To verify a different phone number, update it in Account Settings first.',
            ]);
            exit;
        }

        // Belt-and-suspenders: re-check no OTHER account has already
        // claimed and verified this exact number in the meantime.
        // Normalized comparison — see send-otp.php for why.
        $dupDigits = preg_replace('/\D+/', '', $otp['phone']);
        $dupStmt = $db->prepare("SELECT id FROM users WHERE REGEXP_REPLACE(phone, '[^0-9]', '') = ? AND phone_verified_at IS NOT NULL AND id != ? LIMIT 1");
        $dupStmt->execute([$dupDigits, $userId]);
        if ($dupStmt->fetchColumn()) {
            $db->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'This phone number is already verified on another account.']);
            exit;
        }

        $db->prepare("UPDATE users SET phone = ?, phone_verified_at = NOW() WHERE id = ?")
            ->execute([$otp['phone'], $userId]);

        $db->prepare("UPDATE agent_profiles
                      SET verification_status = 'phone_verified'
                      WHERE user_id = ? AND verification_status = 'pending'")
            ->execute([$userId]);
    }

    $db->commit();

    if ($userId) {
        Security::logActivity($userId, 'phone_verified', "Phone verified ending " . substr(preg_replace('/\D+/', '', $otp['phone']), -4));
    }

    echo json_encode([
        'success' => true,
        'message' => 'Phone number verified successfully.',
    ]);

} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('verify-otp.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong. Please try again.']);
}
