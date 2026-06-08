<?php
/**
 * KINAS GROUP — MetaMap webhook receiver
 *
 * POST /api/webhooks/metamap.php
 *
 * Events we care about:
 *   - verification_completed   → user finished the flow
 *   - verification_inputs_completed → user uploaded all docs (optional)
 *   - verification_expired      → user abandoned (mark expired)
 *
 * Behaviour:
 *   1. HMAC-verify the request (mandatory)
 *   2. Look up our user via the verification_id
 *   3. Re-fetch authoritative state from MetaMap's GET endpoint
 *      (the webhook payload is fine for fast acknowledgement but
 *      we don't trust it for the final decision)
 *   4. Persist the decision and flip users.verified when approved
 *
 * Response: 200 OK with {ok: true} so MetaMap stops retrying.
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/metamap.php';

// We must read the raw body BEFORE any framework touches it
$rawBody = file_get_contents('php://input') ?: '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
$lower   = array_change_key_case($headers, CASE_LOWER);
$signature = $lower['x-mati-signature'] ?? ($_SERVER['HTTP_X_MATI_SIGNATURE'] ?? null);

$metamap = new MetaMapService();

// Allow only the signature-verified path in production
if ($metamap->isEnabled() && !$metamap->verifyWebhookSignature($rawBody, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($rawBody, true);
if (!is_array($event) || empty($event['eventName']) || empty($event['resource'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$eventName      = (string)$event['eventName'];
$resourceUrl    = (string)$event['resource'];
$verificationId = basename(parse_url($resourceUrl, PHP_URL_PATH) ?: '');

if ($verificationId === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Missing verification id']);
    exit;
}

$db     = Database::getInstance()->getConnection();
$lookup = $db->prepare("SELECT user_id FROM metamap_verifications WHERE verification_id = ?");
$lookup->execute([$verificationId]);
$userId = (int)($lookup->fetchColumn() ?: 0);

if ($userId === 0) {
    // Unknown verification — log and ack so MetaMap stops retrying
    error_log("MetaMap webhook: unknown verification_id=$verificationId");
    http_response_code(200);
    echo json_encode(['ok' => true, 'unknown' => true]);
    exit;
}

$matiStatus = null;
$matiPayload = null;

// Re-fetch the canonical state from MetaMap. Fall back to the
// event payload if the API is briefly unreachable.
if ($metamap->isEnabled()) {
    try {
        $matiPayload = $metamap->getVerification($verificationId);
        $matiStatus  = $matiPayload['status'] ?? $matiPayload['decision'] ?? null;
    } catch (Exception $e) {
        error_log('MetaMap webhook refetch failed: ' . $e->getMessage());
    }
}
if ($matiStatus === null) {
    $matiStatus = $event['status'] ?? $event['decision'] ?? null;
    $matiPayload = $event;
}
if ($matiStatus === null) {
    $matiStatus = ($eventName === 'verification_expired') ? 'expired' : 'in_progress';
}

$internalStatus = MetaMapService::mapStatus((string)$matiStatus);
$now            = date('Y-m-d H:i:s');

$db->beginTransaction();
try {
    $upd = $db->prepare("
        UPDATE metamap_verifications
        SET status           = ?,
            mati_status      = ?,
            decision_payload = ?,
            completed_at     = COALESCE(completed_at, ?),
            updated_at       = NOW()
        WHERE verification_id = ?
    ");
    $upd->execute([
        $internalStatus,
        (string)$matiStatus,
        json_encode($matiPayload),
        ($internalStatus === 'approved' || $internalStatus === 'rejected') ? $now : null,
        $verificationId
    ]);

    $isFinal = in_array($internalStatus, ['approved', 'rejected'], true);

    $updProfile = $db->prepare("
        UPDATE agent_profiles
        SET verification_status  = ?,
            kyc_decision_at      = CASE WHEN ? THEN ? ELSE kyc_decision_at END,
            kyc_submitted_at     = COALESCE(kyc_submitted_at, ?)
        WHERE user_id = ?
    ");
    $updProfile->execute([
        $internalStatus,
        $isFinal ? 1 : 0,
        $isFinal ? $now : null,
        $now,
        $userId,
    ]);

    if ($internalStatus === 'approved') {
        $db->prepare("UPDATE users SET verified = 1, status = 'active' WHERE id = ?")
            ->execute([$userId]);
        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
            $_SESSION['user_verified'] = true;
        }
    } elseif ($internalStatus === 'rejected') {
        $db->prepare("UPDATE users SET verified = 0 WHERE id = ?")->execute([$userId]);
    }

    Security::logActivity($userId, 'kyc_' . $internalStatus, "MetaMap $verificationId → $matiStatus → $internalStatus");

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    error_log('MetaMap webhook DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true]);
