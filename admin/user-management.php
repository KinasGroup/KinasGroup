<?php
/**
 * KINAS GROUP — User Management (Live DB)
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireAdmin();

$db  = Database::getInstance()->getConnection();
$msg = '';

// ── Handle status change ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = ['type'=>'error','text'=>'Invalid security token.'];
    } else {
        $uid    = (int)($_POST['user_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($uid && $uid !== (int)$_SESSION['user_id']) {
            $map = ['activate'=>'active','suspend'=>'suspended','ban'=>'banned'];
            if (isset($map[$action])) {
                $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([$map[$action], $uid]);
                Security::logActivity($_SESSION['user_id'], "user_{$action}d", "User $uid status set to {$map[$action]}");
                $msg = ['type'=>'success','text'=>"User status updated to {$map[$action]}."];
            }
        }
    }
}

// ── Filters ───────────────────────────────────────────────────
$role   = $_GET['role']   ?? 'all';
$status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$limit  = 20; $offset = ($page-1)*$limit;

$where = ["1=1"];
$params = [];
if ($role !== 'all')   { $where[] = "role = ?";   $params[] = $role; }
if ($status !== 'all') { $where[] = "status = ?";  $params[] = $status; }
if ($search)           { $where[] = "(name LIKE ? OR email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereSQL = implode(' AND ', $where);

$total = (int)$db->prepare("SELECT COUNT(*) FROM users WHERE $whereSQL")->execute($params) ? $db->prepare("SELECT COUNT(*) FROM users WHERE $whereSQL")->execute($params) && 0 : 0;
$countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $whereSQL"); $countStmt->execute($params); $total = $countStmt->fetchColumn();
$usersStmt = $db->prepare("SELECT * FROM users WHERE $whereSQL ORDER BY created_at DESC LIMIT $limit OFFSET $offset"); $usersStmt->execute($params);
$users = $usersStmt->fetchAll();
$totalPages = max(1, ceil($total/$limit));

// Summary stats
$sTotal   = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$sAgents  = $db->query("SELECT COUNT(*) FROM users WHERE role='agent' AND status='active'")->fetchColumn();
$sPending = $db->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();
$sSuspend = $db->query("SELECT COUNT(*) FROM users WHERE status='suspended'")->fetchColumn();

$csrf = Security::generateCSRFToken();
$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>User Management - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#F5F7FA}
        .admin-layout{display:flex;min-height:100vh}.admin-main{flex:1;padding:30px;background:#F5F7FA}
        .page-header{margin-bottom:24px}.page-header h1{font-family:'Prata',serif;font-size:28px;color:#0A0A0A}
        .page-header p{color:#666;font-size:14px;margin-top:4px}
        .flash{padding:14px 20px;border-radius:10px;margin-bottom:18px;font-size:14px;font-weight:500}
        .flash.success{background:#E8F5E9;color:#2E7D32}.flash.error{background:#FEF2F2;color:#DC2626}
        .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px}
        .stat-card{background:white;border-radius:14px;padding:20px;text-align:center;border:1px solid #E0E0E0}
        .stat-number{font-size:28px;font-weight:700;color:#C6A43F;font-family:'Prata',serif}.stat-label{color:#666;font-size:12px;margin-top:4px}
        .filters-bar{background:white;border-radius:14px;padding:16px 20px;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;border:1px solid #E0E0E0}
        .filters-bar input,.filters-bar select{padding:9px 14px;border:1px solid #E0E0E0;border-radius:8px;font-family:'Inter',sans-serif;font-size:13px}
        .filters-bar input{flex:1;min-width:200px}.btn-filter{padding:9px 18px;background:#C6A43F;border:none;border-radius:8px;font-weight:600;color:#0A0A0A;cursor:pointer}
        .table-container{background:white;border-radius:16px;border:1px solid #E0E0E0;overflow:hidden}
        .table-responsive{overflow-x:auto}
        .data-table{width:100%;border-collapse:collapse}
        .data-table th{text-align:left;padding:14px 16px;background:#F8F8F8;font-size:11px;text-transform:uppercase;color:#666;font-weight:600;border-bottom:1px solid #E0E0E0}
        .data-table td{padding:14px 16px;border-bottom:1px solid #E0E0E0;font-size:13px;color:#333;vertical-align:middle}
        .data-table tr:hover{background:#FAFAFA}
        .user-cell{display:flex;align-items:center;gap:10px}
        .user-avatar{width:36px;height:36px;border-radius:50%;background:#C6A43F;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#0A0A0A;flex-shrink:0}
        .role-badge,.status-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600}
        .role-badge.admin{background:#F3E5F5;color:#7B1FA2}.role-badge.agent{background:#E3F2FD;color:#1565C0}.role-badge.user{background:#E8F5E9;color:#2E7D32}
        .status-badge.active{background:#E8F5E9;color:#2E7D32}.status-badge.pending{background:#FFF3E0;color:#F57C00}.status-badge.suspended,.status-badge.banned{background:#FEF2F2;color:#DC2626}
        .email-verified-badge,.email-unverified-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap}
        .email-verified-badge{background:#E8F5E9;color:#2E7D32}
        .email-unverified-badge{background:#FFF3E0;color:#F57C00}
        .action-btns{display:flex;gap:6px;flex-wrap:wrap}
        .act-btn{height:30px;min-width:30px;padding:0 12px;border-radius:7px;border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:12px;font-weight:600;font-family:inherit;transition:all .2s;line-height:1}
        .act-btn.activate{background:#E8F5E9;color:#2E7D32}.act-btn.suspend{background:#FFF3E0;color:#F57C00}.act-btn.ban{background:#FEF2F2;color:#DC2626}
        .act-btn:hover{transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,0,0,0.08)}
        .act-btn-label{display:inline-block}
        /* If the FA CDN is blocked the <i> shows up empty — the
           button still works because the label is there. */
        .act-btn i{font-style:normal;min-width:14px;text-align:center}
        .pagination{display:flex;justify-content:center;gap:6px;padding:18px;border-top:1px solid #E0E0E0}
        .page-btn{padding:7px 12px;border:1px solid #E0E0E0;border-radius:7px;background:white;color:#333;text-decoration:none;font-size:13px;transition:all .2s}
        .page-btn:hover,.page-btn.active{background:#C6A43F;border-color:#C6A43F;color:#0A0A0A}
        @media(max-width:768px){.admin-main{padding:20px}.data-table th:nth-child(4),.data-table td:nth-child(4),.data-table th:nth-child(7),.data-table td:nth-child(7){display:none}}
    </style>

</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>
<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main">
<main class="je-dash-main">
    <div class="page-header">
        <h1><i class="fas fa-users" style="color:#C6A43F;margin-right:10px"></i>User Management</h1>
        <p>Manage all platform users, roles, and account status</p>
    </div>

    <?php if ($msg): ?><div class="flash <?= $msg['type'] ?>"><?= htmlspecialchars($msg['text']) ?></div><?php endif; ?>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-number"><?= number_format($sTotal) ?></div><div class="stat-label">Total Users</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($sAgents) ?></div><div class="stat-label">Active Agents</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($sPending) ?></div><div class="stat-label">Pending</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($sSuspend) ?></div><div class="stat-label">Suspended</div></div>
    </div>

    <form method="GET" class="filters-bar">
        <input type="text" name="search" placeholder="Search name or email…" value="<?= htmlspecialchars($search) ?>">
        <select name="role">
            <option value="all" <?= $role==='all'?'selected':'' ?>>All Roles</option>
            <option value="admin" <?= $role==='admin'?'selected':'' ?>>Admin</option>
            <option value="agent" <?= $role==='agent'?'selected':'' ?>>Agent</option>
            <option value="user" <?= $role==='user'?'selected':'' ?>>User</option>
        </select>
        <select name="status">
            <option value="all" <?= $status==='all'?'selected':'' ?>>All Status</option>
            <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
            <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
            <option value="suspended" <?= $status==='suspended'?'selected':'' ?>>Suspended</option>
            <option value="banned" <?= $status==='banned'?'selected':'' ?>>Banned</option>
        </select>
        <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
    </form>

    <div class="table-container">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Division</th><th>Status</th><th>Email</th><th>Joined</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="8" style="text-align:center;padding:40px;color:#999">No users match the current filter.</td></tr>
                <?php else: ?>
                <?php foreach ($users as $u):
                    $initials = strtoupper(substr($u['name'],0,1) . (strpos($u['name'],' ')!==false ? substr($u['name'],strpos($u['name'],' ')+1,1) : ''));
                ?>
                <tr>
                    <td><div class="user-cell"><div class="user-avatar"><?= htmlspecialchars($initials) ?></div><strong><?= htmlspecialchars($u['name']) ?></strong></div></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="role-badge <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td><?= htmlspecialchars($u['division'] ?? '—') ?></td>
                    <td><span class="status-badge <?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
                    <td>
                        <?php if (!empty($u['email_verified_at'])): ?>
                            <span class="email-verified-badge" title="Verified <?= htmlspecialchars(date('M j, Y H:i', strtotime($u['email_verified_at']))) ?>">
                                <i class="fas fa-check-circle" style="color:#2E7D32"></i> Verified
                            </span>
                        <?php else: ?>
                            <span class="email-unverified-badge" title="This user has not clicked the verification link in their email">
                                <i class="fas fa-exclamation-triangle" style="color:#F57C00"></i> Unverified
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <form method="POST" style="display:contents">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <div class="action-btns">
                                <?php if ($u['status'] !== 'active'): ?>
                                <button class="act-btn activate" name="action" value="activate" title="Activate user"><i class="fas fa-check" aria-hidden="true"></i><span class="act-btn-label">Activate</span></button>
                                <?php endif; ?>
                                <?php if ($u['status'] !== 'suspended'): ?>
                                <button class="act-btn suspend" name="action" value="suspend" title="Suspend user"><i class="fas fa-pause" aria-hidden="true"></i><span class="act-btn-label">Suspend</span></button>
                                <?php endif; ?>
                                <?php if ($u['status'] !== 'banned' && $u['role'] !== 'admin'): ?>
                                <button class="act-btn ban" name="action" value="ban" title="Ban user" onclick="return confirm('Ban this user?')"><i class="fas fa-ban" aria-hidden="true"></i><span class="act-btn-label">Ban</span></button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($p=1;$p<=$totalPages;$p++): ?>
            <a class="page-btn<?= $p===$page?' active':'' ?>" href="?page=<?= $p ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

</main>
</div>

</main>
</div>

<?php require_once __DIR__ . "/../templates/footer.php"; ?>
