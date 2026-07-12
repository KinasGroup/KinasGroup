<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

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
@media (max-width: 768px) {
    .je-dash-main { padding: 10px !important; }
    .je-table th, .je-table td { padding: 8px 8px; font-size: 11px; }
    .je-table th:nth-child(1), .je-table td:nth-child(1) { display: none; }
    .je-table th:nth-child(4), .je-table td:nth-child(4) { display: none; }
    .je-table th:nth-child(5), .je-table td:nth-child(5) { display: none; }
}
@media (max-width: 480px) {
    .je-table th:nth-child(7), .je-table td:nth-child(7) { display: none; }
    .je-table th:nth-child(6), .je-table td:nth-child(6) { display: none; }
}
/* Action buttons */
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
    margin: 2px;
}
.action-btn i { font-size: 11px; }
.action-btn-view { background: #1565C0; color: #FFFFFF !important; }
.action-btn-view:hover { background: #0D47A1; color: #FFFFFF !important; transform: translateY(-1px); }
.action-btn-edit { background: #F57C00; color: #FFFFFF !important; }
.action-btn-edit:hover { background: #E65100; color: #FFFFFF !important; transform: translateY(-1px); }
.action-btn-delete { background: #C62828; color: #FFFFFF !important; }
.action-btn-delete:hover { background: #B71C1C; color: #FFFFFF !important; transform: translateY(-1px); }

/* Search bar */
.listings-search-wrap {
    background: #fff;
    border-radius: 8px;
    margin-bottom: 16px;
    border: 1px solid #E0E0E0;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.listings-search-wrap .search-icon { color: #C6A43F; font-size: 15px; flex-shrink: 0; }
.listings-search-wrap input[type="text"] {
    flex: 1; border: none; outline: none;
    font-family: 'Inter', sans-serif; font-size: 14px;
    color: #0A0A0A; background: transparent;
}
.listings-search-wrap input[type="text"]::placeholder { color: #aaa; }
.listings-search-wrap .search-clear {
    background: none; border: none; cursor: pointer;
    color: #aaa; font-size: 16px; line-height: 1;
    display: none; padding: 2px 4px;
}
.listings-search-wrap .search-clear:hover { color: #555; }
.search-no-results {
    display: none; text-align: center;
    padding: 40px 20px; color: #888; font-size: 14px;
}
.search-no-results i { font-size: 32px; color: #C6A43F; display: block; margin-bottom: 12px; }

/* ============================================================
   DARK MODE — force this page's own styling to stay identical
   to light mode. Auto-generated from every hardcoded
   background/color/border-color rule already on this page.
   ============================================================ */
@media (prefers-color-scheme: dark) {
    .action-btn-view { background: #1565C0 !important; color: #FFFFFF !important; }
    .action-btn-view:hover { background: #0D47A1 !important; color: #FFFFFF !important; }
    .action-btn-edit { background: #F57C00 !important; color: #FFFFFF !important; }
    .action-btn-edit:hover { background: #E65100 !important; color: #FFFFFF !important; }
    .action-btn-delete { background: #C62828 !important; color: #FFFFFF !important; }
    .action-btn-delete:hover { background: #B71C1C !important; color: #FFFFFF !important; }
    .listings-search-wrap { background: #fff !important; }
    .listings-search-wrap .search-icon { color: #C6A43F !important; }
    .listings-search-wrap input[type="text"] { color: #0A0A0A !important; }
    .listings-search-wrap input[type="text"]::placeholder { color: #aaa !important; }
    .listings-search-wrap .search-clear { color: #aaa !important; }
    .listings-search-wrap .search-clear:hover { color: #555 !important; }
    .search-no-results { color: #888 !important; }
    .search-no-results i { color: #C6A43F !important; }
}
</style>

<div class="je-dash-shell" style="max-width:100%;overflow-x:hidden;">
    <?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>

    <main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">
        <div class="je-dash-header" style="flex-wrap: wrap;">
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

            <!-- Search Bar -->
            <div class="listings-search-wrap">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="hardwareSearch" placeholder="Search by title, brand, type…" autocomplete="off">
                <button class="search-clear" id="hardwareSearchClear" title="Clear search">&#x2715;</button>
            </div>

            <div class="je-panel" style="overflow-x: hidden;">
                <div class="je-panel-body" style="overflow-x: hidden;">
                    <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%;">
                    <table class="je-table" id="hardwareTable" style="min-width: 700px; width: 100%;">
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
                        <tbody id="hardwareBody">
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
                                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                        <a href="edit-listing.php?id=<?php echo $item['id']; ?>&division=solar" 
                                           class="action-btn action-btn-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="delete-listing.php?id=<?php echo $item['id']; ?>&division=solar&csrf_token=<?php echo Security::generateCSRFToken(); ?>" 
                                           class="action-btn action-btn-delete" 
                                           data-kinas-confirm="Delete this hardware item? This cannot be undone." data-kinas-title="Delete Hardware Item" data-kinas-warning="This is a permanent, irreversible action.">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="search-no-results" id="hardwareNoResults">
                        <i class="fas fa-search"></i>
                        No hardware items match your search.
                    </div>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                var input = document.getElementById('hardwareSearch');
                var clear = document.getElementById('hardwareSearchClear');
                var tbody = document.getElementById('hardwareBody');
                var noRes = document.getElementById('hardwareNoResults');
                if (!input || !tbody) return;

                function filterHardware() {
                    var q = input.value.trim().toLowerCase();
                    var rows = tbody.querySelectorAll('tr');
                    var visible = 0;
                    rows.forEach(function(row) {
                        var match = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
                        row.style.display = match ? '' : 'none';
                        if (match) visible++;
                    });
                    clear.style.display = q ? 'block' : 'none';
                    if (noRes) noRes.style.display = (visible === 0 && q) ? 'block' : 'none';
                }

                input.addEventListener('input', filterHardware);
                clear.addEventListener('click', function() {
                    input.value = '';
                    filterHardware();
                    input.focus();
                });
            })();
            </script>

        <?php endif; ?>
    </main>
</div>

<?php include '../templates/footer.php'; ?>
