<?php
/**
 * Agent Dashboard - KINAS GROUP
 * Complete agent dashboard with hardware management
 */

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../api/config/database.php';

// Check if user is logged in and is an agent
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$agentId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Agent';

// Get agent stats
$stats = [
    'total_listings' => $db->query("SELECT COUNT(*) FROM solar_listings WHERE agent_id = $agentId AND status = 'active'")->fetchColumn(),
    'total_hardware' => $db->query("SELECT COUNT(*) FROM solar_listings WHERE agent_id = $agentId AND service_type IN ('solar_panel', 'inverter', 'battery', 'charge_controller') AND status = 'active'")->fetchColumn(),
    'total_views' => $db->query("SELECT SUM(views) FROM solar_listings WHERE agent_id = $agentId")->fetchColumn(),
    'pending_verification' => $db->query("SELECT COUNT(*) FROM solar_listings WHERE agent_id = $agentId AND status = 'pending'")->fetchColumn(),
];

// Get recent hardware
$hardware = $db->query("
    SELECT id, title, service_type, brand, capacity_kw, price, warranty_years, status, views, created_at
    FROM solar_listings 
    WHERE agent_id = $agentId 
      AND service_type IN ('solar_panel', 'inverter', 'battery', 'charge_controller')
      AND status = 'active'
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

$pageTitle = 'Agent Dashboard - KINAS VOLT';
include '../templates/header.php';
?>

<div class="je-dash-shell">
    <!-- Sidebar -->
    <aside class="je-dash-sidebar">
        <div class="je-dash-sidebar-brand">
            <i class="fas fa-solar-panel"></i> KINAS VOLT
        </div>
        <ul class="je-dash-nav">
            <li><a href="dashboard.php" class="is-active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="listings.php"><i class="fas fa-list-ul"></i> My Listings</a></li>
            <li><a href="add-listing.php"><i class="fas fa-plus-circle"></i> Add Listing</a></li>
            <li><a href="hardware.php"><i class="fas fa-microchip"></i> Hardware Inventory</a></li>
            <li><a href="add-hardware.php"><i class="fas fa-plus"></i> Add Hardware</a></li>
            <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <hr class="sidebar-divider">
            <li><a href="/"><i class="fas fa-home"></i> Back to Site</a></li>
            <li class="je-dash-signout"><a href="/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="je-dash-main">
        <div class="je-dash-header">
            <div>
                <h1><i class="fas fa-tachometer-alt" style="color: #C6A43F;"></i> Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($userName); ?>!</p>
            </div>
            <div>
                <a href="add-hardware.php" class="je-btn je-btn-gold" style="background: #C6A43F; color: #0A0A0A;">
                    <i class="fas fa-plus"></i> Add Hardware
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="je-card-grid">
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-solar-panel"></i></div>
                <div>
                    <div class="je-stat-label">Total Listings</div>
                    <div class="je-stat-value"><?php echo $stats['total_listings']; ?></div>
                </div>
            </div>
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-microchip"></i></div>
                <div>
                    <div class="je-stat-label">Hardware Items</div>
                    <div class="je-stat-value"><?php echo $stats['total_hardware']; ?></div>
                </div>
            </div>
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-eye"></i></div>
                <div>
                    <div class="je-stat-label">Total Views</div>
                    <div class="je-stat-value"><?php echo number_format($stats['total_views'] ?? 0); ?></div>
                </div>
            </div>
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="je-stat-label">Pending Verification</div>
                    <div class="je-stat-value"><?php echo $stats['pending_verification']; ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 32px;">
            <a href="add-hardware.php" style="background: #1A3A2A; color: white; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-microchip" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>Add Hardware</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">Solar panels, inverters, batteries</p>
            </a>
            <a href="add-listing.php" style="background: #C6A43F; color: #0A0A0A; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-plus-circle" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>Add Listing</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">Services, installations, projects</p>
            </a>
            <a href="listings.php" style="background: #2C3E50; color: white; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-list-ul" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>My Listings</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">View and manage all listings</p>
            </a>
        </div>

        <!-- Recent Hardware -->
        <div class="je-panel">
            <div class="je-panel-header">
                <div class="je-panel-title">
                    <i class="fas fa-microchip" style="color: #C6A43F;"></i> Recent Hardware Inventory
                </div>
                <a href="hardware.php" class="je-btn je-btn-sm je-btn-outline">View All</a>
            </div>
            <div class="je-panel-body">
                <?php if (empty($hardware)): ?>
                    <div class="je-panel-empty">
                        <i class="fas fa-microchip"></i>
                        <p>No hardware items added yet.</p>
                        <a href="add-hardware.php" class="je-btn je-btn-gold" style="margin-top: 12px;">Add Your First Hardware</a>
                    </div>
                <?php else: ?>
                    <table class="je-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Brand</th>
                                <th>Capacity</th>
                                <th>Price</th>
                                <th>Warranty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hardware as $item): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['title']); ?></strong></td>
                                <td><span style="background: #F0F0F0; padding: 2px 8px; border-radius: 4px; font-size: 11px;"><?php echo str_replace('_', ' ', $item['service_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($item['brand']); ?></td>
                                <td><?php echo $item['capacity_kw']; ?> kW</td>
                                <td>₦<?php echo number_format($item['price']); ?></td>
                                <td><?php echo $item['warranty_years']; ?> years</td>
                                <td><span class="je-status is-active">Active</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php include '../templates/footer.php'; ?>
