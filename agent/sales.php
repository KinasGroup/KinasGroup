<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/helpers.php';

SessionManager::requireAgent();

$db = Database::getInstance()->getConnection();
$agentId = SessionManager::getUserId();
$csrf = Security::generateCSRFToken();

$flash = $_SESSION['sales_flash'] ?? null;
unset($_SESSION['sales_flash']);

// Only show items belonging to orders that actually paid — nothing to
// fulfil for a pending/failed/abandoned checkout.
$items = $db->prepare("
    SELECT oi.*, o.reference, o.email AS buyer_email, o.shipping_address, o.created_at AS order_date,
           (SELECT url FROM listing_images WHERE listing_id = oi.listing_id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE oi.agent_id = ? AND o.status = 'paid'
    ORDER BY FIELD(oi.shipping_status, 'pending', 'shipped', 'delivered'), o.created_at DESC
");
$items->execute([$agentId]);
$items = $items->fetchAll(PDO::FETCH_ASSOC);

$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<style>
.je-dash-shell { max-width: 100% !important; overflow-x: hidden !important; }
.sl-wrap { max-width: 1100px; }
.sl-card { background: #fff; border: 1px solid #E0E0E0; border-radius: 14px; padding: 24px; margin-bottom: 20px; }
.sl-card h1 { font-family: 'Prata', serif; font-size: 22px; color: #0A0A0A; margin: 0 0 20px; }
.sl-flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; }
.sl-flash.success { background: #E8F5E9; color: #2E7D32; }
.sl-flash.error { background: #FFEBEE; color: #C62828; }
.sl-row { display: flex; gap: 16px; align-items: center; padding: 16px 0; border-bottom: 1px solid #F0F0F0; flex-wrap: wrap; }
.sl-row:last-child { border-bottom: none; }
.sl-thumb { width: 64px; height: 64px; border-radius: 8px; object-fit: cover; background: #f0f0f0; flex-shrink: 0; }
.sl-info { flex: 1; min-width: 200px; }
.sl-info .title { font-weight: 700; font-size: 14px; color: #0A0A0A; }
.sl-info .meta { font-size: 12px; color: #717171; margin-top: 4px; }
.sl-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; margin-left: 8px; }
.sl-badge.pending { background: #FFF3E0; color: #E65100; }
.sl-badge.shipped { background: #E3F2FD; color: #1565C0; }
.sl-badge.delivered { background: #E8F5E9; color: #2E7D32; }
.sl-ship-form { display: flex; gap: 8px; align-items: center; }
.sl-ship-form input { padding: 8px 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 12px; width: 150px; }
.sl-btn { padding: 8px 16px; border-radius: 40px; font-weight: 600; font-size: 12px; cursor: pointer; border: none; }
.sl-btn-ship { background: #151515; color: #fff; }
.sl-btn-deliver { background: #2E7D32; color: #fff; }
.sl-empty { text-align: center; padding: 40px; color: #999; }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/agent-sidebar.php"; ?>
<main class="je-dash-main">
    <div class="sl-wrap">
        <?php if ($flash): ?>
            <div class="sl-flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <div class="sl-card">
            <h1>Marketplace Sales</h1>

            <?php if (empty($items)): ?>
                <div class="sl-empty">No paid orders yet.</div>
            <?php else: ?>
                <?php foreach ($items as $it): ?>
                <div class="sl-row">
                    <img class="sl-thumb" src="<?= htmlspecialchars($it['thumbnail'] ?: '/assets/images/placeholder/product-placeholder.svg') ?>" alt=""
                         onerror="this.onerror=null;this.src='/assets/images/placeholder/product-placeholder.svg';">
                    <div class="sl-info">
                        <div class="title"><?= htmlspecialchars($it['title']) ?> <span class="sl-badge <?= htmlspecialchars($it['shipping_status']) ?>"><?= ucfirst($it['shipping_status']) ?></span></div>
                        <div class="meta">
                            Order <?= htmlspecialchars($it['reference']) ?> · <?= formatPrice((float)$it['price']) ?> ·
                            Ship to: <?= htmlspecialchars($it['shipping_address']) ?>
                            <?php if (!empty($it['tracking_number'])): ?> · Tracking: <?= htmlspecialchars($it['tracking_number']) ?><?php endif; ?>
                        </div>
                    </div>

                    <?php if ($it['shipping_status'] === 'pending'): ?>
                    <form method="POST" action="/api/agent/ship-order-item.php" class="sl-ship-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                        <input type="hidden" name="action" value="ship">
                        <input type="text" name="tracking_number" placeholder="Tracking # (optional)">
                        <button type="submit" class="sl-btn sl-btn-ship">Mark Shipped</button>
                    </form>
                    <?php elseif ($it['shipping_status'] === 'shipped'): ?>
                    <form method="POST" action="/api/agent/ship-order-item.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                        <input type="hidden" name="action" value="deliver">
                        <button type="submit" class="sl-btn sl-btn-deliver">Mark Delivered</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
