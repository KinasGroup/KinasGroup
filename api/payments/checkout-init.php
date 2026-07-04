<?php
/**
 * KINAS MARKETPLACE — Checkout: create an order and initialize the
 * Paystack transaction.
 *
 * POST /api/payments/checkout-init.php
 *   { mode: 'cart' }                  → checkout everything in the buyer's cart
 *   { mode: 'buy_now', listing_id }   → checkout a single listing directly
 *
 * Per the Paystack golden rule: this is the ONLY place the secret key
 * is used. The frontend gets back an `access_code` + the PUBLIC key
 * to open the Popup with — never the secret key itself.
 *
 * Settlement:
 *   - If every item in this order belongs to the SAME agent, and that
 *     agent has a verified Paystack subaccount, the payment is split
 *     at source: the agent's bank account receives (price − commission)
 *     directly from Paystack, we never touch it.
 *   - Otherwise (a cart spanning multiple agents, or an agent who
 *     hasn't connected Paystack), the full amount goes to the
 *     platform's main account, and the agent is paid out through the
 *     existing manual payout flow — Paystack only supports a single
 *     subaccount per transaction, so a mixed-seller cart can't be
 *     auto-split in one charge.
 *
 * Fee gross-up:
 *   - If PAYSTACK_PASS_FEES_TO_BUYER is enabled, we add the estimated
 *     Paystack processing fee on top of the listing price(s) as a
 *     separate, clearly-labelled line item, and route the exact
 *     matching amount to the platform via `transaction_charge` so
 *     the agent's share is never touched by it — see
 *     includes/paystack.php for the fee math and why this uses
 *     transaction_charge rather than the subaccount's percentage split.
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/helpers.php';
require_once '../../includes/paystack.php';
require_once '../../api/config/constants.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please login to checkout']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$mode = $data['mode'] ?? 'cart';

$paystack = new PaystackService();
if (!$paystack->isEnabled()) {
    http_response_code(503);
    echo json_encode(['error' => 'Card payments are temporarily unavailable. Please try again shortly.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // ── Fetch and validate buyer contact details ──
    $userStmt = $db->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $buyer = $userStmt->fetch();
    if (!$buyer || empty($buyer['email'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Your account needs a valid email before you can check out']);
        exit;
    }

    // ── Resolve which listings are being purchased, re-checking price
    //    and availability live (never trust anything the client sent
    //    about price). ──
    if ($mode === 'buy_now') {
        $listingId = (int)($data['listing_id'] ?? 0);
        if (!$listingId) {
            http_response_code(422);
            echo json_encode(['error' => 'No item specified']);
            exit;
        }
        $stmt = $db->prepare("SELECT id, agent_id, title, price, status FROM marketplace_listings WHERE id = ?");
        $stmt->execute([$listingId]);
        $row = $stmt->fetch();
        $listings = $row ? [$row] : [];
    } else {
        $stmt = $db->prepare("
            SELECT m.id, m.agent_id, m.title, m.price, m.status
            FROM cart_items ci
            JOIN marketplace_listings m ON m.id = ci.listing_id
            WHERE ci.buyer_id = ? AND ci.listing_type = 'marketplace'
        ");
        $stmt->execute([$userId]);
        $listings = $stmt->fetchAll();
    }

    if (empty($listings)) {
        http_response_code(422);
        echo json_encode(['error' => 'No items to checkout']);
        exit;
    }

    $unavailable = array_filter($listings, fn($l) => $l['status'] !== 'active');
    if (!empty($unavailable)) {
        http_response_code(409);
        echo json_encode([
            'error' => 'Some items in your cart are no longer available: '
                . implode(', ', array_column($unavailable, 'title')),
        ]);
        exit;
    }

    $ownListing = array_filter($listings, fn($l) => (int)$l['agent_id'] === $userId);
    if (!empty($ownListing)) {
        http_response_code(422);
        echo json_encode(['error' => "You can't purchase your own listing"]);
        exit;
    }

    $subtotal = array_sum(array_map(fn($l) => (float)$l['price'], $listings));
    if ($subtotal <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid order total']);
        exit;
    }

    // ── Determine settlement: single-agent order with a verified
    //    Paystack subaccount can be auto-split at source. ──
    $agentIds = array_unique(array_map(fn($l) => (int)$l['agent_id'], $listings));
    $settlementMode  = 'platform';
    $subaccountCode  = null;

    if (count($agentIds) === 1) {
        $psStmt = $db->prepare("
            SELECT paystack_subaccount_code
            FROM payout_settings
            WHERE agent_id = ? AND payment_method = 'paystack' AND paystack_account_verified = 1
        ");
        $psStmt->execute([$agentIds[0]]);
        $code = $psStmt->fetchColumn();
        if ($code) {
            $settlementMode = 'subaccount';
            $subaccountCode = $code;
        }
    }

    // ── Fee gross-up: buyer covers the Paystack processing fee as a
    //    separate, visible line item, rather than it quietly eating
    //    into the agent's or platform's cut. ──
    $passFeesToBuyer = strtolower(getenv('PAYSTACK_PASS_FEES_TO_BUYER') ?: 'true') !== 'false';
    $feeAmount = $passFeesToBuyer
        ? round(PaystackService::grossUpForFee($subtotal) - $subtotal, 2)
        : 0.0;
    $chargeTotal = round($subtotal + $feeAmount, 2);

    // ── Create the order + snapshot line items ──
    $reference = 'KINAS-' . strtoupper(bin2hex(random_bytes(8)));

    $db->beginTransaction();

    $db->prepare("
        INSERT INTO orders
            (buyer_id, reference, email, phone, amount, subtotal_amount, fee_amount,
             settlement_mode, subaccount_code, currency, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'NGN', 'pending')
    ")->execute([
        $userId, $reference, $buyer['email'], $buyer['phone'] ?? null,
        $chargeTotal, $subtotal, $feeAmount, $settlementMode, $subaccountCode,
    ]);
    $orderId = (int)$db->lastInsertId();

    $itemStmt = $db->prepare("
        INSERT INTO order_items (order_id, listing_id, listing_type, agent_id, title, price)
        VALUES (?, ?, 'marketplace', ?, ?, ?)
    ");
    foreach ($listings as $l) {
        $itemStmt->execute([$orderId, $l['id'], $l['agent_id'], $l['title'], $l['price']]);
    }

    $db->commit();

    // ── Initialize the transaction with Paystack (server-side, secret key) ──
    $callbackUrl = url('/divisions/kinas-marketplace/checkout.php?ref=' . urlencode($reference));

    $split = [];
    if ($settlementMode === 'subaccount') {
        // transaction_charge = exactly what the MAIN account should
        // receive: our commission on the subtotal, plus the fee
        // gross-up (since the main account is the one that pays
        // Paystack's actual fee — bearer defaults to "account").
        // Whatever's left over goes straight to the agent's subaccount,
        // untouched by either the commission or the processing fee.
        $commissionKobo = (int) round($subtotal * ((float)COMMISSION_RATE) / 100 * 100);
        $feeKobo        = (int) round($feeAmount * 100);
        $split = [
            'subaccount'         => $subaccountCode,
            'transaction_charge' => $commissionKobo + $feeKobo,
            'bearer'             => 'account',
        ];
    }

    $init = $paystack->initializeTransaction(
        $buyer['email'],
        $chargeTotal,
        $reference,
        $callbackUrl,
        [
            'order_id' => $orderId,
            'buyer_id' => $userId,
            'custom_fields' => [[
                'display_name'  => 'Order Reference',
                'variable_name' => 'order_reference',
                'value'         => $reference,
            ]],
        ],
        $split
    );

    if (!$init['success']) {
        // Roll the order back to a clean "failed" state rather than
        // leaving a dangling pending order with no way to pay it.
        $db->prepare("UPDATE orders SET status = 'failed', gateway_response = ? WHERE id = ?")
           ->execute([$init['error'] ?? 'Failed to initialize payment', $orderId]);
        http_response_code(502);
        echo json_encode(['error' => $init['error'] ?? 'Unable to start payment. Please try again.']);
        exit;
    }

    $db->prepare("UPDATE orders SET paystack_access_code = ? WHERE id = ?")
       ->execute([$init['access_code'], $orderId]);

    echo json_encode([
        'success'          => true,
        'reference'        => $reference,
        'access_code'      => $init['access_code'],
        'public_key'       => $paystack->getPublicKey(),
        'subtotal'         => $subtotal,
        'subtotal_label'   => formatPrice($subtotal),
        'fee_amount'       => $feeAmount,
        'fee_label'        => $feeAmount > 0 ? formatPrice($feeAmount) : null,
        'amount'           => $chargeTotal,
        'amount_label'     => formatPrice($chargeTotal),
        'settlement_mode'  => $settlementMode,
        'email'            => $buyer['email'],
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('checkout-init.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong starting checkout. Please try again.']);
}
