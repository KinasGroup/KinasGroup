<?php
/**
 * Admin: Users Management
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

// Get all users
$users = $db->query("
    SELECT id, email, role, status, verified, created_at 
    FROM users 
    ORDER BY created_at DESC
")->fetchAll();

$pageTitle = 'Users Management - Admin';
include '../templates/header.php';
?>

<div class="je-dash-shell">
    <aside class="je-dash-sidebar">
        <div class="je-dash-sidebar-brand">
            <i class="fas fa-crown"></i> KINAS GROUP
        </div>
        <ul class="je-dash-nav">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php" class="is-active"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="agents.php"><i class="fas fa-user-tie"></i> Agents</a></li>
            <li><a href="listings.php"><i class="fas fa-list-ul"></i> Listings</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li class="je-dash-nav-heading">FEATURED MANAGEMENT</li>
            <li><a href="test-featured.php"><i class="fas fa-chart-line"></i> Test Algorithm</a></li>
            <li><a href="update-featured.php"><i class="fas fa-sync-alt"></i> Update Featured</a></li>
            <li class="je-dash-nav-divider"></li>
            <li><a href="/"><i class="fas fa-home"></i> Back to Site</a></li>
            <li class="je-dash-signout"><a href="/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a></li>
        </ul>
    </aside>

    <main class="je-dash-main">
        <div class="je-dash-header">
            <div>
                <h1><i class="fas fa-users" style="color: #C6A43F;"></i> Users Management</h1>
                <p>Manage all registered users</p>
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

        <div class="je-panel">
            <div class="je-panel-body">
                <?php if (empty($users)): ?>
                    <div class="je-panel-empty">
                        <i class="fas fa-users"></i>
                        <p>No users found.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="je-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Verified</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($user['email']); ?></strong></td>
                                <td><span class="status-badge status-badge-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                <td><span class="status-badge status-badge-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                <td><?php echo $user['verified'] ? '✅ Yes' : '❌ No'; ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <?php if ($user['status'] === 'active'): ?>
                                            <a href="suspend-user.php?id=<?php echo $user['id']; ?>" 
                                               class="action-btn action-btn-suspend" 
                                               onclick="return confirm('Suspend this user? They will not be able to log in.')">
                                                Suspend
                                            </a>
                                        <?php elseif ($user['status'] === 'suspended'): ?>
                                            <a href="activate-user.php?id=<?php echo $user['id']; ?>" 
                                               class="action-btn action-btn-activate" 
                                               onclick="return confirm('Activate this user? They will be able to log in again.')">
                                                Activate
                                            </a>
                                        <?php endif; ?>
                                        <a href="delete-user.php?id=<?php echo $user['id']; ?>" 
                                           class="action-btn action-btn-delete" 
                                           onclick="return confirm('Delete this user? This will also remove all their listings.')">
                                            Delete
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 11px;">(You)</span>
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

<style>
.action-btn {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    margin: 2px;
}
.action-btn-suspend { background: #F57C00; color: white; }
.action-btn-activate { background: #2E7D32; color: white; }
.action-btn-delete { background: #C62828; color: white; }
.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}
.status-badge-user { background: #E3F2FD; color: #0D47A1; }
.status-badge-agent { background: #FFF3E0; color: #E65100; }
.status-badge-admin { background: #E8F5E9; color: #1B5E20; }
.status-badge-active { background: #E8F5E9; color: #1B5E20; }
.status-badge-pending { background: #FFF8E1; color: #F57F17; }
.status-badge-suspended { background: #FFF3E0; color: #E65100; }
.status-badge-banned { background: #FFEBEE; color: #C62828; }
</style>

<?php include '../templates/footer.php'; ?>
