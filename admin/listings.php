<?php
/**
 * Admin: Listings Management (New)
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
$allListings = [];

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
    min-width: 700px !important;
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

@media (max-width: 768px) {
    .je-dash-main { padding: 10px !important; }
    .je-table th, .je-table td { padding: 8px 8px; font-size: 11px; }
    .action-btn { font-size: 9px; padding: 3px 6px; }
    .je-table th:nth-child(1), .je-table td:nth-child(1) { display: none; }
}
@media (max-width: 480px) {
    .je-table th:nth-child(5), .je-table td:nth-child(5) { display: none; }
    .je-table th:nth-child(7), .je-table td:nth-child(7) { display: none; }
}
</style>

<div class="je-dash-shell" style="max-width:100%;overflow-x:hidden;">
    <?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>

    <main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">
        <div class="je-dash-header" style="flex-wrap: wrap;">
            <div>
                <h1><i class="fas fa-list-ul" style="color: #C6A43F;"></i> Listings Management</h1>
                <p>Manage all listings across all divisions</p>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="je-banner is-success">
                <i class="je-banner-icon fas fa-check-circle"></i>
                <div class="je-banner-body">
                    <div class="je-banner-text"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="je-banner is-danger">
                <i class="je-banner-icon fas fa-exclamation-circle"></i>
                <div class="je-banner-body">
                    <div class="je-banner-text"><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Division Stats & Filter -->
        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; align-items: center;">
            <a href="?division=all" style="padding: 6px 14px; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 12px; 
                background: <?php echo $filterDivision === 'all' ? '#C6A43F' : '#f0f0f0'; ?>; 
                color: <?php echo $filterDivision === 'all' ? '#0A0A0A' : '#333'; ?>;">
                📊 All (<?php echo count($allListings); ?>)
            </a>
            <?php foreach ($divisionCounts as $div => $count): 
                $config = $divConfig[$div] ?? ['label' => ucfirst($div), 'color' => '#f0f0f0', 'text' => '#333'];
            ?>
                <a href="?division=<?php echo $div; ?>" style="padding: 6px 14px; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 12px;
                    background: <?php echo $filterDivision === $div ? $config['color'] : '#f0f0f0'; ?>; 
                    color: <?php echo $filterDivision === $div ? $config['text'] : '#333'; ?>;
                    border: <?php echo $filterDivision === $div ? '2px solid ' . $config['text'] : '1px solid #e0e0e0'; ?>;">
                    <?php echo $config['label']; ?> (<?php echo $count; ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <div class="je-panel" style="overflow-x: hidden;">
            <div class="je-panel-body" style="overflow-x: hidden;">
                <?php if (empty($allListings)): ?>
                    <div class="je-panel-empty">
                        <i class="fas fa-list-ul"></i>
                        <p>No listings found in this division.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%;">
                    <table class="je-table" style="min-width: 700px; width: 100%;">
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
                                        <!-- Delete button with CSRF token -->
                                        <a href="delete-listing.php?id=<?php echo $listing['id']; ?>&division=<?php echo $listing['division']; ?>&csrf_token=<?php echo Security::generateCSRFToken(); ?>" 
                                           class="action-btn action-btn-delete" 
                                           onclick="return confirm('Delete this listing?')">
                                            Delete
                                        </a>
                                    </div>
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

<?php include '../templates/footer.php'; ?>
