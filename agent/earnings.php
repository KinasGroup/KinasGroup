<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

// Auth: handled by SessionManager::requireAgent()

// KYC soft-guard
$kycStatus='pending';try{$st=Database::getInstance()->getConnection()->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");$st->execute([(int)$_SESSION['user_id']]);$kycStatus=$st->fetchColumn()?:'pending';}catch(Exception $e){}

require_once __DIR__ . '/../templates/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.agent-container { max-width: 1400px; margin: 0 auto; padding: 30px; }
.agent-header { margin-bottom: 32px; }
.agent-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; }
.agent-header h1 i { color: #C6A43F; margin-right: 12px; }
.earnings-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 40px; }
.summary-card { background: white; border-radius: 20px; padding: 24px; border: 1px solid #E0E0E0; transition: all 0.3s; }
.summary-card:hover { transform: translateY(-3px); border-color: #C6A43F; }
.summary-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.summary-amount { font-size: 32px; font-weight: 700; color: #C6A43F; margin-bottom: 8px; }
.trend { font-size: 12px; color: #2E7D32; }
.chart-card { background: white; border-radius: 20px; padding: 24px; border: 1px solid #E0E0E0; margin-bottom: 40px; }
.chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.chart-header h3 { font-family: 'Prata', serif; font-size: 18px; color: #0A0A0A; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.section-header h3 { font-family: 'Prata', serif; font-size: 20px; color: #0A0A0A; }
.btn-export { background: #C6A43F; color: #0A0A0A; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; }
.table-container { background: white; border-radius: 20px; border: 1px solid #E0E0E0; overflow: hidden; margin-bottom: 40px; }
.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; padding: 16px 20px; background: #F8F8F8; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; border-bottom: 1px solid #E0E0E0; }
.data-table td { padding: 16px 20px; border-bottom: 1px solid #E0E0E0; color: #333; font-size: 13px; }
.status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.status-badge.paid { background: #E8F5E9; color: #2E7D32; }
.status-badge.pending { background: #FFF3E0; color: #F57C00; }
.payout-settings { background: white; border-radius: 20px; padding: 28px; border: 1px solid #E0E0E0; }
.payout-settings h3 { font-family: 'Prata', serif; font-size: 20px; margin-bottom: 24px; }
.settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; align-items: end; }
.setting-item { display: flex; flex-direction: column; gap: 8px; }
.setting-item label { font-size: 12px; font-weight: 600; color: #666; }
.setting-item input, .setting-item select { padding: 12px; border: 1px solid #E0E0E0; border-radius: 10px; font-family: 'Inter', sans-serif; }
.btn-save { background: #C6A43F; color: #0A0A0A; border: none; padding: 12px 24px; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
.btn-save:hover { background: #A8882E; transform: translateY(-2px); }
@media (max-width: 768px) { .agent-container { padding: 20px; } .earnings-summary { grid-template-columns: 1fr; } .settings-grid { grid-template-columns: 1fr; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">

<div class="agent-container">
    <div class="agent-header"><h1><i class="fas fa-money-bill-wave"></i> Earnings</h1><p>Track your commissions and payouts in Nigerian Naira</p></div>

    <div class="earnings-summary">
        <div class="summary-card"><div class="summary-label">Total Earnings</div><div class="summary-amount">₦72,450,000</div><span class="trend"><i class="fas fa-arrow-up"></i> +12% from last month</span></div>
        <div class="summary-card"><div class="summary-label">This Month</div><div class="summary-amount">₦7,800,000</div><span class="trend">Pending: ₦2,700,000</span></div>
        <div class="summary-card"><div class="summary-label">Total Transactions</div><div class="summary-amount">28</div><span class="trend">Avg. ₦2,587,500 per sale</span></div>
        <div class="summary-card"><div class="summary-label">Next Payout</div><div class="summary-amount">₦3,675,000</div><span class="trend">Est. May 30, 2024</span></div>
    </div>

    <div class="chart-card"><div class="chart-header"><h3>Earnings Overview (2024)</h3><select id="yearSelect"><option>2024</option><option>2023</option></select></div><canvas id="earningsChart" height="250"></canvas></div>

    <div class="section-header"><h3>Recent Transactions</h3><button class="btn-export" onclick="exportTransactions()"><i class="fas fa-download"></i> Export</button></div>
    <div class="table-container"><div class="table-responsive"><table class="data-table"><thead><tr><th>Date</th><th>Listing</th><th>Buyer</th><th>Amount (₦)</th><th>Commission (₦)</th><th>Status</th></tr></thead><tbody><tr><td>2024-03-15</td><td>2024 Mercedes-Benz S-Class</td><td>John Doe</td><td>185,000,000</td><td>9,250,000</td><td><span class="status-badge paid">Paid</span></td></tr><tr><td>2024-03-10</td><td>Luxury Villa</td><td>Jane Smith</td><td>3,450,000,000</td><td>172,500,000</td><td><span class="status-badge pending">Pending</span></td></tr><tr><td>2024-02-28</td><td>10kW Solar System</td><td>Michael Roberts</td><td>28,500,000</td><td>2,850,000</td><td><span class="status-badge paid">Paid</span></td></tr><tr><td>2024-02-20</td><td>Rolex Watch</td><td>Sarah Lee</td><td>12,500,000</td><td>1,250,000</td><td><span class="status-badge paid">Paid</span></td></tr><tr><td>2024-02-05</td><td>BMW i8</td><td>David Kim</td><td>145,000,000</td><td>7,250,000</td><td><span class="status-badge pending">Pending</span></td></tr></tbody></table></div></div>

    <div class="payout-settings"><h3>Payout Settings</h3><div class="settings-grid"><div class="setting-item"><label>Payment Method</label><select><option>Bank Transfer (NGN)</option><option>PayPal</option><option>Stripe</option></select></div><div class="setting-item"><label>Bank Account Details</label><input type="text" placeholder="Enter your Nigerian bank account details"></div><div class="setting-item"><label>Minimum Payout Threshold</label><select><option>₦50,000</option><option>₦100,000</option><option>₦250,000</option><option>₦500,000</option></select></div><div class="setting-item"><button class="btn-save" onclick="savePayoutSettings()">Save Settings</button></div></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('earningsChart')?.getContext('2d');
if(ctx) new Chart(ctx, {type:'bar',data:{labels:['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],datasets:[{label:'Earnings (₦ Millions)',data:[4.8,6.2,8.7,7.8,7.2,9.3,10.7,10.2,8.9,8.1,9.5,11.7],backgroundColor:'rgba(198,164,63,0.5)',borderColor:'#C6A43F',borderWidth:2,borderRadius:8}]},options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{labels:{color:'#666'}}},scales:{y:{grid:{color:'#F0F0F0'},ticks:{color:'#666',callback:function(v){return '₦'+v+'M';}}},x:{grid:{display:false},ticks:{color:'#666'}}}}});
function exportTransactions() { alert('Exporting transactions to CSV...'); }
function savePayoutSettings() { alert('Payout settings saved successfully!'); }
</script>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
