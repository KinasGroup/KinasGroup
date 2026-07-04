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
     * @param array  $split       Optional single-subaccount split:
     *                            ['subaccount' => 'ACCT_xxx', 'transaction_charge' => int (kobo, goes to MAIN account), 'bearer' => 'account'|'subaccount']
     * @return array{success: bool, access_code: ?string, authorization_url: ?string, reference: ?string, error: ?string}
     */
    public function initializeTransaction(
        string $email,
        float $amountNaira,
        string $reference,
        string $callbackUrl = '',
        array $metadata = [],
        array $split = []
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

        // Single-subaccount split (marketplace order where every item
        // belongs to the same agent). transaction_charge is a FLAT
        // amount that goes to the MAIN account regardless of the
        // subaccount's stored percentage_charge — this is what lets us
        // hand the agent exactly (price − commission) untouched by
        // whatever the actual Paystack processing fee turns out to be,
        // instead of the fee being split proportionally.
        if (!empty($split['subaccount'])) {
            $payload['subaccount'] = $split['subaccount'];
            if (isset($split['transaction_charge'])) {
                $payload['transaction_charge'] = (int) $split['transaction_charge'];
            }
            $payload['bearer'] = $split['bearer'] ?? 'account';
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
    // Subaccounts — one per agent, used to auto-settle a sale
    // straight to the agent's bank account, minus our commission.
    // ──────────────────────────────────────────────────────────

    /**
     * List Nigerian banks (for a "select your bank" dropdown). Small,
     * infrequently-changing list — safe to call live each time a
     * settings page needs it.
     */
    public function listBanks(): array
    {
        if (!$this->enabled) return ['success' => false, 'banks' => [], 'error' => 'Paystack is not configured'];
        try {
            $res = $this->request('GET', '/bank?country=nigeria&currency=NGN&type=nuban', []);
        } catch (Exception $e) {
            return ['success' => false, 'banks' => [], 'error' => $e->getMessage()];
        }
        if (empty($res['status'])) {
            return ['success' => false, 'banks' => [], 'error' => $res['message'] ?? 'Unable to fetch banks'];
        }
        $banks = array_map(fn($b) => ['name' => $b['name'], 'code' => $b['code']], $res['data'] ?? []);
        return ['success' => true, 'banks' => $banks, 'error' => null];
    }

    /**
     * Confirm an account number actually belongs to the name the agent
     * claims, BEFORE we create a subaccount that pays out to it.
     * Paystack explicitly says they are not liable for payouts to a
     * wrong account, so this check is not optional.
     */
    public function resolveAccountNumber(string $accountNumber, string $bankCode): array
    {
        if (!$this->enabled) return ['success' => false, 'account_name' => null, 'error' => 'Paystack is not configured'];
        try {
            $res = $this->request('GET', '/bank/resolve?account_number=' . rawurlencode($accountNumber) . '&bank_code=' . rawurlencode($bankCode), []);
        } catch (Exception $e) {
            return ['success' => false, 'account_name' => null, 'error' => $e->getMessage()];
        }
        if (empty($res['status'])) {
            return ['success' => false, 'account_name' => null, 'error' => $res['message'] ?? 'Could not verify this account number'];
        }
        return ['success' => true, 'account_name' => $res['data']['account_name'] ?? null, 'error' => null];
    }

    /**
     * Create a subaccount for an agent. `percentageCharge` is what the
     * MAIN account receives per transaction routed to this subaccount
     * (per Paystack's docs) — we default this to our commission rate,
     * though we always override it per-transaction with an explicit
     * `transaction_charge` at checkout, so this stored value only
     * matters as a fallback.
     */
    public function createSubaccount(
        string $businessName,
        string $bankCode,
        string $accountNumber,
        float $percentageCharge,
        string $email,
        ?string $phone = null
    ): array {
        if (!$this->enabled) return ['success' => false, 'subaccount_code' => null, 'subaccount_id' => null, 'account_name' => null, 'error' => 'Paystack is not configured'];

        $payload = [
            'business_name'        => $businessName,
            'settlement_bank'      => $bankCode,
            'account_number'       => $accountNumber,
            'percentage_charge'    => $percentageCharge,
            'description'          => 'KINAS Marketplace agent payout account',
            'primary_contact_email'=> $email,
        ];
        if ($phone) $payload['primary_contact_phone'] = $phone;

        try {
            $res = $this->request('POST', '/subaccount', $payload);
        } catch (Exception $e) {
            return ['success' => false, 'subaccount_code' => null, 'subaccount_id' => null, 'account_name' => null, 'error' => $e->getMessage()];
        }
        if (empty($res['status'])) {
            return ['success' => false, 'subaccount_code' => null, 'subaccount_id' => null, 'account_name' => null, 'error' => $res['message'] ?? 'Unable to create subaccount'];
        }
        $data = $res['data'] ?? [];
        return [
            'success'         => true,
            'subaccount_code' => $data['subaccount_code'] ?? null,
            'subaccount_id'   => $data['id'] ?? null,
            'account_name'    => $data['account_name'] ?? null,
            'error'           => null,
        ];
    }

    /** Update an existing subaccount's bank details / commission split. */
    public function updateSubaccount(
        string $subaccountCode,
        string $businessName,
        string $bankCode,
        string $accountNumber,
        float $percentageCharge
    ): array {
        if (!$this->enabled) return ['success' => false, 'error' => 'Paystack is not configured'];

        $payload = [
            'business_name'     => $businessName,
            'settlement_bank'   => $bankCode,
            'account_number'    => $accountNumber,
            'percentage_charge' => $percentageCharge,
        ];

        try {
            $res = $this->request('PUT', '/subaccount/' . rawurlencode($subaccountCode), $payload);
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
        if (empty($res['status'])) {
            return ['success' => false, 'error' => $res['message'] ?? 'Unable to update subaccount'];
        }
        $data = $res['data'] ?? [];
        return ['success' => true, 'account_name' => $data['account_name'] ?? null, 'error' => null];
    }

    // ──────────────────────────────────────────────────────────
    // Fee gross-up — "who pays for the Paystack charge"
    //
    // Nigeria local-card pricing (as published on paystack.com/pricing,
    // checked 2026): 1.5% + ₦100, the ₦100 is waived under ₦2,500, and
    // the total fee is capped at ₦2,000 regardless of transaction size.
    // This is necessarily an ESTIMATE at checkout time — the actual
    // channel (local vs international card, bank transfer, etc.) is
    // only known after payment. International cards are 3.9% + ₦100
    // with no cap and are NOT modelled here; on the rare international
    // charge, the platform absorbs the small extra cost, never the
    // agent (see checkout-init.php for why).
    // ──────────────────────────────────────────────────────────

    public static function estimateLocalCardFee(float $amountNaira): float
    {
        if ($amountNaira <= 0) return 0;
        $fee = $amountNaira * 0.015;
        if ($amountNaira >= 2500) $fee += 100;
        return round(min($fee, 2000), 2);
    }

    /**
     * Given the amount we want to actually land (net of Paystack's
     * fee), return the amount to charge the buyer so that
     * charge - fee(charge) == targetNet. The fee function is a simple
     * capped/waived linear function, so a few fixed-point iterations
     * converge immediately.
     */
    public static function grossUpForFee(float $targetNet): float
    {
        if ($targetNet <= 0) return 0;
        $charge = $targetNet;
        for ($i = 0; $i < 6; $i++) {
            $fee = self::estimateLocalCardFee($charge);
            $next = round($targetNet + $fee, 2);
            if (abs($next - $charge) < 0.01) { $charge = $next; break; }
            $charge = $next;
        }
        return $charge;
    }

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
        if ($method === 'POST' || $method === 'PUT') {
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
