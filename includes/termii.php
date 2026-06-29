<?php
/**
 * KINAS GROUP — Termii SMS / OTP integration
 *
 * https://developers.termii.com
 *
 * Used for:
 *   - Phone verification OTP (registration, login, password reset)
 *   - Transactional SMS (KYC decisions, message notifications, listing events)
 *
 * Required env:
 *   TERMII_API_KEY
 *   TERMII_SENDER_ID            (e.g. "KINAS" or registered dedicated sender)
 *   TERMII_CHANNEL              (generic | dnd | whatsapp | voice)
 *   TERMII_ENABLED              (true | false)
 *
 * Optional:
 *   TERMII_API_BASE             (default https://api.ng.termii.com)
 */
class TermiiService
{
    private string $apiKey;
    private string $senderId;
    private string $channel;
    private string $apiBase;
    private bool   $enabled;

    public function __construct()
    {
        $this->apiKey   = self::env('TERMII_API_KEY', '');
        $this->senderId = self::env('TERMII_SENDER_ID', 'KINAS');
        $this->channel  = strtolower(self::env('TERMII_CHANNEL', 'generic'));
        $this->apiBase  = rtrim(self::env('TERMII_API_BASE', 'https://api.ng.termii.com'), '/');
        $this->enabled  = self::env('TERMII_ENABLED', 'true') !== 'false'
            && $this->apiKey !== '';
    }

    public function isEnabled(): bool { return $this->enabled; }
    public function getChannel(): string { return $this->channel; }
    public function getSenderId(): string { return $this->senderId; }

    // ──────────────────────────────────────────────────────────
    // OTP
    // ──────────────────────────────────────────────────────────

    /**
     * Send a numeric OTP via the "numeric" sender (no sender ID needed,
     * cheaper than the branded channel).
     *
     * @param string $phone E.164 (e.g. "+2348012345678") or local Nigerian
     * @param int    $length 4-8 digits (6 is standard)
     * @param int    $ttlMinutes
     * @param string $type "NUMERIC" or "ALPHANUMERIC" (Termii param)
     * @return array{success: bool, pin_id: ?string, message: ?string, code: ?string}
     */
    public function sendOtp(string $phone, int $length = 6, int $ttlMinutes = 10, string $type = 'NUMERIC'): array
    {
        // IMPORTANT: Termii generates and tracks the actual PIN internally.
        // `pin_placeholder` is just a template marker — whatever literal
        // string we put there must also appear in `message_text`, and
        // Termii substitutes ITS OWN generated pin in that spot before
        // sending. The value is never something we choose, so we must not
        // embed our own random code in the message (that's why every send
        // was failing with "No message available" — the placeholder never
        // actually appeared in the message text). Verification later must
        // go through Termii's /api/sms/otp/verify using the returned
        // pin_id, not by comparing against a code we made up ourselves.
        $placeholder = '<' . str_repeat('#', $length) . '>';
        $body = $this->buildOtpBody($ttlMinutes, $placeholder);

        $payload = [
            'api_key'      => $this->apiKey,
            'message_type' => 'NUMERIC',
            'to'           => $this->normalizePhone($phone),
            'from'         => $this->senderId,
            'channel'      => 'generic',
            'pin_attempts' => 5,
            'pin_time_to_live' => $ttlMinutes,
            'pin_length'   => $length,
            'pin_placeholder' => $placeholder,
            'message_text' => $body,
            'pin_type'     => $type,
        ];

        $res = $this->request('POST', '/api/sms/otp/send', $payload);

        // IMPORTANT: Termii's OTP-send endpoint does NOT return a `success`
        // field. A successful call returns something like:
        //   { "pinId": "...", "to": "...", "smsStatus": "Message Sent" }
        // Checking $res['success'] (which never exists) made every send
        // look like a failure even when Termii actually delivered the SMS.
        // We treat it as successful when a pinId was issued, and (if Termii
        // included a status string) that status doesn't explicitly say it
        // failed.
        $status = $res['smsStatus'] ?? '';
        $success = !empty($res['pinId']) && stripos($status, 'fail') === false;

        return [
            'success' => $success,
            'pin_id'  => $res['pinId'] ?? null,
            'message' => $status ?: ($res['message'] ?? null),
        ];
    }

    /**
     * Verify an OTP using Termii's verify endpoint.
     * Returns ['success' => bool, 'verified' => bool, 'message' => string].
     *
     * NOTE: We also verify the code locally against phone_otps.code_hash so
     * Termii being down doesn't lock out the user.
     */
    public function verifyOtp(string $pinId, string $code): array
    {
        $payload = [
            'api_key' => $this->apiKey,
            'pin_id'  => $pinId,
            'pin'     => $code,
        ];
        try {
            $res = $this->request('POST', '/api/sms/otp/verify', $payload);
            return [
                'success'  => true,
                'verified' => strtoupper($res['verified'] ?? '') === 'TRUE'
                    || ($res['code'] ?? null) === 'ok',
                'message'  => $res['message'] ?? '',
            ];
        } catch (Exception $e) {
            return ['success' => false, 'verified' => false, 'message' => $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────────────────
    // Transactional SMS
    // ──────────────────────────────────────────────────────────

    /**
     * Send a plain SMS (notification, marketing with consent, etc.).
     * Returns ['success' => bool, 'message_id' => ?string, 'error' => ?string].
     */
    public function sendSms(string $phone, string $message): array
    {
        $payload = [
            'api_key'    => $this->apiKey,
            'to'         => $this->normalizePhone($phone),
            'from'       => $this->senderId,
            'sms'        => $message,
            'type'       => $this->channel,
            'channel'    => $this->channel,
        ];
        try {
            $res = $this->request('POST', '/api/sms/send', $payload);
            return [
                'success'     => ($res['code'] ?? null) === 'ok',
                'message_id' => $res['message_id'] ?? null,
                'error'      => $res['message'] ?? null,
                'balance'    => $res['balance'] ?? null,
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a templated notification. If templates are configured in
     * .env (TERMII_TEMPLATE_*), uses Termii's templated endpoint; else
     * falls back to plain SMS.
     */
    public function sendNotification(string $phone, string $message, ?string $templateId = null): array
    {
        if ($templateId) {
            $payload = [
                'api_key'    => $this->apiKey,
                'phone'      => $this->normalizePhone($phone),
                'template_id'=> $templateId,
                'message'    => $message,
            ];
            try {
                $res = $this->request('POST', '/api/sms/template/send', $payload);
                return ['success' => ($res['code'] ?? null) === 'ok', 'message_id' => $res['message_id'] ?? null];
            } catch (Exception $e) {
                // Fall through to plain SMS
            }
        }
        return $this->sendSms($phone, $message);
    }

    // ──────────────────────────────────────────────────────────
    // Webhook (Termii delivery status callbacks)
    // ──────────────────────────────────────────────────────────

    /**
     * Termii does not sign webhooks the same way as MetaMap — they POST
     * a plain JSON body with a `message_id` and a status. We just verify
     * the message_id exists in our phone_otps table.
     */
    public function processDeliveryReport(array $body): array
    {
        $msgId = $body['message_id'] ?? $body['sms_id'] ?? null;
        $status = strtolower($body['status'] ?? '');
        if (!$msgId) return ['processed' => false, 'reason' => 'no_message_id'];

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE phone_otps SET termii_status = ? WHERE termii_message_id = ?");
        $stmt->execute([$status, $msgId]);
        return ['processed' => $stmt->rowCount() > 0, 'status' => $status];
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    public function generateCode(int $length = 6): string
    {
        $min = (int)('1' . str_repeat('0', $length - 1));
        $max = (int)str_repeat('9', $length);
        return (string)random_int($min, $max);
    }

    public function normalizePhone(string $phone): string
    {
        // Remove spaces, dashes, parens
        $p = preg_replace('/[\s\-\(\)]/', '', $phone);
        // If starts with 0, replace with +234
        if (str_starts_with($p, '0')) $p = '+234' . substr($p, 1);
        // If starts with 234 without +, add it
        if (preg_match('/^234\d+$/', $p)) $p = '+' . $p;
        return $p;
    }

    private function buildOtpBody(int $ttlMinutes, string $placeholder): string
    {
        return "Your KINAS GROUP verification code is {$placeholder}. "
             . "Valid for {$ttlMinutes} minutes. Do not share this code with anyone.";
    }

    private function request(string $method, string $path, array $body): array
    {
        $ch = curl_init($this->apiBase . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) throw new RuntimeException('Termii request failed: ' . $err);
        $decoded = json_decode($raw, true);
        if ($code >= 400) {
            $msg = $decoded['message'] ?? $raw;
            throw new RuntimeException('Termii ' . $method . ' ' . $path . ' → ' . $code . ': ' . (is_string($msg) ? $msg : json_encode($msg)));
        }
        return is_array($decoded) ? $decoded : [];
    }

    private static function env(string $key, string $default = ''): string
    {
        $val = getenv($key);
        if ($val === false || $val === '') $val = $_ENV[$key] ?? '';
        if ($val === '' || $val === null) {
            $envFile = __DIR__ . '/../.env';
            if (is_readable($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($k, $v) = explode('=', $line, 2);
                        if (trim($k) === $key) { $val = trim($v); break; }
                    }
                }
            }
        }
        return ($val === null || $val === '') ? $default : (string)$val;
    }
}
