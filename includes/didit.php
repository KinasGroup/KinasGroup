<?php
/**
 * KINAS GROUP — Didit identity (KYC) & business (KYB) verification
 *
 * Docs: https://docs.didit.me
 * API:  https://verification.didit.me/v3
 *
 * Didit uses ONE session API for both KYC and KYB — the only
 * difference is which `workflow_id` you point a session at (a
 * "KYC" workflow vs a "KYB" workflow, each configured separately in
 * the Didit Console under Workflows). That's why this is a single
 * service class with two workflow IDs, rather than two classes —
 * it mirrors how Didit itself models "2 different workflows".
 *
 * Required env:
 *   DIDIT_API_KEY            (x-api-key header on every call)
 *   DIDIT_WEBHOOK_SECRET     (HMAC secret for webhook verification)
 *   DIDIT_KYC_WORKFLOW_ID    (personal identity verification workflow)
 *   DIDIT_KYB_WORKFLOW_ID    (business verification workflow)
 *
 * Optional:
 *   DIDIT_API_BASE           (default https://verification.didit.me/v3)
 *   DIDIT_ENABLED            (true|false, default true if API key set)
 *
 * Security note on webhooks: this class verifies using X-Signature
 * (HMAC-SHA256 over the exact raw request bytes) with X-Signature-Simple
 * (HMAC-SHA256 over "{timestamp}:{session_id}:{status}:{webhook_type}")
 * as a fallback. We deliberately do NOT implement X-Signature-V2 (a
 * canonical JSON re-encoding with float-shortening) — PHP receives the
 * exact wire bytes via php://input with no framework re-serialization
 * in between, so X-Signature is reliable here and avoids replicating
 * Didit's float-normalisation rules exactly, which is easy to get
 * subtly wrong.
 */
class DiditService
{
    private string $apiKey;
    private string $webhookSecret;
    private string $apiBase;
    private string $kycWorkflowId;
    private string $kybWorkflowId;
    private bool   $enabled;

    public function __construct()
    {
        $this->apiKey        = self::env('DIDIT_API_KEY', '');
        $this->webhookSecret = self::env('DIDIT_WEBHOOK_SECRET', '');
        $this->apiBase       = rtrim(self::env('DIDIT_API_BASE', 'https://verification.didit.me/v3'), '/');
        $this->kycWorkflowId = self::env('DIDIT_KYC_WORKFLOW_ID', '');
        $this->kybWorkflowId = self::env('DIDIT_KYB_WORKFLOW_ID', '');
        $this->enabled       = self::env('DIDIT_ENABLED', 'true') !== 'false' && $this->apiKey !== '';
    }

    public function isEnabled(): bool    { return $this->enabled; }
    public function isKycEnabled(): bool { return $this->enabled && $this->kycWorkflowId !== ''; }
    public function isKybEnabled(): bool { return $this->enabled && $this->kybWorkflowId !== ''; }
    public function getKycWorkflowId(): string { return $this->kycWorkflowId; }
    public function getKybWorkflowId(): string { return $this->kybWorkflowId; }

    // ──────────────────────────────────────────────────────────
    // Sessions
    // ──────────────────────────────────────────────────────────

    /**
     * Create a verification session on a given workflow (KYC or KYB —
     * whichever workflow_id you pass in).
     *
     * @param string $workflowId   getKycWorkflowId() or getKybWorkflowId()
     * @param string $vendorData   our internal reference (we use "kyc:{userId}" / "kyb:{userId}")
     * @param string $callbackUrl  where the user lands after finishing the hosted flow
     * @param array  $metadata     arbitrary JSON, echoed back in webhooks — for correlation only, never trusted
     * @param array  $contactDetails  ['email' => ..., 'phone' => ...] optional prefill
     * @return array{success: bool, session_id: ?string, session_token: ?string, url: ?string, status: ?string, error: ?string}
     */
    public function createSession(
        string $workflowId,
        string $vendorData,
        string $callbackUrl = '',
        array $metadata = [],
        array $contactDetails = []
    ): array {
        if (!$this->enabled || $workflowId === '') {
            return ['success' => false, 'session_id' => null, 'session_token' => null, 'url' => null, 'status' => null, 'error' => 'Didit is not configured'];
        }

        $payload = [
            'workflow_id'  => $workflowId,
            'vendor_data'  => $vendorData,
            'metadata'     => $metadata,
        ];
        if ($callbackUrl !== '') $payload['callback'] = $callbackUrl;
        if (!empty($contactDetails)) $payload['contact_details'] = $contactDetails;

        try {
            $res = $this->request('POST', '/session/', $payload);
        } catch (Exception $e) {
            error_log('Didit createSession failed: ' . $e->getMessage());
            return ['success' => false, 'session_id' => null, 'session_token' => null, 'url' => null, 'status' => null, 'error' => $e->getMessage()];
        }

        if (empty($res['session_id'])) {
            return ['success' => false, 'session_id' => null, 'session_token' => null, 'url' => null, 'status' => null, 'error' => $res['detail'] ?? 'Unable to create verification session'];
        }

        return [
            'success'       => true,
            'session_id'    => $res['session_id'],
            'session_number'=> $res['session_number'] ?? null,
            'session_token' => $res['session_token'] ?? null,
            'url'           => $res['url'] ?? null, // hosted verification URL — field is "url", not "verification_url"
            'status'        => $res['status'] ?? 'Not Started',
            'workflow_id'   => $res['workflow_id'] ?? $workflowId,
            'error'         => null,
        ];
    }

    /**
     * Fetch the authoritative decision for a session — used both for
     * on-demand status checks and to re-verify what a webhook claims.
     */
    public function getDecision(string $sessionId): array
    {
        if (!$this->enabled) return ['success' => false, 'status' => null, 'decision' => null, 'error' => 'Didit is not configured'];

        try {
            $res = $this->request('GET', '/session/' . rawurlencode($sessionId) . '/decision/', []);
        } catch (Exception $e) {
            error_log('Didit getDecision failed: ' . $e->getMessage());
            return ['success' => false, 'status' => null, 'decision' => null, 'error' => $e->getMessage()];
        }

        if (empty($res['status'])) {
            return ['success' => false, 'status' => null, 'decision' => null, 'error' => $res['detail'] ?? 'Unable to fetch decision'];
        }

        return ['success' => true, 'status' => $res['status'], 'decision' => $res, 'error' => null];
    }

    // ──────────────────────────────────────────────────────────
    // Webhooks
    // ──────────────────────────────────────────────────────────

    /**
     * Verify a Didit webhook. Prefers X-Signature (HMAC over the raw
     * bytes); falls back to X-Signature-Simple (HMAC over a fixed
     * "timestamp:session_id:status:webhook_type" string) if the first
     * doesn't match — Didit sends both on every delivery. Either way,
     * requests older than 5 minutes are rejected to block replays.
     */
    public function verifyWebhookSignature(
        string $rawBody,
        ?string $signature,
        ?string $signatureSimple,
        ?string $timestamp,
        string $sessionId,
        string $status,
        string $webhookType
    ): bool {
        if ($this->webhookSecret === '' || $timestamp === null) return false;

        if (abs(time() - (int)$timestamp) > 300) return false; // reject stale/replayed deliveries

        if ($signature !== null) {
            $expected = hash_hmac('sha256', $rawBody, $this->webhookSecret);
            if (hash_equals($expected, $signature)) return true;
        }

        if ($signatureSimple !== null) {
            $canonical = $timestamp . ':' . $sessionId . ':' . $status . ':' . $webhookType;
            $expectedSimple = hash_hmac('sha256', $canonical, $this->webhookSecret);
            if (hash_equals($expectedSimple, $signatureSimple)) return true;
        }

        return false;
    }

    /**
     * Map Didit's exact, case-sensitive session status strings to our
     * internal vocabulary (mirrors MetaMapService::mapStatus so both
     * providers feed the same agent_profiles state machine).
     */
    public static function mapStatus(string $diditStatus): string
    {
        switch ($diditStatus) {
            case 'Approved':      return 'approved';
            case 'Declined':      return 'rejected';
            case 'In Review':     return 'review_needed';
            case 'In Progress':
            case 'Awaiting User':
            case 'Resubmitted':   return 'in_progress';
            case 'Abandoned':
            case 'Expired':
            case 'Kyc Expired':   return 'expired';
            case 'Not Started':
            default:              return 'created';
        }
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
                'x-api-key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ];
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) throw new RuntimeException('Didit request failed: ' . $err);
        $decoded = json_decode($raw, true);

        // Didit returns 403 (not 401) for any auth problem, and 400 for
        // insufficient credits — both are still "the call failed",
        // surfaced generically here; callers check for missing expected fields.
        if ($code >= 400) {
            $msg = $decoded['detail'] ?? $raw;
            throw new RuntimeException('Didit ' . $method . ' ' . $path . ' → ' . $code . ': ' . (is_string($msg) ? $msg : json_encode($msg)));
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
