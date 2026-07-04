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
 * Behaviour:
 *   1. Verify the signature (X-Signature, falling back to
 *      X-Signature-Simple) and reject stale timestamps — mandatory.
 *   2. Look up which of our users + which workflow (kyc/kyb) this
 *      session_id belongs to.
 *   3. Re-fetch the authoritative decision from Didit's API rather
 *      than trusting the webhook payload's status for the final call
 *      (falls back to the signed payload if the re-fetch itself fails).
 *   4. Persist the decision, update the relevant agent_profiles
 *      column (kyc or kyb), and flip users.verified for KYC approvals.
 *   5. Dedupe on event_id — Didit reuses the same event_id on retries
 *      and across fan-out destinations.
 *
 * Always returns 2xx quickly (Didit's delivery timeout is 5 seconds)
 * so it stops retrying, even for events we choose not to act on.
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/didit.php';

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

if ($didit->isEnabled() && !$didit->verifyWebhookSignature($rawBody, $signature, $signatureSimple, $timestamp, $sessionId, $diditStatus, $webhookType)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$db     = Database::getInstance()->getConnection();
$lookup = $db->prepare("SELECT user_id, session_type, last_event_id FROM didit_verifications WHERE session_id = ?");
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
$sessionType = $verification['session_type']; // 'kyc' | 'kyb'

// Re-fetch the authoritative decision rather than trusting the (already
// signature-verified) payload alone — cheap insurance against a stale
// or partial webhook body.
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
$isFinal        = in_array($internalStatus, ['approved', 'rejected'], true);

$db->beginTransaction();
try {
    $db->prepare("
        UPDATE didit_verifications
        SET status          = ?,
            didit_status    = ?,
            decision_payload= ?,
            last_event_id   = ?,
            completed_at    = COALESCE(completed_at, ?),
            updated_at      = NOW()
        WHERE session_id = ?
    ")->execute([
        $internalStatus, $diditStatus, json_encode($decision), $eventId ?: null,
        $isFinal ? $now : null, $sessionId,
    ]);

    if ($sessionType === 'kyc') {
        $db->prepare("
            UPDATE agent_profiles
            SET verification_status = ?,
                kyc_decision_at     = CASE WHEN ? THEN ? ELSE kyc_decision_at END,
                kyc_submitted_at    = COALESCE(kyc_submitted_at, ?)
            WHERE user_id = ?
        ")->execute([$internalStatus, $isFinal ? 1 : 0, $isFinal ? $now : null, $now, $userId]);

        if ($internalStatus === 'approved') {
            $db->prepare("UPDATE users SET verified = 1, status = 'active' WHERE id = ?")->execute([$userId]);
            if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
                $_SESSION['user_verified'] = true;
            }
        } elseif ($internalStatus === 'rejected') {
            $db->prepare("UPDATE users SET verified = 0 WHERE id = ?")->execute([$userId]);
        }
    } else { // kyb
        // Didit's KYB status vocabulary maps onto our internal set the
        // same way KYC does, except our column uses 'not_started'
        // rather than 'created' for the initial state.
        $kybStatus = $internalStatus === 'created' ? 'not_started' : $internalStatus;

        // Pull the registry snapshot out of the decision payload when present.
        $registrySnapshot = null;
        if (is_array($decision) && !empty($decision['kyb_registry'])) {
            $registrySnapshot = json_encode($decision['kyb_registry']);
        }

        $db->prepare("
            UPDATE agent_profiles
            SET kyb_status         = ?,
                kyb_decision_at    = CASE WHEN ? THEN ? ELSE kyb_decision_at END,
                kyb_submitted_at   = COALESCE(kyb_submitted_at, ?),
                kyb_registry_snapshot = COALESCE(?, kyb_registry_snapshot)
            WHERE user_id = ?
        ")->execute([$kybStatus, $isFinal ? 1 : 0, $isFinal ? $now : null, $now, $registrySnapshot, $userId]);
    }

    Security::logActivity($userId, $sessionType . '_' . $internalStatus, "Didit " . strtoupper($sessionType) . " $sessionId → $diditStatus → $internalStatus");

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    error_log('Didit webhook DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true]);
