<?php
/**
 * KINAS GROUP — Paystack payment integration
 *
 * https://paystack.com/docs/
 *
 * Golden rule this class enforces:
 *   1. Transactions are ALWAYS initialized server-side, using the
 *      SECRET key. The frontend only ever sees the `access_code`
 *      returned by initializeTransaction() and the PUBLIC key.
 *   2. We NEVER trust a client-side "payment successful" callback on
 *      its own. Before any value is delivered (marking a listing
 *      sold, crediting an agent), verifyTransaction() must be called
 *      from the server, and the amount/currency/status it returns
 *      must be checked against what we expect.
 *   3. Webhooks are HMAC-verified using verifyWebhookSignature()
 *      before the payload is trusted for anything.
 *
 * Required env:
 *   PAYSTACK_SECRET_KEY   (sk_live_... / sk_test_...)
 *   PAYSTACK_PUBLIC_KEY   (pk_live_... / pk_test_...)
 *
 * Optional:
 *   PAYSTACK_ENABLED      (true | false, default true if secret key set)
 *   PAYSTACK_API_BASE     (default https://api.paystack.co)
 */
class PaystackService
{
    private string $secretKey;
    private string $publicKey;
    private string $apiBase;
    private bool   $enabled;

    public function __construct()
    {
        $this->secretKey = self::env('PAYSTACK_SECRET_KEY', '');
        $this->publicKey = self::env('PAYSTACK_PUBLIC_KEY', '');
        $this->apiBase   = rtrim(self::env('PAYSTACK_API_BASE', 'https://api.paystack.co'), '/');
        $this->enabled   = self::env('PAYSTACK_ENABLED', 'true') !== 'false'
            && $this->secretKey !== ''
            && $this->publicKey !== '';
    }

    public function isEnabled(): bool { return $this->enabled; }
    public function getPublicKey(): string { return $this->publicKey; }

    // ──────────────────────────────────────────────────────────
    // Transactions
    // ──────────────────────────────────────────────────────────

    /**
     * Initialize a transaction on Paystack's server. This MUST be
     * called from our backend only — never expose the secret key to
     * the frontend.
     *
     * @param string $email
     * @param float  $amountNaira Amount in NGN major unit (e.g. 50000.00 for ₦50,000)
     * @param string $reference   Our own unique reference for this payment
     * @param string $callbackUrl Where Paystack redirects after payment (used as a fallback;
     *                            we complete the flow via the Popup + verify, not the redirect)
     * @param array  $metadata    Arbitrary metadata (order_id, buyer_id, etc.)
     * @return array{success: bool, access_code: ?string, authorization_url: ?string, reference: ?string, error: ?string}
     */
    public function initializeTransaction(
        string $email,
        float $amountNaira,
        string $reference,
        string $callbackUrl = '',
        array $metadata = []
    ): array {
        if (!$this->enabled) {
            return ['success' => false, 'access_code' => null, 'authorization_url' => null, 'reference' => null, 'error' => 'Paystack is not configured'];
        }

        // Paystack expects the amount in the SUBUNIT of the currency
        // (kobo for NGN) — i.e. amount * 100. Round to avoid float dust.
        $amountKobo = (int) round($amountNaira * 100);

        $payload = [
            'email'     => $email,
            'amount'    => $amountKobo,
            'currency'  => 'NGN',
            'reference' => $reference,
            'metadata'  => $metadata,
        ];
        if ($callbackUrl !== '') {
            $payload['callback_url'] = $callbackUrl;
        }

        try {
            $res = $this->request('POST', '/transaction/initialize', $payload);
        } catch (Exception $e) {
            error_log('Paystack initializeTransaction failed: ' . $e->getMessage());
            return ['success' => false, 'access_code' => null, 'authorization_url' => null, 'reference' => null, 'error' => $e->getMessage()];
        }

        if (empty($res['status']) || empty($res['data']['access_code'])) {
            return ['success' => false, 'access_code' => null, 'authorization_url' => null, 'reference' => null, 'error' => $res['message'] ?? 'Unable to initialize transaction'];
        }

        return [
            'success'            => true,
            'access_code'        => $res['data']['access_code'],
            'authorization_url'  => $res['data']['authorization_url'] ?? null,
            'reference'          => $res['data']['reference'] ?? $reference,
            'error'              => null,
        ];
    }

    /**
     * Verify a transaction directly against Paystack's API. This is the
     * ONLY source of truth for "did this payment actually succeed" —
     * a client-side onSuccess callback or a webhook payload are both
     * just signals to go check this endpoint, never proof by themselves.
     *
     * @return array{success: bool, status: ?string, amount_kobo: ?int, currency: ?string, channel: ?string, gateway_response: ?string, paid_at: ?string, error: ?string}
     */
    public function verifyTransaction(string $reference): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'status' => null, 'amount_kobo' => null, 'currency' => null, 'channel' => null, 'gateway_response' => null, 'paid_at' => null, 'error' => 'Paystack is not configured'];
        }

        try {
            $res = $this->request('GET', '/transaction/verify/' . rawurlencode($reference), []);
        } catch (Exception $e) {
            error_log('Paystack verifyTransaction failed: ' . $e->getMessage());
            return ['success' => false, 'status' => null, 'amount_kobo' => null, 'currency' => null, 'channel' => null, 'gateway_response' => null, 'paid_at' => null, 'error' => $e->getMessage()];
        }

        if (empty($res['status'])) {
            return ['success' => false, 'status' => null, 'amount_kobo' => null, 'currency' => null, 'channel' => null, 'gateway_response' => null, 'paid_at' => null, 'error' => $res['message'] ?? 'Verification failed'];
        }

        $data = $res['data'] ?? [];
        return [
            'success'          => true,
            'status'           => $data['status'] ?? null, // 'success' | 'failed' | 'abandoned' | ...
            'amount_kobo'      => isset($data['amount']) ? (int)$data['amount'] : null,
            'currency'         => $data['currency'] ?? null,
            'channel'          => $data['channel'] ?? null,
            'gateway_response' => $data['gateway_response'] ?? null,
            'paid_at'          => $data['paid_at'] ?? null,
            'error'            => null,
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Webhooks
    // ──────────────────────────────────────────────────────────

    /**
     * Paystack signs webhook payloads with `x-paystack-signature`:
     * HMAC-SHA512 of the RAW request body, keyed with our secret key.
     * We must read the raw body BEFORE anything else touches it, and
     * compare using hash_equals() to avoid timing attacks.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (!$signatureHeader || $this->secretKey === '') return false;
        $expected = hash_hmac('sha512', $rawBody, $this->secretKey);
        return hash_equals($expected, $signatureHeader);
    }

    // ──────────────────────────────────────────────────────────
    // Internals
    // ──────────────────────────────────────────────────────────

    private function request(string $method, string $path, array $body): array
    {
        $ch = curl_init($this->apiBase . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) throw new RuntimeException('Paystack request failed: ' . $err);
        $decoded = json_decode($raw, true);
        if ($code >= 400) {
            $msg = $decoded['message'] ?? $raw;
            throw new RuntimeException('Paystack ' . $method . ' ' . $path . ' → ' . $code . ': ' . (is_string($msg) ? $msg : json_encode($msg)));
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
