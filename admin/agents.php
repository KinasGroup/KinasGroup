<?php
/**
 * Admin: Agents Management
 */

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../api/config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Check for success/error messages
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Get all agents (users with role = 'agent')
$agents = $db->query("
    SELECT 
        u.id, 
        u.email, 
        u.status, 
        u.created_at, 
        u.verified,
        u.role,
        a.verification_status as agent_verification,
        a.id as profile_id
    FROM users u
    LEFT JOIN agent_profiles a ON u.id = a.user_id
    WHERE u.role = 'agent'
    ORDER BY u.created_at DESC
")->fetchAll();

// Super Agent email that should always show as verified
$superAgentEmail = 'listing@kinas-group.com';

$pageTitle = 'Agents Management - Admin';
include '../templates/header.php';
?>

<!-- ============================================================
     RESPONSIVE FIX - Added container and responsive styles
     ============================================================ -->
<style>
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
.table-responsive {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    width: 100% !important;
}
.je-table {
    min-width: 600px !important;
    width: 100% !important;
}
.action-btn {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    margin: 2px;
    border: none;
    cursor: pointer;
}
.action-btn-suspend { background: #F57C00; color: white; }
.action-btn-activate { background: #2E7D32; color: white; }
.action-btn-delete { background: #C62828; color: white; }
.action-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}
.status-badge-active { background: #E8F5E9; color: #1B5E20; }
.status-badge-suspended { background: #FFF3E0; color: #E65100; }
.status-badge-pending { background: #FFF8E1; color: #F57F17; }
.status-badge-verified { background: #E8F5E9; color: #1B5E20; }
.status-badge-unverified { background: #FFEBEE; color: #C62828; }

@media (max-width: 768px) {
    .je-dash-main { padding: 10px !important; }
    .je-table th, .je-table td { padding: 8px 10px; font-size: 12px; }
    .action-btn { font-size: 10px; padding: 3px 8px; }
}
@media (max-width: 480px) {
    .je-table th:nth-child(1), .je-table td:nth-child(1) { display: none; }
    .je-table th:nth-child(4), .je-table td:nth-child(4) { display: none; }
}
</style>

<div class="je-dash-shell" style="max-width:100%;overflow-x:hidden;">
    <?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>

    <main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">
        <div class="je-dash-header" style="flex-wrap: wrap;">
            <div>
                <h1><i class="fas fa-user-tie" style="color: #C6A43F;"></i> Agents Management</h1>
                <p>Manage all registered agents</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="je-banner is-success">
                <i class="je-banner-icon fas fa-check-circle"></i>
                <div class="je-banner-body">
                    <div class="je-banner-text"><?php echo htmlspecialchars($success); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="je-banner is-danger">
                <i class="je-banner-icon fas fa-exclamation-circle"></i>
                <div class="je-banner-body">
                    <div class="je-banner-text"><?php echo htmlspecialchars($error); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="je-panel" style="overflow-x: hidden;">
            <div class="je-panel-body" style="overflow-x: hidden;">
                <?php if (empty($agents)): ?>
                    <div class="je-panel-empty">
                        <i class="fas fa-user-tie"></i>
                        <p>No agents found.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%;">
                    <table class="je-table" style="min-width: 600px; width: 100%;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Agent Verification</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agents as $agent): ?>
                            <tr>
                                <td><?php echo $agent['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($agent['email']); ?></strong></td>
                                <td>
                                    <span class="status-badge status-badge-<?php echo $agent['status']; ?>">
                                        <?php echo ucfirst($agent['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    // Super Agent (listing@kinas-group.com) should always show as verified
                                    if ($agent['email'] === $superAgentEmail) {
                                        echo '<span class="status-badge status-badge-verified">✅ Verified (Super Agent)</span>';
                                    } elseif ($agent['agent_verification'] === 'verified') {
                                        echo '<span class="status-badge status-badge-verified">✅ Verified</span>';
                                    } elseif ($agent['agent_verification'] === 'pending') {
                                        echo '<span class="status-badge status-badge-pending">⏳ Pending</span>';
                                    } else {
                                        echo '<span class="status-badge status-badge-unverified">❌ Unverified</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($agent['created_at'])); ?></td>
                                <td>
                                    <?php if ($agent['email'] === $superAgentEmail): ?>
                                        <span style="color: #999; font-size: 11px;">(Super Agent)</span>
                                    <?php else: ?>
                                        <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                            <?php if ($agent['status'] === 'active'): ?>
                                                <button onclick="suspendAgent(<?php echo $agent['id']; ?>, this)" 
                                                        class="action-btn action-btn-suspend">
                                                    Suspend
                                                </button>
                                            <?php elseif ($agent['status'] === 'suspended'): ?>
                                                <button onclick="activateAgent(<?php echo $agent['id']; ?>, this)" 
                                                        class="action-btn action-btn-activate">
                                                    Activate
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="deleteAgent(<?php echo $agent['id']; ?>, this)" 
                                                    class="action-btn action-btn-delete">
                                                Delete
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
// ============================================================
// SUSPEND AGENT WITH CUSTOM CONFIRMATION
// ============================================================
function suspendAgent(agentId, button) {
    jeConfirm(
        'Suspend this agent? They will not be able to list or manage listings. This can be reversed.',
        'Suspend Agent',
        'warning'
    ).then(function(confirmed) {
        if (!confirmed) return;
        
        button.disabled = true;
        button.innerHTML = '⏳';
        window.location.href = 'suspend-agent.php?id=' + agentId;
    }).catch(function(error) {
        console.error('Confirmation error:', error);
        button.disabled = false;
        button.innerHTML = 'Suspend';
    });
}

// ============================================================
// ACTIVATE AGENT WITH CUSTOM CONFIRMATION
// ============================================================
function activateAgent(agentId, button) {
    jeConfirm(
        'Activate this agent? They will be able to list and manage listings again.',
        'Activate Agent',
        'warning'
    ).then(function(confirmed) {
        if (!confirmed) return;
        
        button.disabled = true;
        button.innerHTML = '⏳';
        window.location.href = 'activate-agent.php?id=' + agentId;
    }).catch(function(error) {
        console.error('Confirmation error:', error);
        button.disabled = false;
        button.innerHTML = 'Activate';
    });
}

// ============================================================
// DELETE AGENT WITH CUSTOM CONFIRMATION
// ============================================================
function deleteAgent(agentId, button) {
    jeConfirm(
        'Delete this agent? This will permanently remove their account and all their listings. This cannot be undone.',
        'Delete Agent',
        'danger'
    ).then(function(confirmed) {
        if (!confirmed) return;
        
        button.disabled = true;
        button.innerHTML = '⏳';
        window.location.href = 'delete-agent.php?id=' + agentId;
    }).catch(function(error) {
        console.error('Confirmation error:', error);
        button.disabled = false;
        button.innerHTML = 'Delete';
    });
}
</script>

<?php include '../templates/footer.php'; ?>
