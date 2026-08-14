<?php
/**
 * KINAS GROUP — Didit identity (KYC) & business (KYB) verification
 *
 * Docs: https://docs.didit.me
 * API:  https://verification.didit.me/v3
 *
 * OPTION 1 KYC NAME RULE:
 * - Registered name and ID document name must match.
 * - Mismatch = rejected.
 * - Unreadable document name = manual review.
 *
 * AMENDED:
 * - More tolerant Didit status mapping.
 * - Stronger document-name extraction.
 * - Safer extraction that avoids using our own metadata as the document name.
 * - More realistic name comparison, tolerant of missing middle names but
 *   still strict enough to block different identities.
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
                'session_number' => null,
                'session_token' => null,
                'url' => null,
                'status' => null,
                'workflow_id' => null,
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
                'session_number' => null,
                'session_token' => null,
                'url' => null,
                'status' => null,
                'workflow_id' => null,
                'error' => $e->getMessage(),
            ];
        }

        if (empty($res['session_id'])) {
            return [
                'success' => false,
                'session_id' => null,
                'session_number' => null,
                'session_token' => null,
                'url' => null,
                'status' => null,
                'workflow_id' => null,
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
     * More tolerant mapping of Didit statuses.
     */
    public static function mapStatus(string $diditStatus): string
    {
        $status = strtolower(trim($diditStatus));
        $status = preg_replace('/[^a-z0-9\s]+/', ' ', $status);
        $status = preg_replace('/\s+/', ' ', trim((string)$status));

        switch ($status) {
            case 'approved':
            case 'approve':
            case 'success':
            case 'succeeded':
            case 'completed':
            case 'complete':
            case 'verified':
            case 'kyc approved':
            case 'id approved':
            case 'identity approved':
                return 'approved';

            case 'declined':
            case 'rejected':
            case 'failed':
            case 'failure':
            case 'kyc declined':
            case 'id declined':
            case 'identity declined':
                return 'rejected';

            case 'in review':
            case 'review':
            case 'manual review':
            case 'requires review':
            case 'under review':
            case 'pending review':
                return 'review_needed';

            case 'in progress':
            case 'awaiting user':
            case 'resubmitted':
            case 'pending user':
            case 'user pending':
            case 'started':
                return 'in_progress';

            case 'abandoned':
            case 'expired':
            case 'kyc expired':
            case 'session expired':
                return 'expired';

            case 'not started':
            case 'created':
            default:
                return 'created';
        }
    }

    // ──────────────────────────────────────────────────────────
    // Identity cross-check
    // ──────────────────────────────────────────────────────────

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

    public static function extractDocumentName(array $decision): ?string
    {
        return self::extractNameFromArray($decision, 0);
    }

    public static function namesLikelyMatch(string $registeredName, string $documentName): bool
    {
        $result = self::compareNames($registeredName, $documentName);
        return !empty($result['match']);
    }

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

        if ($registeredCount === 1 || $documentCount === 1) {
            $match = $matchedCount >= 1;
            $reason = $match
                ? 'Single-name token matched.'
                : 'No matching name token found.';
        } else {
            /*
             * Option 1 rule:
             * - Two-name accounts must match both names.
             * - Longer names may omit a middle name on the ID, so we allow
             *   a strong partial match.
             * - Different first/last identities should still fail.
             */
            $requiredMatches = min(2, $denominator);

            $match = $matchedCount >= $requiredMatches && $score >= 0.60;

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

    public static function normalizeName(string $name): array
    {
        $name = strtolower(trim($name));

        if ($name === '') {
            return [];
        }

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

    private static function extractNameFromArray(array $data, int $depth = 0): ?string
    {
        if ($depth > 6) {
            return null;
        }

        $candidates = [];

        /*
         * Direct full-name style fields.
         *
         * At root level, we avoid using a bare "name" key because it may be
         * a workflow name, event name, or other non-identity field.
         * In nested arrays, bare "name" is much more likely to be identity data.
         */
        $fullNameKeys = [
            'full_name',
            'fullName',
            'fullname',
            'complete_name',
            'completeName',
            'legal_name',
            'legalName',
            'holder_name',
            'holderName',
            'document_name',
            'documentName',
            'name_on_document',
            'nameOnDocument',
            'name_on_id',
            'nameOnId',
            'document_holder_name',
            'documentHolderName',
            'person_name',
            'personName',
            'applicant_name',
            'applicantName',
            'customer_name',
            'customerName',
            'identity_name',
            'identityName',
        ];

        if ($depth > 0) {
            $fullNameKeys[] = 'name';
        }

        foreach ($fullNameKeys as $key) {
            if (isset($data[$key])) {
                $candidate = self::cleanNameString($data[$key]);
                if ($candidate !== null && $candidate !== '') {
                    $candidates[] = $candidate;
                }
            }
        }

        // First / middle / last style fields.
        $nameParts = [];

        $firstKeys = [
            'first_name',
            'firstName',
            'given_name',
            'givenName',
            'given_names',
            'givenNames',
            'name1',
            'first_name_1',
            'firstName1',
        ];

        $middleKeys = [
            'middle_name',
            'middleName',
            'middle_names',
            'middleNames',
            'second_name',
            'secondName',
            'name2',
            'patronymic',
        ];

        $lastKeys = [
            'last_name',
            'lastName',
            'surname',
            'family_name',
            'familyName',
            'family_name1',
            'lastName1',
            'name3',
        ];

        foreach ($firstKeys as $key) {
            if (isset($data[$key])) {
                $value = self::cleanNameString($data[$key]);
                if ($value !== null) {
                    $nameParts[] = $value;
                }
            }
        }

        foreach ($middleKeys as $key) {
            if (isset($data[$key])) {
                $value = self::cleanNameString($data[$key]);
                if ($value !== null) {
                    $nameParts[] = $value;
                }
            }
        }

        foreach ($lastKeys as $key) {
            if (isset($data[$key])) {
                $value = self::cleanNameString($data[$key]);
                if ($value !== null) {
                    $nameParts[] = $value;
                }
            }
        }

        if (!empty($nameParts)) {
            $candidates[] = implode(' ', $nameParts);
        }

        // Some payloads provide a names array.
        if (!empty($data['names']) && is_array($data['names'])) {
            $nameArrayParts = [];

            foreach ($data['names'] as $nameValue) {
                $value = self::cleanNameString($nameValue);
                if ($value !== null) {
                    $nameArrayParts[] = $value;
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
            'identity_data',
            'document',
            'documents',
            'id_document',
            'document_data',
            'document_information',
            'extracted_data',
            'extraction',
            'ocr',
            'data',
            'result',
            'results',
            'verification',
            'details',
            'person',
            'person_data',
            'person_details',
            'personal_information',
            'customer',
            'user',
            'applicant',
            'application',
            'candidate',
            'record',
            'records',
            'check',
            'checks',
            'output',
            'outputs',
            'decision',
        ];

        foreach ($nestedKeys as $nestedKey) {
            if (!empty($data[$nestedKey]) && is_array($data[$nestedKey])) {
                $found = self::extractNameFromArray($data[$nestedKey], $depth + 1);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        /*
         * Generic recursive search.
         *
         * Skip fields that contain our own metadata or non-identity data.
         */
        $skipKeys = [
            'metadata',
            'vendor_data',
            'callback',
            'contact_details',
            'expected_name',
            'webhook',
            'event',
            'session_data',
            'workflow',
            'status',
            'error',
        ];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $skipKeys, true)) {
                continue;
            }

            if (is_array($value)) {
                $found = self::extractNameFromArray($value, $depth + 1);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private static function cleanNameString($value): ?string
    {
        if (is_array($value)) {
            $parts = [];

            array_walk_recursive($value, static function ($item) use (&$parts) {
                if (is_string($item) && trim($item) !== '') {
                    $parts[] = trim($item);
                }
            });

            $value = implode(' ', $parts);
        }

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
