<?php
/**
 * KINAS GROUP — Didit webhook receiver (KYC + KYB, one endpoint)
 *
 * POST /api/webhooks/didit.php
 *
 * Configure this URL as a webhook destination in the Didit Console
 * (Business Console → API & Webhooks), subscribed to at least
 * "status.updated".
 *
 * OPTION 1 KYC NAME RULE:
 * - Didit approved + registered name matches ID document name => approved/kyc_passed
 * - Didit approved + ID document name does not match => rejected
 * - Didit approved + ID document name cannot be read => review_needed
 *
 * BUSINESS RULES:
 * - KYC approved => users.verified = 1 for identity verification.
 * - Business + KYC approved + KYB not approved => verification_status 'kyc_passed'.
 * - KYB approved while status is 'kyc_passed' => promote to full 'approved'.
 *
 * Always returns 2xx quickly where possible so Didit stops retrying,
 * except for invalid payload/signature cases.
 */

require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/didit.php';

// ============================================================
// LOCAL HELPERS
// ============================================================

function didit_webhook_column_exists($db, string $table, string $column): bool
{
    static $cache = [];

    $key = $table . '.' . $column;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
        ");
        $stmt->execute([$table, $column]);
        $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

/**
 * Fallback name comparison.
 *
 * Used only if DiditService::namesLikelyMatch() is unavailable.
 * The preferred implementation lives in includes/didit.php.
 */
function didit_webhook_fallback_names_match(string $registeredName, string $documentName): bool
{
    $normalize = static function (string $name): array {
        $name = trim($name);

        if ($name === '') {
            return [];
        }

        if (function_exists('mb_strtolower')) {
            $name = mb_strtolower($name, 'UTF-8');
        } else {
            $name = strtolower($name);
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

        return array_values(array_unique($tokens));
    };

    $registeredTokens = $normalize($registeredName);
    $documentTokens = $normalize($documentName);

    if (empty($registeredTokens) || empty($documentTokens)) {
        return false;
    }

    $overlap = count(array_intersect($registeredTokens, $documentTokens));
    $shorter = min(count($registeredTokens), count($documentTokens));

    if ($shorter <= 0) {
        return false;
    }

    /*
     * Fallback is intentionally a little stricter than the main
     * DiditService comparison, but still tolerant of middle-name
     * omission and ordering differences.
     */
    $score = $overlap / $shorter;

    if ($shorter === 1) {
        return $overlap >= 1;
    }

    return $overlap >= min(2, $shorter) && $score >= 0.60;
}

// ============================================================
// RECEIVE WEBHOOK
// ============================================================

// Raw bytes BEFORE anything else touches them — required for X-Signature.
$rawBody = file_get_contents('php://input') ?: '';

$headers = function_exists('getallheaders') ? getallheaders() : [];
$lower   = array_change_key_case($headers, CASE_LOWER);

$signature       = $lower['x-signature'] ?? ($_SERVER['HTTP_X_SIGNATURE'] ?? null);
$signatureSimple = $lower['x-signature-simple'] ?? ($_SERVER['HTTP_X_SIGNATURE_SIMPLE'] ?? null);
$timestamp       = $lower['x-timestamp'] ?? ($_SERVER['HTTP_X_TIMESTAMP'] ?? null);

$event = json_decode($rawBody, true);

if (!is_array($event) || empty($event['session_id']) || empty($event['status'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$sessionId   = (string)$event['session_id'];
$diditStatus = (string)$event['status'];
$webhookType = (string)($event['webhook_type'] ?? 'status.updated');
$eventId     = (string)($event['event_id'] ?? '');

$didit = new DiditService();

if ($didit->isEnabled() && !$didit->verifyWebhookSignature(
    $rawBody,
    $signature,
    $signatureSimple,
    $timestamp,
    $sessionId,
    $diditStatus,
    $webhookType
)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$db = Database::getInstance()->getConnection();

$lookup = $db->prepare("
    SELECT user_id, session_type, last_event_id
    FROM didit_verifications
    WHERE session_id = ?
");
$lookup->execute([$sessionId]);
$verification = $lookup->fetch(PDO::FETCH_ASSOC);

if (!$verification) {
    error_log("Didit webhook: unknown session_id=$sessionId");

    http_response_code(200);
    echo json_encode(['ok' => true, 'unknown' => true]);
    exit;
}

// Idempotency — Didit reuses event_id on retries and fan-out.
if ($eventId !== '' && $verification['last_event_id'] === $eventId) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'duplicate' => true]);
    exit;
}

$userId      = (int)$verification['user_id'];
$sessionType = (string)$verification['session_type']; // 'kyc' | 'kyb'

// ============================================================
// FETCH AUTHORITATIVE DECISION
// ============================================================

$decision = null;

if ($didit->isEnabled()) {
    $fetched = $didit->getDecision($sessionId);

    if ($fetched['success']) {
        $diditStatus = $fetched['status'];
        $decision    = $fetched['decision'];
    }
}

if ($decision === null) {
    $decision = $event['decision'] ?? $event;
}

$internalStatus = DiditService::mapStatus($diditStatus);
$now            = date('Y-m-d H:i:s');

// ============================================================
// OPTION 1 — KYC NAME-MATCH ENFORCEMENT
// ============================================================

$registeredName     = '';
$documentName       = null;
$nameMatchState     = null; // matched | mismatched | unreadable
$nameMismatch       = 0;
$kycRejectionReason = null;

if ($sessionType === 'kyc' && is_array($decision)) {
    // Extract name from Didit decision payload where possible.
    if (method_exists('DiditService', 'extractDocumentName')) {
        try {
            $documentName = DiditService::extractDocumentName($decision);
        } catch (Throwable $e) {
            $documentName = null;
        }
    }

    if (is_string($documentName)) {
        $documentName = trim(preg_replace('/\s+/', ' ', $documentName));

        if ($documentName === '') {
            $documentName = null;
        }
    }

    // Get registered account name.
    try {
        $userRow = $db->prepare("
            SELECT name
            FROM users
            WHERE id = ?
        ");
        $userRow->execute([$userId]);
        $registeredName = trim((string)($userRow->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        $registeredName = '';
    }

    // Only enforce name matching when Didit would otherwise approve KYC.
    if ($internalStatus === 'approved') {
        if ($registeredName === '') {
            $nameMatchState = 'unreadable';
            $internalStatus = 'review_needed';
            $kycRejectionReason = 'Registered account name is missing. Manual review required.';
        } elseif ($documentName === null) {
            $nameMatchState = 'unreadable';
            $internalStatus = 'review_needed';
            $kycRejectionReason = 'ID document name could not be read. Manual review required.';
        } else {
            $namesMatch = false;

            try {
                if (method_exists('DiditService', 'namesLikelyMatch')) {
                    $namesMatch = (bool)DiditService::namesLikelyMatch($registeredName, $documentName);
                } else {
                    $namesMatch = didit_webhook_fallback_names_match($registeredName, $documentName);
                }
            } catch (Throwable $e) {
                $namesMatch = didit_webhook_fallback_names_match($registeredName, $documentName);
            }

            if ($namesMatch) {
                $nameMatchState = 'matched';
            } else {
                $nameMatchState = 'mismatched';
                $nameMismatch   = 1;
                $internalStatus = 'rejected';
                $kycRejectionReason = 'ID document name does not correspond with the registered account name.';

                error_log(
                    "Didit KYC name mismatch: user_id=$userId "
                    . "registered_name=\"$registeredName\" "
                    . "document_name=\"$documentName\" "
                    . "session=$sessionId"
                );
            }
        }
    }
}

$isDecision = in_array($internalStatus, ['approved', 'rejected'], true);
$isTerminal = $isDecision || $internalStatus === 'expired';

// ============================================================
// PERSIST RESULT
// ============================================================

$db->beginTransaction();

try {
    // ------------------------------------------------------------
    // Update didit_verifications
    // ------------------------------------------------------------

    $decisionPayload = json_encode(
        $decision,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
    );

    $sets = [
        'status = ?',
        'didit_status = ?',
        'decision_payload = ?',
        'last_event_id = ?',
        'completed_at = COALESCE(completed_at, ?)',
        'updated_at = NOW()',
    ];

    $params = [
        $internalStatus,
        $diditStatus,
        $decisionPayload !== false ? $decisionPayload : null,
        $eventId !== '' ? $eventId : null,
        $isTerminal ? $now : null,
    ];

    if (didit_webhook_column_exists($db, 'didit_verifications', 'expected_name')) {
        $sets[] = 'expected_name = COALESCE(?, expected_name)';
        $params[] = $registeredName !== '' ? $registeredName : null;
    }

    if (didit_webhook_column_exists($db, 'didit_verifications', 'document_name')) {
        $sets[] = 'document_name = ?';
        $params[] = $documentName;
    }

    if (didit_webhook_column_exists($db, 'didit_verifications', 'name_match') && $nameMatchState !== null) {
        $sets[] = 'name_match = ?';
        $params[] = $nameMatchState;
    }

    $sql = "
        UPDATE didit_verifications
        SET " . implode(', ', $sets) . "
        WHERE session_id = ?
    ";

    $params[] = $sessionId;

    $db->prepare($sql)->execute($params);

    // ------------------------------------------------------------
    // Update agent profile / user verification state
    // ------------------------------------------------------------

    if ($sessionType === 'kyc') {
        $profileRow = $db->prepare("
            SELECT company_name, kyb_status
            FROM agent_profiles
            WHERE user_id = ?
        ");
        $profileRow->execute([$userId]);
        $profile = $profileRow->fetch(PDO::FETCH_ASSOC) ?: [];

        $isBusiness         = trim((string)($profile['company_name'] ?? '')) !== '';
        $kybAlreadyApproved = (($profile['kyb_status'] ?? '') === 'approved');

        /*
         * KYC approval means the person's identity has been verified.
         *
         * For business accounts, full account approval still requires KYB,
         * so verification_status becomes 'kyc_passed' until KYB clears.
         */
        $kycStatusToStore = $internalStatus;
        $grantsVerified   = false;

        if ($internalStatus === 'approved') {
            if ($isBusiness && !$kybAlreadyApproved) {
                $kycStatusToStore = 'kyc_passed';
            }

            $grantsVerified = true;
        }

        $profileSets = [
            'verification_status = ?',
        ];

        $profileParams = [
            $kycStatusToStore,
        ];

        if (didit_webhook_column_exists($db, 'agent_profiles', 'kyc_decision_at')) {
            $profileSets[] = 'kyc_decision_at = CASE WHEN ? THEN ? ELSE kyc_decision_at END';
            $profileParams[] = $isDecision ? 1 : 0;
            $profileParams[] = $isDecision ? $now : null;
        }

        if (didit_webhook_column_exists($db, 'agent_profiles', 'kyc_submitted_at')) {
            $profileSets[] = 'kyc_submitted_at = COALESCE(kyc_submitted_at, ?)';
            $profileParams[] = $now;
        }

        if (didit_webhook_column_exists($db, 'agent_profiles', 'kyc_document_name')) {
            $profileSets[] = 'kyc_document_name = COALESCE(?, kyc_document_name)';
            $profileParams[] = $documentName;
        }

        if (didit_webhook_column_exists($db, 'agent_profiles', 'kyc_name_match') && $nameMatchState !== null) {
            $profileSets[] = 'kyc_name_match = ?';
            $profileParams[] = $nameMatchState;
        }

        if (didit_webhook_column_exists($db, 'agent_profiles', 'kyc_name_mismatch')) {
            $profileSets[] = 'kyc_name_mismatch = ?';
            $profileParams[] = $nameMismatch;
        }

        if (didit_webhook_column_exists($db, 'agent_profiles', 'kyc_rejection_reason')) {
            $profileSets[] = 'kyc_rejection_reason = ?';
            $profileParams[] = $kycRejectionReason;
        }

        if (didit_webhook_column_exists($db, 'agent_profiles', 'kyc_provider')) {
            $profileSets[] = "kyc_provider = 'didit'";
        }

        $profileSql = "
            UPDATE agent_profiles
            SET " . implode(', ', $profileSets) . "
            WHERE user_id = ?
        ";

        $profileParams[] = $userId;

        $db->prepare($profileSql)->execute($profileParams);

        if ($grantsVerified) {
            $db->prepare("
                UPDATE users
                SET verified = 1,
                    status = 'active'
                WHERE id = ?
            ")->execute([$userId]);

            if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
                $_SESSION['user_verified'] = true;
            }
        } elseif ($internalStatus === 'rejected') {
            $db->prepare("
                UPDATE users
                SET verified = 0
                WHERE id = ?
            ")->execute([$userId]);

            if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
                $_SESSION['user_verified'] = false;
            }
        }

        // 'review_needed' deliberately does NOT touch users.verified —
        // it stays whatever it was until an admin makes the call.
    } else {
        // ------------------------------------------------------------
        // KYB
        // ------------------------------------------------------------

        $kybStatus = $internalStatus === 'created' ? 'not_started' : $internalStatus;

        $registrySnapshot = null;

        if (is_array($decision) && !empty($decision['kyb_registry'])) {
            $registrySnapshot = json_encode(
                $decision['kyb_registry'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
        }

        $kybSets = [
            'kyb_status = ?',
        ];

        $kybParams = [
            $kybStatus,
        ];

        if (didit_webhook_column_exists($db, 'agent_profiles', 'kyb_decision_at')) {
            $kybSets[] = 'kyb_decision_at = CASE WHEN ? THEN ? ELSE kyb_decision_at END';
            $kybParams[] = $isDecision ? 1 : 0;
            $kybParams[] = $isDecision ? $now : null;
        }

        if (didit_webhook_column_exists($db, 'agent_profiles', 'kyb_submitted_at')) {
            $kybSets[] = 'kyb_submitted_at = COALESCE(kyb_submitted_at, ?)';
            $kybParams[] = $now;
        }

        if (didit_webhook_column_exists($db, 'agent_profiles', 'kyb_registry_snapshot') && $registrySnapshot !== null) {
            $kybSets[] = 'kyb_registry_snapshot = COALESCE(?, kyb_registry_snapshot)';
            $kybParams[] = $registrySnapshot;
        }

        $kybSql = "
            UPDATE agent_profiles
            SET " . implode(', ', $kybSets) . "
            WHERE user_id = ?
        ";

        $kybParams[] = $userId;

        $db->prepare($kybSql)->execute($kybParams);

        /*
         * KYB just cleared — if identity (KYC) already passed too, this
         * business account is now fully approved.
         */
        if ($kybStatus === 'approved') {
            $kycRow = $db->prepare("
                SELECT verification_status
                FROM agent_profiles
                WHERE user_id = ?
            ");
            $kycRow->execute([$userId]);
            $kycState = (string)$kycRow->fetchColumn();

            if ($kycState === 'kyc_passed') {
                $db->prepare("
                    UPDATE agent_profiles
                    SET verification_status = 'approved'
                    WHERE user_id = ?
                ")->execute([$userId]);

                $db->prepare("
                    UPDATE users
                    SET verified = 1,
                        status = 'active'
                    WHERE id = ?
                ")->execute([$userId]);

                if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
                    $_SESSION['user_verified'] = true;
                }
            }
        }
    }

    $logMessage = "Didit " . strtoupper($sessionType) . " $sessionId → $diditStatus → $internalStatus";

    if ($sessionType === 'kyc' && $nameMismatch) {
        $logMessage .= " (name mismatch: registered=\"$registeredName\", document=\"$documentName\")";
    }

    if (class_exists('Security')) {
        Security::logActivity(
            $userId,
            $sessionType . '_' . $internalStatus,
            $logMessage
        );
    }

    $db->commit();
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Didit webhook DB error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true]);
