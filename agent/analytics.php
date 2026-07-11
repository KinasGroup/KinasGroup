<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

// Auth: handled by SessionManager::requireAgent()

// KYC soft-guard
$kycStatus='pending';try{$st=Database::getInstance()->getConnection()->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");$st->execute([(int)$_SESSION['user_id']]);$kycStatus=$st->fetchColumn()?:'pending';}catch(Exception $e){}

require_once __DIR__ . '/../templates/header.php';
?>

<!-- ============================================================
     RESPONSIVE FIX - Added container and responsive styles
     ============================================================ -->
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.je-dash-shell { max-width: 100% !important; overflow-x: hidden !important; }
.je-dash-main { overflow-x: hidden !important; width: 100% !important; max-width: 100% !important; padding: 15px !important; }
.agent-container { max-width: 1400px; margin: 0 auto; padding: 30px; width: 100%; overflow-x: hidden; }
.agent-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 32px; }
.agent-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; }
.agent-header h1 i { color: #C6A43F; margin-right: 12px; }
.date-range { display: flex; gap: 8px; flex-wrap: wrap; }
.date-btn { padding: 8px 16px; background: white; border: 1px solid #E0E0E0; border-radius: 20px; color: #666; cursor: pointer; transition: all 0.3s; }
.date-btn.active, .date-btn:hover { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
.metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
.metric-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #E0E0E0; display: flex; align-items: center; gap: 16px; transition: all 0.3s; }
.metric-card:hover { transform: translateY(-3px); border-color: #C6A43F; }
.metric-icon { width: 50px; height: 50px; background: rgba(198,164,63,0.1); border-radius: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.metric-icon i { font-size: 24px; color: #C6A43F; }
.metric-info { flex: 1; min-width: 0; }
.metric-value { font-size: 28px; font-weight: 700; color: #C6A43F; }
.metric-label { font-size: 12px; color: #666; margin-top: 4px; }
.metric-change.positive { color: #2E7D32; font-size: 12px; margin-top: 4px; display: inline-block; }
.charts-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 24px; margin-bottom: 40px; }
.chart-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #E0E0E0; overflow: hidden; }
.chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
.chart-header h3 { font-family: 'Prata', serif; font-size: 18px; color: #0A0A0A; }
.division-performance { background: white; border-radius: 20px; padding: 24px; border: 1px solid #E0E0E0; margin-bottom: 40px; overflow: hidden; }
.division-performance h3 { font-family: 'Prata', serif; font-size: 20px; margin-bottom: 20px; }
.division-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
.division-card { display: flex; align-items: center; gap: 14px; padding: 14px; background: #F8F8F8; border-radius: 16px; transition: all 0.3s; }
.division-card:hover { background: rgba(198,164,63,0.05); }
.division-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.division-icon.automobile { background: #E3F2FD; color: #1565C0; }
.division-icon.realestate { background: #E8F5E9; color: #2E7D32; }
.division-icon.solar { background: #FFF3E0; color: #F57C00; }
.division-icon.marketplace { background: #F3E5F5; color: #7B1FA2; }
.division-icon i { font-size: 20px; }
.division-stats { flex: 1; min-width: 0; }
.division-stats h4 { font-weight: 600; margin-bottom: 6px; font-size: 14px; }
.stats-row { display: flex; gap: 12px; font-size: 12px; color: #666; flex-wrap: wrap; }
.progress-bar { height: 6px; background: #E0E0E0; border-radius: 3px; overflow: hidden; }
.progress-fill { height: 100%; background: #C6A43F; border-radius: 3px; }
.insights-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; }
.insight-card { background: white; border-radius: 20px; padding: 24px; border: 1px solid #E0E0E0; overflow: hidden; }
.insight-card h4 { font-family: 'Prata', serif; font-size: 16px; margin-bottom: 16px; }
.chart-empty { display: none; align-items: center; justify-content: center; text-align: center; min-height: 180px; padding: 20px; color: #999; font-size: 13px; font-style: italic; }
.insight-card .chart-empty { min-height: 100px; }
.location-list { list-style: none; }
.location-list li { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #E0E0E0; font-size: 13px; }

@media (max-width: 768px) { 
    .agent-container { padding: 15px; }
    .je-dash-main { padding: 10px !important; }
    .agent-header h1 { font-size: 22px; }
    .date-btn { padding: 6px 12px; font-size: 12px; }
    .metrics-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
    .metric-card { padding: 14px; }
    .metric-value { font-size: 22px; }
    .charts-row { grid-template-columns: 1fr; }
    .division-grid { grid-template-columns: 1fr; }
    .insights-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .metrics-grid { grid-template-columns: 1fr; }
    .agent-header { flex-direction: column; align-items: flex-start; }
    .date-range { width: 100%; justify-content: flex-start; }
}

/* ============================================================
   DARK MODE — force this page's own styling to stay identical
   to light mode. Auto-generated from every hardcoded
   background/color/border-color rule already on this page.
   ============================================================ */
@media (prefers-color-scheme: dark) {
    body { background: #F5F7FA !important; }
    .agent-header h1 { color: #0A0A0A !important; }
    .agent-header h1 i { color: #C6A43F !important; }
    .date-btn { background: white !important; color: #666 !important; }
    .date-btn.active, .date-btn:hover { background: #C6A43F !important; border-color: #C6A43F !important; color: #0A0A0A !important; }
    .metric-card { background: white !important; }
    .metric-card:hover { border-color: #C6A43F !important; }
    .metric-icon { background: rgba(198,164,63,0.1) !important; }
    .metric-icon i { color: #C6A43F !important; }
    .metric-value { color: #C6A43F !important; }
    .metric-label { color: #666 !important; }
    .metric-change.positive { color: #2E7D32 !important; }
    .chart-card { background: white !important; }
    .chart-header h3 { color: #0A0A0A !important; }
    .division-performance { background: white !important; }
    .division-card { background: #F8F8F8 !important; }
    .division-card:hover { background: rgba(198,164,63,0.05) !important; }
    .division-icon.automobile { background: #E3F2FD !important; color: #1565C0 !important; }
    .division-icon.realestate { background: #E8F5E9 !important; color: #2E7D32 !important; }
    .division-icon.solar { background: #FFF3E0 !important; color: #F57C00 !important; }
    .division-icon.marketplace { background: #F3E5F5 !important; color: #7B1FA2 !important; }
    .stats-row { color: #666 !important; }
    .progress-bar { background: #E0E0E0 !important; }
    .progress-fill { background: #C6A43F !important; }
    .insight-card { background: white !important; }
    .chart-empty { color: #999 !important; }
}
</style>

<div class="je-dash-shell" style="max-width:100%;overflow-x:hidden;">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">

<div class="agent-container">
    <div class="agent-header">
        <div>
            <h1><i class="fas fa-chart-line"></i> Analytics</h1>
            <p>Track your performance metrics and insights</p>
        </div>
        <div class="date-range">
            <button class="date-btn active">Last 7 days</button>
            <button class="date-btn">Last 30 days</button>
            <button class="date-btn">Last 90 days</button>
            <button class="date-btn">This Year</button>
        </div>
    </div>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-icon"><i class="fas fa-eye"></i></div>
            <div class="metric-info">
                <div class="metric-value">0</div>
                <div class="metric-label">Total Views</div>
                <span class="metric-change">Awaiting data</span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon"><i class="fas fa-envelope"></i></div>
            <div class="metric-info">
                <div class="metric-value">0</div>
                <div class="metric-label">Inquiries</div>
                <span class="metric-change">Awaiting data</span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon"><i class="fas fa-percent"></i></div>
            <div class="metric-info">
                <div class="metric-value">—</div>
                <div class="metric-label">Conversion Rate</div>
                <span class="metric-change">Awaiting data</span>
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-icon"><i class="fas fa-star"></i></div>
            <div class="metric-info">
                <div class="metric-value">—</div>
                <div class="metric-label">Rating</div>
                <span class="metric-change">Awaiting data</span>
            </div>
        </div>
    </div>

    <div class="charts-row">
        <div class="chart-card">
            <div class="chart-header">
                <h3>Views &amp; Inquiries Trend</h3>
                <i class="fas fa-chart-line" style="color:#C6A43F;"></i>
            </div>
            <canvas id="trendChart" height="200"></canvas>
            <div class="chart-empty" data-for="trendChart">No data yet — analytics will populate once listings get views and inquiries.</div>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <h3>Top Performing Listings</h3>
                <i class="fas fa-trophy" style="color:#C6A43F;"></i>
            </div>
            <canvas id="topListingsChart" height="200"></canvas>
            <div class="chart-empty" data-for="topListingsChart">No listings yet — performance data will appear once your listings go live.</div>
        </div>
    </div>

    <div class="division-performance">
        <h3>Performance by Division</h3>
        <div class="division-grid">
            <div class="division-card">
                <div class="division-icon automobile"><i class="fas fa-car"></i></div>
                <div class="division-stats">
                    <h4>Automobiles</h4>
                    <div class="stats-row">
                        <span>Views: 0</span>
                        <span>Inquiries: 0</span>
                        <span>Sales: 0</span>
                    </div>
                    <div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div>
                </div>
            </div>
            <div class="division-card">
                <div class="division-icon realestate"><i class="fas fa-home"></i></div>
                <div class="division-stats">
                    <h4>Real Estate</h4>
                    <div class="stats-row">
                        <span>Views: 0</span>
                        <span>Inquiries: 0</span>
                        <span>Sales: 0</span>
                    </div>
                    <div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div>
                </div>
            </div>
            <div class="division-card">
                <div class="division-icon solar"><i class="fas fa-solar-panel"></i></div>
                <div class="division-stats">
                    <h4>Solar Energy</h4>
                    <div class="stats-row">
                        <span>Views: 0</span>
                        <span>Inquiries: 0</span>
                        <span>Sales: 0</span>
                    </div>
                    <div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div>
                </div>
            </div>
            <div class="division-card">
                <div class="division-icon marketplace"><i class="fas fa-store"></i></div>
                <div class="division-stats">
                    <h4>Marketplace</h4>
                    <div class="stats-row">
                        <span>Views: 0</span>
                        <span>Inquiries: 0</span>
                        <span>Sales: 0</span>
                    </div>
                    <div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="insights-grid">
        <div class="insight-card">
            <h4>Top Locations</h4>
            <ul class="location-list">
                <li><span>No location data yet</span><span>—</span></li>
            </ul>
        </div>
        <div class="insight-card">
            <h4>Device Breakdown</h4>
            <canvas id="deviceChart" height="120"></canvas>
            <div class="chart-empty" data-for="deviceChart">No traffic data yet.</div>
        </div>
        <div class="insight-card">
            <h4>Peak Hours</h4>
            <canvas id="peakHoursChart" height="120"></canvas>
            <div class="chart-empty" data-for="peakHoursChart">No activity data yet.</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
function emptyChart(canvasId, type) {
    var el = document.getElementById(canvasId);
    if (!el) return;
    new Chart(el, {
        type: type,
        data: { labels: [], datasets: [] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }
    });
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
