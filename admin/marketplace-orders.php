<?php
/**
 * ADMIN — KINAS Marketplace Orders
 *
 * Read-only view over every Paystack order placed through the
 * marketplace: what was bought, how much the buyer paid (including
 * any fee gross-up), and how it was settled — auto-split straight to
 * an agent's Paystack subaccount, or collected to the platform's main
 * account for manual payout.
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/helpers.php';

SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();

$from   = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to     = $_GET['to']   ?? date('Y-m-d');
$status = in_array($_GET['status'] ?? '', ['pending','paid','failed','abandoned'], true) ? $_GET['status'] : '';
$settlement = in_array($_GET['settlement'] ?? '', ['subaccount','platform'], true) ? $_GET['settlement'] : '';

$where  = ["DATE(o.created_at) BETWEEN ? AND ?"];
$params = [$from, $to];
if ($status !== '')     { $where[] = "o.status = ?";          $params[] = $status; }
if ($settlement !== '') { $where[] = "o.settlement_mode = ?"; $params[] = $settlement; }
$whereSql = implode(' AND ', $where);

// ── Summary stats for the selected range ──
$statsStmt = $db->prepare("
    SELECT
        COUNT(*)                                              AS total_orders,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END)       AS paid_orders,
        IFNULL(SUM(CASE WHEN status = 'paid' THEN amount END), 0)          AS gross_revenue,
        IFNULL(SUM(CASE WHEN status = 'paid' THEN fee_amount END), 0)      AS fees_passed_to_buyers,
        IFNULL(SUM(CASE WHEN status = 'paid' AND settlement_mode = 'subaccount' THEN 1 ELSE 0 END), 0) AS auto_settled_orders,
        IFNULL(SUM(CASE WHEN status = 'paid' AND settlement_mode = 'platform'   THEN 1 ELSE 0 END), 0) AS manual_settle_orders
    FROM orders o
    WHERE $whereSql
");
$statsStmt->execute($params);
$stats = $statsStmt->fetch();

// ── Order list (paginated-ish: last 200 in range) ──
$listStmt = $db->prepare("
    SELECT o.*, u.name AS buyer_name
    FROM orders o
    LEFT JOIN users u ON u.id = o.buyer_id
    WHERE $whereSql
    ORDER BY o.created_at DESC
    LIMIT 200
");
$listStmt->execute($params);
$orders = $listStmt->fetchAll();

$itemsByOrder = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'id');
    $in = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsStmt = $db->prepare("
        SELECT oi.order_id, oi.title, oi.price, oi.agent_id, a.name AS agent_name
        FROM order_items oi
        LEFT JOIN users a ON a.id = oi.agent_id
        WHERE oi.order_id IN ($in)
    ");
    $itemsStmt->execute($orderIds);
    foreach ($itemsStmt->fetchAll() as $row) {
        $itemsByOrder[$row['order_id']][] = $row;
    }
}

$pageTitle = 'Marketplace Orders — Admin';
require_once __DIR__ . '/../templates/header.php';
?>

<style>
.page-header { margin-bottom: 20px; }
.page-header h1 { font-family: 'Prata', serif; font-size: 26px; color: #0A0A0A; }
.page-header p { color: #666; font-size: 14px; margin-top: 4px; }

.date-range { display: flex; gap: 14px; align-items: end; flex-wrap: wrap; background: white; border: 1px solid #E0E0E0; border-radius: 14px; padding: 18px; margin-bottom: 22px; }
.date-group { display: flex; flex-direction: column; gap: 6px; }
.date-group label { font-size: 12px; font-weight: 600; color: #666; }
.date-group input, .date-group select { padding: 9px 12px; border: 1px solid #E0E0E0; border-radius: 8px; font-size: 13px; }
.btn-filter { background: #C6A43F; color: #0A0A0A; border: none; padding: 10px 20px; border-radius: 40px; font-weight: 600; cursor: pointer; }
.btn-secondary { color: #666; text-decoration: none; font-size: 13px; padding: 10px 14px; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat-card { background: white; border: 1px solid #E0E0E0; border-radius: 14px; padding: 18px; }
.stat-card h3 { font-size: 22px; color: #0A0A0A; }
.stat-card p { font-size: 12px; color: #888; margin-top: 4px; }

.orders-table-wrap { background: white; border: 1px solid #E0E0E0; border-radius: 14px; overflow: hidden; }
.orders-table-wrap .table-responsive { overflow-x: auto; }
table.orders-table { width: 100%; min-width: 900px; border-collapse: collapse; }
table.orders-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #888; padding: 12px 16px; border-bottom: 1px solid #E0E0E0; background: #FAFAFA; }
table.orders-table td { padding: 14px 16px; border-bottom: 1px solid #F0F0F0; font-size: 13px; vertical-align: top; }
.ref-code { font-family: monospace; font-size: 11px; color: #999; }
.status-pill { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.status-pill.paid { background: #d4edda; color: #155724; }
.status-pill.pending { background: #fff3cd; color: #856404; }
.status-pill.failed, .status-pill.abandoned { background: #f8d7da; color: #721c24; }
.settle-pill { font-size: 11px; font-weight: 700; }
.settle-pill.subaccount { color: #2E7D32; }
.settle-pill.platform { color: #999; }
.item-line { font-size: 12px; color: #444; margin-bottom: 2px; }
.item-line .seller { color: #999; }
.empty-state { padding: 60px 20px; text-align: center; color: #999; }
.empty-state i { font-size: 40px; color: #E0E0E0; display: block; margin-bottom: 12px; }
</style>

<div class="je-dash-shell" style="max-width:100%;overflow-x:hidden;">
<?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>
<main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">

    <div class="page-header">
        <h1><i class="fas fa-receipt" style="color:#C6A43F;margin-right:10px;"></i>Marketplace Orders</h1>
        <p>Every Paystack checkout through KINAS Marketplace — fees, and how each sale was settled.</p>
    </div>

    <form class="date-range" method="GET">
        <div class="date-group"><label>From</label><input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></div>
        <div class="date-group"><label>To</label><input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></div>
        <div class="date-group">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                <?php foreach (['pending','paid','failed','abandoned'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="date-group">
            <label>Settlement</label>
            <select name="settlement">
                <option value="">All</option>
                <option value="subaccount" <?= $settlement === 'subaccount' ? 'selected' : '' ?>>Auto-settled (subaccount)</option>
                <option value="platform" <?= $settlement === 'platform' ? 'selected' : '' ?>>Platform / manual payout</option>
            </select>
        </div>
        <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
        <a href="marketplace-orders.php" class="btn-secondary">Reset</a>
    </form>

    <div class="stats-grid">
        <div class="stat-card"><h3><?= number_format((int)$stats['total_orders']) ?></h3><p>Total checkout attempts</p></div>
        <div class="stat-card"><h3><?= number_format((int)$stats['paid_orders']) ?></h3><p>Paid orders</p></div>
        <div class="stat-card"><h3>₦<?= number_format((float)$stats['gross_revenue']) ?></h3><p>Gross revenue collected</p></div>
        <div class="stat-card"><h3>₦<?= number_format((float)$stats['fees_passed_to_buyers']) ?></h3><p>Paystack fees passed to buyers</p></div>
        <div class="stat-card"><h3><?= number_format((int)$stats['auto_settled_orders']) ?></h3><p>Auto-settled to agents</p></div>
        <div class="stat-card"><h3><?= number_format((int)$stats['manual_settle_orders']) ?></h3><p>Pending manual payout</p></div>
    </div>

    <div class="orders-table-wrap">
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No orders in the selected range.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order</th><th>Buyer</th><th>Items</th><th>Amount</th><th>Fee</th><th>Settlement</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): $items = $itemsByOrder[$o['id']] ?? []; ?>
                    <tr>
                        <td>
                            <span class="ref-code"><?= htmlspecialchars($o['reference']) ?></span><br>
                            <span style="font-size:11px;color:#999;"><?= date('M j, Y g:i A', strtotime($o['created_at'])) ?></span>
                        </td>
                        <td>
                            <?= htmlspecialchars($o['buyer_name'] ?? '—') ?><br>
                            <span style="font-size:11px;color:#999;"><?= htmlspecialchars($o['email']) ?></span>
                        </td>
                        <td>
                            <?php foreach ($items as $it): ?>
                                <div class="item-line"><?= htmlspecialchars($it['title']) ?> <span class="seller">— <?= htmlspecialchars($it['agent_name'] ?? 'agent #' . $it['agent_id']) ?></span></div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            ₦<?= number_format((float)$o['amount']) ?>
                            <?php if ((float)($o['fee_amount'] ?? 0) > 0): ?>
                                <br><span style="font-size:11px;color:#999;">(subtotal ₦<?= number_format((float)$o['subtotal_amount']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td>₦<?= number_format((float)($o['fee_amount'] ?? 0)) ?></td>
                        <td>
                            <?php if (($o['settlement_mode'] ?? 'platform') === 'subaccount'): ?>
                                <span class="settle-pill subaccount"><i class="fas fa-bolt"></i> Auto (<?= htmlspecialchars($o['subaccount_code']) ?>)</span>
                            <?php else: ?>
                                <span class="settle-pill platform">Platform</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="status-pill <?= htmlspecialchars($o['status']) ?>"><?= htmlspecialchars(ucfirst($o['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
