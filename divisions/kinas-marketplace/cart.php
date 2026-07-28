<?php
/**
 * KINAS MARKETPLACE — Cart
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
SessionManager::requireLogin();

$pageTitle = 'Your Cart | KINAS Marketplace';
$division = 'marketplace';
include '../../templates/header.php';
?>

<div class="je-dash-shell">
<?php include __DIR__ . '/../../includes/partials/user-sidebar.php'; ?>
<main style="padding-top:80px">
<div class="je-page">
<div class="je-cart-wrap">

    <div class="je-breadcrumb">
        <a href="/">Home</a>
        <span class="je-breadcrumb-sep">/</span>
        <a href="/divisions/kinas-marketplace/">KINAS MARKETPLACE</a>
        <span class="je-breadcrumb-sep">/</span>
        <span>Cart</span>
    </div>

    <h1 class="je-cart-title">Your Cart</h1>

    <div id="cartLoading" style="padding:60px 0;text-align:center;color:#999;">
        <i class="fas fa-spinner fa-spin" style="font-size:22px;"></i>
        <p style="margin-top:10px;">Loading your cart…</p>
    </div>

    <div id="cartEmpty" style="display:none;text-align:center;padding:70px 20px;background:#fafafa;border-radius:8px;">
        <div style="font-size:48px;margin-bottom:16px;color:#ddd;"><i class="fas fa-shopping-cart"></i></div>
        <h3 style="font-family:'Prata',serif;font-size:20px;margin-bottom:8px;">Your cart is empty</h3>
        <p style="color:#666;margin-bottom:20px;">Browse the marketplace and add something you love.</p>
        <a href="/divisions/kinas-marketplace/" class="je-btn je-btn-gold">Browse Marketplace</a>
    </div>

    <div id="cartContent" style="display:none;">
        <div id="cartUnavailableNotice" style="display:none;background:#FFF8E1;border:1px solid #F0C419;color:#7A5B00;padding:14px 18px;border-radius:4px;margin-bottom:20px;font-size:14px;">
            <i class="fas fa-exclamation-triangle"></i> Some items in your cart are no longer available and won't be included in checkout.
        </div>

        <div class="je-cart-grid">
            <div id="cartItemsList"></div>

            <aside class="je-cart-summary">
                <h3>Order Summary</h3>
                <div class="je-cart-summary-row">
                    <span>Subtotal</span>
                    <span id="cartSubtotal">₦0</span>
                </div>
                <p style="font-size:12px;color:#888;margin:10px 0 18px;">Card processing fees, if any, are calculated by Paystack at checkout.</p>
                <button class="je-cta-primary" style="width:100%;" id="checkoutBtn" onclick="jeGoToCheckout();">
                    <i class="fas fa-lock"></i> Proceed to Checkout
                </button>
            </aside>
        </div>
    </div>

</div>
</div>
</main>
</div>

<style>
.je-cart-wrap { max-width: 1100px; margin: 0 auto; padding: 30px 24px 80px; }
.je-cart-title { font-family:'Prata',serif; font-size: 30px; margin: 18px 0 28px; color: var(--je-ink); }
.je-cart-grid { display: grid; grid-template-columns: 1fr 320px; gap: 32px; align-items: start; }
@media (max-width: 820px) { .je-cart-grid { grid-template-columns: 1fr; } }

.je-cart-item { display:flex; gap:16px; padding:18px 0; border-bottom:1px solid #eee; align-items:center; }
.je-cart-item-thumb { width:96px; height:96px; border-radius:8px; overflow:hidden; background:linear-gradient(135deg,#1a1a1a,#0a0a0a); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.je-cart-item-thumb img { width:100%; height:100%; object-fit:cover; }
.je-cart-item-thumb i { color:#C6A43F; font-size:26px; }
.je-cart-item-body { flex:1; min-width:0; }
.je-cart-item-title { font-weight:600; font-size:15px; color:#0A0A0A; margin-bottom:4px; }
.je-cart-item-seller { font-size:12px; color:#888; margin-bottom:6px; }
.je-cart-item-price { font-weight:700; color:#C6A43F; font-size:15px; }
.je-cart-qty { display:flex; align-items:center; gap:10px; margin-top:8px; }
.je-cart-qty button { width:26px; height:26px; border-radius:50%; border:1px solid #ddd; background:#fff; cursor:pointer; font-size:14px; line-height:1; color:#333; }
.je-cart-qty button:hover { background:#f5f5f5; }
.je-cart-qty-value { font-size:14px; font-weight:600; min-width:16px; text-align:center; }
.je-cart-item-unavailable { color:#DC2626; font-size:12px; font-weight:600; margin-top:4px; }
.je-cart-item-remove { background:none; border:none; color:#999; cursor:pointer; font-size:16px; padding:8px; }
.je-cart-item-remove:hover { color:#DC2626; }

.je-cart-summary { background:#fafafa; border:1px solid #eee; border-radius:10px; padding:24px; position:sticky; top:86px; }
.je-cart-summary h3 { font-family:'Prata',serif; font-size:18px; margin-bottom:16px; }
.je-cart-summary-row { display:flex; justify-content:space-between; font-size:14px; color:#333; padding:6px 0; font-weight:600; }
</style>

<?php require_once '../../includes/kinas-ui.php'; ?>

<script>
let jeCartItems = [];

function jeLoadCart() {
    fetch('/api/cart/list.php', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('cartLoading').style.display = 'none';
            if (!data.success) {
                kinasToast(data.error || 'Failed to load cart', 'error');
                return;
            }
            jeCartItems = data.items;
            if (data.count === 0) {
                document.getElementById('cartEmpty').style.display = 'block';
                return;
            }
            document.getElementById('cartContent').style.display = 'block';
            document.getElementById('cartUnavailableNotice').style.display = data.has_unavailable ? 'block' : 'none';
            document.getElementById('cartSubtotal').textContent = data.subtotal_label;

            const list = document.getElementById('cartItemsList');
            list.innerHTML = data.items.map(function(item) {
                const thumb = item.thumbnail
                    ? '<img src="' + item.thumbnail.replace(/"/g, '&quot;') + '" alt="">'
                    : '<i class="fas fa-gem"></i>';
                const unavailable = item.available ? '' : '<div class="je-cart-item-unavailable"><i class="fas fa-exclamation-circle"></i> No longer available</div>';
                const qtyControls = item.available
                    ? '<div class="je-cart-qty" data-listing-id="' + item.listing_id + '">' +
                        '<button type="button" onclick="jeChangeQty(' + item.listing_id + ', ' + (item.quantity - 1) + ')" aria-label="Decrease quantity">−</button>' +
                        '<span class="je-cart-qty-value">' + item.quantity + '</span>' +
                        '<button type="button" onclick="jeChangeQty(' + item.listing_id + ', ' + (item.quantity + 1) + ')" aria-label="Increase quantity">+</button>' +
                      '</div>'
                    : '';
                return '<div class="je-cart-item" data-listing-id="' + item.listing_id + '" style="' + (item.available ? '' : 'opacity:.55;') + '">' +
                    '<a href="' + item.detail_url + '" class="je-cart-item-thumb">' + thumb + '</a>' +
                    '<div class="je-cart-item-body">' +
                        '<a href="' + item.detail_url + '" class="je-cart-item-title" style="color:inherit;text-decoration:none;">' + escapeHtml(item.title) + '</a>' +
                        '<div class="je-cart-item-seller">Sold by ' + escapeHtml(item.agent_name) + '</div>' +
                        '<div class="je-cart-item-price">' + item.price_label + (item.quantity > 1 ? ' × ' + item.quantity + ' = ' + item.line_total_label : '') + '</div>' +
                        qtyControls +
                        unavailable +
                    '</div>' +
                    '<button class="je-cart-item-remove" onclick="jeRemoveFromCart(' + item.listing_id + ')" title="Remove"><i class="fas fa-trash-alt"></i></button>' +
                '</div>';
            }).join('');
        })
        .catch(function() {
            document.getElementById('cartLoading').style.display = 'none';
            kinasToast('Network error loading your cart', 'error');
        });
}

function jeChangeQty(listingId, newQty) {
    if (newQty < 0) return;
    const formData = new FormData();
    formData.append('listing_id', listingId);
    formData.append('quantity', newQty);
    fetch('/api/cart/update-quantity.php', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                jeLoadCart();
            } else {
                kinasToast(data.error || 'Failed to update quantity', 'error');
            }
        })
        .catch(function() { kinasToast('Network error. Please try again.', 'error'); });
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

function jeRemoveFromCart(listingId) {
    const formData = new FormData();
    formData.append('listing_id', listingId);
    fetch('/api/cart/remove.php', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                kinasToast('Removed from cart', 'info', 2500);
                jeLoadCart();
            } else {
                kinasToast(data.error || 'Failed to remove item', 'error');
            }
        })
        .catch(function() { kinasToast('Network error. Please try again.', 'error'); });
}

function jeGoToCheckout() {
    window.location.href = '/divisions/kinas-marketplace/checkout.php';
}

document.addEventListener('DOMContentLoaded', jeLoadCart);
</script>

<?php include '../../templates/footer.php'; ?>
