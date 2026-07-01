<?php
/**
 * Agent Dashboard - All Listings
 * Shows all listings across all divisions with proper action buttons
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
$division = isset($_GET['division']) ? $_GET['division'] : 'all';

// Build query based on division filter
$listings = [];

if ($division === 'all' || $division === 'solar') {
    $solar = $db->query("
        SELECT id, title, 'solar' as division, service_type as type, price, status, views, created_at
        FROM solar_listings 
        WHERE agent_id = $agentId AND status != 'removed'
        ORDER BY created_at DESC
    ")->fetchAll();
    $listings = array_merge($listings, $solar);
}

if ($division === 'all' || $division === 'car') {
    $cars = $db->query("
        SELECT id, title, 'car' as division, 'vehicle' as type, price, status, views, created_at
        FROM car_listings 
        WHERE agent_id = $agentId AND status != 'removed'
        ORDER BY created_at DESC
    ")->fetchAll();
    $listings = array_merge($listings, $cars);
}

if ($division === 'all' || $division === 'property') {
    $properties = $db->query("
        SELECT id, title, 'property' as division, 'property' as type, price, status, views, created_at
        FROM property_listings 
        WHERE agent_id = $agentId AND status != 'removed'
        ORDER BY created_at DESC
    ")->fetchAll();
    $listings = array_merge($listings, $properties);
}

if ($division === 'all' || $division === 'marketplace') {
    $marketplace = $db->query("
        SELECT id, title, 'marketplace' as division, 'product' as type, price, status, views, created_at
        FROM marketplace_listings 
        WHERE agent_id = $agentId AND status != 'removed'
        ORDER BY created_at DESC
    ")->fetchAll();
    $listings = array_merge($listings, $marketplace);
}

// Sort by created_at descending
usort($listings, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Division folder mapping for detail page links
$folderMap = [
    'solar' => 'kinas-volt',
    'car' => 'kinas-automobile',
    'property' => 'williams-connect-home',
    'marketplace' => 'kinas-marketplace'
];

$pageTitle = 'My Listings - Agent Dashboard';
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
.action-btn-group {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
    transition: all 0.2s ease;
    line-height: 1.4;
    min-height: 28px;
}
.action-btn i { font-size: 11px; }
.action-btn-view { background: #1565C0; color: #FFFFFF !important; }
.action-btn-view:hover { background: #0D47A1; color: #FFFFFF !important; transform: translateY(-1px); }
.action-btn-edit { background: #F57C00; color: #FFFFFF !important; }
.action-btn-edit:hover { background: #E65100; color: #FFFFFF !important; transform: translateY(-1px); }
.action-btn-delete { background: #C62828; color: #FFFFFF !important; }
.action-btn-delete:hover { background: #B71C1C; color: #FFFFFF !important; transform: translateY(-1px); }
.action-btn-restore { background: #2E7D32; color: #FFFFFF !important; }
.action-btn-restore:hover { background: #1B5E20; color: #FFFFFF !important; transform: translateY(-1px); }
.division-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}
.division-badge-solar { background: #FFF3E0; color: #E65100; }
.division-badge-car { background: #E3F2FD; color: #0D47A1; }
.division-badge-property { background: #E8F5E9; color: #1B5E20; }
.division-badge-marketplace { background: #F3E5F5; color: #4A148C; }
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
.je-table td { vertical-align: middle; padding: 10px 12px; }
.empty-state { text-align: center; padding: 60px 20px; color: #666; }
.empty-state i { font-size: 48px; color: #C6A43F; margin-bottom: 16px; display: block; }
.empty-state p { margin: 8px 0; }
.empty-state a { color: #C6A43F; text-decoration: none; font-weight: 600; }
.empty-state a:hover { text-decoration: underline; }
.filter-bar {
    background: #fff;
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    border: 1px solid #E0E0E0;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.filter-bar .filter-label {
    font-weight: 600;
    font-size: 14px;
    color: #333;
}
.filter-bar .filter-link {
    padding: 6px 16px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 13px;
    transition: all 0.2s;
}
.filter-bar .filter-link.active { background: #C6A43F; color: #0A0A0A; }
.filter-bar .filter-link.inactive { background: #f0f0f0; color: #333; }
.filter-bar .filter-link.inactive:hover { background: #e0e0e0; }

@media (max-width: 768px) {
    .je-dash-main { padding: 10px !important; }
    .je-table th, .je-table td { padding: 8px 8px; font-size: 11px; }
    .action-btn { font-size: 9px; padding: 3px 6px; min-height: 24px; }
    .action-btn i { font-size: 9px; }
    .filter-bar { flex-wrap: wrap; gap: 6px; padding: 12px 14px; }
    .filter-bar .filter-link { font-size: 11px; padding: 4px 10px; }
    .je-table th:nth-child(1), .je-table td:nth-child(1) { display: none; }
    .je-table th:nth-child(4), .je-table td:nth-child(4) { display: none; }
}
@media (max-width: 480px) {
    .je-table th:nth-child(5), .je-table td:nth-child(5) { display: none; }
    .je-table th:nth-child(7), .je-table td:nth-child(7) { display: none; }
    .action-btn-group { flex-wrap: nowrap; }
}
</style>

<div class="je-dash-shell" style="max-width:100%;overflow-x:hidden;">
    <?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>

    <main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">
        <div class="je-dash-header" style="flex-wrap: wrap;">
            <div>
                <h1><i class="fas fa-list-ul" style="color: #C6A43F;"></i> My Listings</h1>
                <p>Manage all your listings across all divisions</p>
            </div>
            <div>
                <a href="add-listing.php" class="je-btn je-btn-gold" style="background: #C6A43F; color: #0A0A0A;">
                    <i class="fas fa-plus"></i> Add Listing
                </a>
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

        <!-- Filter Bar -->
        <div class="filter-bar">
            <span class="filter-label">Filter:</span>
            <a href="listings.php?division=all" class="filter-link <?php echo $division === 'all' ? 'active' : 'inactive'; ?>">All</a>
            <a href="listings.php?division=solar" class="filter-link <?php echo $division === 'solar' ? 'active' : 'inactive'; ?>">☀️ Solar</a>
            <a href="listings.php?division=car" class="filter-link <?php echo $division === 'car' ? 'active' : 'inactive'; ?>">🚗 Automobile</a>
            <a href="listings.php?division=property" class="filter-link <?php echo $division === 'property' ? 'active' : 'inactive'; ?>">🏠 Homes</a>
            <a href="listings.php?division=marketplace" class="filter-link <?php echo $division === 'marketplace' ? 'active' : 'inactive'; ?>">🛍️ Marketplace</a>
        </div>

        <!-- Listings Table -->
        <div class="je-panel" style="overflow-x: hidden;">
            <div class="je-panel-body" style="overflow-x: hidden;">
                <?php if (empty($listings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-list-ul"></i>
                        <p><strong>No Listings Found</strong></p>
                        <p>You haven't added any listings yet.</p>
                        <p><a href="add-listing.php">Add your first listing →</a></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%;">
                    <table class="je-table" style="min-width: 700px; width: 100%;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Division</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Views</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($listings as $item): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($item['title']); ?></strong></td>
                                <td>
                                    <span class="division-badge division-badge-<?php echo $item['division']; ?>">
                                        <?php echo ucfirst($item['division']); ?>
                                    </span>
                                </td>
                                <td><?php echo ucfirst($item['type']); ?></td>
                                <td>₦<?php echo number_format($item['price']); ?></td>
                                <td>
                                    <span class="status-badge status-badge-<?php echo $item['status']; ?>">
                                        <?php echo ucfirst($item['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($item['views'] ?? 0); ?></td>
                                <td>
                                    <div class="action-btn-group">
                                        <?php 
                                        $folder = $folderMap[$item['division']] ?? $item['division'];
                                        ?>
                                        <a href="/divisions/<?php echo $folder; ?>/detail.php?id=<?php echo $item['id']; ?>" 
                                           class="action-btn action-btn-view" target="_blank">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="edit-listing.php?id=<?php echo $item['id']; ?>&division=<?php echo $item['division']; ?>" 
                                           class="action-btn action-btn-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="delete-listing.php?id=<?php echo $item['id']; ?>&division=<?php echo $item['division']; ?>&csrf_token=<?php echo Security::generateCSRFToken(); ?>" 
                                           class="action-btn action-btn-delete" 
                                           data-kinas-confirm="Delete this listing? It will be permanently removed." data-kinas-title="Delete Listing" data-kinas-warning="This cannot be undone.">
                                            <i class="fas fa-trash"></i> Delete
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
