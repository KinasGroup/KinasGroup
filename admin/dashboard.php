<?php
/**
 * Admin Dashboard - KINAS GROUP
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
$adminName = $_SESSION['user_name'] ?? 'Admin';

// Get admin stats
$stats = [
    'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_agents' => $db->query("SELECT COUNT(*) FROM users WHERE user_role = 'agent'")->fetchColumn(),
    'total_listings' => $db->query("
        SELECT COUNT(*) FROM (
            SELECT id FROM solar_listings WHERE status = 'active'
            UNION ALL
            SELECT id FROM car_listings WHERE status = 'active'
            UNION ALL
            SELECT id FROM property_listings WHERE status = 'active'
            UNION ALL
            SELECT id FROM marketplace_listings WHERE status = 'active'
        ) as all_listings
    ")->fetchColumn(),
    'pending_verifications' => $db->query("SELECT COUNT(*) FROM agent_profiles WHERE verification_status = 'pending'")->fetchColumn(),
];

$pageTitle = 'Admin Dashboard - KINAS GROUP';
include '../templates/header.php';
?>

<div class="je-dash-shell">
    <!-- Sidebar -->
    <aside class="je-dash-sidebar">
        <div class="je-dash-sidebar-brand">
            <i class="fas fa-crown"></i> KINAS GROUP
        </div>
        <ul class="je-dash-nav">
            <!-- Main Navigation -->
            <li><a href="dashboard.php" class="is-active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="agents.php"><i class="fas fa-user-tie"></i> Agents</a></li>
            <li><a href="listings.php"><i class="fas fa-list-ul"></i> Listings</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            
            <!-- Featured Management Section -->
            <li class="je-dash-nav-heading">FEATURED MANAGEMENT</li>
            <li><a href="test-featured.php"><i class="fas fa-chart-line"></i> Test Algorithm</a></li>
            <li><a href="update-featured.php"><i class="fas fa-sync-alt"></i> Update Featured</a></li>
            
            <!-- Footer Links -->
            <li class="je-dash-nav-divider"></li>
            <li><a href="/"><i class="fas fa-home"></i> Back to Site</a></li>
            <li class="je-dash-signout"><a href="/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="je-dash-main">
        <div class="je-dash-header">
            <div>
                <h1><i class="fas fa-tachometer-alt" style="color: #C6A43F;"></i> Admin Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($adminName); ?>!</p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="je-card-grid">
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-users"></i></div>
                <div>
                    <div class="je-stat-label">Total Users</div>
                    <div class="je-stat-value"><?php echo $stats['total_users']; ?></div>
                </div>
            </div>
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-user-tie"></i></div>
                <div>
                    <div class="je-stat-label">Total Agents</div>
                    <div class="je-stat-value"><?php echo $stats['total_agents']; ?></div>
                </div>
            </div>
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-list-ul"></i></div>
                <div>
                    <div class="je-stat-label">Total Listings</div>
                    <div class="je-stat-value"><?php echo $stats['total_listings']; ?></div>
                </div>
            </div>
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="je-stat-label">Pending Verifications</div>
                    <div class="je-stat-value"><?php echo $stats['pending_verifications']; ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 32px;">
            <a href="test-featured.php" style="background: #1A3A2A; color: white; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-chart-line" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>Test Algorithm</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">Preview featured scores</p>
            </a>
            <a href="update-featured.php" style="background: #C6A43F; color: #0A0A0A; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-sync-alt" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>Update Featured</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">Run algorithm & update</p>
            </a>
            <a href="listings.php" style="background: #2C3E50; color: white; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-list-ul" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>All Listings</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">Manage all listings</p>
            </a>
        </div>
    </main>
</div>

<?php include '../templates/footer.php'; ?>
