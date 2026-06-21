<?php
/**
 * Admin: Listings Management
 * Shows all listings across ALL divisions
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

// Get all listings from ALL divisions
$listings = [];

// Helper function to safely get listings from any table
function getTableListings($db, $tableName, $divisionName) {
    $results = [];
    try {
        // First, check if table exists
        $exists = $db->query("SHOW TABLES LIKE '$tableName'")->fetchAll();
        if (empty($exists)) {
            return $results;
        }
        
        // Get column names
        $columns = $db->query("DESCRIBE $tableName")->fetchAll();
        $colNames = array_column($columns, 'Field');
        
        // Build query based on available columns
        $selectFields = ['id', 'title', 'price', 'status'];
        if (in_array('views', $colNames)) $selectFields[] = 'views';
        if (in_array('created_at', $colNames)) $selectFields[] = 'created_at';
        if (in_array('brand', $colNames)) $selectFields[] = 'brand';
        if (in_array('beds', $colNames)) $selectFields[] = 'beds';
        if (in_array('baths', $colNames)) $selectFields[] = 'baths';
        if (in_array('sqft', $colNames)) $selectFields[] = 'sqft';
        if (in_array('city', $colNames)) $selectFields[] = 'city';
        if (in_array('state', $colNames)) $selectFields[] = 'state';
        if (in_array('agent_id', $colNames)) $selectFields[] = 'agent_id';
        
        $selectSQL = implode(', ', $selectFields);
        
        // Get ALL listings regardless of status (not just 'active')
        $query = "SELECT $selectSQL FROM $tableName ORDER BY created_at DESC";
        $stmt = $db->query($query);
        $rows = $stmt->fetchAll();
        
        foreach ($rows as $row) {
            $row['division'] = $divisionName;
            $results[] = $row;
        }
        
    } catch (Exception $e) {
        // Silently fail for missing tables
    }
    return $results;
}

// Get listings from each division (ALL statuses)
$solar = getTableListings($db, 'solar_listings', 'solar');
$listings = array_merge($listings, $solar);

$car = getTableListings($db, 'car_listings', 'car');
$listings = array_merge($listings, $car);

$property = getTableListings($db, 'property_listings', 'property');
$listings = array_merge($listings, $property);

$marketplace = getTableListings($db, 'marketplace_listings', 'marketplace');
$listings = array_merge($listings, $marketplace);

// Sort all listings by created_at (newest first)
usort($listings, function($a, $b) {
    $timeA = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
    $timeB = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
    return $timeB - $timeA;
});

// Count listings by division for stats
$divisionCounts = [];
foreach ($listings as $listing) {
    $div = $listing['division'];
    if (!isset($divisionCounts[$div])) {
        $divisionCounts[$div] = 0;
    }
    $divisionCounts[$div]++;
}

$pageTitle = 'Listings Management - Admin';
include '../templates/header.php';
?>

<div class="je-dash-shell">
    <aside class="je-dash-sidebar">
        <div class="je-dash-sidebar-brand">
            <i class="fas fa-crown"></i> KINAS GROUP
        </div>
        <ul class="je-dash-nav">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="agents.php"><i class="fas fa-user-tie"></i> Agents</a></li>
            <li><a href="listings.php" class="is-active"><i class="fas fa-list-ul"></i> Listings</a></li>
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
                <h1><i class="fas fa-list-ul" style="color: #C6A43F;"></i> Listings Management</h1>
                <p>Manage all listings across all divisions</p>
                <p style="font-size: 12px; color: #888; margin-top: 4px;">
                    Total listings: <?php echo count($listings); ?>
                    <?php foreach ($divisionCounts as $div => $count): ?>
                        • <?php echo ucfirst($div); ?>: <?php echo $count; ?>
                    <?php endforeach; ?>
                </p>
            </div>
        </div>

        <div class="je-panel">
            <div class="je-panel-body">
                <?php if (empty($listings)): ?>
                    <div class="je-panel-empty">
                        <i class="fas fa-list-ul"></i>
                        <p>No listings found across any division.</p>
                    </div>
                <?php else: ?>
                    <table class="je-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Division</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Views</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listings as $listing): ?>
                            <tr>
                                <td><?php echo $listing['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($listing['title']); ?></strong></td>
                                <td>
                                    <span style="display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; 
                                        background: <?php echo $listing['division'] === 'solar' ? '#FFF3E0' : ($listing['division'] === 'car' ? '#E3F2FD' : ($listing['division'] === 'property' ? '#E8F5E9' : '#F3E5F5')); ?>; 
                                        color: <?php echo $listing['division'] === 'solar' ? '#E65100' : ($listing['division'] === 'car' ? '#0D47A1' : ($listing['division'] === 'property' ? '#1B5E20' : '#4A148C')); ?>;">
                                        <?php 
                                        $labels = ['solar' => '☀️ Volt', 'car' => '🚗 Automobile', 'property' => '🏠 Homes', 'marketplace' => '🛍️ Marketplace'];
                                        echo $labels[$listing['division']] ?? ucfirst($listing['division']); 
                                        ?>
                                    </span>
                                </td>
                                <td>₦<?php echo number_format($listing['price']); ?></td>
                                <td>
                                    <span class="status-badge status-badge-<?php echo $listing['status']; ?>">
                                        <?php echo ucfirst($listing['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($listing['views'] ?? 0); ?></td>
                                <td><?php echo isset($listing['created_at']) ? date('M j, Y', strtotime($listing['created_at'])) : 'N/A'; ?></td>
                                <td>
                                    <a href="delete-listing.php?id=<?php echo $listing['id']; ?>&division=<?php echo $listing['division']; ?>" 
                                       class="action-btn action-btn-delete" 
                                       onclick="return confirm('Delete this listing?')">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
.action-btn-delete { background: #C62828; color: white; }
.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}
.status-badge-active { background: #E8F5E9; color: #1B5E20; }
.status-badge-inactive { background: #FFF3E0; color: #E65100; }
.status-badge-pending { background: #FFF8E1; color: #F57F17; }
.status-badge-flagged { background: #FFEBEE; color: #C62828; }
</style>

<?php include '../templates/footer.php'; ?>
