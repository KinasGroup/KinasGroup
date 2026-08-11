<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * KINAS GROUP — My Orders (KINAS Marketplace purchases)
 *
 * AMENDED:
 * - Connects paid marketplace orders to the product review system.
 * - Shows review status for purchased marketplace items.
 * - Gives customers a direct link to leave a review.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireLogin();

$db      = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$csrf    = Security::generateCSRFToken();

$flash = $_SESSION['orders_flash'] ?? null;
unset($_SESSION['orders_flash']);

$orders = $db->prepare("
    SELECT id, reference, amount, currency, status, shipping_address, paid_at, created_at
    FROM orders
    WHERE buyer_id = ?
    ORDER BY created_at DESC
");

$orders->execute([$user_id]);
$orders = $orders->fetchAll();

$itemsByOrder = [];

if (!empty($orders)) {
    $orderIds = array_column($orders, 'id');
    $in = implode(',', array_fill(0, count($orderIds), '?'));

    $itemsStmt = $db->prepare("
        SELECT oi.order_id, oi.title, oi.price, oi.quantity, oi.listing_id,
               oi.shipping_status, oi.tracking_number,
               (SELECT url FROM listing_images WHERE listing_id = oi.listing_id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
        FROM order_items oi
        WHERE oi.order_id IN ($in)
    ");

    $itemsStmt->execute($orderIds);

    foreach ($itemsStmt->fetchAll() as $row) {
        $itemsByOrder[$row['order_id']][] = $row;
    }
}

// ============================================================
// REVIEW SYSTEM CONNECTION
// ============================================================

$reviewSystemAvailable = false;

try {
    $db->query("SELECT 1 FROM product_reviews LIMIT 1");
    $reviewSystemAvailable = true;
} catch (Throwable $e) {
    $reviewSystemAvailable = false;
}

$reviewStatusByListing = [];

if ($reviewSystemAvailable && !empty($itemsByOrder)) {
    try {
        $listingIds = [];

        foreach ($itemsByOrder as $items) {
            foreach ($items as $item) {
                if (!empty($item['listing_id'])) {
                    $listingIds[] = (int)$item['listing_id'];
                }
            }
        }

        $listingIds = array_values(array_unique($listingIds));

        if (!empty($listingIds)) {
            $listingIn = implode(',', array_fill(0, count($listingIds), '?'));

            $reviewStmt = $db->prepare("
                SELECT listing_id, status
                FROM product_reviews
                WHERE user_id = ?
                  AND listing_type = 'marketplace'
                  AND listing_id IN ($listingIn)
            ");

            $reviewStmt->execute(array_merge([$user_id], $listingIds));

            foreach ($reviewStmt->fetchAll(PDO::FETCH_ASSOC) as $reviewRow) {
                $reviewStatusByListing[(int)$reviewRow['listing_id']] = (string)$reviewRow['status'];
            }
        }
    } catch (Throwable $e) {
        // If review lookup fails, continue without review statuses.
    }
}

$current_page = 'orders';
$pageTitle = 'My Orders - KINAS GROUP';

include __DIR__ . '/../templates/header.php';
?>
<style>
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#F5F7FA}
.user-container{max-width:1100px;margin:0 auto;padding:30px}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.page-header h1{font-family:'Prata',serif;font-size:28px;color:#0A0A0A}
.order-card{background:white;border-radius:14px;border:1px solid #E0E0E0;margin-bottom:18px;overflow:hidden}
.order-head{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;background:#FAFAFA;border-bottom:1px solid #E0E0E0;flex-wrap:wrap;gap:8px}
.order-ref{font-size:12px;color:#888;font-family:monospace}
.order-date{font-size:12px;color:#888}
.order-status{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.status-paid{background:#d4edda;color:#155724}
.status-pending{background:#fff3cd;color:#856404}
.status-failed,.status-abandoned{background:#f8d7da;color:#721c24}
.order-items{padding:14px 20px}
.order-item-row{display:flex;align-items:center;gap:12px;padding:8px 0;flex-wrap:wrap}
.order-item-thumb{width:44px;height:44px;border-radius:6px;overflow:hidden;background:linear-gradient(135deg,#1a1a1a,#0a0a0a);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.order-item-thumb img{width:100%;height:100%;object-fit:cover}
.order-item-thumb i{color:#C6A43F;font-size:16px}
.order-item-title{flex:1;font-size:13px;color:#333}
.order-item-price{font-size:13px;font-weight:600;color:#C6A43F}
.order-total{padding:14px 20px;border-top:1px solid #E0E0E0;display:flex;justify-content:space-between;font-weight:700;font-size:14px}
.empty-state{text-align:center;padding:70px 20px;background:white;border-radius:16px;border:1px solid #E0E0E0;color:#999}
.empty-state i{font-size:3rem;color:#E0E0E0;margin-bottom:14px;display:block}
.empty-state a{color:#C6A43F;text-decoration:none;font-weight:600}

/* ============================================================
   REVIEW BADGES
   ============================================================ */

.review-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    margin-right: 10px;
    white-space: nowrap;
}

.review-badge i {
    font-size: 10px;
}

.review-cta {
    background: #F3E5F5;
    color: #6A1B9A;
}

.review-cta:hover {
    background: #E1BEE7;
    color: #4A148C;
}

.review-approved {
    background: #E8F5E9;
    color: #2E7D32;
}

.review-pending {
    background: #FFF8E1;
    color: #8D6E00;
}

.review-rejected {
    background: #FFEBEE;
    color: #C62828;
}

/* ============================================================
DARK MODE — force this page's own styling to stay identical
to light mode. Auto-generated from every hardcoded
background/color/border-color rule already on this page.
============================================================ */
@media (prefers-color-scheme: dark) {
body { background: #F5F7FA !important; }
.page-header h1 { color: #0A0A0A !important; }
.order-card { background: white !important; }
.order-head { background: #FAFAFA !important; }
.order-ref { color: #888 !important; }
.order-date { color: #888 !important; }
.status-paid { background: #d4edda !important; color: #155724 !important; }
.status-pending { background: #fff3cd !important; color: #856404 !important; }
.status-failed,.status-abandoned { background: #f8d7da !important; color: #721c24 !important; }
.order-item-thumb { background: linear-gradient(135deg,#1a1a1a,#0a0a0a) !important; }
.order-item-thumb i { color: #C6A43F !important; }
.order-item-title { color: #333 !important; }
.order-item-price { color: #C6A43F !important; }
.empty-state { background: white !important; color: #999 !important; }
.empty-state i { color: #E0E0E0 !important; }
.empty-state a { color: #C6A43F !important; }

.review-cta { background: #F3E5F5 !important; color: #6A1B9A !important; }
.review-approved { background: #E8F5E9 !important; color: #2E7D32 !important; }
.review-pending { background: #FFF8E1 !important; color: #8D6E00 !important; }
.review-rejected { background: #FFEBEE !important; color: #C62828 !important; }
}
</style>

<div class="je-dash-shell">
    <?php include __DIR__ . '/../includes/partials/user-sidebar.php'; ?>

    <main style="padding-top:80px">
        <div class="user-container">
            <div class="page-header">
                <h1><i class="fas fa-receipt" style="color:#C6A43F;margin-right:10px"></i>My Orders</h1>
                <span style="color:#666;font-size:14px"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?></span>
            </div>

            <?php if ($flash): ?>
                <div style="padding:12px 16px;border-radius:8px;margin-bottom:18px;font-size:13px;background:<?= $flash['type'] === 'success' ? '#E8F5E9' : '#FFEBEE' ?>;color:<?= $flash['type'] === 'success' ? '#2E7D32' : '#C62828' ?>;">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <p>You haven't placed any orders yet.<br>Browse <a href="/divisions/kinas-marketplace/">KINAS Marketplace</a> to find something you love.</p>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): $items = $itemsByOrder[$order['id']] ?? []; ?>
                    <div class="order-card">
                        <div class="order-head">
                            <div>
                                <span class="order-ref"><?= htmlspecialchars($order['reference']) ?></span>
                                <span class="order-date"> · <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                            </div>
                            <span class="order-status status-<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></span>
                        </div>

                        <div class="order-items">
                            <?php foreach ($items as $it): ?>
                                <div class="order-item-row">
                                    <div class="order-item-thumb">
                                        <?php if (!empty($it['thumbnail'])): ?>
                                            <img src="<?= htmlspecialchars($it['thumbnail']) ?>" alt="">
                                        <?php else: ?>
                                            <i class="fas fa-gem"></i>
                                        <?php endif; ?>
                                    </div>

                                    <a href="/divisions/kinas-marketplace/detail.php?id=<?= (int)$it['listing_id'] ?>" class="order-item-title" style="text-decoration:none;color:#333;">
                                        <?= htmlspecialchars($it['title']) ?>
                                    </a>

                                    <?php
                                    $shipBadges = ['pending' => ['#FFF3E0', '#E65100', 'Preparing'], 'shipped' => ['#E3F2FD', '#1565C0', 'Shipped'], 'delivered' => ['#E8F5E9', '#2E7D32', 'Delivered']];
                                    [$bg, $fg, $label] = $shipBadges[$it['shipping_status'] ?? 'pending'] ?? $shipBadges['pending'];
                                    ?>

                                    <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;margin-right:10px;">
                                        <?= $label ?>
                                    </span>

                                    <?php if (!empty($it['tracking_number'])): ?>
                                        <span style="font-size:11px;color:#888;margin-right:10px;">
                                            Tracking: <?= htmlspecialchars($it['tracking_number']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php
                                    $reviewUrl = '/divisions/kinas-marketplace/detail.php?id=' . (int)$it['listing_id'] . '#kinasReviewsSection';
                                    $reviewStatus = $reviewStatusByListing[(int)($it['listing_id'] ?? 0)] ?? null;
                                    ?>

                                    <?php if ($reviewSystemAvailable && $order['status'] === 'paid' && !empty($it['listing_id'])): ?>
                                        <?php if ($reviewStatus === 'approved'): ?>
                                            <a href="<?= htmlspecialchars($reviewUrl) ?>" class="review-badge review-approved" title="View your approved review">
                                                <i class="fas fa-star"></i> Reviewed
                                            </a>
                                        <?php elseif ($reviewStatus === 'pending'): ?>
                                            <span class="review-badge review-pending" title="Your review is awaiting moderation">
                                                <i class="fas fa-clock"></i> Review Pending
                                            </span>
                                        <?php elseif ($reviewStatus === 'rejected'): ?>
                                            <a href="<?= htmlspecialchars($reviewUrl) ?>" class="review-badge review-rejected" title="Your review was not approved">
                                                <i class="fas fa-exclamation-circle"></i> Review Rejected
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= htmlspecialchars($reviewUrl) ?>" class="review-badge review-cta" title="Leave a review for this product">
                                                <i class="fas fa-pen"></i> Leave Review
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <span class="order-item-price">
                                        <?php
                                        $qty = max(1, (int)($it['quantity'] ?? 1));
                                        echo $qty > 1
                                            ? formatPrice((float)$it['price']) . " × {$qty} = " . formatPrice((float)$it['price'] * $qty)
                                            : formatPrice((float)$it['price']);
                                        ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php
                        $allDelivered = !empty($items) && count(array_filter($items, fn($i) => ($i['shipping_status'] ?? 'pending') === 'delivered')) === count($items);
                        $canDelete = in_array($order['status'], ['failed', 'abandoned'], true) || $allDelivered;
                        ?>

                        <?php if ($order['status'] === 'paid' && !$allDelivered): ?>
                            <div style="padding:14px 20px;background:#FAFAFA;border-top:1px solid #E0E0E0;font-size:13px;color:#555;">
                                <strong><i class="fas fa-truck"></i> Delivery Details</strong>
                                <div style="margin-top:6px;">Shipping to: <?= htmlspecialchars($order['shipping_address']) ?></div>

                                <?php
                                $trackingLines = array_filter(array_map(fn($i) => !empty($i['tracking_number']) ? htmlspecialchars($i['title']) . ': ' . htmlspecialchars($i['tracking_number']) : null, $items));
                                ?>

                                <?php if (!empty($trackingLines)): ?>
                                    <div style="margin-top:4px;">Tracking: <?= implode(' · ', $trackingLines) ?></div>
                                <?php else: ?>
                                    <div style="margin-top:4px;color:#888;">Tracking number(s) will appear here once the seller ships your item(s).</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="order-total">
                            <span>Total</span>
                            <span><?= formatPrice((float)$order['amount']) ?></span>
                        </div>

                        <?php if ($canDelete): ?>
                            <form method="POST" action="/api/user/delete-order.php" style="padding:0 20px 16px;text-align:right;"
                                  data-kinas-confirm="Remove this order from your history? This cannot be undone."
                                  data-kinas-title="Remove Order"
                                  data-kinas-warning="This only removes it from your view — it does not affect any completed transaction records.">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                <button type="submit" style="background:none;border:1px solid #ddd;color:#888;padding:6px 14px;border-radius:20px;font-size:12px;cursor:pointer;">
                                    <i class="fas fa-trash-alt"></i> Remove
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
