<?php
/**
 * Agent Dashboard - Hardware Inventory
 * Access via: https://kinas-group.com/agent/hardware.php
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

// Get all hardware
$hardware = $db->query("
    SELECT id, title, service_type, brand, capacity_kw, price, warranty_years, status, views, created_at
    FROM solar_listings 
    WHERE agent_id = $agentId 
      AND service_type IN ('solar_panel', 'inverter', 'battery', 'charge_controller', 'mounting_structure')
    ORDER BY created_at DESC
")->fetchAll();

$pageTitle = 'Hardware Inventory - Agent Dashboard';
include '../templates/header.php';
?>

<style>
/* Table responsive fix */
.table-responsive {
    overflow-x: auto;
}
</style>

<div class="je-dash-shell">
    <?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="je-dash-main">
        <div class="je-dash-header">
            <div>
                <h1><i class="fas fa-microchip" style="color: #C6A43F;"></i> Hardware Inventory</h1>
                <p>Manage your solar hardware inventory</p>
            </div>
            <div>
                <a href="add-hardware.php" class="je-btn je-btn-gold" style="background: #C6A43F; color: #0A0A0A;">
                    <i class="fas fa-plus"></i> Add Hardware
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

        <?php if (empty($hardware)): ?>
            <div class="je-panel">
                <div class="je-panel-body">
                    <div class="je-panel-empty">
                        <i class="fas fa-microchip" style="font-size: 48px; color: #C6A43F;"></i>
                        <h3 style="margin: 16px 0;">No Hardware Inventory</h3>
                        <p style="color: #666;">You haven't added any hardware items yet.</p>
                        <a href="add-hardware.php" class="je-btn je-btn-gold" style="margin-top: 16px; background: #C6A43F; color: #0A0A0A;">
                            <i class="fas fa-plus"></i> Add Your First Hardware
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="je-panel">
                <div class="je-panel-body">
                    <div class="table-responsive">
                    <table class="je-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Brand</th>
                                <th>Capacity</th>
                                <th>Price</th>
                                <th>Warranty</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($hardware as $item): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($item['title']); ?></strong></td>
                                <td><span style="background: #F0F0F0; padding: 2px 8px; border-radius: 4px; font-size: 11px;"><?php echo str_replace('_', ' ', $item['service_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($item['brand']); ?></td>
                                <td><?php echo $item['capacity_kw']; ?> kW</td>
                                <td>₦<?php echo number_format($item['price']); ?></td>
                                <td><?php echo $item['warranty_years']; ?> years</td>
                                <td><span class="je-status is-active">Active</span></td>
                                <td>
                                    <a href="edit-listing.php?id=<?php echo $item['id']; ?>" style="color: #C6A43F; margin-right: 8px;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" onclick="if(confirm('Delete this item?')){window.location='delete-hardware.php?id=<?php echo $item['id']; ?>';}" style="color: #C62828;">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include '../templates/footer.php'; ?>
