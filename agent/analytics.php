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
.agent-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 32px; }
.agent-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; }
.agent-header h1 i { color: #C6A43F; margin-right: 12px; }
.date-range { display: flex; gap: 8px; flex-wrap: wrap; }
.date-btn { padding: 8px 16px; background: white; border: 1px solid #E0E0E0; border-radius: 20px; color: #666; cursor: pointer; transition: all 0.3s; }
.date-btn.active, .date-btn:hover { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
.metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 40px; }
.metric-card { background: white; border-radius: 20px; padding: 24px; border: 1px solid #E0E0E0; display: flex; align-items: center; gap: 20px; transition: all 0.3s; }
.metric-card:hover { transform: translateY(-3px); border-color: #C6A43F; }
.metric-icon { width: 60px; height: 60px; background: rgba(198,164,63,0.1); border-radius: 16px; display: flex; align-items: center; justify-content: center; }
.metric-icon i { font-size: 28px; color: #C6A43F; }
.metric-info { flex: 1; }
.metric-value { font-size: 32px; font-weight: 700; color: #C6A43F; }
.metric-label { font-size: 12px; color: #666; margin-top: 4px; }
.metric-change.positive { color: #2E7D32; font-size: 12px; margin-top: 4px; display: inline-block; }
.charts-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px; margin-bottom: 40px; }
.chart-card { background: white; border-radius: 20px; padding: 24px; border: 1px solid #E0E0E0; }
.chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.chart-header h3 { font-family: 'Prata', serif; font-size: 18px; color: #0A0A0A; }
.division-performance { background: white; border-radius: 20px; padding: 24px; border: 1px solid #E0E0E0; margin-bottom: 40px; }
.division-performance h3 { font-family: 'Prata', serif; font-size: 20px; margin-bottom: 20px; }
.division-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
.division-card { display: flex; align-items: center; gap: 16px; padding: 16px; background: #F8F8F8; border-radius: 16px; transition: all 0.3s; }
.division-card:hover { background: rgba(198,164,63,0.05); }
.division-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
.division-icon.automobile { background: #E3F2FD; color: #1565C0; }
.division-icon.realestate { background: #E8F5E9; color: #2E7D32; }
.division-icon.solar { background: #FFF3E0; color: #F57C00; }
.division-icon.marketplace { background: #F3E5F5; color: #7B1FA2; }
.division-icon i { font-size: 24px; }
.division-stats { flex: 1; }
.division-stats h4 { font-weight: 600; margin-bottom: 8px; }
.stats-row { display: flex; gap: 12px; font-size: 12px; color: #666; margin-bottom: 8px; }
.progress-bar { height: 6px; background: #E0E0E0; border-radius: 3px; overflow: hidden; }
.progress-fill { height: 100%; background: #C6A43F; border-radius: 3px; }
.insights-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; }
.insight-card { background: white; border-radius: 20px; padding: 24px; border: 1px solid #E0E0E0; }
.insight-card h4 { font-family: 'Prata', serif; font-size: 16px; margin-bottom: 16px; }
.chart-empty { display: none; align-items: center; justify-content: center; text-align: center; min-height: 220px; padding: 24px; color: #999; font-size: 13px; font-style: italic; }
.insight-card .chart-empty { min-height: 130px; }
.location-list { list-style: none; }
.location-list li { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #E0E0E0; font-size: 13px; }
@media (max-width: 768px) { .agent-container { padding: 20px; } .charts-row { grid-template-columns: 1fr; } .division-grid { grid-template-columns: 1fr; } .insights-grid { grid-template-columns: 1fr; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">

<div class="agent-container">
    <div class="agent-header"><div><h1><i class="fas fa-chart-line"></i> Analytics</h1><p>Track your performance metrics and insights</p></div><div class="date-range"><button class="date-btn active">Last 7 days</button><button class="date-btn">Last 30 days</button><button class="date-btn">Last 90 days</button><button class="date-btn">This Year</button></div></div>

    <div class="metrics-grid">
        <div class="metric-card"><div class="metric-icon"><i class="fas fa-eye"></i></div><div class="metric-info"><div class="metric-value">0</div><div class="metric-label">Total Views</div><span class="metric-change">Awaiting data</span></div></div>
        <div class="metric-card"><div class="metric-icon"><i class="fas fa-envelope"></i></div><div class="metric-info"><div class="metric-value">0</div><div class="metric-label">Inquiries</div><span class="metric-change">Awaiting data</span></div></div>
        <div class="metric-card"><div class="metric-icon"><i class="fas fa-percent"></i></div><div class="metric-info"><div class="metric-value">—</div><div class="metric-label">Conversion Rate</div><span class="metric-change">Awaiting data</span></div></div>
        <div class="metric-card"><div class="metric-icon"><i class="fas fa-star"></i></div><div class="metric-info"><div class="metric-value">—</div><div class="metric-label">Rating</div><span class="metric-change">Awaiting data</span></div></div>
    </div>

    <div class="charts-row"><div class="chart-card"><div class="chart-header"><h3>Views &amp; Inquiries Trend</h3><i class="fas fa-chart-line" style="color:#C6A43F;"></i></div><canvas id="trendChart" height="250"></canvas><div class="chart-empty" data-for="trendChart">No data yet — analytics will populate once listings get views and inquiries.</div></div><div class="chart-card"><div class="chart-header"><h3>Top Performing Listings</h3><i class="fas fa-trophy" style="color:#C6A43F;"></i></div><canvas id="topListingsChart" height="250"></canvas><div class="chart-empty" data-for="topListingsChart">No listings yet — performance data will appear once your listings go live.</div></div></div>

    <div class="division-performance"><h3>Performance by Division</h3><div class="division-grid">
        <div class="division-card"><div class="division-icon automobile"><i class="fas fa-car"></i></div><div class="division-stats"><h4>Automobiles</h4><div class="stats-row"><span>Views: 0</span><span>Inquiries: 0</span><span>Sales: 0</span></div><div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div></div></div>
        <div class="division-card"><div class="division-icon realestate"><i class="fas fa-home"></i></div><div class="division-stats"><h4>Real Estate</h4><div class="stats-row"><span>Views: 0</span><span>Inquiries: 0</span><span>Sales: 0</span></div><div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div></div></div>
        <div class="division-card"><div class="division-icon solar"><i class="fas fa-solar-panel"></i></div><div class="division-stats"><h4>Solar Energy</h4><div class="stats-row"><span>Views: 0</span><span>Inquiries: 0</span><span>Sales: 0</span></div><div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div></div></div>
        <div class="division-card"><div class="division-icon marketplace"><i class="fas fa-store"></i></div><div class="division-stats"><h4>Marketplace</h4><div class="stats-row"><span>Views: 0</span><span>Inquiries: 0</span><span>Sales: 0</span></div><div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div></div></div>
    </div></div>

    <div class="insights-grid"><div class="insight-card"><h4>Top Locations</h4><ul class="location-list"><li><span>No location data yet</span><span>—</span></li></ul></div><div class="insight-card"><h4>Device Breakdown</h4><canvas id="deviceChart" height="150"></canvas><div class="chart-empty" data-for="deviceChart">No traffic data yet.</div></div><div class="insight-card"><h4>Peak Hours</h4><canvas id="peakHoursChart" height="150"></canvas><div class="chart-empty" data-for="peakHoursChart">No activity data yet.</div></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Empty-state charts — they render the axes only and wait for real
// analytics data to arrive from the Agent / Super Agent dashboards.
function emptyChart(canvasId, type) {
    var el = document.getElementById(canvasId);
    if (!el) return;
    new Chart(el, {
        type: type,
        data: { labels: [], datasets: [] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }
    });
    // Show the empty-state overlay
    var overlay = document.querySelector('.chart-empty[data-for="' + canvasId + '"]');
    if (overlay) overlay.style.display = 'flex';
}
emptyChart('trendChart', 'line');
emptyChart('topListingsChart', 'bar');
emptyChart('deviceChart', 'doughnut');
emptyChart('peakHoursChart', 'line');
</script>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
