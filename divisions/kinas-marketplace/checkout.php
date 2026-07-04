<?php
/**
 * KINAS MARKETPLACE — Checkout
 *
 * Modes:
 *   /checkout.php                    → checkout everything in the cart
 *   /checkout.php?buy_now=123        → checkout a single listing directly
 *   /checkout.php?ref=KINAS-...      → returning from payment (Paystack
 *                                       callback_url or our own verify
 *                                       redirect) — show the receipt
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/paystack.php';
SessionManager::requireLogin();

$db     = Database::getInstance()->getConnection();
$userId = (int)$_SESSION['user_id'];

$returningRef = trim($_GET['ref'] ?? '');
$buyNowId     = (int)($_GET['buy_now'] ?? 0);

// ── Returning from a payment attempt: show the receipt state ──
$returningOrder = null;
if ($returningRef !== '') {
    $stmt = $db->prepare("SELECT * FROM orders WHERE reference = ? AND buyer_id = ?");
    $stmt->execute([$returningRef, $userId]);
    $returningOrder = $stmt->fetch();
}

// ── Fresh checkout: build the summary server-side ──
$checkoutItems = [];
$checkoutTotal = 0.0;
$checkoutMode  = $buyNowId ? 'buy_now' : 'cart';

if (!$returningOrder) {
    if ($buyNowId) {
        $stmt = $db->prepare("
            SELECT m.id AS listing_id, m.title, m.price, m.status, m.agent_id, a.name AS agent_name,
                   (SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
            FROM marketplace_listings m
            LEFT JOIN users a ON a.id = m.agent_id
            WHERE m.id = ?
        ");
        $stmt->execute([$buyNowId]);
        $row = $stmt->fetch();
        if ($row) $checkoutItems = [$row];
    } else {
        $stmt = $db->prepare("
            SELECT m.id AS listing_id, m.title, m.price, m.status, m.agent_id, a.name AS agent_name,
                   (SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
            FROM cart_items ci
            JOIN marketplace_listings m ON m.id = ci.listing_id
            LEFT JOIN users a ON a.id = m.agent_id
            WHERE ci.buyer_id = ? AND ci.listing_type = 'marketplace'
            ORDER BY ci.created_at DESC
        ");
        $stmt->execute([$userId]);
        $checkoutItems = $stmt->fetchAll();
    }

    foreach ($checkoutItems as $it) {
        if ($it['status'] === 'active') $checkoutTotal += (float)$it['price'];
    }
}

$userStmt = $db->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$buyer = $userStmt->fetch();

$paystack       = new PaystackService();
$paystackReady  = $paystack->isEnabled();

$pageTitle = 'Checkout | KINAS Marketplace';
$division  = 'marketplace';
include '../../templates/header.php';
?>

<div class="je-page">
<div class="je-checkout-wrap">

    <div class="je-breadcrumb">
        <a href="/">Home</a>
        <span class="je-breadcrumb-sep">/</span>
        <a href="/divisions/kinas-marketplace/">KINAS MARKETPLACE</a>
        <span class="je-breadcrumb-sep">/</span>
        <span>Checkout</span>
    </div>

    <?php if ($returningOrder): ?>
        <!-- ============================================================ -->
        <!-- RECEIPT STATE — returning from Paystack                       -->
        <!-- ============================================================ -->
        <div id="receiptState" style="max-width:560px;margin:30px auto;text-align:center;padding:40px 24px;">
            <div id="receiptSpinner">
                <i class="fas fa-spinner fa-spin" style="font-size:28px;color:#C6A43F;"></i>
                <p style="margin-top:14px;color:#666;">Confirming your payment…</p>
            </div>
            <div id="receiptResult" style="display:none;"></div>
        </div>
        <script>
        (function() {
            fetch('/api/payments/checkout-verify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ reference: <?= json_encode($returningOrder['reference']) ?> })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('receiptSpinner').style.display = 'none';
                const box = document.getElementById('receiptResult');
                box.style.display = 'block';
                if (data.success) {
                    box.innerHTML = '<div style="font-size:52px;color:#28a745;margin-bottom:14px;"><i class="fas fa-check-circle"></i></div>' +
                        '<h2 style="font-family:\'Prata\',serif;font-size:24px;margin-bottom:10px;">Payment confirmed</h2>' +
                        '<p style="color:#666;margin-bottom:22px;">Thank you for your purchase. A receipt has been sent to your email, and the seller has been notified.</p>' +
                        '<p style="font-size:12px;color:#999;margin-bottom:24px;">Reference: ' + <?= json_encode($returningOrder['reference']) ?> + '</p>' +
                        '<a href="/divisions/kinas-marketplace/" class="je-btn je-btn-gold">Continue Shopping</a>';
                } else {
                    box.innerHTML = '<div style="font-size:52px;color:#DC2626;margin-bottom:14px;"><i class="fas fa-times-circle"></i></div>' +
                        '<h2 style="font-family:\'Prata\',serif;font-size:24px;margin-bottom:10px;">Payment not confirmed</h2>' +
                        '<p style="color:#666;margin-bottom:22px;">' + (data.error || 'We could not confirm this payment. If you were charged, please contact support with your reference below.') + '</p>' +
                        '<p style="font-size:12px;color:#999;margin-bottom:24px;">Reference: ' + <?= json_encode($returningOrder['reference']) ?> + '</p>' +
                        '<a href="/divisions/kinas-marketplace/cart.php" class="je-btn je-btn-outline">Back to Cart</a>';
                }
            })
            .catch(function() {
                document.getElementById('receiptSpinner').style.display = 'none';
                const box = document.getElementById('receiptResult');
                box.style.display = 'block';
                box.innerHTML = '<p style="color:#DC2626;">Network error confirming payment. Please refresh this page.</p>';
            });
        })();
        </script>

    <?php elseif (empty($checkoutItems)): ?>
        <!-- ============================================================ -->
        <!-- EMPTY / INVALID                                                -->
        <!-- ============================================================ -->
        <div style="text-align:center;padding:70px 20px;background:#fafafa;border-radius:8px;max-width:560px;margin:30px auto;">
            <div style="font-size:48px;margin-bottom:16px;">🛍️</div>
            <h3 style="font-family:'Prata',serif;font-size:20px;margin-bottom:8px;">Nothing to check out</h3>
            <p style="color:#666;margin-bottom:20px;">Your cart is empty, or the item you tried to buy is no longer available.</p>
            <a href="/divisions/kinas-marketplace/" class="je-btn je-btn-gold">Browse Marketplace</a>
        </div>

    <?php else: ?>
        <!-- ============================================================ -->
        <!-- CHECKOUT FORM                                                  -->
        <!-- ============================================================ -->
        <h1 class="je-cart-title">Checkout</h1>

        <?php if (!$paystackReady): ?>
        <div style="background:#FEF2F2;border:1px solid #DC2626;color:#991B1B;padding:14px 18px;border-radius:4px;margin-bottom:20px;font-size:14px;">
            <i class="fas fa-exclamation-triangle"></i> Card payments are temporarily unavailable. Please try again shortly.
        </div>
        <?php endif; ?>

        <div class="je-cart-grid">
            <div>
                <?php foreach ($checkoutItems as $it):
                    $unavailable = $it['status'] !== 'active';
                ?>
                <div class="je-cart-item" style="<?= $unavailable ? 'opacity:.5;' : '' ?>">
                    <div class="je-cart-item-thumb">
                        <?php if (!empty($it['thumbnail'])): ?>
                            <img src="<?= htmlspecialchars($it['thumbnail']) ?>" alt="">
                        <?php else: ?>
                            <i class="fas fa-gem"></i>
                        <?php endif; ?>
                    </div>
                    <div class="je-cart-item-body">
                        <div class="je-cart-item-title"><?= htmlspecialchars($it['title']) ?></div>
                        <div class="je-cart-item-seller">Sold by <?= htmlspecialchars($it['agent_name'] ?? 'Seller') ?></div>
                        <div class="je-cart-item-price"><?= formatPrice((float)$it['price']) ?></div>
                        <?php if ($unavailable): ?><div class="je-cart-item-unavailable"><i class="fas fa-exclamation-circle"></i> No longer available — will be excluded</div><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div style="margin-top:24px;padding:20px;background:#fafafa;border-radius:10px;border:1px solid #eee;">
                    <h3 style="font-family:'Prata',serif;font-size:16px;margin-bottom:14px;">Billing Details</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label style="display:block;font-weight:600;font-size:12px;margin-bottom:4px;color:#666;">Name</label>
                            <input type="text" value="<?= htmlspecialchars($buyer['name'] ?? '') ?>" disabled style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;background:#f0f0f0;">
                        </div>
                        <div>
                            <label style="display:block;font-weight:600;font-size:12px;margin-bottom:4px;color:#666;">Email (receipt sent here)</label>
                            <input type="text" value="<?= htmlspecialchars($buyer['email'] ?? '') ?>" disabled style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;background:#f0f0f0;">
                        </div>
                    </div>
                    <p style="font-size:11px;color:#999;margin-top:10px;">To change these, update your <a href="/user/profile.php">profile</a> first.</p>
                </div>
            </div>

            <aside class="je-cart-summary">
                <h3>Order Summary</h3>
                <div class="je-cart-summary-row">
                    <span>Items (<?= count(array_filter($checkoutItems, fn($i) => $i['status'] === 'active')) ?>)</span>
                    <span><?= formatPrice($checkoutTotal) ?></span>
                </div>
                <div class="je-cart-summary-row" style="border-top:1px solid #ddd;margin-top:8px;padding-top:12px;font-size:16px;">
                    <span>Total</span>
                    <span id="checkoutTotalLabel"><?= formatPrice($checkoutTotal) ?></span>
                </div>
                <button class="je-cta-primary" style="width:100%;margin-top:16px;" id="payNowBtn" <?= $paystackReady ? '' : 'disabled' ?> onclick="jeStartPayment();">
                    <i class="fas fa-lock"></i> Pay <?= formatPrice($checkoutTotal) ?>
                </button>
                <p style="font-size:11px;color:#999;margin-top:12px;text-align:center;"><i class="fas fa-shield-alt"></i> Secured by Paystack. We never see or store your card details.</p>
            </aside>
        </div>
    <?php endif; ?>

</div>
</div>

<style>
.je-checkout-wrap { max-width: 1100px; margin: 0 auto; padding: 110px 24px 80px; }
.je-cart-title { font-family:'Prata',serif; font-size: 28px; margin: 18px 0 24px; color: var(--je-ink); }
.je-cart-grid { display: grid; grid-template-columns: 1fr 320px; gap: 32px; align-items: start; }
@media (max-width: 820px) { .je-cart-grid { grid-template-columns: 1fr; } }

.je-cart-item { display:flex; gap:16px; padding:16px 0; border-bottom:1px solid #eee; align-items:center; }
.je-cart-item-thumb { width:80px; height:80px; border-radius:8px; overflow:hidden; background:linear-gradient(135deg,#1a1a1a,#0a0a0a); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.je-cart-item-thumb img { width:100%; height:100%; object-fit:cover; }
.je-cart-item-thumb i { color:#C6A43F; font-size:22px; }
.je-cart-item-body { flex:1; min-width:0; }
.je-cart-item-title { font-weight:600; font-size:15px; color:#0A0A0A; margin-bottom:4px; }
.je-cart-item-seller { font-size:12px; color:#888; margin-bottom:6px; }
.je-cart-item-price { font-weight:700; color:#C6A43F; font-size:15px; }
.je-cart-item-unavailable { color:#DC2626; font-size:12px; font-weight:600; margin-top:4px; }

.je-cart-summary { background:#fafafa; border:1px solid #eee; border-radius:10px; padding:24px; position:sticky; top:110px; }
.je-cart-summary h3 { font-family:'Prata',serif; font-size:18px; margin-bottom:16px; }
.je-cart-summary-row { display:flex; justify-content:space-between; font-size:14px; color:#333; padding:6px 0; font-weight:600; }
</style>

<?php require_once '../../includes/kinas-ui.php'; ?>

<?php if (!$returningOrder && !empty($checkoutItems) && $paystackReady): ?>
<!-- Paystack Inline v2 Popup — https://paystack.com/docs/developer-tools/inlinejs/ -->
<script src="https://js.paystack.co/v2/inline.js"></script>
<script>
const jeCheckoutMode    = <?= json_encode($checkoutMode) ?>;
const jeCheckoutListing = <?= json_encode($checkoutMode === 'buy_now' ? (int)$checkoutItems[0]['listing_id'] : null) ?>;

function jeStartPayment() {
    const btn = document.getElementById('payNowBtn');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing checkout…';

    const payload = jeCheckoutMode === 'buy_now'
        ? { mode: 'buy_now', listing_id: jeCheckoutListing }
        : { mode: 'cart' };

    fetch('/api/payments/checkout-init.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) {
            btn.disabled = false;
            btn.innerHTML = original;
            kinasToast(data.error || 'Unable to start checkout', 'error');
            return;
        }

        const popup = new PaystackPop();
        popup.resumeTransaction(data.access_code, {
            onSuccess: function(transaction) {
                // Client-side signal only — the server re-verifies
                // against Paystack's API before delivering any value.
                window.location.href = '/divisions/kinas-marketplace/checkout.php?ref=' + encodeURIComponent(transaction.reference || data.reference) + '&status=success';
            },
            onCancel: function() {
                btn.disabled = false;
                btn.innerHTML = original;
                kinasToast('Payment cancelled', 'info', 3000);
            },
            onError: function(error) {
                btn.disabled = false;
                btn.innerHTML = original;
                kinasToast('Payment error: ' + (error && error.message ? error.message : 'please try again'), 'error');
            }
        });
    })
    .catch(function() {
        btn.disabled = false;
        btn.innerHTML = original;
        kinasToast('Network error. Please try again.', 'error');
    });
}
</script>
<?php endif; ?>

<?php include '../../templates/footer.php'; ?>
