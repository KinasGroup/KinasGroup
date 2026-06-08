<?php
/**
 * KINAS GROUP — Admin Dashboard
 * Live data from DB. Charts populated via JS fetch from API.
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();

// ── Live stats ────────────────────────────────────────────────
$stats = [];

$stats['total_users']    = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$stats['total_agents']   = $db->query("SELECT COUNT(*) FROM users WHERE role='agent' AND status='active'")->fetchColumn();
$stats['pending_agents'] = $db->query("SELECT COUNT(*) FROM agent_profiles WHERE verification_status='pending'")->fetchColumn();

// Total listings across all division tables
$stats['total_listings'] = (int)$db->query("SELECT COUNT(*) FROM car_listings")->fetchColumn()
    + (int)$db->query("SELECT COUNT(*) FROM property_listings")->fetchColumn()
    + (int)$db->query("SELECT COUNT(*) FROM solar_listings")->fetchColumn()
    + (int)$db->query("SELECT COUNT(*) FROM marketplace_listings")->fetchColumn();

// Revenue (sum of paid earnings)
$rev = $db->query("SELECT COALESCE(SUM(commission_amt),0) FROM earnings WHERE status='paid'")->fetchColumn();
$stats['revenue'] = $rev;

// Recent activity logs
$activity = $db->query(
    "SELECT al.action, al.details, al.created_at, u.name
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     ORDER BY al.created_at DESC LIMIT 8"
)->fetchAll();

// Format naira
function fmt_ngn(float $amount): string {
    if ($amount >= 1_000_000_000) return '₦' . number_format($amount/1_000_000_000, 1) . 'B';
    if ($amount >= 1_000_000)     return '₦' . number_format($amount/1_000_000, 1) . 'M';
    return '₦' . number_format($amount, 0);
}

$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#F5F7FA}
        .admin-layout{display:flex;min-height:100vh}
        .admin-main{flex:1;padding:30px;background:#F5F7FA}
        .admin-container{max-width:1400px;margin:0 auto}
        .page-header{margin-bottom:30px}
        .page-header h1{font-family:'Prata',serif;font-size:28px;color:#0A0A0A;margin-bottom:6px}
        .page-header p{color:#666;font-size:14px}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:30px}
        .stat-card{background:white;border-radius:16px;padding:24px;border:1px solid #E0E0E0;display:flex;align-items:center;gap:18px;transition:all .3s}
        .stat-card:hover{transform:translateY(-3px);border-color:#C6A43F;box-shadow:0 8px 24px rgba(0,0,0,.08)}
        .stat-icon{width:56px;height:56px;border-radius:14px;background:rgba(198,164,63,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .stat-icon i{font-size:1.6rem;color:#C6A43F}
        .stat-info h3{font-size:12px;color:#999;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
        .stat-number{font-size:28px;font-weight:700;color:#0A0A0A;margin-bottom:2px}
        .stat-sub{font-size:12px;color:#2E7D32}
        .stat-sub.warn{color:#F57C00}
        .charts-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:24px;margin-bottom:30px}
        .chart-card{background:white;border-radius:16px;padding:24px;border:1px solid #E0E0E0}
        .chart-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .chart-header h3{font-size:16px;font-weight:600;color:#0A0A0A}
        .chart-period{background:#F5F7FA;border:1px solid #E0E0E0;border-radius:8px;padding:6px 12px;color:#333;font-size:13px;cursor:pointer}
        .activity-card{background:white;border-radius:16px;border:1px solid #E0E0E0;overflow:hidden}
        .activity-header{display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid #E0E0E0}
        .activity-header h3{font-size:16px;font-weight:600;color:#0A0A0A}
        .view-all{color:#C6A43F;text-decoration:none;font-size:13px;font-weight:600}
        .activity-list{padding:8px 0}
        .activity-item{display:flex;align-items:flex-start;gap:14px;padding:14px 24px;border-bottom:1px solid #F5F7FA;transition:background .2s}
        .activity-item:hover{background:#FEFBF5}
        .act-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px}
        .act-icon.user{background:#E3F2FD;color:#1565C0}
        .act-icon.listing{background:rgba(198,164,63,.1);color:#C6A43F}
        .act-icon.agent{background:#E8F5E9;color:#2E7D32}
        .act-icon.alert{background:#FEF2F2;color:#DC2626}
        .activity-item p{font-size:13px;color:#333;margin-bottom:3px;line-height:1.4}
        .activity-item strong{color:#0A0A0A}
        .activity-time{font-size:11px;color:#999}
        .empty-activity{text-align:center;padding:40px;color:#999}
        @media(max-width:768px){.admin-main{padding:20px}.charts-row{grid-template-columns:1fr}.stats-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:480px){.stats-grid{grid-template-columns:1fr}}
    </style>

</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>
<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>
<main class="je-dash-main">
<div class="admin-container">

    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>. Here's what's happening today.</p>
    </div>

    <!-- Live Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3>Total Users</h3>
                <div class="stat-number"><?= number_format($stats['total_users']) ?></div>
                <span class="stat-sub">Registered customers</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-list-ul"></i></div>
            <div class="stat-info">
                <h3>Total Listings</h3>
                <div class="stat-number"><?= number_format($stats['total_listings']) ?></div>
                <span class="stat-sub">Across all divisions</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-check"></i></div>
            <div class="stat-info">
                <h3>Active Agents</h3>
                <div class="stat-number"><?= number_format($stats['total_agents']) ?></div>
                <?php if ($stats['pending_agents'] > 0): ?>
                <span class="stat-sub warn"><?= $stats['pending_agents'] ?> pending approval</span>
                <?php else: ?>
                <span class="stat-sub">No pending approvals</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-naira-sign"></i></div>
            <div class="stat-info">
                <h3>Total Revenue</h3>
                <div class="stat-number"><?= fmt_ngn((float)$stats['revenue']) ?></div>
                <span class="stat-sub">Paid commissions</span>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-row">
        <div class="chart-card">
            <div class="chart-header">
                <h3>User Registrations</h3>
                <select class="chart-period" id="growthPeriod" onchange="loadGrowthChart(this.value)">
                    <option value="7">Last 7 days</option>
                    <option value="30" selected>Last 30 days</option>
                    <option value="90">Last 90 days</option>
                </select>
            </div>
            <canvas id="userGrowthChart" height="220"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <h3>Listings by Division</h3>
                <span style="font-size:12px;color:#999">All time</span>
            </div>
            <canvas id="divisionsChart" height="220"></canvas>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="activity-card">
        <div class="activity-header">
            <h3>Recent Activity</h3>
            <a href="/admin/activity-logs.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="activity-list">
            <?php if (empty($activity)): ?>
            <div class="empty-activity">
                <i class="fas fa-stream" style="font-size:2rem;color:#E0E0E0;margin-bottom:12px;display:block"></i>
                No activity logged yet. Actions will appear here once users start using the platform.
            </div>
            <?php else: ?>
            <?php foreach ($activity as $log):
                $icon = 'stream'; $cls = 'user';
                if (str_contains($log['action'], 'listing')) { $icon = 'plus-circle'; $cls = 'listing'; }
                elseif (str_contains($log['action'], 'agent')) { $icon = 'user-check'; $cls = 'agent'; }
                elseif (str_contains($log['action'], 'flag')) { $icon = 'flag'; $cls = 'alert'; }
            ?>
            <div class="activity-item">
                <div class="act-icon <?= $cls ?>"><i class="fas fa-<?= $icon ?>"></i></div>
                <div>
                    <p>
                        <?php if ($log['name']): ?><strong><?= htmlspecialchars($log['name']) ?></strong> — <?php endif; ?>
                        <?= htmlspecialchars($log['action']) ?>
                        <?php if ($log['details']): ?><span style="color:#666"> — <?= htmlspecialchars(substr($log['details'],0,80)) ?></span><?php endif; ?>
                    </p>
                    <span class="activity-time"><?= date('M j, Y g:ia', strtotime($log['created_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>


<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const GOLD = '#C6A43F';
const GOLD_ALPHA = 'rgba(198,164,63,0.12)';

// Divisions doughnut — pulled from page data (no extra request needed)
const divData = <?= json_encode([
    'car'         => (int)$db->query("SELECT COUNT(*) FROM car_listings")->fetchColumn(),
    'property'    => (int)$db->query("SELECT COUNT(*) FROM property_listings")->fetchColumn(),
    'solar'       => (int)$db->query("SELECT COUNT(*) FROM solar_listings")->fetchColumn(),
    'marketplace' => (int)$db->query("SELECT COUNT(*) FROM marketplace_listings")->fetchColumn(),
]) ?>;

new Chart(document.getElementById('divisionsChart'), {
    type: 'doughnut',
    data: {
        labels: ['Automobile','Real Estate','Solar','Marketplace'],
        datasets: [{ data: Object.values(divData), backgroundColor: ['#1565C0','#2E7D32','#F57C00','#7B1FA2'], borderWidth: 0 }]
    },
    options: {
        responsive:true, maintainAspectRatio:true,
        plugins: { legend: { position:'bottom', labels:{ color:'#555', padding:16 } } }
    }
});

// Growth line chart — loaded from API
let growthChart;
async function loadGrowthChart(days = 30) {
    try {
        const res = await fetch(`/api/admin/dashboard-stats.php?type=growth&days=${days}`);
        const data = await res.json();
        const labels = data.map ? data.map(r => r.date) : ['No data'];
        const counts = data.map ? data.map(r => r.count) : [0];
        if (growthChart) growthChart.destroy();
        growthChart = new Chart(document.getElementById('userGrowthChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [{ label:'New Users', data:counts, borderColor:GOLD, backgroundColor:GOLD_ALPHA, tension:.4, fill:true, pointBackgroundColor:GOLD }]
            },
            options: {
                responsive:true, maintainAspectRatio:true,
                plugins: { legend:{ display:false } },
                scales: {
                    y:{ grid:{color:'rgba(0,0,0,.05)'}, ticks:{color:'#999'}, beginAtZero:true },
                    x:{ grid:{display:false}, ticks:{color:'#999', maxTicksLimit:8} }
                }
            }
        });
    } catch(e) {
        // Fallback: show empty placeholder chart
        if (growthChart) return;
        growthChart = new Chart(document.getElementById('userGrowthChart'), {
            type:'line',
            data:{ labels:['No data'], datasets:[{ data:[0], borderColor:GOLD }] },
            options:{ responsive:true, plugins:{legend:{display:false}} }
        });
    }
}
loadGrowthChart(30);
</script>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
