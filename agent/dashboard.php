<?php
/**
 * Super Agent Dashboard - KINAS GROUP
 * Shows all divisions: Automobile, Volt, Homes, Marketplace
 * Excludes 'removed' listings from active counts
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

// Get ALL ACTIVE listings across ALL divisions (EXCLUDING 'removed' status)
$totalListings = $db->query("
    SELECT COUNT(*) FROM (
        SELECT id FROM solar_listings WHERE agent_id = $agentId AND status = 'active'
        UNION ALL
        SELECT id FROM car_listings WHERE agent_id = $agentId AND status = 'active'
        UNION ALL
        SELECT id FROM property_listings WHERE agent_id = $agentId AND status = 'active'
        UNION ALL
        SELECT id FROM marketplace_listings WHERE agent_id = $agentId AND status = 'active'
    ) as all_listings
")->fetchColumn();

// Get counts by division (ONLY 'active' status)
$solarCount = $db->query("SELECT COUNT(*) FROM solar_listings WHERE agent_id = $agentId AND status = 'active'")->fetchColumn();
$carCount = $db->query("SELECT COUNT(*) FROM car_listings WHERE agent_id = $agentId AND status = 'active'")->fetchColumn();
$propertyCount = $db->query("SELECT COUNT(*) FROM property_listings WHERE agent_id = $agentId AND status = 'active'")->fetchColumn();
$marketplaceCount = $db->query("SELECT COUNT(*) FROM marketplace_listings WHERE agent_id = $agentId AND status = 'active'")->fetchColumn();

// Get total deleted/removed listings
$deletedCount = $db->query("
    SELECT COUNT(*) FROM (
        SELECT id FROM solar_listings WHERE agent_id = $agentId AND status = 'removed'
        UNION ALL
        SELECT id FROM car_listings WHERE agent_id = $agentId AND status = 'removed'
        UNION ALL
        SELECT id FROM property_listings WHERE agent_id = $agentId AND status = 'removed'
        UNION ALL
        SELECT id FROM marketplace_listings WHERE agent_id = $agentId AND status = 'removed'
    ) as deleted_listings
")->fetchColumn();

// Get inactive listings (not active, not removed)
$inactiveCount = $db->query("
    SELECT COUNT(*) FROM (
        SELECT id FROM solar_listings WHERE agent_id = $agentId AND status = 'inactive'
        UNION ALL
        SELECT id FROM car_listings WHERE agent_id = $agentId AND status = 'inactive'
        UNION ALL
        SELECT id FROM property_listings WHERE agent_id = $agentId AND status = 'inactive'
        UNION ALL
        SELECT id FROM marketplace_listings WHERE agent_id = $agentId AND status = 'inactive'
    ) as inactive_listings
")->fetchColumn();

$pageTitle = 'Super Agent Dashboard - KINAS GROUP';
include '../templates/header.php';
?>

<div class="je-dash-shell">
    <!-- Sidebar -->
    <aside class="je-dash-sidebar">
        <div class="je-dash-sidebar-brand">
            <i class="fas fa-crown"></i> KINAS GROUP
        </div>
        <ul class="je-dash-nav">
            <li><a href="dashboard.php" class="is-active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="listings.php"><i class="fas fa-list-ul"></i> All Listings</a></li>
            <li><a href="add-listing.php"><i class="fas fa-plus-circle"></i> Add Listing</a></li>
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
                <h1><i class="fas fa-tachometer-alt" style="color: #C6A43F;"></i> Super Agent Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($userName); ?>! (Agent ID: <?php echo $agentId; ?>)</p>
            </div>
        </div>

        <!-- Overall Stats -->
        <div class="je-card-grid">
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-list-ul"></i></div>
                <div>
                    <div class="je-stat-label">Total Active Listings</div>
                    <div class="je-stat-value"><?php echo $totalListings; ?></div>
                    <div style="font-size: 11px; color: #999; margin-top: 4px;">
                        Across all divisions
                    </div>
                </div>
            </div>
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-trash-alt"></i></div>
                <div>
                    <div class="je-stat-label">Deleted</div>
                    <div class="je-stat-value"><?php echo $deletedCount; ?></div>
                    <div style="font-size: 11px; color: #999; margin-top: 4px;">
                        <?php echo $inactiveCount; ?> inactive
                    </div>
                </div>
            </div>
        </div>

        <!-- Divisions Grid -->
        <h2 style="font-family: 'Prata', serif; margin: 32px 0 20px 0; color: #0A0A0A;">
            <i class="fas fa-cubes" style="color: #C6A43F;"></i> Your Active Listings by Division
        </h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;">
            <!-- KINAS Automobile -->
            <div style="background: #fff; border: 1px solid #E0E0E0; border-radius: 12px; padding: 24px; text-align: center;">
                <div style="font-size: 40px; margin-bottom: 8px;">🚗</div>
                <div style="font-size: 32px; font-weight: 700; color: #C6A43F;"><?php echo $carCount; ?></div>
                <div style="font-size: 14px; color: #666;">KINAS Automobile</div>
                <a href="listings.php?division=car" style="display: inline-block; margin-top: 12px; padding: 4px 16px; border: 1px solid #C6A43F; border-radius: 20px; color: #C6A43F; text-decoration: none; font-size: 12px;">View</a>
            </div>

            <!-- KINAS Volt -->
            <div style="background: #fff; border: 1px solid #E0E0E0; border-radius: 12px; padding: 24px; text-align: center;">
                <div style="font-size: 40px; margin-bottom: 8px;">☀️</div>
                <div style="font-size: 32px; font-weight: 700; color: #C6A43F;"><?php echo $solarCount; ?></div>
                <div style="font-size: 14px; color: #666;">KINAS Volt</div>
                <a href="listings.php?division=solar" style="display: inline-block; margin-top: 12px; padding: 4px 16px; border: 1px solid #C6A43F; border-radius: 20px; color: #C6A43F; text-decoration: none; font-size: 12px;">View</a>
            </div>

            <!-- Williams Connect Home -->
            <div style="background: #fff; border: 1px solid #E0E0E0; border-radius: 12px; padding: 24px; text-align: center;">
                <div style="font-size: 40px; margin-bottom: 8px;">🏠</div>
                <div style="font-size: 32px; font-weight: 700; color: #C6A43F;"><?php echo $propertyCount; ?></div>
                <div style="font-size: 14px; color: #666;">Williams Connect Home</div>
                <a href="listings.php?division=property" style="display: inline-block; margin-top: 12px; padding: 4px 16px; border: 1px solid #C6A43F; border-radius: 20px; color: #C6A43F; text-decoration: none; font-size: 12px;">View</a>
            </div>

            <!-- KINAS Marketplace -->
            <div style="background: #fff; border: 1px solid #E0E0E0; border-radius: 12px; padding: 24px; text-align: center;">
                <div style="font-size: 40px; margin-bottom: 8px;">🛍️</div>
                <div style="font-size: 32px; font-weight: 700; color: #C6A43F;"><?php echo $marketplaceCount; ?></div>
                <div style="font-size: 14px; color: #666;">KINAS Marketplace</div>
                <a href="listings.php?division=marketplace" style="display: inline-block; margin-top: 12px; padding: 4px 16px; border: 1px solid #C6A43F; border-radius: 20px; color: #C6A43F; text-decoration: none; font-size: 12px;">View</a>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <a href="add-listing.php" style="background: #C6A43F; color: #0A0A0A; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-plus-circle" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>Add Listing</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">Across any division</p>
            </a>
            <a href="listings.php" style="background: #1A3A2A; color: white; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-list-ul" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>All Listings</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">View and manage all</p>
            </a>
            <a href="analytics.php" style="background: #2C3E50; color: white; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-chart-bar" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>Analytics</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">Performance insights</p>
            </a>
       
