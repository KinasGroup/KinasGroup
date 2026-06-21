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

// 1. Solar listings (KINAS Volt)
try {
    $solar = $db->query("
        SELECT id, title, price, status, views, created_at, 'solar' as division, 
               brand, NULL as beds, NULL as baths, NULL as sqft
        FROM solar_listings 
        ORDER BY created_at DESC
    ")->fetchAll();
    $listings = array_merge($listings, $solar);
} catch (Exception $e) {}

// 2. Car listings (KINAS Automobile)
try {
    $cars = $db->query("
        SELECT id, title, price, status, views, created_at, 'car' as division, 
               brand, NULL as beds, NULL as baths, NULL as sqft
        FROM car_listings 
        ORDER BY created_at DESC
    ")->fetchAll();
    $listings = array_merge($listings, $cars);
} catch (Exception $e) {}

// 3. Property listings (Williams Connect Home)
try {
    $properties = $db->query("
        SELECT id, title, price, status, views, created_at, 'property' as division, 
               brand, beds, baths, sqft
        FROM property_listings 
        ORDER BY created_at DESC
    ")->fetchAll();
    $listings = array_merge($listings, $properties);
} catch (Exception $e) {
    // Try with different column names if beds/baths/sqft don't exist
    try {
        $properties = $db->query("
            SELECT id, title, price, status, views, created_at, 'property' as division, 
                   brand, NULL as beds, NULL as baths, NULL as sqft
            FROM property_listings 
            ORDER BY created_at DESC
        ")->fetchAll();
        $listings = array_merge($listings, $properties);
    } catch (Exception $e2) {}
}

// 4. Marketplace listings (KINAS Marketplace)
try {
    $marketplace = $db->query("
        SELECT id, title, price, status, views, created_at, 'marketplace' as division, 
               brand, NULL as beds, NULL as baths, NULL as sqft
        FROM marketplace_listings 
        ORDER BY created_at DESC
    ")->fetchAll();
    $listings = array_merge($listings, $marketplace);
} catch (Exception $e) {}

// Sort all listings by created_at (newest first)
usort($listings, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
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
            </div>
        </div>

        <!-- Division Stats -->
        <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px;">
            <?php
            $divColors = [
                'solar' => '#FFF3E0',
                'car' => '#E3F2FD',
                'property' => '#E8F5E9',
                'marketplace' => '#F3E5F5'
            ];
            $divLabels = [
                'solar' => '☀️ Volt',
                'car' => '🚗 Automobile',
                'property' => '🏠 Homes',
                'marketplace' => '🛍️ Marketplace'
            ];
            $divTextColors = [
                'solar' => '#E65100',
                'car' => '#0D47A1',
                'property' => '#1B5E20',
                'marketplace' => '#4A148C'
            ];
            foreach ($divisionCounts as $div => $count):
                $color = $divColors[$div] ?? '#f0f0f0';
                $textColor = $divTextColors[$div] ?? '#333';
                $label = $divLabels[$div] ?? ucfirst($div);
            ?>
                <div style="background: <?php echo $color; ?>; padding: 12px 20px; border-radius: 8px; min-width: 120px; text-align: center;">
                    <div style="font-size: 20px; font-weight: 700; color: <?php echo $textColor; ?>;"><?php echo $count; ?></div>
                    <div style="font-size: 12px; color: <?php echo $textColor; ?>;"><?php echo $label; ?></div>
                </div>
            <?php endforeach; ?>
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
                                <th>Details</th>
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
                                        background: <?php echo $divColors[$listing['division']] ?? '#f0f0f0'; ?>; 
                                        color: <?php echo $divTextColors[$listing['division']] ?? '#333'; ?>;">
                                        <?php echo $divLabels[$listing['division']] ?? ucfirst($listing['division']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($listing['division'] === 'property'): ?>
                                        <?php if (!empty($listing['beds'])): ?><?php echo $listing['beds']; ?> beds <?php endif; ?>
                                        <?php if (!empty($listing['baths'])): ?><?php echo $listing['baths']; ?> baths <?php endif; ?>
                                        <?php if (!empty($listing['sqft'])): ?><?php echo $listing['sqft']; ?> sqft <?php endif; ?>
                                        <?php if (!empty($listing['brand'])): ?><?php echo htmlspecialchars($listing['brand']); ?><?php endif; ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($listing['brand'] ?? 'N/A'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>₦<?php echo number_format($listing['price']); ?></td>
                                <td>
                                    <span class="status-badge status-badge-<?php echo $listing['status']; ?>">
                                        <?php echo ucfirst($listing['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($listing['views'] ?? 0); ?></td>
                                <td><?php echo date('M j, Y', strtotime($listing['created_at'])); ?></td>
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
