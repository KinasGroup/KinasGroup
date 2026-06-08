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
        <div class="metric-card"><div class="metric-icon"><i class="fas fa-eye"></i></div><div class="metric-info"><div class="metric-value">2,847</div><div class="metric-label">Total Views</div><span class="metric-change positive"><i class="fas fa-arrow-up"></i> +18%</span></div></div>
        <div class="metric-card"><div class="metric-icon"><i class="fas fa-envelope"></i></div><div class="metric-info"><div class="metric-value">156</div><div class="metric-label">Inquiries</div><span class="metric-change positive"><i class="fas fa-arrow-up"></i> +12%</span></div></div>
        <div class="metric-card"><div class="metric-icon"><i class="fas fa-percent"></i></div><div class="metric-info"><div class="metric-value">24.3%</div><div class="metric-label">Conversion Rate</div><span class="metric-change positive"><i class="fas fa-arrow-up"></i> +5%</span></div></div>
        <div class="metric-card"><div class="metric-icon"><i class="fas fa-star"></i></div><div class="metric-info"><div class="metric-value">4.8</div><div class="metric-label">Rating</div><span class="metric-change positive">★★★★★</span></div></div>
    </div>

    <div class="charts-row"><div class="chart-card"><div class="chart-header"><h3>Views & Inquiries Trend</h3><i class="fas fa-chart-line" style="color:#C6A43F;"></i></div><canvas id="trendChart" height="250"></canvas></div><div class="chart-card"><div class="chart-header"><h3>Top Performing Listings</h3><i class="fas fa-trophy" style="color:#C6A43F;"></i></div><canvas id="topListingsChart" height="250"></canvas></div></div>

    <div class="division-performance"><h3>Performance by Division</h3><div class="division-grid">
        <div class="division-card"><div class="division-icon automobile"><i class="fas fa-car"></i></div><div class="division-stats"><h4>Automobiles</h4><div class="stats-row"><span>Views: 1,284</span><span>Inquiries: 68</span><span>Sales: 12</span></div><div class="progress-bar"><div class="progress-fill" style="width:65%"></div></div></div></div>
        <div class="division-card"><div class="division-icon realestate"><i class="fas fa-home"></i></div><div class="division-stats"><h4>Real Estate</h4><div class="stats-row"><span>Views: 892</span><span>Inquiries: 45</span><span>Sales: 8</span></div><div class="progress-bar"><div class="progress-fill" style="width:45%"></div></div></div></div>
        <div class="division-card"><div class="division-icon solar"><i class="fas fa-solar-panel"></i></div><div class="division-stats"><h4>Solar Energy</h4><div class="stats-row"><span>Views: 342</span><span>Inquiries: 23</span><span>Sales: 5</span></div><div class="progress-bar"><div class="progress-fill" style="width:25%"></div></div></div></div>
        <div class="division-card"><div class="division-icon marketplace"><i class="fas fa-store"></i></div><div class="division-stats"><h4>Marketplace</h4><div class="stats-row"><span>Views: 329</span><span>Inquiries: 20</span><span>Sales: 3</span></div><div class="progress-bar"><div class="progress-fill" style="width:20%"></div></div></div></div>
    </div></div>

    <div class="insights-grid"><div class="insight-card"><h4>Top Locations</h4><ul class="location-list"><li><span>Lagos, Nigeria</span><span>45%</span></li><li><span>Abuja, Nigeria</span><span>23%</span></li><li><span>Accra, Ghana</span><span>12%</span></li><li><span>Nairobi, Kenya</span><span>8%</span></li></ul></div><div class="insight-card"><h4>Device Breakdown</h4><canvas id="deviceChart" height="150"></canvas></div><div class="insight-card"><h4>Peak Hours</h4><canvas id="peakHoursChart" height="150"></canvas></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('trendChart'), {type:'line',data:{labels:['Week1','Week2','Week3','Week4'],datasets:[{label:'Views',data:[420,580,650,890],borderColor:'#3B82F6',backgroundColor:'rgba(59,130,246,0.1)',tension:0.4,fill:true},{label:'Inquiries',data:[28,35,42,51],borderColor:'#C6A43F',backgroundColor:'rgba(198,164,63,0.1)',tension:0.4,fill:true}]},options:{responsive:true,plugins:{legend:{labels:{color:'#666'}}},scales:{y:{grid:{color:'#F0F0F0'},ticks:{color:'#666'}},x:{grid:{display:false},ticks:{color:'#666'}}}}});
new Chart(document.getElementById('topListingsChart'), {type:'bar',data:{labels:['Mercedes S-Class','Luxury Villa','Solar System','Rolex Watch','BMW i8'],datasets:[{label:'Views',data:[342,284,189,156,142],backgroundColor:'rgba(198,164,63,0.7)',borderRadius:8}]},options:{responsive:true,indexAxis:'y',plugins:{legend:{labels:{color:'#666'}}},scales:{x:{grid:{color:'#F0F0F0'},ticks:{color:'#666'}},y:{grid:{display:false},ticks:{color:'#666'}}}}});
new Chart(document.getElementById('deviceChart'), {type:'doughnut',data:{labels:['Mobile','Desktop','Tablet'],datasets:[{data:[58,32,10],backgroundColor:['#C6A43F','#3B82F6','#10B981']}]},options:{responsive:true,plugins:{legend:{position:'bottom',labels:{color:'#666'}}}}});
new Chart(document.getElementById('peakHoursChart'), {type:'line',data:{labels:['9am','12pm','3pm','6pm','9pm'],datasets:[{label:'Activity',data:[45,78,92,85,62],borderColor:'#C6A43F',backgroundColor:'rgba(198,164,63,0.1)',fill:true,tension:0.4}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{grid:{color:'#F0F0F0'},ticks:{color:'#666'}},x:{grid:{display:false},ticks:{color:'#666'}}}}});
</script>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
