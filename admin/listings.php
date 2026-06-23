<?php
/**
 * Admin: Listings Management
 * Shows all listings across ALL divisions with filter
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

// Get filter from URL
$filterDivision = isset($_GET['division']) ? $_GET['division'] : 'all';

// Get all listings from ALL divisions
$listings = [];

// Helper function to safely get listings from any table
function getTableListings($db, $tableName, $divisionName) {
    $results = [];
    try {
        $exists = $db->query("SHOW TABLES LIKE '$tableName'")->fetchAll();
        if (empty($exists)) {
            return $results;
        }
        
        $columns = $db->query("DESCRIBE $tableName")->fetchAll();
        $colNames = array_column($columns, 'Field');
        
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
        
        $query = "SELECT $selectSQL FROM $tableName ORDER BY created_at DESC";
        $stmt = $db->query($query);
        $rows = $stmt->fetchAll();
        
        foreach ($rows as $row) {
            $row['division'] = $divisionName;
            $results[] = $row;
        }
        
    } catch (Exception $e) {
        // Silently fail
    }
    return $results;
}

// Get listings from each division
$allListings = [];

if ($filterDivision === 'all' || $filterDivision === 'solar') {
    $solar = getTableListings($db, 'solar_listings', 'solar');
    $allListings = array_merge($allListings, $solar);
}

if ($filterDivision === 'all' || $filterDivision === 'car') {
    $car = getTableListings($db, 'car_listings', 'car');
    $allListings = array_merge($allListings, $car);
}

if ($filterDivision === 'all' || $filterDivision === 'property') {
    $property = getTableListings($db, 'property_listings', 'property');
    $allListings = array_merge($allListings, $property);
}

if ($filterDivision === 'all' || $filterDivision === 'marketplace') {
    $marketplace = getTableListings($db, 'marketplace_listings', 'marketplace');
    $allListings = array_merge($allListings, $marketplace);
}

// Sort all listings by created_at (newest first)
usort($allListings, function($a, $b) {
    $timeA = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
    $timeB = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
    return $timeB - $timeA;
});

// Count listings by division for stats
$divisionCounts = [];
foreach ($allListings as $listing) {
    $div = $listing['division'];
    if (!isset($divisionCounts[$div])) {
        $divisionCounts[$div] = 0;
    }
    $divisionCounts[$div]++;
}

// Division labels and colors
$divConfig = [
    'solar' => ['label' => '☀️ Volt', 'color' => '#FFF3E0', 'text' => '#E65100', 'folder' => 'kinas-volt'],
    'car' => ['label' => '🚗 Automobile', 'color' => '#E3F2FD', 'text' => '#0D47A1', 'folder' => 'kinas-automobile'],
    'property' => ['label' => '🏠 Homes', 'color' => '#E8F5E9', 'text' => '#1B5E20', 'folder' => 'williams-connect-home'],
    'marketplace' => ['label' => '🛍️ Marketplace', 'color' => '#F3E5F5', 'text' => '#4A148C', 'folder' => 'kinas-marketplace']
];

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
            </div>
        </div>

        <!-- Division Stats & Filter -->
        <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; align-items: center;">
            <a href="?division=all" style="padding: 8px 16px; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 13px; 
                background: <?php echo $filterDivision === 'all' ? '#C6A43F' : '#f0f0f0'; ?>; 
                color: <?php echo $filterDivision === 'all' ? '#0A0A0A' : '#333'; ?>;">
                📊 All (<?php echo count($allListings); ?>)
            </a>
            <?php foreach ($divisionCounts as $div => $count): 
                $config = $divConfig[$div] ?? ['label' => ucfirst($div), 'color' => '#f0f0f0', 'text' => '#333'];
            ?>
                <a href="?division=<?php echo $div; ?>" style="padding: 8px 16px; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 13px;
                    background: <?php echo $filterDivision === $div ? $config['color'] : '#f0f0f0'; ?>; 
                    color: <?php echo $filterDivision === $div ? $config['text'] : '#333'; ?>;
                    border: <?php echo $filterDivision === $div ? '2px solid ' . $config['text'] : '1px solid #e0e0e0'; ?>;">
                    <?php echo $config['label']; ?> (<?php echo $count; ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <div class="je-panel">
            <div class="je-panel-body">
                <?php if (empty($allListings)): ?>
                    <div class="je-panel-empty">
                        <i class="fas fa-list-ul"></i>
                        <p>No listings found in this division.</p>
                    </div>
                <?php else: ?>
                    <table class="je-table">
                        <thead>
                            <tr>
                                <th>#</th>
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
                            <?php $counter = 1; foreach ($allListings as $listing): 
                                $config = $divConfig[$listing['division']] ?? ['folder' => $listing['division']];
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><strong><?php echo htmlspecialchars($listing['title']); ?></strong></td>
                                <td>
                                    <?php 
                                    $config = $divConfig[$listing['division']] ?? ['label' => ucfirst($listing['division']), 'color' => '#f0f0f0', 'text' => '#333'];
                                    ?>
                                    <span style="display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; 
                                        background: <?php echo $config['color']; ?>; 
                                        color: <?php echo $config['text']; ?>;">
                                        <?php echo $config['label']; ?>
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
                                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                        <?php 
                                        // Map division to folder for the detail page link
                                        $folderMap = [
                                            'solar' => 'kinas-volt',
                                            'car' => 'kinas-automobile',
                                            'property' => 'williams-connect-home',
                                            'marketplace' => 'kinas-marketplace'
                                        ];
                                        $folder = $folderMap[$listing['division']] ?? $listing['division'];
                                        ?>
                                        <a href="/divisions/<?php echo $folder; ?>/detail.php?id=<?php echo $listing['id']; ?>" 
                                           class="action-btn action-btn-view" target="_blank">
                                            View
                                        </a>
                                        <!-- FIXED: Changed to POST form for delete -->
                                        <form method="POST" action="/api/admin/remove-listing.php" style="display:inline;" 
                                              onsubmit="return confirm('Delete this listing?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                            <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                            <input type="hidden" name="listing_type" value="<?php echo $listing['division']; ?>">
                                            <button type="submit" class="action-btn action-btn-delete" style="border:none;cursor:pointer;">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
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
    white-space: nowrap;
}
.action-btn-view { background: #1565C0; color: white; }
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
.status-badge-removed { background: #FFEBEE; color: #C62828; }
</style>

<?php include '../templates/footer.php'; ?>
