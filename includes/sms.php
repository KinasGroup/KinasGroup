<?php
/**
 * KINAS GROUP — SMS Service (Termii Token API)
 *
 * Uses Termii's dedicated Token API for OTP send + verify:
 *   Send:   POST https://api.ng.termii.com/api/sms/otp/send
 *   Verify: POST https://api.ng.termii.com/api/sms/otp/verify
 *
 * Docs:
 *   https://developers.termii.com/send-token
 *   https://developers.termii.com/verify-token
 *
 * Required Railway environment variables:
 *   TERMII_API_KEY      — your Termii API key
 *   TERMII_SENDER_ID    — approved sender ID registered with Termii (default: KINAS)
 *   TERMII_CHANNEL      — dnd | generic | whatsapp (default: generic)
 */

class SMSService {

    private string $apiKey;
    private string $senderId;
    private string $channel;
    private string $baseUrl = 'https://api.ng.termii.com/api';

    public function __construct() {
        $this->apiKey   = getenv('TERMII_API_KEY')   ?: '';
        $this->senderId = getenv('TERMII_SENDER_ID') ?: 'KINAS';
        $this->channel  = getenv('TERMII_CHANNEL')   ?: 'generic';
    }

    /* ────────────────────────────────────────────────
       PUBLIC: Send OTP via Termii Token API
       Returns ['success' => true, 'pin_id' => '...']
            or ['success' => false, 'error' => '...']
    ──────────────────────────────────────────────── */
    public function sendOTP(string $phoneNumber): array {
        $phone = $this->normalisePhone($phoneNumber);
        if (!$phone) {
            return ['success' => false, 'error' => 'Invalid phone number format.'];
        }

        // Rate-limit: max 5 requests per hour per number
        if (!$this->canRequestOTP($phone)) {
            return ['success' => false, 'error' => 'Too many OTP requests. Please wait and try again.'];
        }

        if (empty($this->apiKey)) {
            error_log('Termii: TERMII_API_KEY not set in environment.');
            return ['success' => false, 'error' => 'SMS service not configured.'];
        }

        $placeholder = '< 000000 >';

        $payload = json_encode([
            'api_key'          => $this->apiKey,
            'message_type'     => 'NUMERIC',
            'to'               => $phone,
            'from'             => $this->senderId,
            'channel'          => $this->channel,
            'pin_attempts'     => 3,
            'pin_time_to_live' => 10,   // minutes — matches OTP_EXPIRY constant (600s)
            'pin_length'       => 6,
            'pin_placeholder'  => $placeholder,
            'message_text'     => "Your KINAS GROUP verification code is $placeholder. Valid for 10 minutes. Do not share this code.",
            'pin_type'         => 'NUMERIC',
        ]);

        $ch = curl_init("{$this->baseUrl}/sms/otp/send");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log("Termii sendOTP cURL error to {$phone}: {$curlErr}");
            return ['success' => false, 'error' => "Network error: {$curlErr}"];
        }

        $response = json_decode($raw, true);

        // Success response includes pinId / pin_id and smsStatus
        $pinId = $response['pinId'] ?? $response['pin_id'] ?? null;

        if ($httpCode === 200 && $pinId) {
            // Store pin_id against this phone so verifyOTP can retrieve it
            $this->storePinId($phone, $pinId);
            return ['success' => true, 'pin_id' => $pinId];
        }

        $errMsg = $response['message'] ?? $response['error'] ?? "HTTP {$httpCode}";
        error_log("Termii sendOTP failed to {$phone}: {$errMsg}");
        return ['success' => false, 'error' => $errMsg];
    }

    /* ────────────────────────────────────────────────
       PUBLIC: Verify OTP via Termii Token API
       Returns ['valid' => true]
            or ['valid' => false, 'error' => '...']
    ──────────────────────────────────────────────── */
    public function verifyOTP(string $phoneNumber, string $pin): array {
        $phone = $this->normalisePhone($phoneNumber);
        if (!$phone) {
            return ['valid' => false, 'error' => 'Invalid phone number.'];
        }

        $pinId = $this->getPinId($phone);
        if (!$pinId) {
            return ['valid' => false, 'error' => 'OTP expired or not found. Please request a new one.'];
        }

        if (empty($this->apiKey)) {
            return ['valid' => false, 'error' => 'SMS service not configured.'];
        }

        $payload = json_encode([
            'api_key' => $this->apiKey,
            'pin_id'  => $pinId,
            'pin'     => $pin,
        ]);

        $ch = curl_init("{$this->baseUrl}/sms/otp/verify");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['valid' => false, 'error' => "Network error: {$curlErr}"];
        }

        $response = json_decode($raw, true);

        // Termii returns { "pinId": "...", "verified": "True", "msisdn": "..." } on success
        $verified = $response['verified'] ?? '';
        if ($httpCode === 200 && strtolower((string)$verified) === 'true') {
            $this->deletePinId($phone);
            return ['valid' => true];
        }

        $errMsg = $response['message'] ?? $response['error'] ?? 'Verification failed.';
        return ['valid' => false, 'error' => $errMsg];
    }

    /* ────────────────────────────────────────────────
       PRIVATE: pin_id persistence (otp_codes table)
    ──────────────────────────────────────────────── */
    private function storePinId(string $phone, string $pinId): void {
        $db = Database::getInstance()->getConnection();
        $db->prepare("DELETE FROM otp_codes WHERE phone = ?")->execute([$phone]);
        $db->prepare(
            "INSERT INTO otp_codes (phone, pin_id, created_at, expires_at)
             VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 10 MINUTE))"
        )->execute([$phone, $pinId]);
    }

    private function getPinId(string $phone): string|false {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT pin_id FROM otp_codes
             WHERE phone = ? AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$phone]);
        $row = $stmt->fetch();
        return $row ? $row['pin_id'] : false;
    }

    private function deletePinId(string $phone): void {
        $db = Database::getInstance()->getConnection();
        $db->prepare("DELETE FROM otp_codes WHERE phone = ?")->execute([$phone]);
    }

    private function canRequestOTP(string $phone): bool {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM otp_codes
             WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute([$phone]);
        return (int)$stmt->fetchColumn() < 5;
    }

    /* ────────────────────────────────────────────────
       PRIVATE: Phone normalisation
    ──────────────────────────────────────────────── */
    /**
     * Normalise to E.164 digits (no +).
     * Handles: +2348012345678, 2348012345678, 08012345678, 8012345678
     */
    private function normalisePhone(string $phone): string|false {
        $phone = preg_replace('/[\s\-()]+/', '', $phone);

        if (preg_match('/^\+?234\d{10}$/', $phone)) {
            return ltrim($phone, '+');
        }
        if (preg_match('/^0\d{10}$/', $phone)) {
            return '234' . substr($phone, 1);
        }
        if (preg_match('/^\d{10}$/', $phone)) {
            return '234' . $phone;
        }
        if (preg_match('/^\+(\d{7,15})$/', $phone, $m)) {
            return $m[1];
        }

        return false;
    }
}
