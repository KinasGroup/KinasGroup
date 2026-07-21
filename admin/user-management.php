<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * KINAS GROUP — User Management (Live DB)
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireAdmin();

$db  = Database::getInstance()->getConnection();
$msg = '';

// Human-readable division labels — keys match agent_profiles.division /
// the DIVISION_* constants used by api/listings/create.php.
$divisionLabels = [
    'kinas-automobile'       => 'Automobile',
    'williams-connect-home'  => 'Real Estate',
    'kinas-volt'             => 'Solar',
    'kinas-marketplace'      => 'Marketplace',
];

// Read any flash message left by a redirect-based action (e.g. delete-user.php).
if (!empty($_SESSION['flash_success'])) { $msg = ['type'=>'success','text'=>$_SESSION['flash_success']]; unset($_SESSION['flash_success']); }
elseif (!empty($_SESSION['flash_error'])) { $msg = ['type'=>'error','text'=>$_SESSION['flash_error']]; unset($_SESSION['flash_error']); }
elseif (!empty($_SESSION['flash_info'])) { $msg = ['type'=>'info','text'=>$_SESSION['flash_info']]; unset($_SESSION['flash_info']); }

// ── Handle status / verification changes ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = ['type'=>'error','text'=>'Please refresh the page and try again.'];
    } else {
        $uid    = (int)($_POST['user_id'] ?? 0);
        $action = $_POST['action'] ?? '';

        if ($uid && $uid !== (int)$_SESSION['user_id']) {
            $statusMap = ['activate'=>'active','suspend'=>'suspended','ban'=>'banned'];

            if (isset($statusMap[$action])) {
                $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([$statusMap[$action], $uid]);
                Security::logActivity($_SESSION['user_id'], "user_{$action}d", "User $uid status set to {$statusMap[$action]}");
                $msg = ['type'=>'success','text'=>"User status updated to {$statusMap[$action]}."];

            } elseif ($action === 'verify' || $action === 'unverify') {
                // Manual admin override of the agent's KYC/KYB verification
                // tiers — bypasses the Didit flow entirely. Only meaningful
                // for agents (agent_profiles rows).
                $chk = $db->prepare("SELECT role FROM users WHERE id=?");
                $chk->execute([$uid]);
                $targetRole = $chk->fetchColumn();

                if ($targetRole !== 'agent') {
                    $msg = ['type'=>'error','text'=>'Only agents have a verification tier to change.'];
                } elseif ($action === 'verify') {
                    $db->prepare("
                        UPDATE agent_profiles
                        SET verification_status='approved',
                            kyb_status='approved',
                            kyc_decision_at=COALESCE(kyc_decision_at, NOW()),
                            kyb_decision_at=COALESCE(kyb_decision_at, NOW())
                        WHERE user_id=?
                    ")->execute([$uid]);
                    // A manual admin pass counts as clearing every prerequisite
                    // step too, so the badge doesn't contradict itself (e.g.
                    // "approved" in the DB but still showing as Unverified
                    // because phone was never OTP-confirmed).
                    $db->prepare("UPDATE users SET phone_verified_at = COALESCE(phone_verified_at, NOW()) WHERE id=?")->execute([$uid]);
                    Security::logActivity($_SESSION['user_id'], 'agent_manually_verified', "Admin manually verified agent #$uid (all steps marked approved)");
                    $msg = ['type'=>'success','text'=>'Agent manually verified — all steps marked approved.'];
                } else {
                    $db->prepare("
                        UPDATE agent_profiles
                        SET verification_status='pending', kyb_status='not_started'
                        WHERE user_id=?
                    ")->execute([$uid]);
                    Security::logActivity($_SESSION['user_id'], 'agent_manually_unverified', "Admin reset verification for agent #$uid");
                    $msg = ['type'=>'success','text'=>"Agent's verification has been reset to pending."];
                }
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

$where = ["u.status != 'deleted'"];   // soft-deleted users are hidden by default
$params = [];
if ($role !== 'all')   { $where[] = "u.role = ?";   $params[] = $role; }
if ($status !== 'all') { $where[] = "u.status = ?";  $params[] = $status; }
if ($search)           { $where[] = "(u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereSQL = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE $whereSQL"); $countStmt->execute($params); $total = $countStmt->fetchColumn();

// LEFT JOIN agent_profiles: division/verification/KYB live there, not on
// users — a plain "SELECT * FROM users" (as this page used to run) can
// never show an agent's division or real verification tier.
$usersStmt = $db->prepare("
    SELECT u.*,
           ap.division AS agent_division,
           ap.verification_status AS agent_verification_status,
           ap.kyb_status AS agent_kyb_status
    FROM users u
    LEFT JOIN agent_profiles ap ON ap.user_id = u.id
    WHERE $whereSQL
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
");
$usersStmt->execute($params);
$users = $usersStmt->fetchAll();
$totalPages = max(1, ceil($total/$limit));

// Summary stats
$sTotal   = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$sAgents  = $db->query("SELECT COUNT(*) FROM users WHERE role='agent' AND status='active'")->fetchColumn();
$sPending = $db->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();
$sSuspend = $db->query("SELECT COUNT(*) FROM users WHERE status='suspended'")->fetchColumn();

$csrf = Security::generateCSRFToken();
$headerDepth = '../';
$pageTitle = 'User Management - KINAS GROUP';
require_once __DIR__ . '/../templates/header.php';
?>

<!-- ============================================================
     RESPONSIVE FIX - Added container and viewport styles
     ============================================================ -->
<style>
    *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#F5F7FA}
    .admin-layout{display:flex;min-height:100vh}.admin-main{flex:1;padding:30px;background:#F5F7FA}
    .page-header{margin-bottom:24px}.page-header h1{font-family:'Prata',serif;font-size:28px;color:#0A0A0A}
    .page-header p{color:#666;font-size:14px;margin-top:4px}
    .flash{padding:14px 20px;border-radius:10px;margin-bottom:18px;font-size:14px;font-weight:500}
    .flash.success{background:#E8F5E9;color:#2E7D32}.flash.error{background:#FEF2F2;color:#DC2626}
    .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px}
    .stat-card{background:white;border-radius:14px;padding:20px;text-align:center;border:1.5px solid #C6A43F;transition:all .3s}
    .stat-card:hover{border-color:#C6A43F;box-shadow:0 8px 24px rgba(198,164,63,0.15);transform:translateY(-3px)}
    .stat-number{font-size:28px;font-weight:700;color:#C6A43F;font-family:'Prata',serif}.stat-label{color:#666;font-size:12px;margin-top:4px}
    .filters-bar{background:white;border-radius:14px;padding:16px 20px;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;border:1px solid #E0E0E0}
    .filters-bar .search-input-wrap{flex:1;min-width:200px;display:flex;align-items:center;gap:10px;border:1px solid #E0E0E0;border-radius:8px;padding:0 14px;background:#fff}
    .filters-bar .search-input-wrap:focus-within{border-color:#C6A43F;box-shadow:0 0 0 3px rgba(198,164,63,0.12)}
    .filters-bar .search-input-wrap i{color:#C6A43F;font-size:14px;flex-shrink:0}
    .filters-bar .search-input-wrap input{flex:1;border:none;outline:none;padding:9px 0;font-family:'Inter',sans-serif;font-size:13px;background:transparent}
    .filters-bar .search-input-wrap input::placeholder{color:#aaa}
    .filters-bar select{padding:9px 14px;border:1px solid #E0E0E0;border-radius:8px;font-family:'Inter',sans-serif;font-size:13px}
    .btn-filter{padding:9px 18px;background:#C6A43F;border:none;border-radius:8px;font-weight:600;color:#0A0A0A;cursor:pointer}
    .table-container{background:white;border-radius:16px;border:1px solid #E0E0E0;overflow:hidden;width:100%}
    .table-responsive{overflow-x:auto;-webkit-overflow-scrolling:touch;width:100%}
    .data-table{width:100%;border-collapse:collapse;min-width:700px}
    .data-table th{text-align:left;padding:14px 16px;background:#F8F8F8;font-size:11px;text-transform:uppercase;color:#666;font-weight:600;border-bottom:1px solid #E0E0E0}
    .data-table td{padding:14px 16px;border-bottom:1px solid #E0E0E0;font-size:13px;color:#333;vertical-align:middle}
    .data-table tr:hover{background:#FAFAFA}
    .user-cell{display:flex;align-items:center;gap:10px}
    .user-avatar{width:36px;height:36px;border-radius:50%;background:#C6A43F;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#0A0A0A;flex-shrink:0}
    .role-badge,.status-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600}
    .role-badge.admin{background:#F3E5F5;color:#7B1FA2}.role-badge.agent{background:#E3F2FD;color:#1565C0}.role-badge.user{background:#E8F5E9;color:#2E7D32}
    .status-badge.active{background:#E8F5E9;color:#2E7D32}.status-badge.pending{background:#FFF3E0;color:#F57C00}.status-badge.suspended,.status-badge.banned{background:#FEF2F2;color:#DC2626}.status-badge.deleted{background:#1A1A1A;color:#fff}
    .email-verified-badge,.email-unverified-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap}
    .email-verified-badge{background:#E8F5E9;color:#2E7D32}
    .email-unverified-badge{background:#FFF3E0;color:#F57C00}
    /* Verification column — 3 tiers:
       complete = green,  all 4 steps done (or admin manually passed an
                  agent who'd already cleared steps 1-3)
       partial  = orange, steps 1-3 done (email+phone+KYC) but KYB
                  (business verification) not yet approved
       none     = red, hasn't even cleared steps 1-3 yet */
    .verify-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap}
    .verify-badge.complete{background:#E8F5E9;color:#2E7D32}
    .verify-badge.partial{background:#FFF3E0;color:#B8860B}
    .verify-badge.none{background:#FEF2F2;color:#DC2626}
    .act-btn.verify{background:#FFF3E0;color:#B8860B}.act-btn.unverify{background:#F3F4F6;color:#555}
    .action-btns{display:flex;gap:6px;flex-wrap:wrap}
    .act-btn{height:30px;min-width:30px;padding:0 12px;border-radius:7px;border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:12px;font-weight:600;font-family:inherit;transition:all .2s;line-height:1}
    .act-btn.activate{background:#E8F5E9;color:#2E7D32}.act-btn.suspend{background:#FFF3E0;color:#F57C00}.act-btn.ban{background:#FEF2F2;color:#DC2626}.act-btn.delete{background:#FEE2E2;color:#B91C1C}
    .act-btn:hover{transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,0,0,0.08)}
    .act-btn-label{display:inline-block}
    .act-btn i{font-style:normal;min-width:14px;text-align:center}
    .pagination{display:flex;justify-content:center;gap:6px;padding:18px;border-top:1px solid #E0E0E0;flex-wrap:wrap}
    .page-btn{padding:7px 12px;border:1px solid #E0E0E0;border-radius:7px;background:white;color:#333;text-decoration:none;font-size:13px;transition:all .2s}
    .page-btn:hover,.page-btn.active{background:#C6A43F;border-color:#C6A43F;color:#0A0A0A}

    /* ============================================================
       RESPONSIVE FIXES - Added
       ============================================================ */
    .je-dash-shell {
        max-width: 100% !important;
        overflow-x: hidden !important;
    }
    .je-dash-main {
        overflow-x: hidden !important;
        width: 100% !important;
        max-width: 100% !important;
        padding: 15px !important;
    }
    .je-container {
        max-width: 100% !important;
        overflow-x: hidden !important;
        padding: 0 10px !important;
    }

    @media(max-width:768px){
        .admin-main{padding:15px}
        .data-table th:nth-child(4),.data-table td:nth-child(4),
        .data-table th:nth-child(7),.data-table td:nth-child(7){display:none}
        .filters-bar{flex-direction:column;align-items:stretch}
        .filters-bar input{min-width:auto;width:100%}
        .filters-bar select{width:100%}
        .stats-row{grid-template-columns:1fr 1fr;gap:10px}
        .stat-card{padding:14px}
        .stat-number{font-size:22px}
        .page-header h1{font-size:22px}
        .act-btn-label{display:none}
        .act-btn{padding:0 10px;min-width:36px}
        .data-table td{padding:10px 8px;font-size:12px}
        .data-table th{padding:10px 8px;font-size:10px}
        .user-avatar{width:28px;height:28px;font-size:10px}
        .action-btns{flex-wrap:nowrap}
    }

    @media(max-width:480px){
        .admin-main{padding:10px}
        .stats-row{grid-template-columns:1fr 1fr;gap:8px}
        .stat-card{padding:10px}
        .stat-number{font-size:18px}
        .data-table th:nth-child(3),.data-table td:nth-child(3){display:none}
        .data-table th:nth-child(5),.data-table td:nth-child(5){display:none}
        .pagination .page-btn{padding:5px 8px;font-size:11px}
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
    .flash.success { background: #E8F5E9 !important; color: #2E7D32 !important; }
    .flash.error { background: #FEF2F2 !important; color: #DC2626 !important; }
    .stat-card { background: white !important; }
    .stat-card:hover { border-color: #C6A43F !important; }
    .stat-number { color: #C6A43F !important; }
    .stat-label { color: #666 !important; }
    .filters-bar { background: white !important; }
    .filters-bar .search-input-wrap { background: #fff !important; }
    .filters-bar .search-input-wrap:focus-within { border-color: #C6A43F !important; }
    .filters-bar .search-input-wrap i { color: #C6A43F !important; }
    .filters-bar .search-input-wrap input::placeholder { color: #aaa !important; }
    .btn-filter { background: #C6A43F !important; color: #0A0A0A !important; }
    .table-container { background: white !important; }
    .data-table th { background: #F8F8F8 !important; color: #666 !important; }
    .data-table td { color: #333 !important; }
    .data-table tr:hover { background: #FAFAFA !important; }
    .user-avatar { background: #C6A43F !important; color: #0A0A0A !important; }
    .role-badge.admin { background: #F3E5F5 !important; color: #7B1FA2 !important; }
    .role-badge.agent { background: #E3F2FD !important; color: #1565C0 !important; }
    .role-badge.user { background: #E8F5E9 !important; color: #2E7D32 !important; }
    .status-badge.active { background: #E8F5E9 !important; color: #2E7D32 !important; }
    .status-badge.pending { background: #FFF3E0 !important; color: #F57C00 !important; }
    .status-badge.suspended,.status-badge.banned { background: #FEF2F2 !important; color: #DC2626 !important; }
    .status-badge.deleted { background: #1A1A1A !important; color: #fff !important; }
    .email-verified-badge { background: #E8F5E9 !important; color: #2E7D32 !important; }
    .email-unverified-badge { background: #FFF3E0 !important; color: #F57C00 !important; }
    .verify-badge.complete { background: #E8F5E9 !important; color: #2E7D32 !important; }
    .verify-badge.partial { background: #FFF3E0 !important; color: #B8860B !important; }
    .verify-badge.none { background: #FEF2F2 !important; color: #DC2626 !important; }
    .act-btn.verify { background: #FFF3E0 !important; color: #B8860B !important; }
    .act-btn.unverify { background: #F3F4F6 !important; color: #555 !important; }
    .act-btn.activate { background: #E8F5E9 !important; color: #2E7D32 !important; }
    .act-btn.suspend { background: #FFF3E0 !important; color: #F57C00 !important; }
    .act-btn.ban { background: #FEF2F2 !important; color: #DC2626 !important; }
    .act-btn.delete { background: #FEE2E2 !important; color: #B91C1C !important; }
    .page-btn { background: white !important; color: #333 !important; }
    .page-btn:hover,.page-btn.active { background: #C6A43F !important; border-color: #C6A43F !important; color: #0A0A0A !important; }
}
</style>

<div class="je-dash-shell" style="max-width:100%;overflow-x:hidden;">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">
    <div class="page-header">
        <h1><i class="fas fa-users" style="color:#C6A43F;margin-right:10px"></i>User Management</h1>
        <p>Manage all platform users, roles, and account status</p>
    </div>

    <?php if ($msg): ?><div class="flash <?= $msg['type'] === 'info' ? 'success' : $msg['type'] ?>"><?= htmlspecialchars($msg['text']) ?></div><?php endif; ?>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-number"><?= number_format($sTotal) ?></div><div class="stat-label">Total Users</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($sAgents) ?></div><div class="stat-label">Active Agents</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($sPending) ?></div><div class="stat-label">Pending</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($sSuspend) ?></div><div class="stat-label">Suspended</div></div>
    </div>

    <form method="GET" class="filters-bar">
        <div class="search-input-wrap">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Search name or email…" value="<?= htmlspecialchars($search) ?>">
        </div>
        <select name="role">
            <option value="all" <?= $role==='all'?'selected':'' ?>>All Roles</option>
            <option value="admin" <?= $role==='admin'?'selected':'' ?>>Admin</option>
            <option value="agent" <?= $role==='agent'?'selected':'' ?>>Agent</option>
            <option value="user" <?= $role==='user'?'selected':'' ?>>User</option>
        </select>
        <select name="status">
            <option value="all" <?= $status==='all'?'selected':'' ?>>All (non-deleted)</option>
            <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
            <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
            <option value="suspended" <?= $status==='suspended'?'selected':'' ?>>Suspended</option>
            <option value="banned" <?= $status==='banned'?'selected':'' ?>>Banned</option>
            <option value="deleted" <?= $status==='deleted'?'selected':'' ?>>Deleted</option>
        </select>
        <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
    </form>

    <div class="table-container">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Division</th><th>Status</th><th>Verification</th><th>Joined</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="8" style="text-align:center;padding:40px;color:#999">No users match the current filter.</td></tr>
                <?php else: ?>
                <?php foreach ($users as $u):
                    $initials = strtoupper(substr($u['name'],0,1) . (strpos($u['name'],' ')!==false ? substr($u['name'],strpos($u['name'],' ')+1,1) : ''));
                    $isAgent       = $u['role'] === 'agent';
                    $emailVerified = !empty($u['email_verified_at']);
                    // Steps 1–3 of the agent wizard: email (implicit — you
                    // can't reach phone/KYC without a verified account),
                    // phone OTP, and Didit KYC (personal identity).
                    $kycPassed = in_array($u['agent_verification_status'] ?? '', ['kyc_passed','documents_submitted','approved'], true);
                    $stepsOneToThreeDone = $isAgent && !empty($u['phone_verified_at']) && $kycPassed;
                    // Step 4: Didit KYB (business verification), OR an admin
                    // manually pushing a steps-1-3 agent over the line.
                    $kybApproved = ($u['agent_kyb_status'] ?? '') === 'approved';

                    if ($isAgent) {
                        $fullyVerified     = $stepsOneToThreeDone && $kybApproved;   // green
                        $partiallyVerified = $stepsOneToThreeDone && !$kybApproved;  // orange
                    } else {
                        // Regular users have no KYC/KYB wizard — email is
                        // their only verification signal.
                        $fullyVerified     = $emailVerified;
                        $partiallyVerified = false;
                    }
                ?>
                <tr>
                    <td><div class="user-cell"><div class="user-avatar"><?= htmlspecialchars($initials) ?></div><strong><?= htmlspecialchars($u['name']) ?></strong><?php if (!empty($u['duplicate_flag_reason'])): ?> <i class="fas fa-user-friends" style="color:#F57C00;" title="Possible duplicate account: <?= htmlspecialchars($u['duplicate_flag_reason']) ?>"></i><?php endif; ?></div></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="role-badge <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td><?= $isAgent ? htmlspecialchars($divisionLabels[$u['agent_division']] ?? $u['agent_division'] ?? '—') : '—' ?></td>
                    <td><span class="status-badge <?= $u['status'] ?>" title="Account moderation state — not related to verification. Active = not suspended/banned."><?= ucfirst($u['status']) ?></span></td>
                    <td>
                        <?php if ($fullyVerified): ?>
                            <span class="verify-badge complete" title="<?= $isAgent ? 'All 4 steps complete: email, phone, Didit KYC, and Didit KYB.' : 'Email verified' ?>">
                                <i class="fas fa-check-circle" style="color:#2E7D32"></i> Verified
                            </span>
                        <?php elseif ($partiallyVerified): ?>
                            <span class="verify-badge partial" title="Steps 1–3 complete (email, phone, Didit KYC). Business verification (KYB) still pending.">
                                <i class="fas fa-award" style="color:#B8860B"></i> Verified
                            </span>
                        <?php else: ?>
                            <span class="verify-badge none" title="<?= $isAgent ? 'Has not completed phone verification and Didit KYC yet.' : 'This user has not clicked the verification link in their email.' ?>">
                                <i class="fas fa-exclamation-triangle" style="color:#DC2626"></i> Unverified
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <div class="action-btns">
                            <form method="POST" style="display:contents">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <?php if ($u['status'] !== 'active'): ?>
                                <button class="act-btn activate" name="action" value="activate" title="Activate user"><i class="fas fa-check" aria-hidden="true"></i><span class="act-btn-label">Activate</span></button>
                                <?php endif; ?>
                                <?php if ($u['status'] !== 'suspended' && $u['status'] !== 'deleted'): ?>
                                <button class="act-btn suspend" name="action" value="suspend" title="Suspend user"><i class="fas fa-pause" aria-hidden="true"></i><span class="act-btn-label">Suspend</span></button>
                                <?php endif; ?>
                            </form>
                            <?php if ($isAgent): ?>
                                <?php if ($fullyVerified): ?>
                                <form method="POST" style="display:inline" data-kinas-confirm="Reset this agent's verification back to pending? They'll lose their verified status and won't be able to list until re-verified." data-kinas-title="Unverify Agent" data-kinas-label="Unverify" data-kinas-variant="warning" data-kinas-icon="fa-user-times">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="act-btn unverify" name="action" value="unverify" title="Manually revoke this agent's verification"><i class="fas fa-user-times" aria-hidden="true"></i><span class="act-btn-label">Unverify</span></button>
                                </form>
                                <?php else: ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="act-btn verify" name="action" value="verify" title="<?= $partiallyVerified ? 'Manually approve business verification (KYB) to complete this agent\'s verification' : 'Manually approve all steps (phone, KYC, KYB) for this agent' ?>"><i class="fas fa-check-circle" aria-hidden="true"></i><span class="act-btn-label">Verify</span></button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($u['status'] !== 'deleted' && $u['id'] !== (int)$_SESSION['user_id']): ?>
                            <form method="POST" action="/api/admin/delete-user.php" style="display:inline" data-kinas-confirm="Delete this user? Their account will be deactivated<?= $isAgent ? ' and any active listings removed' : '' ?>." data-kinas-title="Delete User" data-kinas-warning="This is a permanent, irreversible action.">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="act-btn delete" title="Delete user"><i class="fas fa-trash-alt" aria-hidden="true"></i><span class="act-btn-label">Delete</span></button>
                            </form>
                            <?php endif; ?>
                        </div>
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

<?php require_once __DIR__ . "/../templates/footer.php"; ?>
