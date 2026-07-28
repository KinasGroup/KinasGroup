<?php
/**
 * KINAS GROUP — Inspection fee fulfillment
 *
 * Same pattern as includes/order-fulfillment.php: called from both the
 * client fast-path (api/inspection/verify.php) and the authoritative
 * webhook (api/webhooks/paystack.php, routed by reference prefix).
 * Idempotent — safe to call twice for the same reference. Never trusts
 * the caller about payment success; always re-verifies against
 * Paystack's API first.
 */
require_once __DIR__ . '/paystack.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/../api/config/constants.php';

/**
 * @return array{success: bool, already: bool, error: ?string, booking: ?array}
 */
function finalizeInspectionBooking(PDO $db, string $reference): array
{
    $reference = trim($reference);
    if ($reference === '') {
        return ['success' => false, 'already' => false, 'error' => 'Missing reference', 'booking' => null];
    }

    $db->beginTransaction();

    try {
        $stmt = $db->prepare("SELECT * FROM inspection_bookings WHERE reference = ? FOR UPDATE");
        $stmt->execute([$reference]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            $db->rollBack();
            return ['success' => false, 'already' => false, 'error' => 'Booking not found', 'booking' => null];
        }

        if (in_array($booking['status'], ['paid', 'confirmed'], true)) {
            $db->commit();
            return ['success' => true, 'already' => true, 'error' => null, 'booking' => $booking];
        }

        $paystack = new PaystackService();
        $verify   = $paystack->verifyTransaction($reference);

        if (!$verify['success'] || $verify['status'] !== 'success') {
            $db->prepare("UPDATE inspection_bookings SET status = 'cancelled' WHERE id = ?")->execute([$booking['id']]);
            $db->commit();
            return ['success' => false, 'already' => false, 'error' => $verify['error'] ?? 'Payment was not successful', 'booking' => $booking];
        }

        $expectedKobo = (int) round(((float)$booking['fee_amount']) * 100);
        if ((int)$verify['amount_kobo'] !== $expectedKobo) {
            error_log("Paystack amount mismatch on inspection booking #{$booking['id']} ref={$reference}: expected {$expectedKobo}, got {$verify['amount_kobo']}");
            $db->prepare("UPDATE inspection_bookings SET status = 'cancelled' WHERE id = ?")->execute([$booking['id']]);
            $db->commit();
            return ['success' => false, 'already' => false, 'error' => 'Payment amount did not match the inspection fee', 'booking' => $booking];
        }

        // ── Verified. Mark paid + confirmed (payment success IS the
        //    confirmation trigger — "only after successful payment
        //    should the inspection appointment be confirmed"). ──
        $db->prepare("
            UPDATE inspection_bookings
               SET status = 'confirmed', paid_at = NOW()
             WHERE id = ?
        ")->execute([$booking['id']]);

        // Record the platform's 10% commission the same way a
        // marketplace/vehicle/property sale does — one shared ledger.
        $tableMap = ['car' => 'car_listings', 'property' => 'property_listings'];
        $table = $tableMap[$booking['listing_type']] ?? null;
        $listingTitle = $booking['listing_id'];
        if ($table) {
            $titleStmt = $db->prepare("SELECT title FROM $table WHERE id = ?");
            $titleStmt->execute([$booking['listing_id']]);
            $listingTitle = $titleStmt->fetchColumn() ?: $booking['listing_id'];
        }

        $db->prepare("
            INSERT INTO transactions
                (agent_id, listing_id, listing_type, buyer_id, buyer_name, buyer_email,
                 payment_method, paystack_reference, amount, commission_pct, commission,
                 currency, status, paid_at, notes)
            VALUES (?, ?, ?, ?, ?, ?, 'paystack', ?, ?, ?, ?, 'NGN', 'paid', NOW(), ?)
        ")->execute([
            $booking['agent_id'], $booking['listing_id'], $booking['listing_type'],
            $booking['buyer_id'], $booking['buyer_name'], $booking['buyer_email'],
            $reference, $booking['fee_amount'], $booking['commission_pct'], $booking['commission'],
            "Inspection fee for \"{$listingTitle}\" ({$booking['listing_type']}) — 90% due to agent per settlement policy.",
        ]);

        $db->commit();

        notifyInspectionConfirmed($db, $booking, $listingTitle);

        $booking['status'] = 'confirmed';
        return ['success' => true, 'already' => false, 'error' => null, 'booking' => $booking];

    } catch (Throwable $e) {
        $db->rollBack();
        error_log('finalizeInspectionBooking error: ' . $e->getMessage());
        return ['success' => false, 'already' => false, 'error' => 'Internal error finalizing inspection booking', 'booking' => null];
    }
}

/**
 * Best-effort confirmation emails to buyer + agent. Wrapped so a
 * notification failure never surfaces as a payment failure.
 */
function notifyInspectionConfirmed(PDO $db, array $booking, $listingTitle): void
{
    try {
        $dateLabel = date('l, F j, Y', strtotime($booking['preferred_date']));
        $feeFmt = number_format((float)$booking['fee_amount']);

        Notify::email(
            $booking['buyer_email'],
            "Inspection confirmed — {$listingTitle}",
            "Hi {$booking['buyer_name']},\n\nYour inspection fee (₦{$feeFmt}) has been received and your appointment is confirmed:\n\n{$listingTitle}\nDate: {$dateLabel}\nTime: {$booking['preferred_time']}\n\nThe agent will be in touch with any final details.",
            null,
            INFO_EMAIL,
            'KINAS GROUP'
        );

        $agentStmt = $db->prepare("SELECT name, email, phone, phone_verified_at FROM users WHERE id = ?");
        $agentStmt->execute([$booking['agent_id']]);
        $agent = $agentStmt->fetch(PDO::FETCH_ASSOC);

        if ($agent) {
            Notify::email(
                $agent['email'],
                "Paid inspection confirmed — {$listingTitle}",
                "Hi {$agent['name']},\n\n{$booking['buyer_name']} ({$booking['buyer_email']}" . (!empty($booking['buyer_phone']) ? ", {$booking['buyer_phone']}" : '') . ") has paid the inspection fee (₦{$feeFmt}) for {$listingTitle}.\n\nDate: {$dateLabel}\nTime: {$booking['preferred_time']}\n\nYour share (after KINAS GROUP's 10%) will be processed per the standard settlement policy.",
                null,
                SUPPORT_EMAIL,
                'KINAS GROUP Notifications'
            );
            if (!empty($agent['phone']) && !empty($agent['phone_verified_at'])) {
                Notify::sms($agent['phone'], "Paid inspection confirmed for {$listingTitle} on {$dateLabel} at {$booking['preferred_time']}. Check your dashboard.", 'NEW_INQUIRY');
            }
        }
    } catch (Throwable $e) {
        error_log('notifyInspectionConfirmed failed: ' . $e->getMessage());
    }
}
