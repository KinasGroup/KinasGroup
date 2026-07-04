<?php
/**
 * KINAS MARKETPLACE — Paystack webhook receiver
 *
 * POST /api/webhooks/paystack.php
 *
 * Configure this URL in the Paystack Dashboard → Settings → API Keys
 * & Webhooks. This is the AUTHORITATIVE fulfillment path — Paystack's
 * own guidance is "don't call us, we'll call you": rather than trust
 * a client-side redirect/callback, listen here for the definitive
 * word on whether a charge succeeded.
 *
 * Behaviour:
 *   1. HMAC-SHA512 verify the raw request body against our secret key
 *      (mandatory — anyone can POST a fake "charge.success" payload
 *      otherwise).
 *   2. We do NOT trust the payload's own claim that the charge
 *      succeeded — finalizeMarketplaceOrder() re-fetches the
 *      authoritative status from Paystack's Verify Transaction
 *      endpoint before delivering any value.
 *   3. Always return 200 OK quickly so Paystack stops retrying, even
 *      if we choose not to act on a particular event.
 */
require_once '../config/database.php';
require_once '../../includes/paystack.php';
require_once '../../includes/order-fulfillment.php';

// Read the raw body BEFORE anything else touches it — needed for an
// exact-byte HMAC comparison.
$rawBody = file_get_contents('php://input') ?: '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
$lower   = array_change_key_case($headers, CASE_LOWER);
$signature = $lower['x-paystack-signature'] ?? ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? null);

$paystack = new PaystackService();

if (!$paystack->isEnabled() || !$paystack->verifyWebhookSignature($rawBody, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($rawBody, true);
if (!is_array($event) || empty($event['event'])) {
    http_response_code(200); // ack anyway — malformed payloads aren't worth a retry storm
    echo json_encode(['ok' => true]);
    exit;
}

$eventName = (string)$event['event'];
$reference = (string)($event['data']['reference'] ?? '');

// We only act on successful charges; other events (transfer.*, etc.)
// are acknowledged and ignored.
if ($eventName === 'charge.success' && $reference !== '') {
    try {
        $db = Database::getInstance()->getConnection();
        $result = finalizeMarketplaceOrder($db, $reference);
        if (!$result['success']) {
            error_log("Paystack webhook: finalize failed for ref={$reference}: " . ($result['error'] ?? 'unknown'));
        }
    } catch (Throwable $e) {
        error_log('Paystack webhook processing error: ' . $e->getMessage());
    }
}

// Always 200 — acknowledging receipt is separate from whether we
// found the order interesting.
http_response_code(200);
echo json_encode(['ok' => true]);
