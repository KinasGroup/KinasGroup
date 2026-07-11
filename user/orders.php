<?php
/**
 * KINAS GROUP — My Orders (KINAS Marketplace purchases)
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireLogin();

$db      = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

$orders = $db->prepare("
    SELECT id, reference, amount, currency, status, paid_at, created_at
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
        SELECT oi.order_id, oi.title, oi.price, oi.listing_id,
               (SELECT url FROM listing_images WHERE listing_id = oi.listing_id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
        FROM order_items oi
        WHERE oi.order_id IN ($in)
    ");
    $itemsStmt->execute($orderIds);
    foreach ($itemsStmt->fetchAll() as $row) {
        $itemsByOrder[$row['order_id']][] = $row;
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
.order-item-row{display:flex;align-items:center;gap:12px;padding:8px 0}
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
                    <a href="/divisions/kinas-marketplace/detail.php?id=<?= (int)$it['listing_id'] ?>" class="order-item-title" style="text-decoration:none;color:#333;"><?= htmlspecialchars($it['title']) ?></a>
                    <span class="order-item-price"><?= formatPrice((float)$it['price']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="order-total">
                <span>Total</span>
                <span><?= formatPrice((float)$order['amount']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</main>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
