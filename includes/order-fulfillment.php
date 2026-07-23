<?php
/**
 * KINAS MARKETPLACE — Order fulfillment
 *
 * Single source of truth for "an order got paid, now what". Called
 * from TWO places:
 *   - api/payments/checkout-verify.php  (fast path, right after the
 *     Paystack Popup calls onSuccess in the browser)
 *   - api/webhooks/paystack.php         (authoritative path — Paystack's
 *     "don't call us, we'll call you" webhook)
 *
 * Both callers hit the exact same function so behaviour can never
 * drift between the two, and the function is written to be safely
 * called twice for the same reference (idempotent) since both paths
 * can fire for the same payment.
 *
 * IMPORTANT: this function does NOT trust its caller about whether a
 * payment succeeded. It always re-verifies against Paystack's API
 * before marking anything as paid or delivering value.
 */
require_once __DIR__ . '/paystack.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/../api/config/constants.php';

/**
 * @return array{success: bool, already: bool, error: ?string, order: ?array}
 */
function finalizeMarketplaceOrder(PDO $db, string $reference): array
{
    $reference = trim($reference);
    if ($reference === '') {
        return ['success' => false, 'already' => false, 'error' => 'Missing reference', 'order' => null];
    }

    $db->beginTransaction();

    try {
        // Row-lock the order so a simultaneous webhook + client-verify
        // call for the same reference can't both try to fulfil it.
        $stmt = $db->prepare("SELECT * FROM orders WHERE reference = ? FOR UPDATE");
        $stmt->execute([$reference]);
        $order = $stmt->fetch();

        if (!$order) {
            $db->rollBack();
            return ['success' => false, 'already' => false, 'error' => 'Order not found', 'order' => null];
        }

        if ($order['status'] === 'paid') {
            // Already fulfilled by the other path — nothing more to do.
            $db->commit();
            return ['success' => true, 'already' => true, 'error' => null, 'order' => $order];
        }

        // ── The golden rule: verify against Paystack's API, never trust
        //    the caller (client callback or webhook payload) alone. ──
        $paystack = new PaystackService();
        $verify   = $paystack->verifyTransaction($reference);

        if (!$verify['success'] || $verify['status'] !== 'success') {
            $db->prepare("UPDATE orders SET status = 'failed', gateway_response = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$verify['gateway_response'] ?? ($verify['error'] ?? 'Verification failed'), $order['id']]);
            $db->commit();
            return ['success' => false, 'already' => false, 'error' => $verify['error'] ?? 'Payment was not successful', 'order' => $order];
        }

        // Amount + currency must match exactly what we asked for.
        $expectedKobo = (int) round(((float)$order['amount']) * 100);
        if ((int)$verify['amount_kobo'] !== $expectedKobo || strtoupper((string)$verify['currency']) !== strtoupper($order['currency'])) {
            error_log("Paystack amount mismatch on order #{$order['id']} ref={$reference}: expected {$expectedKobo} {$order['currency']}, got {$verify['amount_kobo']} {$verify['currency']}");
            $db->prepare("UPDATE orders SET status = 'failed', gateway_response = 'Amount/currency mismatch', updated_at = NOW() WHERE id = ?")
               ->execute([$order['id']]);
            $db->commit();
            return ['success' => false, 'already' => false, 'error' => 'Payment amount did not match the order', 'order' => $order];
        }

        // ── Verified. Mark the order paid and deliver value. ──
        $db->prepare("
            UPDATE orders
               SET status = 'paid', paid_at = NOW(), paystack_channel = ?, gateway_response = ?, updated_at = NOW()
             WHERE id = ?
        ")->execute([$verify['channel'], $verify['gateway_response'], $order['id']]);

        $itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$order['id']]);
        $items = $itemsStmt->fetchAll();

        $buyerStmt = $db->prepare("SELECT name FROM users WHERE id = ?");
        $buyerStmt->execute([$order['buyer_id']]);
        $buyerName = $buyerStmt->fetchColumn() ?: 'Buyer';

        $commissionPct = (float) COMMISSION_RATE;

        foreach ($items as $item) {
            // Lock + check the listing hasn't already been sold via a
            // different order (shouldn't happen if init validated it,
            // but a listing could theoretically be purchased twice if
            // two buyers checked out at once before either paid).
            $lockStmt = $db->prepare("SELECT status FROM marketplace_listings WHERE id = ? FOR UPDATE");
            $lockStmt->execute([$item['listing_id']]);
            $currentStatus = $lockStmt->fetchColumn();

            if ($currentStatus !== false && $currentStatus !== 'sold') {
                $db->prepare("UPDATE marketplace_listings SET status = 'sold' WHERE id = ?")
                   ->execute([$item['listing_id']]);
            }

            $commission = round($item['price'] * $commissionPct / 100, 2);

            $db->prepare("
                INSERT INTO transactions
                    (agent_id, listing_id, listing_type, order_id, buyer_id, payment_method,
                     paystack_reference, settlement_mode, buyer_name, buyer_email, amount, commission_pct,
                     commission, currency, status, paid_at)
                VALUES
                    (?, ?, 'marketplace', ?, ?, 'paystack',
                     ?, ?, ?, ?, ?, ?,
                     ?, ?, 'paid', NOW())
            ")->execute([
                $item['agent_id'], $item['listing_id'], $order['id'], $order['buyer_id'],
                $reference, $order['settlement_mode'], $buyerName, $order['email'], $item['price'], $commissionPct,
                $commission, $order['currency'],
            ]);
        }

        // Clear these items out of the buyer's cart if they were bought
        // straight from there (harmless no-op for "Buy Now" purchases).
        if (!empty($items)) {
            $listingIds = array_column($items, 'listing_id');
            $in = implode(',', array_fill(0, count($listingIds), '?'));
            $db->prepare("DELETE FROM cart_items WHERE buyer_id = ? AND listing_type = 'marketplace' AND listing_id IN ($in)")
               ->execute(array_merge([$order['buyer_id']], $listingIds));
        }

        $db->commit();

        $order['status']  = 'paid';
        $order['items']   = $items;

        // Notifications are best-effort — never let a delivery failure
        // undo a payment that has already been recorded.
        notifyOrderPaid($db, $order, $items);

        return ['success' => true, 'already' => false, 'error' => null, 'order' => $order];

    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('finalizeMarketplaceOrder failed for ref=' . $reference . ': ' . $e->getMessage());
        return ['success' => false, 'already' => false, 'error' => 'Internal error finalizing order', 'order' => null];
    }
}

/**
 * Best-effort receipt to the buyer + heads-up to each seller. Wrapped
 * so a notification failure never surfaces as a payment failure.
 */
function notifyOrderPaid(PDO $db, array $order, array $items): void
{
    try {
        $total = number_format((float)$order['amount'], 0);
        $lines = array_map(fn($i) => '• ' . $i['title'] . ' — ₦' . number_format((float)$i['price'], 0), $items);
        $itemList = implode("\n", $lines);

        Notify::email(
            $order['email'],
            'Your KINAS Marketplace order is confirmed',
            "Thank you for your purchase!\n\nOrder reference: {$order['reference']}\n\n{$itemList}\n\nTotal paid: ₦{$total}\n\nThe seller(s) have been notified and will be in touch about handover/shipping.",
            null,
            SALES_EMAIL,
            'KINAS Marketplace Sales'
        );

        // Group items by agent so each seller gets one message.
        $byAgent = [];
        foreach ($items as $item) {
            $byAgent[$item['agent_id']][] = $item;
        }
        foreach ($byAgent as $agentId => $agentItems) {
            $titles = implode(', ', array_column($agentItems, 'title'));
            Notify::userEvent(
                (int)$agentId,
                'new_sale',
                "You made a sale on KINAS Marketplace: {$titles}. Check your dashboard for buyer details.",
                'You made a sale on KINAS Marketplace',
                "Good news — your listing(s) sold: {$titles}.\n\nOrder reference: {$order['reference']}\n\nLog in to your agent dashboard for buyer contact details and next steps."
            );
        }
    } catch (Throwable $e) {
        error_log('notifyOrderPaid failed: ' . $e->getMessage());
    }
}
