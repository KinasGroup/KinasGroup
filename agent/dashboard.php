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

// Get agent stats - FIXED: Count ALL active listings
$stats = [
    // Total ALL active listings (including hardware and services)
    'total_listings' => $db->query("
        SELECT COUNT(*) FROM solar_listings 
        WHERE agent_id = $agentId AND status = 'active'
    ")->fetchColumn(),
    
    // Total hardware listings only
    'total_hardware' => $db->query("
        SELECT COUNT(*) FROM solar_listings 
        WHERE agent_id = $agentId 
          AND service_type IN ('solar_panel', 'inverter', 'battery', 'charge_controller', 'mounting_structure')
          AND status = 'active'
    ")->fetchColumn(),
    
    // Total service listings (non-hardware)
    'total_services' => $db->query("
        SELECT COUNT(*) FROM solar_listings 
        WHERE agent_id = $agentId 
          AND service_type NOT IN ('solar_panel', 'inverter', 'battery', 'charge_controller', 'mounting_structure')
          AND status = 'active'
    ")->fetchColumn(),
    
    // Total views across all listings
    'total_views' => $db->query("
        SELECT COALESCE(SUM(views), 0) FROM solar_listings 
        WHERE agent_id = $agentId
    ")->fetchColumn(),
    
    // Pending verification
    'pending_verification' => $db->query("
        SELECT COUNT(*) FROM solar_listings 
        WHERE agent_id = $agentId AND status = 'pending'
    ")->fetchColumn(),
    
    // Inactive/deleted listings
    'inactive_listings' => $db->query("
        SELECT COUNT(*) FROM solar_listings 
        WHERE agent_id = $agentId AND status IN ('inactive', 'removed')
    ")->fetchColumn(),
];

// Get all active hardware
$hardware = $db->query("
    SELECT id, title, service_type, brand, capacity_kw, price, warranty_years, status, views, created_at
    FROM solar_listings 
    WHERE agent_id = $agentId 
      AND service_type IN ('solar_panel', 'inverter', 'battery', 'charge_controller', 'mounting_structure')
      AND status = 'active'
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

// Get all active service listings
$services = $db->query("
    SELECT id, title, service_type, price, status, views, created_at
    FROM solar_listings 
    WHERE agent_id = $agentId 
      AND service_type NOT IN ('solar_panel', 'inverter', 'battery', 'charge_controller', 'mounting_structure')
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
                <div class="je-stat-icon"><i class="fas fa-list-ul"></i></div>
                <div>
                    <div class="je-stat-label">Total Active Listings</div>
                    <div class="je-stat-value"><?php echo $stats['total_listings']; ?></div>
                    <div style="font-size: 11px; color: #999; margin-top: 4px;">
                        <?php echo $stats['total_services']; ?> services · <?php echo $stats['total_hardware']; ?> hardware
                    </div>
                </div>
            </div>
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-microchip"></i></div>
                <div>
                    <div class="je-stat-label">Hardware Items</div>
                    <div class="je-stat-value"><?php echo $stats['total_hardware']; ?></div>
                    <div style="font-size: 11px; color: #999; margin-top: 4px;">Panels, inverters, batteries</div>
                </div>
            </div>
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-eye"></i></div>
                <div>
                    <div class="je-stat-label">Total Views</div>
                    <div class="je-stat-value"><?php echo number_format($stats['total_views']); ?></div>
                </div>
            </div>
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-trash-alt"></i></div>
                <div>
                    <div class="je-stat-label">Inactive/Deleted</div>
                    <div class="je-stat-value"><?php echo $stats['inactive_listings']; ?></div>
                    <div style="font-size: 11px; color: #999; margin-top: 4px;"><?php echo $stats['pending_verification']; ?> pending</div>
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
                <strong>Add Service</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">Services, installations, projects</p>
            </a>
            <a href="listings.php" style="background: #2C3E50; color: white; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-list-ul" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>My Listings</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">View and manage all listings</p>
            </a>
        </div>

        <!-- Recent Activity -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
            <!-- Recent Hardware -->
            <div class="je-panel">
                <div class="je-panel-header">
                    <div class="je-panel-title">
                        <i class="fas fa-microchip" style="color: #C6A43F;"></i> Recent Hardware
                    </div>
                    <a href="hardware.php" class="je-btn je-btn-sm je-btn-outline">View All</a>
                </div>
                <div class="je-panel-body">
                    <?php if (empty($hardware)): ?>
                        <div class="je-panel-empty">
                            <i class="fas fa-microchip"></i>
                            <p>No hardware items added yet.</p>
                        </div>
                    <?php else: ?>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <?php foreach ($hardware as $item): ?>
                            <li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                    <br>
                                    <span style="font-size: 12px; color: #888;"><?php echo $item['brand']; ?> · <?php echo $item['capacity_kw']; ?> kW</span>
                                </div>
                                <div style="text-align: right;">
                                    <span style="color: #C6A43F; font-weight: bold;">₦<?php echo number_format($item['price']); ?></span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Services -->
            <div class="je-panel">
                <div class="je-panel-header">
                    <div class="je-panel-title">
                        <i class="fas fa-list-ul" style="color: #C6A43F;"></i> Recent Services
                    </div>
                    <a href="listings.php" class="je-btn je-btn-sm je-btn-outline">View All</a>
                </div>
                <div class="je-panel-body">
                    <?php if (empty($services)): ?>
                        <div class="je-panel-empty">
                            <i class="fas fa-list-ul"></i>
                            <p>No service listings added yet.</p>
                        </div>
                    <?php else: ?>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <?php foreach ($services as $item): ?>
                            <li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                    <br>
                                    <span style="font-size: 12px; color: #888;"><?php echo str_replace('_', ' ', $item['service_type']); ?></span>
                                </div>
                                <div style="text-align: right;">
                                    <span style="color: #C6A43F; font-weight: bold;">₦<?php echo number_format($item['price']); ?></span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include '../templates/footer.php'; ?>

