<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to']   ?? date('Y-m-d');

// ── Top stats ────────────────────────────────────────────────
$stats = [
    'agents'    => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='agent'")->fetchColumn(),
    'listings'  => (int)$db->query("SELECT
            (SELECT COUNT(*) FROM car_listings WHERE status='active') +
            (SELECT COUNT(*) FROM property_listings WHERE status='active') +
            (SELECT COUNT(*) FROM solar_listings WHERE status='active') +
            (SELECT COUNT(*) FROM marketplace_listings WHERE status='active')")->fetchColumn(),
    'inquiries' => (int)$db->query("SELECT COUNT(*) FROM inquiries WHERE created_at BETWEEN '$from 00:00:00' AND '$to 23:59:59'")->fetchColumn(),
    'value'     => (float)$db->query("SELECT
            IFNULL((SELECT SUM(price) FROM car_listings WHERE status='active'),0) +
            IFNULL((SELECT SUM(price) FROM property_listings WHERE status='active'),0) +
            IFNULL((SELECT SUM(price) FROM solar_listings WHERE status='active'),0) +
            IFNULL((SELECT SUM(price) FROM marketplace_listings WHERE status='active'),0)")->fetchColumn(),
];

// ── Listings by category (real counts) ───────────────────────
$catCounts = [
    'car'         => (int)$db->query("SELECT COUNT(*) FROM car_listings WHERE status='active'")->fetchColumn(),
    'property'    => (int)$db->query("SELECT COUNT(*) FROM property_listings WHERE status='active'")->fetchColumn(),
    'solar'       => (int)$db->query("SELECT COUNT(*) FROM solar_listings WHERE status='active'")->fetchColumn(),
    'marketplace' => (int)$db->query("SELECT COUNT(*) FROM marketplace_listings WHERE status='active'")->fetchColumn(),
];
$catTotal = max(1, array_sum($catCounts));
$catPct = array_map(fn($c) => round($c / $catTotal * 100), $catCounts);

// ── Monthly growth (last 12 months, new listings) ────────────
$monthlyLabels = [];
$monthlyCounts = [];
for ($i = 11; $i >= 0; $i--) {
    $mStart = date('Y-m-01', strtotime("-$i months"));
    $mEnd   = date('Y-m-t', strtotime("-$i months"));
    $c = (int)$db->query("SELECT
            (SELECT COUNT(*) FROM car_listings WHERE DATE(created_at) BETWEEN '$mStart' AND '$mEnd') +
            (SELECT COUNT(*) FROM property_listings WHERE DATE(created_at) BETWEEN '$mStart' AND '$mEnd') +
            (SELECT COUNT(*) FROM solar_listings WHERE DATE(created_at) BETWEEN '$mStart' AND '$mEnd') +
            (SELECT COUNT(*) FROM marketplace_listings WHERE DATE(created_at) BETWEEN '$mStart' AND '$mEnd')")->fetchColumn();
    $monthlyLabels[] = date('M Y', strtotime($mStart));
    $monthlyCounts[] = $c;
}

// ── Recent activity (real, from activity_logs) ──────────────
$recent = $db->prepare("
    SELECT a.created_at, a.action, a.details, u.name AS user_name
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.created_at BETWEEN ? AND ?
    ORDER BY a.created_at DESC
    LIMIT 10
");
$recent->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
$recentRows = $recent->fetchAll(PDO::FETCH_ASSOC);

$headerDepth = '../';
$pageTitle = 'Reports - KINAS GROUP';
require_once __DIR__ . '/../templates/header.php';
?>
    <!-- ============================================================
         RESPONSIVE FIX - Added container and responsive styles
         ============================================================ -->
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-main { flex: 1; padding: 30px; background: #F5F7FA; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; margin-bottom: 8px; }
        .page-header p { color: #666; font-size: 14px; }
        .date-range { background: white; border-radius: 16px; padding: 20px; margin-bottom: 30px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; border: 1px solid #E0E0E0; }
        .date-group { display: flex; flex-direction: column; gap: 5px; }
        .date-group label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #666; }
        .date-group input { padding: 10px 12px; border: 1px solid #E0E0E0; border-radius: 8px; font-family: 'Inter', sans-serif; }
        .btn-filter { background: #C6A43F; color: #0A0A0A; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-filter:hover { background: #A8882E; }
        .btn-secondary { background: #F5F5F5; color: #333; border: 1px solid #E0E0E0; padding: 10px 16px; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 13px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 16px; padding: 25px; border: 1.5px solid #C6A43F; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-3px); border-color: #C6A43F; box-shadow: 0 8px 24px rgba(198,164,63,0.15); }
        .stat-card .icon { font-size: 32px; color: #C6A43F; margin-bottom: 15px; }
        .stat-card h3 { font-family: 'Prata', serif; font-size: 32px; color: #C6A43F; margin-bottom: 5px; }
        .stat-card p { color: #666; font-size: 13px; }
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 30px; }
        .chart-card { background: white; border-radius: 16px; padding: 25px; border: 1px solid #E0E0E0; }
        .chart-card h3 { font-family: 'Prata', serif; font-size: 18px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #C6A43F; display: inline-block; }
        .recent-table { background: white; border-radius: 16px; border: 1px solid #E0E0E0; overflow: hidden; width: 100%; }
        .recent-table h3 { padding: 20px 25px; margin: 0; font-family: 'Prata', serif; font-size: 18px; border-bottom: 1px solid #E0E0E0; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th { text-align: left; padding: 15px 20px; background: #F8F8F8; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; }
        td { padding: 12px 20px; border-bottom: 1px solid #E0E0E0; font-size: 13px; }
        tr:hover { background: #F8F8F8; }
        .empty-state { padding: 60px 20px; text-align: center; color: #999; }
        .empty-state i { font-size: 40px; color: #C6A43F; opacity: 0.4; display: block; margin-bottom: 12px; }
        .export-buttons { display: flex; gap: 12px; margin-top: 20px; justify-content: flex-end; padding: 0 25px 20px; flex-wrap: wrap; }
        .btn-export { background: #666; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-export:hover { background: #333; }
        .je-dash-shell { max-width: 100% !important; overflow-x: hidden !important; }
        .je-dash-main { overflow-x: hidden !important; width: 100% !important; max-width: 100% !important; padding: 15px !important; }
        @media (max-width: 992px) { .charts-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { 
            .admin-main { padding: 15px; }
            .je-dash-main { padding: 10px !important; }
            .date-range { flex-direction: column; align-items: stretch; }
            .date-group input { width: 100%; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 15px; }
            .stat-card h3 { font-size: 24px; }
            .stat-card .icon { font-size: 24px; margin-bottom: 10px; }
            table { min-width: 400px; }
            th, td { padding: 10px 12px; font-size: 12px; }
            .export-buttons { justify-content: center; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .stat-card h3 { font-size: 20px; }
            .chart-card { padding: 15px; }
            .chart-card h3 { font-size: 15px; }
        }
    </style>
<div class="je-dash-shell" style="max-width:100%;overflow-x:hidden;">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">
    <div class="page-header">
        <h1><i class="fas fa-chart-line" style="color: #C6A43F; margin-right: 10px;"></i>Analytics & Reports</h1>
        <p>Comprehensive marketplace insights and performance metrics</p>
    </div>

    <form class="date-range" method="GET">
        <div class="date-group"><label>From Date</label><input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></div>
        <div class="date-group"><label>To Date</label><input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></div>
        <button type="submit" class="btn-filter"><i class="fas fa-sync-alt"></i> Generate</button>
        <a href="reports.php" class="btn-secondary">Reset</a>
    </form>

    <div class="stats-grid">
        <div class="stat-card"><div class="icon"><i class="fas fa-user-tie"></i></div><h3><?= number_format($stats['agents']) ?></h3><p>Total Agents</p></div>
        <div class="stat-card"><div class="icon"><i class="fas fa-list-ul"></i></div><h3><?= number_format($stats['listings']) ?></h3><p>Active Listings</p></div>
        <div class="stat-card"><div class="icon"><i class="fas fa-envelope"></i></div><h3><?= number_format($stats['inquiries']) ?></h3><p>Inquiries (selected range)</p></div>
        <div class="stat-card"><div class="icon"><i class="fas fa-coins"></i></div><h3>₦<?= number_format($stats['value'] / 1e9, 2) ?>B</h3><p>Active Inventory Value</p></div>
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <h3>Listings by Category</h3>
            <canvas id="catChart" height="220"></canvas>
        </div>
        <div class="chart-card">
            <h3>Monthly Growth</h3>
            <canvas id="growthChart" height="220"></canvas>
        </div>
    </div>

    <div class="recent-table" style="overflow-x: hidden; width: 100%;">
        <h3>Recent Activity</h3>
        <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%;">
            <?php if (empty($recentRows)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No activity in the selected date range.</p>
                </div>
            <?php else: ?>
            <table style="min-width: 450px; width: 100%;">
                <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recentRows as $r):
                    $type = strtolower($r['action'] ?? '');
                    $statusLabel = '—';
                    $statusColor = '#666';
                    if (str_contains($type,'approved') || str_contains($type,'success') || str_contains($type,'create')) { $statusLabel='✓ Completed'; $statusColor='#2E7D32'; }
                    elseif (str_contains($type,'reject') || str_contains($type,'removed') || str_contains($type,'flagged')) { $statusLabel='Resolved'; $statusColor='#DC2626'; }
                    elseif (str_contains($type,'pending') || str_contains($type,'request')) { $statusLabel='Pending'; $statusColor='#F57C00'; }
                    elseif (str_contains($type,'login') || str_contains($type,'auth')) { $statusLabel='✓ Auth'; $statusColor='#1565C0'; }
                ?>
                    <tr>
                        <td><?= htmlspecialchars($r['created_at']) ?></td>
                        <td><?= htmlspecialchars(ucwords(str_replace('_',' ', $r['action'] ?? ''))) ?></td>
                        <td><?= htmlspecialchars(($r['details'] ?? '') . ($r['user_name'] ? ' — by ' . $r['user_name'] : '')) ?></td>
                        <td><span style="color:<?= $statusColor ?>; font-weight:600;"><?= $statusLabel ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <div class="export-buttons">
            <a href="reports-export.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&format=csv" class="btn-export"><i class="fas fa-file-csv"></i> Export CSV</a>
            <button type="button" class="btn-export" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>
</main>
</div>

<script>
new Chart(document.getElementById('catChart'), {
    type: 'doughnut',
    data: {
        labels: ['Cars (<?= $catCounts['car'] ?>)', 'Properties (<?= $catCounts['property'] ?>)', 'Solar (<?= $catCounts['solar'] ?>)', 'Marketplace (<?= $catCounts['marketplace'] ?>)'],
        datasets: [{ data: [<?= $catCounts['car'] ?>, <?= $catCounts['property'] ?>, <?= $catCounts['solar'] ?>, <?= $catCounts['marketplace'] ?>], backgroundColor: ['#1565C0', '#2E7D32', '#F57C00', '#7B1FA2'], borderWidth: 0 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($monthlyLabels) ?>,
        datasets: [{ label: 'New listings', data: <?= json_encode($monthlyCounts) ?>, borderColor: '#C6A43F', backgroundColor: 'rgba(198,164,63,0.1)', tension: 0.4, fill: true }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
