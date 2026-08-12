<?php
// Authenticated, per-session content — never cache this page.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * ADMIN — Solar Calculator Control
 *
 * Tabs:
 *  - settings:  calculation assumptions + fallback prices (admin-controlled)
 *  - products:  which KINAS VOLT listings the calculator may use + specs
 *  - proposals: every quotation produced by the calculator
 */
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../api/config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// ============================================================
// HELPERS
// ============================================================
function solar_admin_table_exists($db, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ");
        $stmt->execute([$table]);
        $cache[$table] = ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$table] = false;
    }
    return $cache[$table];
}

function solar_admin_redirect(string $tab, string $type, string $message, array $extra = []): void
{
    SessionManager::setFlash($type === 'success' ? 'solar_calc_success' : 'solar_calc_error', $message);
    $params = array_merge(['tab' => $tab], $extra);
    header('Location: solar-calculator.php?' . http_build_query($params));
    exit;
}

// ============================================================
// SETTINGS CATALOGUE (must mirror includes/solar-engine.php)
// ============================================================
$settingsCatalog = [
    'technical' => [
        'sun_hours_default'      => ['label' => 'Peak Sun Hours (default)', 'hint' => 'Average daily peak sun hours used for PV sizing.'],
        'load_margin_pct'        => ['label' => 'Load Safety Margin %', 'hint' => 'Extra margin added to daily energy before sizing.'],
        'pv_performance_ratio'   => ['label' => 'PV Performance Ratio', 'hint' => 'Combined PV losses (temp, dust, wiring). e.g. 0.80'],
        'battery_dod_pct'        => ['label' => 'Battery Depth of Discharge %', 'hint' => 'Usable portion of the battery. LiFePO4 ≈ 90.'],
        'battery_efficiency_pct' => ['label' => 'Battery Efficiency %', 'hint' => 'Round-trip efficiency ≈ 95.'],
        'inverter_safety_factor' => ['label' => 'Inverter Safety Multiplier', 'hint' => 'Peak-load multiplier. e.g. 1.25'],
        'default_panel_wattage'  => ['label' => 'Reference Panel Wattage (W)', 'hint' => 'Used when no panel product is listed.'],
        'co2_kg_per_kwh'         => ['label' => 'CO2 Factor (kg per kWh)', 'hint' => 'Grid CO2 intensity ≈ 0.85.'],
    ],
    'pricing' => [
        'default_panel_price'    => ['label' => 'Fallback Panel Price (₦)', 'hint' => 'Used ONLY while no panel product is listed.'],
        'default_inverter_price' => ['label' => 'Fallback Inverter Price (₦)', 'hint' => 'Used ONLY while no inverter product is listed.'],
        'default_battery_price'  => ['label' => 'Fallback Battery Price (₦)', 'hint' => 'Used ONLY while no battery product is listed.'],
        'installation_cost'      => ['label' => 'Installation Cost (₦)', 'hint' => ''],
        'cabling_cost'           => ['label' => 'Cabling & Protection Cost (₦)', 'hint' => ''],
        'mounting_cost'          => ['label' => 'Mounting Structure Cost (₦)', 'hint' => ''],
        'transport_cost'         => ['label' => 'Transport & Logistics Cost (₦)', 'hint' => ''],
        'electricity_tariff_ngn' => ['label' => 'Grid Tariff (₦ per kWh)', 'hint' => 'Used for the savings calculation.'],
    ],
];

$settingsDefaults = [
    'sun_hours_default' => 5.0, 'load_margin_pct' => 10.0, 'pv_performance_ratio' => 0.80,
    'battery_dod_pct' => 90.0, 'battery_efficiency_pct' => 95.0, 'inverter_safety_factor' => 1.25,
    'default_panel_wattage' => 550.0, 'co2_kg_per_kwh' => 0.85,
    'default_panel_price' => 450000.0, 'default_inverter_price' => 3500000.0, 'default_battery_price' => 2800000.0,
    'installation_cost' => 1500000.0, 'cabling_cost' => 500000.0, 'mounting_cost' => 400000.0,
    'transport_cost' => 200000.0, 'electricity_tariff_ngn' => 225.0,
];

$settingsInstalled = solar_admin_table_exists($db, 'solar_calculator_settings');
$productsInstalled = solar_admin_table_exists($db, 'solar_calculator_products');
$proposalsInstalled = solar_admin_table_exists($db, 'solar_proposals');

// Current settings = defaults overlaid with DB values.
$currentSettings = $settingsDefaults;
if ($settingsInstalled) {
    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM solar_calculator_settings")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (array_key_exists($row['setting_key'], $currentSettings)) {
                $currentSettings[$row['setting_key']] = (float)$row['setting_value'];
            }
        }
    } catch (Throwable $e) { /* keep defaults */ }
}

// ============================================================
// POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!Security::verifyCSRFToken($csrf)) {
        solar_admin_redirect($_POST['tab'] ?? 'settings', 'error', 'Invalid security token. Please try again.');
    }

    $action = (string)($_POST['admin_action'] ?? '');

    try {
        // ----------------------------------------------------
        // SAVE SETTINGS
        // ----------------------------------------------------
        if ($action === 'save_settings') {
            if (!$settingsInstalled) {
                solar_admin_redirect('settings', 'error', 'The solar calculator tables are not installed. Run the FILE 1 SQL migration first.');
            }
            $posted = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];
            $saved = 0;
            foreach ($settingsCatalog as $group => $items) {
                foreach ($items as $key => $meta) {
                    if (!array_key_exists($key, $posted)) continue;
                    $value = (float)str_replace(',', '', (string)$posted[$key]);
                    if ($value < 0) continue;
                    // Ratios that must stay within sane bounds.
                    if (in_array($key, ['pv_performance_ratio'], true)) $value = max(0.1, min(1, $value));
                    if (in_array($key, ['battery_dod_pct', 'battery_efficiency_pct', 'load_margin_pct'], true)) $value = max(1, min(100, $value));
                    if (in_array($key, ['sun_hours_default', 'inverter_safety_factor'], true) && $value <= 0) continue;

                    $db->prepare("
                        INSERT INTO solar_calculator_settings (setting_key, setting_value, setting_label, setting_group)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ")->execute([$key, $value, $meta['label'], $group]);
                    $saved++;
                }
            }
            Security::logActivity((int)$_SESSION['user_id'], 'solar_calc_settings_updated', "Updated $saved solar calculator settings");
            solar_admin_redirect('settings', 'success', "Settings saved ($saved values). New calculations now use them immediately.");
        }

        // ----------------------------------------------------
        // ADD PRODUCT TO CALCULATOR
        // ----------------------------------------------------
        if ($action === 'add_product') {
            if (!$productsInstalled) {
                solar_admin_redirect('products', 'error', 'The solar calculator tables are not installed. Run the FILE 1 SQL migration first.');
            }
            $listingId = (int)($_POST['listing_id'] ?? 0);
            $type = (string)($_POST['product_type'] ?? '');
            if ($listingId <= 0 || !in_array($type, ['panel', 'generator', 'inverter', 'battery'], true)) {
                solar_admin_redirect('products', 'error', 'Please choose a listing and a valid product type.');
            }
            $check = $db->prepare("SELECT id, status FROM solar_listings WHERE id = ?");
            $check->execute([$listingId]);
            $listing = $check->fetch(PDO::FETCH_ASSOC);
            if (!$listing) solar_admin_redirect('products', 'error', 'Listing not found.');
            if ($listing['status'] !== 'active') solar_admin_redirect('products', 'error', 'Only active listings can be added to the calculator.');

            $db->prepare("
                INSERT INTO solar_calculator_products
                (listing_id, product_type, panel_wattage_w, inverter_capacity_kva, continuous_kw,
                 battery_capacity_kwh, usable_battery_kwh, battery_voltage_v, expandable, priority, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ")->execute([
                $listingId,
                $type,
                (int)max(0, (float)($_POST['panel_wattage_w'] ?? 0)),
                round(max(0, (float)($_POST['inverter_capacity_kva'] ?? 0)), 2),
                round(max(0, (float)($_POST['continuous_kw'] ?? 0)), 2),
                round(max(0, (float)($_POST['battery_capacity_kwh'] ?? 0)), 2),
                round(max(0, (float)($_POST['usable_battery_kwh'] ?? 0)), 2),
                (int)max(0, (float)($_POST['battery_voltage_v'] ?? 0)),
                isset($_POST['expandable']) ? 1 : 0,
                (int)($_POST['priority'] ?? 100),
            ]);
            Security::logActivity((int)$_SESSION['user_id'], 'solar_calc_product_added', "Added listing #$listingId to the solar calculator");
            solar_admin_redirect('products', 'success', 'Product added to the calculator.');
        }

        // ----------------------------------------------------
        // SAVE PRODUCT SPECS
        // ----------------------------------------------------
        if ($action === 'save_product') {
            $productId = (int)($_POST['product_id'] ?? 0);
            if ($productId <= 0) solar_admin_redirect('products', 'error', 'Invalid product.');
            $db->prepare("
                UPDATE solar_calculator_products
                SET product_type = ?, panel_wattage_w = ?, inverter_capacity_kva = ?, continuous_kw = ?,
                    battery_capacity_kwh = ?, usable_battery_kwh = ?, battery_voltage_v = ?,
                    expandable = ?, priority = ?
                WHERE id = ?
            ")->execute([
                in_array($_POST['product_type'] ?? '', ['panel', 'generator', 'inverter', 'battery'], true) ? $_POST['product_type'] : 'generator',
                (int)max(0, (float)($_POST['panel_wattage_w'] ?? 0)),
                round(max(0, (float)($_POST['inverter_capacity_kva'] ?? 0)), 2),
                round(max(0, (float)($_POST['continuous_kw'] ?? 0)), 2),
                round(max(0, (float)($_POST['battery_capacity_kwh'] ?? 0)), 2),
                round(max(0, (float)($_POST['usable_battery_kwh'] ?? 0)), 2),
                (int)max(0, (float)($_POST['battery_voltage_v'] ?? 0)),
                isset($_POST['expandable']) ? 1 : 0,
                (int)($_POST['priority'] ?? 100),
                $productId,
            ]);
            Security::logActivity((int)$_SESSION['user_id'], 'solar_calc_product_updated', "Updated solar calculator product #$productId");
            solar_admin_redirect('products', 'success', 'Product specs saved.');
        }

        // ----------------------------------------------------
        // TOGGLE / REMOVE PRODUCT
        // ----------------------------------------------------
        if ($action === 'toggle_product') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $db->prepare("UPDATE solar_calculator_products SET active = 1 - active WHERE id = ?")->execute([$productId]);
            solar_admin_redirect('products', 'success', 'Product status updated.');
        }

        if ($action === 'delete_product') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $db->prepare("DELETE FROM solar_calculator_products WHERE id = ?")->execute([$productId]);
            Security::logActivity((int)$_SESSION['user_id'], 'solar_calc_product_removed', "Removed solar calculator product #$productId");
            solar_admin_redirect('products', 'success', 'Product removed from the calculator. The listing itself is untouched.');
        }

        solar_admin_redirect('settings', 'error', 'Unknown action.');
    } catch (Throwable $e) {
        error_log('admin/solar-calculator.php error: ' . $e->getMessage());
        solar_admin_redirect($_POST['tab'] ?? 'settings', 'error', 'Something went wrong: ' . $e->getMessage());
    }
}

// ============================================================
// GET DATA
// ============================================================
$tab = (string)($_GET['tab'] ?? 'settings');
if (!in_array($tab, ['settings', 'products', 'proposals'], true)) $tab = 'settings';

$csrf = Security::generateCSRFToken();
$flashSuccess = SessionManager::getFlash('solar_calc_success');
$flashError = SessionManager::getFlash('solar_calc_error');

$products = [];
$availableListings = [];
if ($productsInstalled) {
    try {
        $products = $db->query("
            SELECT p.*, l.title, l.price, l.status AS listing_status
            FROM solar_calculator_products p
            JOIN solar_listings l ON l.id = p.listing_id
            ORDER BY p.priority ASC, p.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $availableListings = $db->query("
            SELECT id, title, price, service_type
            FROM solar_listings
            WHERE status = 'active'
              AND id NOT IN (SELECT listing_id FROM solar_calculator_products)
            ORDER BY title
            LIMIT 200
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('admin/solar-calculator.php products load error: ' . $e->getMessage());
    }
}

$proposals = [];
$proposalView = null;
$proposalItems = [];
if ($proposalsInstalled) {
    try {
        $proposals = $db->query("
            SELECT * FROM solar_proposals ORDER BY created_at DESC LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $viewId = (int)($_GET['view'] ?? 0);
        if ($viewId > 0) {
            $st = $db->prepare("SELECT * FROM solar_proposals WHERE id = ?");
            $st->execute([$viewId]);
            $proposalView = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($proposalView && solar_admin_table_exists($db, 'solar_proposal_items')) {
                $st2 = $db->prepare("SELECT * FROM solar_proposal_items WHERE proposal_id = ? ORDER BY id ASC");
                $st2->execute([$viewId]);
                $proposalItems = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
    } catch (Throwable $e) {
        error_log('admin/solar-calculator.php proposals load error: ' . $e->getMessage());
    }
}

$pageTitle = 'Solar Calculator Control - Admin';
include '../templates/header.php';
?>
<style>
.sc-wrap { max-width: 100%; overflow-x: hidden; }
.sc-header h1 { font-family: 'Prata', serif; font-size: 26px; color: #0A0A0A; margin: 0; }
.sc-header p { color: #666; font-size: 14px; margin-top: 6px; }
.sc-flash { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; }
.sc-flash.success { background: #E8F5E9; color: #2E7D32; border: 1px solid #A5D6A7; }
.sc-flash.error { background: #FFEBEE; color: #C62828; border: 1px solid #EF9A9A; }
.sc-tabs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
.sc-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 30px; background: #fff; border: 1px solid #E0E0E0; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; }
.sc-tabs a.active { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
.sc-card { background: #fff; border: 1px solid #E0E0E0; border-radius: 14px; overflow: hidden; margin-bottom: 20px; }
.sc-card-pad { padding: 18px; }
.sc-card h3 { margin: 0 0 6px; font-size: 16px; }
.sc-hint { color: #888; font-size: 12px; margin: 0 0 14px; }
.sc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
.sc-field label { display: block; font-size: 12px; font-weight: 700; color: #555; margin-bottom: 6px; }
.sc-field small { display: block; color: #999; font-size: 11px; margin-top: 4px; }
.sc-field input, .sc-field select { width: 100%; padding: 10px 12px; border: 1px solid #DDD; border-radius: 8px; font-size: 13px; box-sizing: border-box; background: #fff; }
.sc-btn { border: none; border-radius: 30px; padding: 10px 18px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.sc-btn.gold { background: #C6A43F; color: #0A0A0A; }
.sc-btn.secondary { background: #F1F1F1; color: #333; }
.sc-btn.sm { padding: 6px 12px; font-size: 12px; border-radius: 20px; }
.sc-btn.success { background: #E8F5E9; color: #2E7D32; }
.sc-btn.danger { background: #FFEBEE; color: #C62828; }
.sc-table-wrap { overflow-x: auto; }
table.sc-table { width: 100%; min-width: 1000px; border-collapse: collapse; }
table.sc-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #888; padding: 12px 14px; border-bottom: 1px solid #E0E0E0; background: #FAFAFA; }
table.sc-table td { padding: 12px 14px; border-bottom: 1px solid #F0F0F0; font-size: 13px; vertical-align: top; }
table.sc-table td input, table.sc-table td select { padding: 7px 9px; border: 1px solid #DDD; border-radius: 6px; font-size: 12px; background: #fff; }
.sc-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.sc-pill.on { background: #E8F5E9; color: #2E7D32; }
.sc-pill.off { background: #ECEFF1; color: #546E7A; }
.sc-muted { color: #888; font-size: 12px; }
.sc-empty { padding: 50px 20px; text-align: center; color: #999; }
.sc-inline-form { display: inline-block; margin-right: 6px; }
@media (prefers-color-scheme: dark) {
    .sc-header h1, .sc-header p, .sc-card, table.sc-table th, table.sc-table td,
    .sc-field input, .sc-field select, table.sc-table td input, table.sc-table td select { background: #fff !important; color: #111 !important; }
}
</style>

<div class="je-dash-shell sc-wrap">
<?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>
<main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">
    <div class="sc-header">
        <h1><i class="fas fa-solar-panel" style="color:#C6A43F;margin-right:10px;"></i>Solar Calculator Control</h1>
        <p>Control the assumptions, approved products and pricing used by the KINAS VOLT solar calculator.</p>
    </div>

    <?php if (!empty($flashSuccess)): ?><div class="sc-flash success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if (!empty($flashError)): ?><div class="sc-flash error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <?php if (!$settingsInstalled || !$productsInstalled): ?>
    <div class="sc-flash error">
        <strong>Setup required:</strong> the solar calculator tables are not fully installed.
        Run the FILE 1 SQL migration (solar_calculator_settings / solar_calculator_products / solar_proposals / solar_proposal_items) first.
    </div>
    <?php endif; ?>

    <div class="sc-tabs">
        <a href="?tab=settings" class="<?= $tab === 'settings' ? 'active' : '' ?>"><i class="fas fa-sliders-h"></i> Settings</a>
        <a href="?tab=products" class="<?= $tab === 'products' ? 'active' : '' ?>"><i class="fas fa-boxes"></i> Products (<?= count($products) ?>)</a>
        <a href="?tab=proposals" class="<?= $tab === 'proposals' ? 'active' : '' ?>"><i class="fas fa-file-invoice"></i> Proposals (<?= count($proposals) ?>)</a>
    </div>

    <?php if ($tab === 'settings'): ?>
    <form method="POST" class="sc-card">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="tab" value="settings">
        <input type="hidden" name="admin_action" value="save_settings">
        <div class="sc-card-pad">
            <h3><i class="fas fa-cog" style="color:#C6A43F;"></i> Technical Assumptions</h3>
            <p class="sc-hint">These drive every new calculation. Changes apply immediately — existing proposals are not rewritten.</p>
            <div class="sc-grid">
                <?php foreach ($settingsCatalog['technical'] as $key => $meta): ?>
                <div class="sc-field">
                    <label><?= htmlspecialchars($meta['label']) ?></label>
                    <input type="number" step="0.01" name="settings[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars((string)$currentSettings[$key]) ?>">
                    <?php if ($meta['hint']): ?><small><?= htmlspecialchars($meta['hint']) ?></small><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="sc-card-pad" style="border-top:1px solid #F0F0F0;">
            <h3><i class="fas fa-tags" style="color:#C6A43F;"></i> Pricing &amp; Costs</h3>
            <p class="sc-hint">Fallback prices are used ONLY when no matching product is listed. When a product is listed, its live listing price is always used instead.</p>
            <div class="sc-grid">
                <?php foreach ($settingsCatalog['pricing'] as $key => $meta): ?>
                <div class="sc-field">
                    <label><?= htmlspecialchars($meta['label']) ?></label>
                    <input type="number" step="1" min="0" name="settings[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars((string)$currentSettings[$key]) ?>">
                    <?php if ($meta['hint']): ?><small><?= htmlspecialchars($meta['hint']) ?></small><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="sc-card-pad" style="border-top:1px solid #F0F0F0;">
            <button type="submit" class="sc-btn gold"><i class="fas fa-save"></i> Save Settings</button>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($tab === 'products'): ?>
    <!-- ADD PRODUCT -->
    <div class="sc-card">
        <div class="sc-card-pad">
            <h3><i class="fas fa-plus-circle" style="color:#C6A43F;"></i> Add a KINAS VOLT product to the calculator</h3>
            <p class="sc-hint">Only active listings appear here. The calculator always uses the listing's live price.</p>
            <?php if (empty($availableListings)): ?>
            <p class="sc-muted">No additional active listings are available to add.</p>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="tab" value="products">
                <input type="hidden" name="admin_action" value="add_product">
                <div class="sc-grid">
                    <div class="sc-field" style="min-width:260px;">
                        <label>Listing</label>
                        <select name="listing_id" required>
                            <option value="">Select a listing…</option>
                            <?php foreach ($availableListings as $l): ?>
                            <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['title']) ?> (₦<?= number_format((float)$l['price']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sc-field">
                        <label>Product Type</label>
                        <select name="product_type" required>
                            <option value="generator">Generator (inverter + battery)</option>
                            <option value="panel">Solar Panel</option>
                            <option value="inverter">Inverter</option>
                            <option value="battery">Battery</option>
                        </select>
                    </div>
                    <div class="sc-field"><label>Panel Wattage (W)</label><input type="number" name="panel_wattage_w" value="0" min="0"></div>
                    <div class="sc-field"><label>Inverter (kVA)</label><input type="number" step="0.01" name="inverter_capacity_kva" value="0" min="0"></div>
                    <div class="sc-field"><label>Continuous (kW)</label><input type="number" step="0.01" name="continuous_kw" value="0" min="0"></div>
                    <div class="sc-field"><label>Battery (kWh)</label><input type="number" step="0.01" name="battery_capacity_kwh" value="0" min="0"></div>
                    <div class="sc-field"><label>Usable (kWh)</label><input type="number" step="0.01" name="usable_battery_kwh" value="0" min="0"></div>
                    <div class="sc-field"><label>Voltage (V)</label><input type="number" name="battery_voltage_v" value="0" min="0"></div>
                    <div class="sc-field"><label>Priority (lower = preferred)</label><input type="number" name="priority" value="100"></div>
                    <div class="sc-field" style="align-self:end;">
                        <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="expandable" checked style="width:auto;"> Expandable / stackable</label>
                    </div>
                </div>
                <div style="margin-top:14px;"><button type="submit" class="sc-btn gold"><i class="fas fa-plus"></i> Add Product</button></div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- MANAGE PRODUCTS -->
    <div class="sc-card">
        <div class="sc-card-pad"><h3><i class="fas fa-boxes" style="color:#C6A43F;"></i> Calculator products</h3>
        <p class="sc-hint">Edit specs, priority and availability. Inactive products are ignored by the calculator.</p></div>
        <?php if (empty($products)): ?>
        <div class="sc-empty"><i class="fas fa-box-open" style="font-size:36px;display:block;margin-bottom:10px;color:#DDD;"></i>No products configured yet.</div>
        <?php else: ?>
        <div class="sc-table-wrap">
        <table class="sc-table">
            <thead><tr>
                <th>Product</th><th>Type</th><th>Panel W</th><th>kVA</th><th>kW</th><th>Batt kWh</th><th>Usable kWh</th><th>V</th><th>Priority</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($p['title']) ?></strong><br>
                    <span class="sc-muted">Listing #<?= (int)$p['listing_id'] ?> · ₦<?= number_format((float)$p['price']) ?> · <?= htmlspecialchars(ucfirst((string)$p['listing_status'])) ?></span>
                </td>
                <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="tab" value="products">
                <input type="hidden" name="admin_action" value="save_product">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <td>
                    <select name="product_type">
                        <?php foreach (['generator', 'panel', 'inverter', 'battery'] as $t): ?>
                        <option value="<?= $t ?>" <?= $p['product_type'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="panel_wattage_w" value="<?= (int)$p['panel_wattage_w'] ?>" style="width:70px;"></td>
                <td><input type="number" step="0.01" name="inverter_capacity_kva" value="<?= htmlspecialchars((string)$p['inverter_capacity_kva']) ?>" style="width:70px;"></td>
                <td><input type="number" step="0.01" name="continuous_kw" value="<?= htmlspecialchars((string)$p['continuous_kw']) ?>" style="width:70px;"></td>
                <td><input type="number" step="0.01" name="battery_capacity_kwh" value="<?= htmlspecialchars((string)$p['battery_capacity_kwh']) ?>" style="width:70px;"></td>
                <td><input type="number" step="0.01" name="usable_battery_kwh" value="<?= htmlspecialchars((string)$p['usable_battery_kwh']) ?>" style="width:70px;"></td>
                <td><input type="number" name="battery_voltage_v" value="<?= (int)$p['battery_voltage_v'] ?>" style="width:60px;"></td>
                <td><input type="number" name="priority" value="<?= (int)$p['priority'] ?>" style="width:60px;"></td>
                <td><span class="sc-pill <?= $p['active'] ? 'on' : 'off' ?>"><?= $p['active'] ? 'Active' : 'Inactive' ?></span></td>
                <td>
                    <button type="submit" class="sc-btn sm success"><i class="fas fa-save"></i> Save</button>
                </td>
                </form>
                <td style="white-space:nowrap;">
                    <form method="POST" class="sc-inline-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="tab" value="products">
                        <input type="hidden" name="admin_action" value="toggle_product">
                        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="sc-btn sm secondary"><i class="fas fa-power-off"></i> <?= $p['active'] ? 'Disable' : 'Enable' ?></button>
                    </form>
                    <form method="POST" class="sc-inline-form" data-kinas-confirm="Remove this product from the calculator? The listing itself stays." data-kinas-title="Remove Product" data-kinas-warning="The calculator will stop using this product.">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="tab" value="products">
                        <input type="hidden" name="admin_action" value="delete_product">
                        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="sc-btn sm danger"><i class="fas fa-trash"></i> Remove</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'proposals'): ?>
    <?php if ($proposalView): ?>
    <div class="sc-card">
        <div class="sc-card-pad">
            <h3><i class="fas fa-file-invoice" style="color:#C6A43F;"></i> Proposal <?= htmlspecialchars($proposalView['reference']) ?></h3>
            <p class="sc-hint">
                <?= htmlspecialchars($proposalView['full_name']) ?> · <?= htmlspecialchars($proposalView['email']) ?> · <?= htmlspecialchars($proposalView['phone']) ?> ·
                <?= htmlspecialchars($proposalView['city_state']) ?> · <?= htmlspecialchars(ucfirst((string)$proposalView['property_type'])) ?> ·
                Backup <?= (int)$proposalView['backup_hours'] ?>h ·
                Created <?= date('M j, Y g:i A', strtotime((string)$proposalView['created_at'])) ?>
            </p>
            <div class="sc-grid" style="margin-bottom:14px;">
                <div class="sc-field"><label>Total Load (W)</label><input readonly value="<?= number_format((float)$proposalView['total_load_w']) ?>"></div>
                <div class="sc-field"><label>Daily (kWh)</label><input readonly value="<?= htmlspecialchars((string)$proposalView['daily_kwh']) ?>"></div>
                <div class="sc-field"><label>Required PV (kW)</label><input readonly value="<?= htmlspecialchars((string)$proposalView['required_pv_kw']) ?>"></div>
                <div class="sc-field"><label>Panels</label><input readonly value="<?= (int)$proposalView['panels_recommended'] ?>"></div>
                <div class="sc-field"><label>Required Battery (kWh)</label><input readonly value="<?= htmlspecialchars((string)$proposalView['required_battery_kwh']) ?>"></div>
                <div class="sc-field"><label>Total Cost (₦)</label><input readonly value="<?= number_format((float)$proposalView['total_cost']) ?>"></div>
                <div class="sc-field"><label>Monthly Savings (₦)</label><input readonly value="<?= number_format((float)$proposalView['monthly_savings']) ?>"></div>
                <div class="sc-field"><label>Payback (years)</label><input readonly value="<?= htmlspecialchars((string)$proposalView['payback_years']) ?>"></div>
            </div>
            <div class="sc-table-wrap">
            <table class="sc-table">
                <thead><tr><th>Item</th><th>Type</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead>
                <tbody>
                <?php foreach ($proposalItems as $it): ?>
                <tr>
                    <td><?= htmlspecialchars($it['description']) ?><?= !empty($it['listing_id']) ? ' <span class="sc-muted">(listing #' . (int)$it['listing_id'] . ')</span>' : '' ?></td>
                    <td><?= htmlspecialchars(ucfirst((string)$it['item_type'])) ?></td>
                    <td><?= (int)$it['qty'] ?></td>
                    <td>₦<?= number_format((float)$it['unit_price']) ?></td>
                    <td><strong>₦<?= number_format((float)$it['line_total']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div style="margin-top:14px;"><a href="?tab=proposals" class="sc-btn secondary"><i class="fas fa-arrow-left"></i> Back to proposals</a></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="sc-card">
        <div class="sc-card-pad"><h3><i class="fas fa-file-invoice" style="color:#C6A43F;"></i> Recent proposals</h3>
        <p class="sc-hint">Every quotation produced by the solar calculator, newest first.</p></div>
        <?php if (empty($proposals)): ?>
        <div class="sc-empty"><i class="fas fa-inbox" style="font-size:36px;display:block;margin-bottom:10px;color:#DDD;"></i>No proposals yet.</div>
        <?php else: ?>
        <div class="sc-table-wrap">
        <table class="sc-table">
            <thead><tr><th>Reference</th><th>Customer</th><th>Location</th><th>System</th><th>Total (₦)</th><th>Savings (₦/mo)</th><th>Created</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($proposals as $pr): ?>
            <tr>
                <td><strong><?= htmlspecialchars($pr['reference']) ?></strong></td>
                <td><?= htmlspecialchars($pr['full_name']) ?><br><span class="sc-muted"><?= htmlspecialchars($pr['email']) ?></span></td>
                <td><?= htmlspecialchars($pr['city_state']) ?></td>
                <td><?= htmlspecialchars((string)$pr['required_pv_kw']) ?> kW · <?= (int)$pr['panels_recommended'] ?> panels</td>
                <td><strong><?= number_format((float)$pr['total_cost']) ?></strong></td>
                <td><?= number_format((float)$pr['monthly_savings']) ?></td>
                <td><?= date('M j, Y', strtotime((string)$pr['created_at'])) ?></td>
                <td><a href="?tab=proposals&view=<?= (int)$pr['id'] ?>" class="sc-btn sm secondary"><i class="fas fa-eye"></i> View</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>
</div>
<?php include '../templates/footer.php'; ?>
