<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();

// ── Filters ──────────────────────────────────────────────────
$from      = $_GET['from']  ?? date('Y-m-d', strtotime('-30 days'));
$to        = $_GET['to']    ?? date('Y-m-d');
$action    = trim($_GET['action'] ?? '');
$userQuery = trim($_GET['user'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 25;
$offset    = ($page - 1) * $perPage;

$where = ["DATE(a.created_at) BETWEEN ? AND ?"];
$params = [$from, $to];
if ($action !== '')         { $where[] = "a.action LIKE ?";    $params[] = "%$action%"; }
if ($userQuery !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$userQuery%";
    $params[] = "%$userQuery%";
}
$whereSQL = implode(' AND ', $where);

// ── Pull logs ────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT a.*, u.name AS user_name, u.email AS user_email
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE $whereSQL
    ORDER BY a.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalStmt = $db->prepare("SELECT COUNT(*) FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id WHERE $whereSQL");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// ── Stats ────────────────────────────────────────────────────
$stats = [
    'total'  => (int)$db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn(),
    'week'   => (int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'admin'  => (int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE action LIKE 'admin%' OR action LIKE 'agent%'")->fetchColumn(),
    'pending'=> (int)$db->query("SELECT COUNT(*) FROM business_documents WHERE status='pending'")->fetchColumn(),
];

$headerDepth = '../';
$pageTitle = 'Activity Logs - KINAS GROUP';
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
        .filters-bar { background: white; border-radius: 16px; padding: 20px; margin-bottom: 25px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; border: 1px solid #E0E0E0; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #666; letter-spacing: 0.5px; }
        .filter-group select, .filter-group input { padding: 10px 12px; border: 1px solid #E0E0E0; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 13px; min-width: 150px; }
        .btn-filter { background: #C6A43F; color: #0A0A0A; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-filter:hover { background: #A8882E; }
        .btn-secondary { background: #F5F5F5; color: #333; border: 1px solid #E0E0E0; padding: 10px 16px; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 13px; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 12px; padding: 15px 20px; border: 1.5px solid #C6A43F; transition: all 0.3s; }
        .stat-card:hover { border-color: #C6A43F; box-shadow: 0 8px 24px rgba(198,164,63,0.15); transform: translateY(-3px); }
        .stat-card .number { font-size: 24px; font-weight: 700; color: #C6A43F; font-family: 'Prata', serif; }
        .stat-card .label { font-size: 12px; color: #666; margin-top: 4px; }
        .logs-card { background: white; border-radius: 20px; border: 1px solid #E0E0E0; overflow: hidden; width: 100%; }
        .logs-header { padding: 20px 25px; background: #F8F8F8; border-bottom: 1px solid #E0E0E0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .logs-header h2 { font-family: 'Prata', serif; font-size: 18px; color: #0A0A0A; }
        .export-btn { background: #666; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 12px; cursor: pointer; }
        .export-btn:hover { background: #333; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }
        .logs-table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .logs-table th { text-align: left; padding: 15px 20px; background: #F8F8F8; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; letter-spacing: 0.5px; border-bottom: 1px solid #E0E0E0; }
        .logs-table td { padding: 14px 20px; border-bottom: 1px solid #E0E0E0; font-size: 13px; color: #333; }
        .logs-table tr:hover { background: #F8F8F8; }
        .log-type { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .log-type.user { background: #E3F2FD; color: #1565C0; }
        .log-type.listing { background: #E8F5E9; color: #2E7D32; }
        .log-type.agent { background: #FFF3E0; color: #F57C00; }
        .log-type.admin { background: #F3E5F5; color: #7B1FA2; }
        .log-type.system { background: #ECEFF1; color: #455A64; }
        .log-type.auth { background: #FCE4EC; color: #AD1457; }
        .log-type.message { background: #E0F2F1; color: #00695C; }
        .empty-state { padding: 80px 20px; text-align: center; color: #999; }
        .empty-state i { font-size: 48px; color: #C6A43F; margin-bottom: 16px; display: block; opacity: 0.5; }
        .empty-state p { font-size: 14px; }
        .pagination { display: flex; justify-content: center; gap: 8px; padding: 20px; border-top: 1px solid #E0E0E0; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 14px; background: white; border: 1px solid #E0E0E0; border-radius: 8px; text-decoration: none; color: #333; transition: all 0.3s; font-size: 13px; }
        .pagination a:hover, .pagination .active { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
        .pagination .disabled { color: #CCC; cursor: not-allowed; }
        .je-dash-shell { max-width: 100% !important; overflow-x: hidden !important; }
        .je-dash-main { overflow-x: hidden !important; width: 100% !important; max-width: 100% !important; padding: 15px !important; }
        @media (max-width: 768px) { 
            .admin-main { padding: 15px; }
            .je-dash-main { padding: 10px !important; }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .filter-group select, .filter-group input { width: 100%; }
            .stats-row { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 12px 15px; }
            .stat-card .number { font-size: 20px; }
            .logs-table { min-width: 450px; }
            .logs-table th, .logs-table td { padding: 10px 12px; font-size: 12px; }
            .logs-header { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 480px) {
            .stats-row { grid-template-columns: 1fr; }
            .logs-table th:nth-child(3), .logs-table td:nth-child(3) { display: none; }
        }
    
/* ============================================================
   DARK MODE — force this page's own styling to stay identical
   to light mode. Auto-generated from every hardcoded
   background/color/border-color rule already on this page.
   ============================================================ */
@media (prefers-color-scheme: dark) {
    body { background: #F5F7FA !important; }
    .admin-main { background: #F5F7FA !important; }
    .page-header h1 { color: #0A0A0A !important; }
    .page-header p { color: #666 !important; }
    .filters-bar { background: white !important; }
    .filter-group label { color: #666 !important; }
    .btn-filter { background: #C6A43F !important; color: #0A0A0A !important; }
    .btn-filter:hover { background: #A8882E !important; }
    .btn-secondary { background: #F5F5F5 !important; color: #333 !important; }
    .stat-card { background: white !important; }
    .stat-card:hover { border-color: #C6A43F !important; }
    .stat-card .number { color: #C6A43F !important; }
    .stat-card .label { color: #666 !important; }
    .logs-card { background: white !important; }
    .logs-header { background: #F8F8F8 !important; }
    .logs-header h2 { color: #0A0A0A !important; }
    .export-btn { background: #666 !important; color: white !important; }
    .export-btn:hover { background: #333 !important; }
    .logs-table th { background: #F8F8F8 !important; color: #666 !important; }
    .logs-table td { color: #333 !important; }
    .logs-table tr:hover { background: #F8F8F8 !important; }
    .log-type.user { background: #E3F2FD !important; color: #1565C0 !important; }
    .log-type.listing { background: #E8F5E9 !important; color: #2E7D32 !important; }
    .log-type.agent { background: #FFF3E0 !important; color: #F57C00 !important; }
    .log-type.admin { background: #F3E5F5 !important; color: #7B1FA2 !important; }
    .log-type.system { background: #ECEFF1 !important; color: #455A64 !important; }
    .log-type.auth { background: #FCE4EC !important; color: #AD1457 !important; }
    .log-type.message { background: #E0F2F1 !important; color: #00695C !important; }
    .empty-state { color: #999 !important; }
    .empty-state i { color: #C6A43F !important; }
    .pagination a, .pagination span { background: white !important; color: #333 !important; }
    .pagination a:hover, .pagination .active { background: #C6A43F !important; border-color: #C6A43F !important; color: #0A0A0A !important; }
    .pagination .disabled { color: #CCC !important; }
}
</style>
<div class="je-dash-shell" style="max-width:100%;overflow-x:hidden;">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">
    <div class="page-header">
        <h1><i class="fas fa-history" style="color: #C6A43F; margin-right: 10px;"></i>Activity Logs</h1>
        <p>Monitor all user actions and system events</p>
    </div>

    <form class="filters-bar" method="GET">
        <div class="filter-group"><label>From</label><input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></div>
        <div class="filter-group"><label>To</label><input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></div>
        <div class="filter-group"><label>Action</label>
            <select name="action">
                <option value="">All actions</option>
                <?php foreach (['login','register','listing_created','listing_updated','listing_deleted','agent_approved','agent_rejected','message_sent','admin_login','admin_action'] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $action === $opt ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$opt)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group"><label>User</label><input type="text" name="user" placeholder="Name or email…" value="<?= htmlspecialchars($userQuery) ?>"></div>
        <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
        <a href="activity-logs.php" class="btn-secondary"><i class="fas fa-undo"></i> Reset</a>
    </form>

    <div class="stats-row">
        <div class="stat-card"><div class="number"><?= number_format($stats['total']) ?></div><div class="label">Total Events</div></div>
        <div class="stat-card"><div class="number"><?= number_format($stats['week']) ?></div><div class="label">This Week</div></div>
        <div class="stat-card"><div class="number"><?= number_format($stats['admin']) ?></div><div class="label">Admin / Agent Actions</div></div>
        <div class="stat-card"><div class="number"><?= number_format($stats['pending']) ?></div><div class="label">Pending Reviews</div></div>
    </div>

    <div class="logs-card">
        <div class="logs-header">
            <h2><i class="fas fa-list" style="color: #C6A43F; margin-right: 8px;"></i>Event Timeline
                <span style="font-size:12px; color:#999; font-family:Inter,sans-serif; font-weight:400; margin-left:8px;">
                    (<?= number_format($total) ?> <?= $total === 1 ? 'event' : 'events' ?>)
                </span>
            </h2>
            <form method="GET" action="activity-logs-export.php" style="display:inline">
                <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
                <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
                <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                <input type="hidden" name="user" value="<?= htmlspecialchars($userQuery) ?>">
                <button type="submit" class="export-btn"><i class="fas fa-download"></i> Export CSV</button>
            </form>
        </div>
        <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%;">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No activity events match the current filters.</p>
                    <?php if ($action || $userQuery || $from !== date('Y-m-d', strtotime('-30 days'))): ?>
                        <p style="margin-top:8px;"><a href="activity-logs.php" style="color:#C6A43F;">Clear filters</a></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
            <table class="logs-table" style="min-width: 450px; width: 100%;">
                <thead>
                    <tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l):
                        $actionLabel = htmlspecialchars($l['action'] ?? '');
                        $badgeClass  = 'system';
                        $lower       = strtolower($actionLabel);
                        if (str_contains($lower,'login') || str_contains($lower,'register') || str_contains($lower,'auth') || str_contains($lower,'otp')) $badgeClass = 'auth';
                        elseif (str_contains($lower,'listing') || str_contains($lower,'saved') || str_contains($lower,'favorite')) $badgeClass = 'listing';
                        elseif (str_contains($lower,'agent') || str_contains($lower,'kyc') || str_contains($lower,'verification')) $badgeClass = 'agent';
                        elseif (str_contains($lower,'admin') || str_contains($lower,'role') || str_contains($lower,'suspend')) $badgeClass = 'admin';
                        elseif (str_contains($lower,'message') || str_contains($lower,'inquiry')) $badgeClass = 'message';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($l['created_at']) ?></td>
                        <td>
                            <?php if (!empty($l['user_email'])): ?>
                                <?= htmlspecialchars($l['user_name'] ?: '—') ?><br>
                                <span style="font-size:11px; color:#999;"><?= htmlspecialchars($l['user_email']) ?></span>
                            <?php else: ?>
                                <span style="color:#999;">(anonymous)</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="log-type <?= $badgeClass ?>"><?= htmlspecialchars(ucwords(str_replace('_',' ', $actionLabel))) ?></span></td>
                        <td><?= htmlspecialchars($l['details'] ?? '—') ?></td>
                        <td style="font-family:monospace; font-size:12px; color:#666;"><?= htmlspecialchars($l['ip_address'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
                $base = '?' . http_build_query(array_merge($_GET, ['page' => '__P__']));
            ?>
            <?php if ($page > 1): ?>
                <a href="<?= str_replace('__P__', (string)($page-1), $base) ?>">← Prev</a>
            <?php else: ?>
                <span class="disabled">← Prev</span>
            <?php endif; ?>

            <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
            ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= str_replace('__P__', (string)$i, $base) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= str_replace('__P__', (string)($page+1), $base) ?>">Next →</a>
            <?php else: ?>
                <span class="disabled">Next →</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
