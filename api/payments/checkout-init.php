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
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/helpers.php';
require_once '../../includes/paystack.php';

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

    $total = array_sum(array_map(fn($l) => (float)$l['price'], $listings));
    if ($total <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid order total']);
        exit;
    }

    // ── Create the order + snapshot line items ──
    $reference = 'KINAS-' . strtoupper(bin2hex(random_bytes(8)));

    $db->beginTransaction();

    $db->prepare("
        INSERT INTO orders (buyer_id, reference, email, phone, amount, currency, status)
        VALUES (?, ?, ?, ?, ?, 'NGN', 'pending')
    ")->execute([$userId, $reference, $buyer['email'], $buyer['phone'] ?? null, $total]);
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
    $paystack = new PaystackService();
    if (!$paystack->isEnabled()) {
        http_response_code(503);
        echo json_encode(['error' => 'Card payments are temporarily unavailable. Please try again shortly.']);
        exit;
    }

    $callbackUrl = url('/divisions/kinas-marketplace/checkout.php?ref=' . urlencode($reference));

    $init = $paystack->initializeTransaction(
        $buyer['email'],
        $total,
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
        ]
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
        'success'      => true,
        'reference'    => $reference,
        'access_code'  => $init['access_code'],
        'public_key'   => $paystack->getPublicKey(),
        'amount'       => $total,
        'amount_label' => formatPrice($total),
        'email'        => $buyer['email'],
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('checkout-init.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong starting checkout. Please try again.']);
}
