<?php
/**
 * KINAS GROUP — Didit identity (KYC) & business (KYB) verification
 *
 * Docs: https://docs.didit.me
 * API:  https://verification.didit.me/v3
 *
 * AMENDED FOR KYC NAME-MATCH ENFORCEMENT:
 * - Adds stronger document-name extraction.
 * - Adds normalized name comparison helpers.
 * - Adds a central KYC name check method for use by the webhook.
 * - Keeps existing Didit session/webhook behaviour intact.
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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isKycEnabled(): bool
    {
        return $this->enabled && $this->kycWorkflowId !== '';
    }

    public function isKybEnabled(): bool
    {
        return $this->enabled && $this->kybWorkflowId !== '';
    }

    public function getKycWorkflowId(): string
    {
        return $this->kycWorkflowId;
    }

    public function getKybWorkflowId(): string
    {
        return $this->kybWorkflowId;
    }

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
            return [
                'success' => false,
                'session_id' => null,
                'session_token' => null,
                'url' => null,
                'status' => null,
                'error' => 'Didit is not configured',
            ];
        }

        $payload = [
            'workflow_id' => $workflowId,
            'vendor_data' => $vendorData,
            'metadata'    => $metadata,
        ];

        if ($callbackUrl !== '') {
            $payload['callback'] = $callbackUrl;
        }

        if (!empty($contactDetails)) {
            $payload['contact_details'] = $contactDetails;
        }

        try {
            $res = $this->request('POST', '/session/', $payload);
        } catch (Exception $e) {
            error_log('Didit createSession failed: ' . $e->getMessage());

            return [
                'success' => false,
                'session_id' => null,
                'session_token' => null,
                'url' => null,
                'status' => null,
                'error' => $e->getMessage(),
            ];
        }

        if (empty($res['session_id'])) {
            return [
                'success' => false,
                'session_id' => null,
                'session_token' => null,
                'url' => null,
                'status' => null,
                'error' => $res['detail'] ?? 'Unable to create verification session',
            ];
        }

        return [
            'success' => true,
            'session_id' => $res['session_id'],
            'session_number' => $res['session_number'] ?? null,
            'session_token' => $res['session_token'] ?? null,
            'url' => $res['url'] ?? null,
            'status' => $res['status'] ?? 'Not Started',
            'workflow_id' => $res['workflow_id'] ?? $workflowId,
            'error' => null,
        ];
    }

    /**
     * Fetch the authoritative decision for a session — used both for
     * on-demand status checks and to re-verify what a webhook claims.
     */
    public function getDecision(string $sessionId): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'status' => null,
                'decision' => null,
                'error' => 'Didit is not configured',
            ];
        }

        try {
            $res = $this->request('GET', '/session/' . rawurlencode($sessionId) . '/decision/', []);
        } catch (Exception $e) {
            error_log('Didit getDecision failed: ' . $e->getMessage());

            return [
                'success' => false,
                'status' => null,
                'decision' => null,
                'error' => $e->getMessage(),
            ];
        }

        if (empty($res['status'])) {
            return [
                'success' => false,
                'status' => null,
                'decision' => null,
                'error' => $res['detail'] ?? 'Unable to fetch decision',
            ];
        }

        return [
            'success' => true,
            'status' => $res['status'],
            'decision' => $res,
            'error' => null,
        ];
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
        if ($this->webhookSecret === '' || $timestamp === null) {
            return false;
        }

        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }

        if ($signature !== null) {
            $expected = hash_hmac('sha256', $rawBody, $this->webhookSecret);

            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        if ($signatureSimple !== null) {
            $canonical = $timestamp . ':' . $sessionId . ':' . $status . ':' . $webhookType;
            $expectedSimple = hash_hmac('sha256', $canonical, $this->webhookSecret);

            if (hash_equals($expectedSimple, $signatureSimple)) {
                return true;
            }
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
            case 'Approved':
                return 'approved';

            case 'Declined':
                return 'rejected';

            case 'In Review':
                return 'review_needed';

            case 'In Progress':
            case 'Awaiting User':
            case 'Resubmitted':
                return 'in_progress';

            case 'Abandoned':
            case 'Expired':
            case 'Kyc Expired':
                return 'expired';

            case 'Not Started':
            default:
                return 'created';
        }
    }

    // ──────────────────────────────────────────────────────────
    // Identity cross-check (registered name vs. scanned ID document)
    // ──────────────────────────────────────────────────────────

    /**
     * Central KYC name check.
     *
     * This is the method the webhook should use. It extracts the name
     * from the Didit decision payload and compares it against the
     * registered account name.
     *
     * Returns:
     * [
     *   'match' => bool,
     *   'document_name' => ?string,
     *   'score' => float,
     *   'reason' => string,
     *   'registered_tokens' => array,
     *   'document_tokens' => array,
     *   'matched_tokens' => array,
     * ]
     */
    public static function kycNameCheck(array $decision, string $registeredName): array
    {
        $documentName = self::extractDocumentName($decision);

        if ($documentName === null || trim($documentName) === '') {
            return [
                'match' => false,
                'document_name' => null,
                'score' => 0.0,
                'reason' => 'Document name could not be read from the KYC decision payload.',
                'registered_tokens' => self::normalizeName($registeredName),
                'document_tokens' => [],
                'matched_tokens' => [],
            ];
        }

        $comparison = self::compareNames($registeredName, $documentName);
        $comparison['document_name'] = trim(preg_replace('/\s+/', ' ', $documentName));

        return $comparison;
    }

    /**
     * Best-effort extraction of the full name Didit read off the scanned
     * ID document. Tries the shapes Didit's decision payload is known to
     * use, most specific first. Returns null if no name field is found
     * anywhere — callers must treat null as "cannot confirm", not as a
     * pass.
     */
    public static function extractDocumentName(array $decision): ?string
    {
        return self::extractNameFromArray($decision, 0);
    }

    /**
     * Loose but controlled name match.
     *
     * Tolerant of reordering, extra middle names, common honorifics,
     * punctuation/case differences, but strict about the actual name
     * tokens differing.
     */
    public static function namesLikelyMatch(string $registeredName, string $documentName): bool
    {
        $result = self::compareNames($registeredName, $documentName);

        return !empty($result['match']);
    }

    /**
     * Compare two names and return structured match information.
     */
    public static function compareNames(string $registeredName, ?string $documentName): array
    {
        $registeredTokens = self::normalizeName($registeredName);
        $documentTokens = self::normalizeName((string)($documentName ?? ''));

        if (empty($registeredTokens) || empty($documentTokens)) {
            return [
                'match' => false,
                'score' => 0.0,
                'reason' => empty($registeredTokens)
                    ? 'Registered account name is empty.'
                    : 'Document name is empty or unreadable.',
                'registered_tokens' => $registeredTokens,
                'document_tokens' => $documentTokens,
                'matched_tokens' => [],
            ];
        }

        $registeredSorted = $registeredTokens;
        $documentSorted = $documentTokens;

        sort($registeredSorted);
        sort($documentSorted);

        if ($registeredSorted === $documentSorted) {
            return [
                'match' => true,
                'score' => 1.0,
                'reason' => 'Exact normalized name match.',
                'registered_tokens' => $registeredTokens,
                'document_tokens' => $documentTokens,
                'matched_tokens' => $registeredTokens,
            ];
        }

        $matched = array_values(array_intersect($registeredTokens, $documentTokens));
        $matchedUnique = array_values(array_unique($matched));

        $registeredCount = count($registeredTokens);
        $documentCount = count($documentTokens);
        $matchedCount = count($matchedUnique);

        $denominator = max(1, min($registeredCount, $documentCount));
        $score = $matchedCount / $denominator;

        $match = false;
        $reason = 'Insufficient name overlap between registered name and ID document name.';

        if ($registeredCount === 1) {
            // Single-name accounts are rare but possible. Require the
            // registered token to exist in the document name.
            $match = $matchedCount === 1;
            $reason = $match
                ? 'Single registered name token matched the document name.'
                : 'The registered single name was not found on the document.';
        } else {
            // For normal multi-token names, require at least two matching
            // tokens and a strong overlap ratio. This prevents
            // "Tango Delta" from matching "Alpha Delta".
            $match = $matchedCount >= 2 && $score >= 0.75;

            if ($match) {
                $reason = 'Registered name tokens sufficiently match the document name.';
            } else {
                $missing = array_values(array_diff($registeredTokens, $documentTokens));
                $reason = 'Registered name tokens missing from document name: ' . implode(', ', $missing) . '.';
            }
        }

        return [
            'match' => $match,
            'score' => round($score, 4),
            'reason' => $reason,
            'registered_tokens' => $registeredTokens,
            'document_tokens' => $documentTokens,
            'matched_tokens' => $matchedUnique,
        ];
    }

    /**
     * Normalize a name into comparable lowercase ASCII tokens.
     */
    public static function normalizeName(string $name): array
    {
        $name = strtolower(trim($name));

        if ($name === '') {
            return [];
        }

        // Convert accented/non-ASCII characters to ASCII where possible.
        if (function_exists('transliterator_transliterate')) {
            $converted = @transliterator_transliterate('Any-Latin; Latin-ASCII', $name);

            if (is_string($converted)) {
                $name = $converted;
            }
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

            if ($converted !== false) {
                $name = $converted;
            }
        }

        // Remove common honorifics/titles/suffixes that can appear on IDs
        // or account names but are not part of the core identity.
        $ignoreWords = [
            'mr', 'mrs', 'ms', 'miss', 'dr', 'engr', 'chief', 'prince',
            'princess', 'alhaji', 'alhaja', 'hajiya', 'barr', 'prof',
            'sir', 'madam', 'mallam', 'hon', 'elder', 'pastor', 'rev',
            'capt', 'gen', 'col', 'major', 'maj', 'lt', 'sgt', 'arc',
            'oba', 'obie', 'lolo', 'dey', 'the', 'jr', 'sr', 'ii',
            'iii', 'iv', 'v',
        ];

        $name = preg_replace('/\b(' . implode('|', $ignoreWords) . ')\b\.?/', ' ', $name);
        $name = preg_replace('/[^a-z\s]/', ' ', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));

        if ($name === '') {
            return [];
        }

        $tokens = explode(' ', $name);
        $tokens = array_filter($tokens, static function ($token) {
            return trim($token) !== '';
        });

        $tokens = array_map('trim', $tokens);
        $tokens = array_values(array_unique($tokens));

        return $tokens;
    }

    /**
     * Extract a human name from a nested Didit payload array.
     */
    private static function extractNameFromArray(array $data, int $depth = 0): ?string
    {
        if ($depth > 4) {
            return null;
        }

        $candidates = [];

        // Direct full-name style fields.
        foreach (['full_name', 'fullName', 'name', 'complete_name', 'legal_name', 'holder_name', 'document_name'] as $key) {
            if (isset($data[$key])) {
                $candidates[] = self::cleanNameString($data[$key]);
            }
        }

        // First/middle/last style fields.
        $nameParts = [];

        foreach (['first_name', 'firstName', 'given_name', 'givenName', 'middle_name', 'middleName', 'last_name', 'lastName', 'surname', 'family_name', 'familyName'] as $partKey) {
            if (isset($data[$partKey]) && is_string($data[$partKey]) && trim($data[$partKey]) !== '') {
                $nameParts[] = trim($data[$partKey]);
            }
        }

        if (!empty($nameParts)) {
            $candidates[] = implode(' ', $nameParts);
        }

        // Some payloads provide a names array.
        if (!empty($data['names']) && is_array($data['names'])) {
            $nameArrayParts = [];

            foreach ($data['names'] as $nameValue) {
                if (is_string($nameValue) && trim($nameValue) !== '') {
                    $nameArrayParts[] = trim($nameValue);
                }
            }

            if (!empty($nameArrayParts)) {
                $candidates[] = implode(' ', $nameArrayParts);
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return $candidate;
            }
        }

        // Nested sections where Didit may store identity/document data.
        $nestedKeys = [
            'id_verification',
            'ID_VERIFICATION',
            'identity',
            'identity_verification',
            'document',
            'id_document',
            'document_data',
            'data',
            'result',
            'verification',
            'details',
            'person',
            'customer',
            'user',
        ];

        foreach ($nestedKeys as $nestedKey) {
            if (!empty($data[$nestedKey]) && is_array($data[$nestedKey])) {
                $found = self::extractNameFromArray($data[$nestedKey], $depth + 1);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Clean a possible name string.
     */
    private static function cleanNameString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value));

        return $value !== '' ? $value : null;
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

        if ($raw === false) {
            throw new RuntimeException('Didit request failed: ' . $err);
        }

        $decoded = json_decode($raw, true);

        // Didit returns 403 (not 401) for any auth problem, and 400 for
        // insufficient credits — both are still "the call failed",
        // surfaced generically here; callers check for missing expected fields.
        if ($code >= 400) {
            $msg = $decoded['detail'] ?? $raw;

            throw new RuntimeException(
                'Didit ' . $method . ' ' . $path . ' → ' . $code . ': ' . (is_string($msg) ? $msg : json_encode($msg))
            );
        }

        return is_array($decoded) ? $decoded : [];
    }

    private static function env(string $key, string $default = ''): string
    {
        $val = getenv($key);

        if ($val === false || $val === '') {
            $val = $_ENV[$key] ?? '';
        }

        if ($val === '' || $val === null) {
            $envFile = __DIR__ . '/../.env';

            if (is_readable($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if (strpos(trim($line), '#') === 0) {
                        continue;
                    }

                    if (strpos($line, '=') !== false) {
                        list($k, $v) = explode('=', $line, 2);

                        if (trim($k) === $key) {
                            $val = trim($v);
                            break;
                        }
                    }
                }
            }
        }

        return ($val === null || $val === '') ? $default : (string)$val;
    }
}
