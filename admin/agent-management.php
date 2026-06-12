<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();

// ── Filters ──────────────────────────────────────────────────
$search   = trim($_GET['q'] ?? '');
$division = $_GET['division'] ?? '';
$status   = $_GET['status']   ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$where = ["u.role = 'agent'", "u.status != 'deleted'"];   // soft-deleted agents are hidden by default
$params = [];
if ($search !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($division !== '') { $where[] = "u.division = ?"; $params[] = $division; }
if ($status !== '')   { $where[] = "u.status = ?";   $params[] = $status;   }
$whereSQL = implode(' AND ', $where);

// Count + paginate
$total = (int)$db->prepare("SELECT COUNT(*) FROM users u WHERE $whereSQL")
                 ->execute($params) ? 0 : 0;
$countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// Pull agents + listing counts + profile data
$stmt = $db->prepare("
    SELECT u.id, u.name, u.email, u.division, u.status, u.verified, u.created_at,
           ap.company_name, ap.verification_status,
           (SELECT COUNT(*) FROM car_listings         WHERE agent_id = u.id) +
           (SELECT COUNT(*) FROM property_listings    WHERE agent_id = u.id) +
           (SELECT COUNT(*) FROM solar_listings       WHERE agent_id = u.id) +
           (SELECT COUNT(*) FROM marketplace_listings WHERE agent_id = u.id) AS listing_count
    FROM users u
    LEFT JOIN agent_profiles ap ON ap.user_id = u.id
    WHERE $whereSQL
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Stats ────────────────────────────────────────────────────
$stats = [
    'total'    => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='agent' AND status != 'deleted'")->fetchColumn(),
    'active'   => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='agent' AND status='active'")->fetchColumn(),
    'pending'  => (int)$db->query("SELECT COUNT(*) FROM agent_profiles WHERE verification_status IN ('documents_submitted','phone_verified','kyc_passed')")->fetchColumn(),
    'suspend'  => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='agent' AND status='suspended'")->fetchColumn(),
];

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Agents - KINAS GROUP Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-main { flex: 1; padding: 30px; background: #F5F7FA; }
        .admin-container { max-width: 1400px; margin: 0 auto; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; }
        .admin-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; margin: 0; }
        .admin-header h1 i { color: #C6A43F; margin-right: 12px; }
        .admin-header p { color: #666; font-size: 14px; margin-top: 5px; }
        .search-input { padding: 10px 16px; border: 1px solid #E0E0E0; border-radius: 10px; width: 280px; font-family: 'Inter', sans-serif; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; text-align: center; border: 1.5px solid #C6A43F; transition: all 0.3s; }
        .stat-card:hover { border-color: #C6A43F; box-shadow: 0 8px 24px rgba(198,164,63,0.15); transform: translateY(-3px); }
        .stat-number { font-size: 32px; font-weight: 700; color: #C6A43F; font-family: 'Prata', serif; }
        .stat-label { color: #666; font-size: 13px; margin-top: 5px; }
        .filters-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 25px; background: white; padding: 16px 24px; border-radius: 16px; border: 1px solid #E0E0E0; }
        .filter-group { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .filter-group select { padding: 10px 16px; border: 1px solid #E0E0E0; border-radius: 10px; background: white; cursor: pointer; }
        .btn-filter { padding: 10px 20px; background: #C6A43F; border: none; border-radius: 10px; font-weight: 600; color: #0A0A0A; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.3s; text-decoration: none; }
        .btn-filter:hover { background: #A8882E; }
        .btn-secondary { background: #F5F5F5; color: #333; border: 1px solid #E0E0E0; padding: 10px 16px; border-radius: 10px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; }
        .table-container { background: white; border-radius: 20px; border: 1px solid #E0E0E0; overflow: hidden; }
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 16px 20px; background: #F8F8F8; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; border-bottom: 1px solid #E0E0E0; }
        .data-table td { padding: 16px 20px; border-bottom: 1px solid #E0E0E0; color: #333; font-size: 13px; }
        .data-table tr:hover { background: #F8F8F8; }
        .agent-cell { display: flex; align-items: center; gap: 12px; }
        .agent-avatar { width: 40px; height: 40px; background: #C6A43F; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #0A0A0A; flex-shrink: 0; }
        .agent-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .division-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .division-badge.automobile { background: #E3F2FD; color: #1565C0; }
        .division-badge.realestate { background: #E8F5E9; color: #2E7D32; }
        .division-badge.solar { background: #FFF3E0; color: #F57C00; }
        .division-badge.marketplace { background: #F3E5F5; color: #7B1FA2; }
        .division-badge.none { background: #ECEFF1; color: #607D8B; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-badge.active { background: #E8F5E9; color: #2E7D32; }
        .status-badge.pending { background: #FFF3E0; color: #F57C00; }
        .status-badge.suspended { background: #FEF2F2; color: #DC2626; }
        .status-badge.banned { background: #1A1A1A; color: white; }
        .status-badge.deleted { background: #1A1A1A; color: #fff; text-decoration: line-through; }
        .action-buttons { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        /* Labeled buttons (mirror user-management.php's .act-btn style so
           the icon + text both show, even when the FA CDN is slow/blocked) */
        .action-btn { height: 30px; min-width: 30px; padding: 0 12px; border-radius: 7px; border: none; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 12px; font-weight: 600; font-family: inherit; line-height: 1; }
        .action-btn-label { display: inline-block; }
        .action-btn.view { background: rgba(59,130,246,0.1); color: #3B82F6; }
        .action-btn.edit { background: rgba(198,164,63,0.1); color: #C6A43F; }
        .action-btn.suspend { background: rgba(245,158,11,0.12); color: #B45309; }
        .action-btn.delete { background: rgba(220,38,38,0.12); color: #B91C1C; }
        .action-btn.verify { background: rgba(34,197,94,0.1); color: #22C55E; }
        .action-btn.activate { background: rgba(34,197,94,0.12); color: #15803D; }
        .action-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .action-btn i { font-style: normal; min-width: 14px; text-align: center; }
        .pagination { display: flex; justify-content: center; gap: 8px; padding: 20px; border-top: 1px solid #E0E0E0; }
        .page-btn { padding: 8px 14px; background: white; border: 1px solid #E0E0E0; border-radius: 8px; cursor: pointer; transition: all 0.3s; text-decoration: none; color: #333; font-size: 13px; }
        .page-btn.active, .page-btn:hover:not(:disabled) { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
        .page-btn:disabled { color: #CCC; cursor: not-allowed; }
        .empty-state { padding: 80px 20px; text-align: center; color: #999; }
        .empty-state i { font-size: 48px; color: #C6A43F; opacity: 0.4; display: block; margin-bottom: 14px; }
        .empty-state p { font-size: 14px; }
        .verified-badge { display:inline-block; margin-left:6px; color:#2E7D32; font-size:11px; font-weight:600; }
        .flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
        .flash.success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
        .flash.error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        @media (max-width: 768px) { .admin-main { padding: 20px; } .admin-header { flex-direction: column; align-items: flex-start; } .search-input { width: 100%; } .filters-bar { flex-direction: column; } .filter-group { width: 100%; justify-content: stretch; } .filter-group select { flex: 1; } }
    </style>

</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>
<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main">
    <div class="admin-container">
        <?php if ($flashSuccess): ?><div class="flash success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
        <?php if ($flashError):   ?><div class="flash error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div><?php endif; ?>

        <div class="admin-header">
            <div><h1><i class="fas fa-user-tie"></i>Manage Agents</h1><p>View and manage all registered agents</p></div>
            <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
                <input type="search" name="q" class="search-input" placeholder="Search agents by name or email…" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Search</button>
            </form>
        </div>

        <div class="stats-row">
            <div class="stat-card"><div class="stat-number"><?= number_format($stats['total']) ?></div><div class="stat-label">Total Agents</div></div>
            <div class="stat-card"><div class="stat-number"><?= number_format($stats['active']) ?></div><div class="stat-label">Active</div></div>
            <div class="stat-card"><div class="stat-number"><?= number_format($stats['pending']) ?></div><div class="stat-label">Pending Verification</div></div>
            <div class="stat-card"><div class="stat-number"><?= number_format($stats['suspend']) ?></div><div class="stat-label">Suspended</div></div>
        </div>

        <form class="filters-bar" method="GET">
            <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
            <div class="filter-group">
                <select name="division">
                    <option value="">All Divisions</option>
                    <option value="automobile"  <?= $division === 'automobile'  ? 'selected' : '' ?>>Kinas Automobile</option>
                    <option value="real_estate" <?= $division === 'real_estate' ? 'selected' : '' ?>>Williams Connect Home</option>
                    <option value="solar"       <?= $division === 'solar'       ? 'selected' : '' ?>>Kinas Volt</option>
                    <option value="marketplace" <?= $division === 'marketplace' ? 'selected' : '' ?>>Kinas Marketplace</option>
                </select>
                <select name="status">
                    <option value="">All (non-deleted)</option>
                    <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
                    <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    <option value="banned"    <?= $status === 'banned'    ? 'selected' : '' ?>>Banned</option>
                    <option value="deleted"   <?= $status === 'deleted'   ? 'selected' : '' ?>>Deleted</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="agent-management.php" class="btn-secondary">Reset</a>
            </div>
        </form>

        <div class="table-container">
            <div class="table-responsive">
                <?php if (empty($agents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No agents match the current filter.</p>
                        <?php if (!$search && !$division && !$status): ?>
                            <p style="margin-top:8px; color:#bbb; font-size:12px;">Agents will appear here once they register and select the agent role.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Agent</th><th>Email</th><th>Division</th><th>Listings</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
                    <tbody id="agentTableBody">
                    <?php
                        $divisionLabels = [
                            'automobile'  => ['Kinas Automobile',     'automobile'],
                            'real_estate' => ['Williams Connect Home', 'realestate'],
                            'solar'       => ['Kinas Volt',           'solar'],
                            'marketplace' => ['Kinas Marketplace',    'marketplace'],
                        ];
                        foreach ($agents as $a):
                            $initials = strtoupper(substr($a['name'], 0, 1) . (strpos($a['name'],' ') !== false ? substr($a['name'], strpos($a['name'],' ')+1, 1) : ''));
                            $div = $a['division'] ?? '';
                            $divLabel = $divisionLabels[$div][0] ?? '—';
                            $divClass = $divisionLabels[$div][1] ?? 'none';
                    ?>
                        <tr>
                            <td>
                                <div class="agent-cell">
                                    <div class="agent-avatar"><?= htmlspecialchars($initials) ?></div>
                                    <div>
                                        <strong><?= htmlspecialchars($a['name']) ?></strong>
                                        <?php if (!empty($a['verified'])): ?>
                                            <span class="verified-badge" title="Personal KYC passed"><i class="fas fa-check-circle"></i> Verified</span>
                                        <?php endif; ?>
                                        <?php if (!empty($a['company_name'])): ?>
                                            <br><span style="font-size:11px; color:#999;"><?= htmlspecialchars($a['company_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($a['email']) ?></td>
                            <td><span class="division-badge <?= htmlspecialchars($divClass) ?>"><?= htmlspecialchars($divLabel) ?></span></td>
                            <td><strong><?= (int)$a['listing_count'] ?></strong></td>
                            <td>
                                <span class="status-badge <?= htmlspecialchars($a['status']) ?>"><?= htmlspecialchars(ucfirst($a['status'])) ?></span>
                                <?php
                                    $v = $a['verification_status'] ?? '';
                                    if ($v === 'documents_submitted'): ?>
                                        <br><span style="font-size:10px; color:#F57C00;">Docs pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($a['created_at']))) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($a['status'] === 'suspended' || $a['status'] === 'banned'): ?>
                                        <form method="POST" action="/api/admin/suspend-agent.php" style="display:inline" onsubmit="return confirm('Reactivate this agent?');">
                                            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                            <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" class="action-btn activate" title="Reactivate agent"><i class="fas fa-undo" aria-hidden="true"></i><span class="action-btn-label">Reactivate</span></button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="/api/admin/suspend-agent.php" style="display:inline" onsubmit="return confirm('Suspend this agent? Their listings will be hidden.');">
                                            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                            <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
                                            <input type="hidden" name="action" value="suspend">
                                            <button type="submit" class="action-btn suspend" title="Suspend agent"><i class="fas fa-pause" aria-hidden="true"></i><span class="action-btn-label">Suspend</span></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($a['status'] !== 'deleted' && (int)$a['id'] !== (int)$_SESSION['user_id']): ?>
                                    <form method="POST" action="/api/admin/delete-user.php" style="display:inline" onsubmit="return confirm('Delete this agent? Their account will be deactivated and their active listings hidden. This cannot be undone from the admin UI.');">
                                        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                        <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
                                        <button type="submit" class="action-btn delete" title="Delete agent"><i class="fas fa-trash-alt" aria-hidden="true"></i><span class="action-btn-label">Delete</span></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php
                    $baseQuery = array_diff_key($_GET, ['page' => '']);
                    $pageUrl = fn($p) => ($p <= 1
                        ? '?' . http_build_query($baseQuery)
                        : '?' . http_build_query($baseQuery + ['page' => $p]));
                ?>
                <?php if ($page > 1): ?>
                    <a class="page-btn" href="<?= $pageUrl($page-1) ?>"><i class="fas fa-chevron-left"></i></a>
                <?php else: ?>
                    <button class="page-btn" disabled><i class="fas fa-chevron-left"></i></button>
                <?php endif; ?>

                <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $page): ?>
                        <button class="page-btn active"><?= $i ?></button>
                    <?php else: ?>
                        <a class="page-btn" href="<?= $pageUrl($i) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a class="page-btn" href="<?= $pageUrl($page+1) ?>"><i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <button class="page-btn" disabled><i class="fas fa-chevron-right"></i></button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>

<?php require_once __DIR__ . "/../templates/footer.php"; ?>
