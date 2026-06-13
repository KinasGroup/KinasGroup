<?php
/**
 * KINAS GROUP — MetaMap (KYC) integration
 *
 * MetaMap (formerly Mati) handles identity verification, document
 * scanning, liveness checks, AML/PEP screening, and government
 * database checks (NIN/BVN/CAC for Nigeria, etc.).
 *
 * Docs:   https://docs.metamap.com
 * API:    https://api.getmati.com
 * OAuth:  https://api.prod.metamap.com   (token endpoint, Basic auth)
 * Webhook: HMAC-SHA256 of raw body, header `x-mati-signature`
 *
 * ── Authentication (two-step) ─────────────────────────────────────
 *  1. Exchange clientId:clientSecret for a JWT access_token
 *        POST {METAMAP_OAUTH_BASE}/oauth
 *        Authorization: Basic base64(clientId:clientSecret)
 *        Content-Type: application/x-www-form-urlencoded
 *        Body: grant_type=client_credentials
 *  2. Send the JWT on every API call
 *        Authorization: Bearer <access_token>
 *  3. Cache the token in-memory + on disk until ~60s before expiry,
 *     and force-refresh on any 401 (one retry, then give up).
 *
 * Required env:
 *   METAMAP_CLIENT_ID
 *   METAMAP_CLIENT_SECRET
 *   METAMAP_WEBHOOK_SECRET
 *
 * Optional:
 *   METAMAP_FLOW_ID         (the verification flow to use)
 *   METAMAP_API_BASE        (verifications API, default https://api.getmati.com)
 *   METAMAP_OAUTH_BASE      (token endpoint, default https://api.prod.metamap.com)
 *   METAMAP_OAUTH_PATH      (default /oauth)
 *   METAMAP_ENABLED         (set to "false" to short-circuit in dev)
 */

class MetaMapService
{
    private string $clientId;
    private string $clientSecret;
    private string $webhookSecret;
    private string $flowId;
    private string $apiBase;
    private string $oauthBase;
    private string $oauthPath;
    private bool   $enabled;

    /** In-memory access token cache (per request) */
    private ?string $accessToken = null;
    /** Unix timestamp (seconds) when the cached token expires */
    private ?int    $accessTokenExpiresAt = null;
    /** Filesystem cache path for the access token (survives between requests in long-running workers) */
    private string $tokenCacheFile;

    public function __construct()
    {
        $this->clientId      = self::env('METAMAP_CLIENT_ID', '');
        $this->clientSecret  = self::env('METAMAP_CLIENT_SECRET', '');
        $this->webhookSecret = self::env('METAMAP_WEBHOOK_SECRET', '');
        $this->flowId        = self::env('METAMAP_FLOW_ID', '');
        $this->apiBase       = rtrim(self::env('METAMAP_API_BASE',   'https://api.getmati.com'),         '/');
        $this->oauthBase     = rtrim(self::env('METAMAP_OAUTH_BASE', 'https://api.prod.metamap.com'),     '/');
        $this->oauthPath     = self::env('METAMAP_OAUTH_PATH', '/oauth');
        $this->enabled       = self::env('METAMAP_ENABLED', 'true') !== 'false'
            && $this->clientId !== '' && $this->clientSecret !== '';

        // Store token cache in /storage (writable). Falls back to sys_get_temp_dir.
        $storage = __DIR__ . '/../storage/metamap';
        if (!is_dir($storage)) { @mkdir($storage, 0700, true); }
        $this->tokenCacheFile = rtrim(sys_get_temp_dir(), '/') . '/kinas_metamap_token.json';
        if (is_dir($storage) && is_writable($storage)) {
            $this->tokenCacheFile = $storage . '/access_token.json';
        }

        // Hydrate from disk cache if present and still valid
        $this->loadCachedToken();
    }

    /** Convenience env lookup that mirrors EmailService's pattern. */
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
        return $val !== null ? (string)$val : $default;
    }

    public function isEnabled(): bool  { return $this->enabled; }
    public function getFlowId(): string { return $this->flowId; }

    /**
     * Create a verification for the given user.
     *
     * Returns ['id' => '...', 'status' => '...', 'redirectUrl' => '...']
     * or throws RuntimeException.
     *
     * @param string $userId    Your internal user id (stored in MetaMap as metadata)
     * @param array  $metadata  Free-form metadata, e.g. ['division' => 'kinas-automobile']
     * @param string $country   ISO-3166-1 alpha-2, e.g. 'NG'
     */
    public function createVerification(string $userId, array $metadata = [], string $country = 'NG'): array
    {
        if (!$this->enabled) {
            throw new RuntimeException('MetaMap is not configured. Set METAMAP_CLIENT_ID and METAMAP_CLIENT_SECRET in .env');
        }
        if ($this->flowId === '') {
            throw new RuntimeException('METAMAP_FLOW_ID is not configured');
        }

        $payload = [
            'flowId'   => $this->flowId,
            'userId'   => $userId,
            'country'  => strtoupper($country),
            'metadata' => $metadata,
        ];

        $res = $this->request('POST', '/v2/verifications', $payload);

        // MetaMap returns { _id, status, ... } or { id, status, ... }
        $id = $res['_id'] ?? $res['id'] ?? null;
        if (!$id) {
            throw new RuntimeException('MetaMap did not return a verification id: ' . json_encode($res));
        }

        return [
            'id'          => (string)$id,
            'status'      => $res['status'] ?? 'created',
            'redirectUrl' => $res['redirectUrl'] ?? $res['url'] ?? null,
        ];
    }

    /**
     * Fetch the latest status for a verification id.
     * Returns the raw resource object.
     */
    public function getVerification(string $verificationId): array
    {
        if (!$this->enabled) {
            throw new RuntimeException('MetaMap is not configured');
        }
        return $this->request('GET', '/v2/verifications/' . urlencode($verificationId));
    }

    /**
     * List verifications for a userId (MetaMap accepts ?userId=... on the
     * /v2/verifications endpoint).
     */
    public function listVerificationsForUser(string $userId): array
    {
        if (!$this->enabled) {
            throw new RuntimeException('MetaMap is not configured');
        }
        $res = $this->request('GET', '/v2/verifications?userId=' . urlencode($userId));
        return is_array($res) ? $res : ($res['data'] ?? []);
    }

    /**
     * Build the hosted-flow URL the agent will be sent to.
     * MetaMap's web SDK is launched from this URL.
     */
    public function buildHostedUrl(string $verificationId): string
    {
        // MetaMap's hosted flow takes the verification id and launches the SDK
        return $this->apiBase . '/v2/verifications/' . urlencode($verificationId) . '/hosted';
    }

    /**
     * Verify the HMAC signature on an incoming webhook.
     *
     * MetaMap sends `x-mati-signature: t=<unix_ts>,v1=<hex_hmac>`
     *   signed = "<unix_ts>." + raw_body
     *   hmac   = HMAC_SHA256(secret, signed)
     *
     * We allow a 5-minute clock skew window to absorb retried deliveries.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (!$signatureHeader || $this->webhookSecret === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $chunk) {
            $kv = explode('=', trim($chunk), 2);
            if (count($kv) === 2) $parts[trim($kv[0])] = trim($kv[1]);
        }
        $ts  = $parts['t']   ?? null;
        $sig = $parts['v1']  ?? null;
        if (!$ts || !$sig) return false;

        // Reject old replays
        if (abs(time() - (int)$ts) > 300) return false;

        $expected = hash_hmac('sha256', $ts . '.' . $rawBody, $this->webhookSecret);
        return hash_equals($expected, $sig);
    }

    /**
     * Map MetaMap's granular status to our internal verification_status enum.
     *
     *   approved     → user.verified = 1, agent can list
     *   review_needed → admin should inspect (manual decision)
     *   rejected     → blocked
     *   in_progress  → still processing
     *   created      → not started
     */
    public static function mapStatus(string $matiStatus): string
    {
        $s = strtolower($matiStatus);
        return match (true) {
            in_array($s, ['verified', 'approved', 'completed', 'success'], true)         => 'approved',
            in_array($s, ['rejected', 'failed', 'fraud', 'declined'], true)             => 'rejected',
            str_contains($s, 'review')                                                    => 'review_needed',
            in_array($s, ['in_progress', 'pending', 'created', 'started'], true)          => 'in_progress',
            in_array($s, ['expired', 'cancelled', 'canceled', 'abandoned'], true)        => 'expired',
            default                                                                       => 'in_progress',
        };
    }

    // ─────────────────────────────────────────────────────────────
    // Token cache (file-backed, in-memory mirror)
    // ─────────────────────────────────────────────────────────────

    private function loadCachedToken(): void
    {
        if (!is_readable($this->tokenCacheFile)) return;
        $raw = @file_get_contents($this->tokenCacheFile);
        if (!$raw) return;
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['access_token']) || empty($data['expires_at'])) return;
        if ((int)$data['expires_at'] <= time() + 60) return; // expired or about to expire
        $this->accessToken          = (string)$data['access_token'];
        $this->accessTokenExpiresAt = (int)$data['expires_at'];
    }

    private function saveCachedToken(string $token, int $expiresAt): void
    {
        $this->accessToken          = $token;
        $this->accessTokenExpiresAt = $expiresAt;
        @file_put_contents(
            $this->tokenCacheFile,
            json_encode(['access_token' => $token, 'expires_at' => $expiresAt], JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
        @chmod($this->tokenCacheFile, 0600);
    }

    private function clearCachedToken(): void
    {
        $this->accessToken          = null;
        $this->accessTokenExpiresAt = null;
        if (is_file($this->tokenCacheFile)) { @unlink($this->tokenCacheFile); }
    }

    /**
     * Return a valid bearer token, exchanging client creds if needed
     * or if the cached one is within 60s of expiry.
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken && $this->accessTokenExpiresAt && $this->accessTokenExpiresAt > time() + 60) {
            return $this->accessToken;
        }
        $this->exchangeForToken();
        if (!$this->accessToken) {
            throw new RuntimeException('MetaMap: failed to obtain access token. Check METAMAP_CLIENT_ID / METAMAP_CLIENT_SECRET and METAMAP_OAUTH_BASE.');
        }
        return $this->accessToken;
    }

    /**
     * Exchange clientId:clientSecret (HTTP Basic) for a JWT access_token
     * via the OAuth endpoint. Persists the token + its expiry.
     */
    private function exchangeForToken(): void
    {
        $url = $this->oauthBase . $this->oauthPath;

        $ch = curl_init($url);
        $basic = base64_encode($this->clientId . ':' . $this->clientSecret);
        $headers = [
            'Accept: application/json',
            'Authorization: Basic ' . $basic,
            'Content-Type: application/x-www-form-urlencoded',
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('MetaMap OAuth request failed: ' . $err);
        }
        $decoded = json_decode($raw, true);
        if ($code >= 400 || empty($decoded['access_token'])) {
            $msg = $decoded['message'] ?? $decoded['error'] ?? $raw;
            throw new RuntimeException('MetaMap OAuth ' . $code . ': ' . (is_string($msg) ? $msg : json_encode($msg)));
        }

        $token  = (string)$decoded['access_token'];
        $ttl    = (int)($decoded['expires_in'] ?? 3600); // seconds, default 1h
        $now    = time();
        // Refresh 60s before MetaMap says it expires
        $expire = $now + max(60, $ttl) - 60;

        $this->saveCachedToken($token, $expire);
    }

    // ─────────────────────────────────────────────────────────────
    // HTTP layer
    // ─────────────────────────────────────────────────────────────

    /**
     * Authenticated JSON request to the MetaMap verifications API.
     * Sends `Authorization: Bearer <jwt>`; refreshes + retries once on 401.
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->apiBase . $path;
        $raw = null; $code = 0; $err = null; $decoded = null;

        $attempt = function () use (&$raw, &$code, &$err, &$decoded, $method, $url, $body) {
            $token = $this->getAccessToken();
            $ch = curl_init($url);
            $headers = [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ];
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => 20,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
            $raw     = curl_exec($ch);
            $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err     = curl_error($ch);
            curl_close($ch);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
        };

        $attempt();

        // 401 → force-refresh token and retry exactly once
        if ($code === 401) {
            $this->clearCachedToken();
            $attempt();
        }

        if ($raw === false) {
            throw new RuntimeException('MetaMap request failed: ' . $err);
        }
        if ($code >= 400) {
            $msg = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? json_encode($decoded)) : $raw;
            throw new RuntimeException('MetaMap ' . $method . ' ' . $path . ' → ' . $code . ': ' . $msg);
        }
        return is_array($decoded) ? $decoded : [];
    }
}
