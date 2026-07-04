<?php
/**
 * KINAS MARKETPLACE — Called by the frontend immediately after the
 * Paystack Popup's onSuccess callback fires.
 *
 * This is a CONVENIENCE fast-path only — it does not, by itself,
 * decide the payment is real. It calls the exact same
 * finalizeMarketplaceOrder() that the webhook uses, which re-verifies
 * the transaction against Paystack's API before delivering value.
 * The webhook (api/webhooks/paystack.php) is the authoritative path
 * in case the browser tab closes before this request completes.
 *
 * POST /api/payments/checkout-verify.php   { reference }
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/order-fulfillment.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please login to verify your payment']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$reference = trim((string)($data['reference'] ?? ''));

if ($reference === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Missing payment reference']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Make sure this order actually belongs to the logged-in buyer
    // before we let them trigger fulfillment for it.
    $ownStmt = $db->prepare("SELECT id FROM orders WHERE reference = ? AND buyer_id = ?");
    $ownStmt->execute([$reference, $userId]);
    if (!$ownStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    $result = finalizeMarketplaceOrder($db, $reference);

    if (!$result['success']) {
        http_response_code(402);
        echo json_encode(['error' => $result['error'] ?? 'Payment could not be verified']);
        exit;
    }

    echo json_encode([
        'success'   => true,
        'reference' => $reference,
        'redirect'  => '/divisions/kinas-marketplace/checkout.php?ref=' . urlencode($reference) . '&status=success',
    ]);

} catch (Exception $e) {
    error_log('checkout-verify.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong verifying your payment. If you were charged, contact support with your reference: ' . $reference]);
}
