<?php
/**
 * KINAS GROUP — Agent: Earnings
 * Real data from `transactions` + `payout_settings` tables.
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireAgent();

$db     = Database::getInstance()->getConnection();
$userId = (int)$_SESSION['user_id'];

// KYC soft-guard
$kycStatus = 'pending';
try {
    $st = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
    $st->execute([$userId]);
    $kycStatus = $st->fetchColumn() ?: 'pending';
} catch (Exception $e) {}

// Date range (last 12 months by default)
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-12 months'));
$to   = $_GET['to']   ?? date('Y-m-d');

// ── Top stats from transactions ──────────────────────────────
$stats = [
    'total'         => 0.0,
    'this_month'    => 0.0,
    'pending'       => 0.0,
    'paid'          => 0.0,
    'next_payout'   => 0.0,
    'tx_count'      => 0,
    'avg_per_sale'  => 0.0,
    'last_payout'   => null,
];

$total = $db->prepare("SELECT IFNULL(SUM(commission),0) FROM transactions WHERE agent_id = ?");
$total->execute([$userId]);
$stats['total'] = (float)$total->fetchColumn();

$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');
$monthTotal = $db->prepare("SELECT IFNULL(SUM(commission),0) FROM transactions WHERE agent_id = ? AND DATE(created_at) BETWEEN ? AND ?");
$monthTotal->execute([$userId, $monthStart, $monthEnd]);
$stats['this_month'] = (float)$monthTotal->fetchColumn();

$pending = $db->prepare("SELECT IFNULL(SUM(commission),0) FROM transactions WHERE agent_id = ? AND status = 'pending'");
$pending->execute([$userId]);
$stats['pending'] = (float)$pending->fetchColumn();

$paid = $db->prepare("SELECT IFNULL(SUM(commission),0) FROM transactions WHERE agent_id = ? AND status = 'paid'");
$paid->execute([$userId]);
$stats['paid'] = (float)$paid->fetchColumn();

$txCount = $db->prepare("SELECT COUNT(*) FROM transactions WHERE agent_id = ?");
$txCount->execute([$userId]);
$stats['tx_count'] = (int)$txCount->fetchColumn();
$stats['avg_per_sale'] = $stats['tx_count'] > 0 ? $stats['total'] / $stats['tx_count'] : 0;

$lastPayout = $db->prepare("SELECT MAX(paid_at) FROM transactions WHERE agent_id = ? AND status = 'paid'");
$lastPayout->execute([$userId]);
$stats['last_payout'] = $lastPayout->fetchColumn() ?: null;

$stats['next_payout'] = $stats['pending'];

// ── Monthly bar chart: last 12 months ────────────────────────
$monthlyLabels = [];
$monthlyData   = [];
for ($i = 11; $i >= 0; $i--) {
    $mStart = date('Y-m-01', strtotime("-$i months"));
    $mEnd   = date('Y-m-t', strtotime("-$i months"));
    $stmt = $db->prepare("SELECT IFNULL(SUM(commission),0) FROM transactions WHERE agent_id = ? AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$userId, $mStart, $mEnd]);
    $monthlyLabels[] = date('M Y', strtotime($mStart));
    $monthlyData[]   = round((float)$stmt->fetchColumn() / 1e6, 2); // in millions
}

// ── Recent transactions (last 30) ────────────────────────────
$txStmt = $db->prepare("
    SELECT t.*,
           CASE
             WHEN t.listing_type = 'car'         THEN (SELECT title FROM car_listings         WHERE id = t.listing_id)
             WHEN t.listing_type = 'property'    THEN (SELECT title FROM property_listings    WHERE id = t.listing_id)
             WHEN t.listing_type = 'solar'       THEN (SELECT title FROM solar_listings       WHERE id = t.listing_id)
             WHEN t.listing_type = 'marketplace' THEN (SELECT title FROM marketplace_listings WHERE id = t.listing_id)
           END AS listing_title
    FROM transactions t
    WHERE t.agent_id = ?
    ORDER BY t.created_at DESC
    LIMIT 30
");
$txStmt->execute([$userId]);
$transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Payout settings ──────────────────────────────────────────
$psStmt = $db->prepare("SELECT * FROM payout_settings WHERE agent_id = ?");
$psStmt->execute([$userId]);
$payout = $psStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$csrf = Security::generateCSRFToken();
require_once __DIR__ . '/../templates/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.agent-container { max-width: 1400px; margin: 0 auto; padding: 30px; }
.agent-header { margin-bottom: 32px; }
.agent-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; }
.agent-header h1 i { color: #C6A43F; margin-right: 12px; }
.flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
.flash.success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
.flash.error   { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
.earnings-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 40px; }
.summary-card { background: white; border-radius: 20px; padding: 24px; border: 1px solid #E0E0E0; transition: all 0.3s; }
.summary-card:hover { transform: translateY(-3px); border-color: #C6A43F; }
.summary-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.summary-amount { font-size: 28px; font-weight: 700; color: #C6A43F; margin-bottom: 8px; word-break: break-word; }
.trend { font-size: 12px; color: #2E7D32; display: flex; align-items: center; gap: 4px; }
.trend.neutral { color: #888; }
.trend.warn { color: #F57C00; }
.chart-card { background: white; border-radius: 20px; padding: 24px; border: 1px solid #E0E0E0; margin-bottom: 40px; }
.chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
.chart-header h3 { font-family: 'Prata', serif; font-size: 18px; color: #0A0A0A; }
.year-select { padding: 8px 14px; border: 1px solid #E0E0E0; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 13px; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
.section-header h3 { font-family: 'Prata', serif; font-size: 20px; color: #0A0A0A; }
.btn-export { background: #666; color: white; border: none; padding: 9px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
.btn-export:hover { background: #333; }
.table-container { background: white; border-radius: 20px; border: 1px solid #E0E0E0; overflow: hidden; margin-bottom: 40px; }
.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 16px 20px; background: #F8F8F8; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; border-bottom: 1px solid #E0E0E0; }
.data-table td { padding: 16px 20px; border-bottom: 1px solid #E0E0E0; color: #333; font-size: 13px; vertical-align: middle; }
.status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.status-badge.paid       { background: #E8F5E9; color: #2E7D32; }
.status-badge.pending    { background: #FFF3E0; color: #F57C00; }
.status-badge.cancelled  { background: #ECEFF1; color: #607D8B; }
.status-badge.refunded   { background: #FEF2F2; color: #DC2626; }
.payout-settings { background: white; border-radius: 20px; padding: 28px; border: 1px solid #E0E0E0; }
.payout-settings h3 { font-family: 'Prata', serif; font-size: 20px; margin-bottom: 24px; }
.settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; align-items: end; }
.setting-item { display: flex; flex-direction: column; gap: 8px; }
.setting-item label { font-size: 12px; font-weight: 600; color: #666; }
.setting-item input, .setting-item select { padding: 11px 14px; border: 1px solid #E0E0E0; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 14px; }
.setting-item-full { grid-column: 1 / -1; }
.btn-save { background: #C6A43F; color: #0A0A0A; border: none; padding: 12px 24px; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
.btn-save:hover { background: #A8882E; transform: translateY(-2px); }
.empty-state { padding: 60px 20px; text-align: center; color: #999; }
.empty-state i { font-size: 48px; color: #C6A43F; opacity: 0.4; display: block; margin-bottom: 14px; }
.empty-state p { font-size: 14px; }
.checkbox-group { display: flex; align-items: center; gap: 8px; }
.checkbox-group input { accent-color: #C6A43F; }
.checkbox-group label { font-size: 13px; color: #333; }
.bank-fields, .paypal-fields, .stripe-fields { display: none; }
.bank-fields.active, .paypal-fields.active, .stripe-fields.active { display: contents; }
@media (max-width: 768px) { .agent-container { padding: 20px; } .earnings-summary { grid-template-columns: 1fr; } .settings-grid { grid-template-columns: 1fr; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">

<div class="agent-container">
    <?php if ($flashSuccess): ?><div class="flash success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError):   ?><div class="flash error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="agent-header">
        <h1><i class="fas fa-money-bill-wave"></i> Earnings</h1>
        <p style="color:#666; font-size:14px; margin-top:4px;">Track your commissions and payouts in Nigerian Naira</p>
    </div>

    <?php if ($stats['tx_count'] === 0): ?>
        <div class="chart-card" style="text-align:center;">
            <div class="empty-state">
                <i class="fas fa-coins"></i>
                <p>You don't have any transactions yet.</p>
                <p style="margin-top:6px; color:#bbb; font-size:12px;">Transactions are recorded when a buyer completes a purchase through your listings.</p>
            </div>
        </div>
    <?php else: ?>

    <div class="earnings-summary">
        <div class="summary-card">
            <div class="summary-label">Total Earnings</div>
            <div class="summary-amount">₦<?= number_format($stats['total']) ?></div>
            <span class="trend"><i class="fas fa-arrow-up"></i> <?= number_format($stats['tx_count']) ?> transaction<?= $stats['tx_count'] === 1 ? '' : 's' ?> to date</span>
        </div>
        <div class="summary-card">
            <div class="summary-label">This Month</div>
            <div class="summary-amount">₦<?= number_format($stats['this_month']) ?></div>
            <?php if ($stats['pending'] > 0): ?>
                <span class="trend warn">Pending: ₦<?= number_format($stats['pending']) ?></span>
            <?php else: ?>
                <span class="trend neutral">No pending commissions</span>
            <?php endif; ?>
        </div>
        <div class="summary-card">
            <div class="summary-label">Paid Out</div>
            <div class="summary-amount">₦<?= number_format($stats['paid']) ?></div>
            <?php if ($stats['last_payout']): ?>
                <span class="trend">Last payout: <?= htmlspecialchars(date('M j, Y', strtotime($stats['last_payout']))) ?></span>
            <?php else: ?>
                <span class="trend neutral">No payouts yet</span>
            <?php endif; ?>
        </div>
        <div class="summary-card">
            <div class="summary-label">Next Payout</div>
            <div class="summary-amount">₦<?= number_format($stats['next_payout']) ?></div>
            <span class="trend">Avg. ₦<?= number_format($stats['avg_per_sale']) ?> per sale</span>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-header">
            <h3>Earnings Overview (last 12 months)</h3>
            <select class="year-select" disabled>
                <option><?= date('Y') ?></option>
            </select>
        </div>
        <canvas id="earningsChart" height="250"></canvas>
    </div>

    <div class="section-header">
        <h3>Recent Transactions</h3>
        <a href="/api/agent/export-transactions.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn-export"><i class="fas fa-download"></i> Export CSV</a>
    </div>
    <div class="table-container">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Listing</th><th>Buyer</th><th>Amount</th><th>Commission</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('M j, Y', strtotime($t['created_at']))) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($t['listing_title'] ?? ucfirst($t['listing_type']) . ' #' . (int)$t['listing_id']) ?></strong>
                            <br><span style="font-size:11px; color:#999;"><?= ucfirst($t['listing_type']) ?> · #<?= (int)$t['listing_id'] ?></span>
                        </td>
                        <td>
                            <?= htmlspecialchars($t['buyer_name'] ?? '—') ?>
                            <?php if (!empty($t['buyer_email'])): ?>
                                <br><span style="font-size:11px; color:#999;"><?= htmlspecialchars($t['buyer_email']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>₦<?= number_format((float)$t['amount']) ?></td>
                        <td>
                            ₦<?= number_format((float)$t['commission']) ?>
                            <br><span style="font-size:11px; color:#999;"><?= rtrim(rtrim(number_format((float)$t['commission_pct'], 2), '0'), '.') ?>%</span>
                        </td>
                        <td><span class="status-badge <?= htmlspecialchars($t['status']) ?>"><?= htmlspecialchars(ucfirst($t['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; // end "no transactions" guard ?>

    <form class="payout-settings" method="POST" action="/api/agent/save-payout-settings.php" id="payoutForm">
        <h3><i class="fas fa-university" style="color:#C6A43F; margin-right:8px;"></i> Payout Settings</h3>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="redirect" value="/agent/earnings.php">

        <div class="settings-grid">
            <div class="setting-item">
                <label>Payment Method</label>
                <select name="payment_method" id="paymentMethod">
                    <option value="bank_transfer_ngn" <?= ($payout['payment_method'] ?? '') === 'bank_transfer_ngn' ? 'selected' : '' ?>>Bank Transfer (NGN)</option>
                    <option value="paypal"           <?= ($payout['payment_method'] ?? '') === 'paypal'           ? 'selected' : '' ?>>PayPal</option>
                    <option value="stripe"           <?= ($payout['payment_method'] ?? '') === 'stripe'           ? 'selected' : '' ?>>Stripe</option>
                    <option value="flutterwave"      <?= ($payout['payment_method'] ?? '') === 'flutterwave'      ? 'selected' : '' ?>>Flutterwave</option>
                    <option value="paystack"         <?= ($payout['payment_method'] ?? '') === 'paystack'         ? 'selected' : '' ?>>Paystack</option>
                </select>
            </div>

            <!-- Bank fields -->
            <div class="setting-item bank-fields active">
                <label>Bank Name</label>
                <input type="text" name="bank_name" value="<?= htmlspecialchars($payout['bank_name'] ?? '') ?>" placeholder="e.g., GTBank, Zenith">
            </div>
            <div class="setting-item bank-fields active">
                <label>Account Name</label>
                <input type="text" name="bank_account_name" value="<?= htmlspecialchars($payout['bank_account_name'] ?? '') ?>" placeholder="Account holder name">
            </div>
            <div class="setting-item bank-fields active">
                <label>Account Number / IBAN</label>
                <input type="text" name="bank_account_number" value="<?= htmlspecialchars($payout['bank_account_number'] ?? '') ?>" placeholder="0123456789">
            </div>

            <!-- PayPal fields -->
            <div class="setting-item paypal-fields">
                <label>PayPal Email</label>
                <input type="email" name="paypal_email" value="<?= htmlspecialchars($payout['paypal_email'] ?? '') ?>" placeholder="you@paypal.com">
            </div>

            <!-- Stripe fields -->
            <div class="setting-item stripe-fields">
                <label>Stripe Account ID</label>
                <input type="text" name="stripe_account_id" value="<?= htmlspecialchars($payout['stripe_account_id'] ?? '') ?>" placeholder="acct_…">
            </div>

            <div class="setting-item">
                <label>Minimum Payout Threshold</label>
                <select name="min_payout">
                    <?php foreach ([50000, 100000, 250000, 500000, 1000000] as $opt): ?>
                        <option value="<?= $opt ?>" <?= (float)($payout['min_payout'] ?? 50000) === (float)$opt ? 'selected' : '' ?>>₦<?= number_format($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="setting-item">
                <label>Auto-Payout</label>
                <div class="checkbox-group">
                    <input type="checkbox" name="auto_payout" value="1" id="autoPayout" <?= !empty($payout['auto_payout']) ? 'checked' : '' ?>>
                    <label for="autoPayout">Automatically request payout when balance exceeds the threshold.</label>
                </div>
            </div>
            <div class="setting-item">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Payout Settings</button>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php if ($stats['tx_count'] > 0): ?>
<script>
const ctx = document.getElementById('earningsChart')?.getContext('2d');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthlyLabels) ?>,
            datasets: [{
                label: 'Earnings (₦ Millions)',
                data: <?= json_encode($monthlyData) ?>,
                backgroundColor: 'rgba(198,164,63,0.5)',
                borderColor: '#C6A43F',
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { labels: { color: '#666' } } },
            scales: {
                y: { grid: { color: '#F0F0F0' }, ticks: { color: '#666', callback: function(v){ return '₦' + v + 'M'; } } },
                x: { grid: { display: false }, ticks: { color: '#666' } }
            }
        }
    });
}
</script>
<?php endif; ?>

<script>
// Show/hide payment-method-specific fields
(function() {
    var sel = document.getElementById('paymentMethod');
    if (!sel) return;
    function sync() {
        var v = sel.value;
        document.querySelectorAll('.bank-fields').forEach(function(el){ el.classList.toggle('active', v === 'bank_transfer_ngn'); });
        document.querySelectorAll('.paypal-fields').forEach(function(el){ el.classList.toggle('active', v === 'paypal'); });
        document.querySelectorAll('.stripe-fields').forEach(function(el){ el.classList.toggle('active', v === 'stripe'); });
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
