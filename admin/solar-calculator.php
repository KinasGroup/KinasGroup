<?php
/**
 * ADMIN — Solar Calculator Control (bundle-aware rebuild)
 *
 * Tabs:
 * - settings:  admin-controlled calculation assumptions + fallback pricing
 * - products:  which KINAS VOLT listings the calculator may quote + specs
 * - proposals: every quotation the calculator has produced (with items)
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/helpers.php';

SessionManager::requireAdmin();
$db = Database::getInstance()->getConnection();

// ============================================================
// SETTINGS CATALOGUE (labels + groups; mirrors the engine defaults)
// ============================================================
$settingsCatalog = [
    'technical' => [
        'sun_hours_default'      => 'Peak Sun Hours (default)',
        'load_margin_pct'        => 'Load Safety Margin %',
        'pv_performance_ratio'   => 'PV Performance Ratio (0–1)',
        'battery_dod_pct'        => 'Battery Depth of Discharge %',
        'battery_efficiency_pct' => 'Battery Efficiency %',
        'inverter_safety_factor' => 'Inverter Safety Multiplier',
        'default_panel_wattage'  => 'Reference Panel Wattage (W)',
        'co2_kg_per_kwh'         => 'CO2 Factor (kg per kWh)',
    ],
    'pricing' => [
        'default_panel_price'    => 'Fallback Panel Price (₦) — used only while no panel product is listed',
        'electricity_tariff_ngn' => 'Grid Tariff (₦ per kWh) — used for savings',
    ],
];

function solar_admin_redirect(string $tab, string $type, string $message): void
{
    $key = $type === 'success' ? 'solar_admin_success' : 'solar_admin_error';
    SessionManager::setFlash($key, $message);
    header('Location: solar-calculator.php?tab=' . urlencode($tab));
    exit;
}

// ============================================================
// PROCESS POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!Security::verifyCSRFToken($csrf)) {
        solar_admin_redirect('settings', 'error', 'Invalid security token. Please try again.');
    }
    $action  = (string)($_POST['admin_action'] ?? '');
    $adminId = (int)SessionManager::getUserId();

    try {
        // ----------------------------------------------------
        // Save settings
        // ----------------------------------------------------
        if ($action === 'save_settings') {
            $posted = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];
            $saved = 0;
            foreach ($settingsCatalog as $group => $items) {
                foreach ($items as $key => $label) {
                    if (!array_key_exists($key, $posted)) continue;
                    $value = (float)str_replace(',', '', (string)$posted[$key]);
                    if ($value < 0) continue;
                    $db->prepare("
                        INSERT INTO solar_calculator_settings (setting_key, setting_value, setting_label, setting_group)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ")->execute([$key, $value, $label, $group]);
                    $saved++;
                }
            }
            Security::logActivity($adminId, 'solar_settings_updated', "Updated $saved solar calculator settings");
            solar_admin_redirect('settings', 'success', "Settings saved ($saved values). New calculations use them immediately.");
        }

        // ----------------------------------------------------
        // Save product specs
        // ----------------------------------------------------
        if ($action === 'save_product') {
            $productId = (int)($_POST['product_id'] ?? 0);
            if (!$productId) solar_admin_redirect('products', 'error', 'Invalid product.');
            $db->prepare("
                UPDATE solar_calculator_products
                SET product_type = ?, panel_wattage_w = ?, inverter_capacity_kva = ?, continuous_kw = ?,
                    battery_capacity_kwh = ?, usable_battery_kwh = ?, battery_voltage_v = ?,
                    expandable = ?, priority = ?, active = ?
                WHERE id = ?
            ")->execute([
                in_array($_POST['product_type'] ?? '', ['generator', 'panel', 'inverter', 'battery'], true) ? $_POST['product_type'] : 'generator',
                (int)max(0, (float)($_POST['panel_wattage_w'] ?? 0)),
                round(max(0, (float)($_POST['inverter_capacity_kva'] ?? 0)), 2),
                round(max(0, (float)($_POST['continuous_kw'] ?? 0)), 2),
                round(max(0, (float)($_POST['battery_capacity_kwh'] ?? 0)), 2),
                round(max(0, (float)($_POST['usable_battery_kwh'] ?? 0)), 2),
                (int)max(0, (float)($_POST['battery_voltage_v'] ?? 0)),
                isset($_POST['expandable']) ? 1 : 0,
                (int)($_POST['priority'] ?? 100),
                isset($_POST['active']) ? 1 : 0,
                $productId,
            ]);
            Security::logActivity($adminId, 'solar_product_updated', "Updated solar calculator product #$productId");
            solar_admin_redirect('products', 'success', 'Product specs saved.');
        }

        // ----------------------------------------------------
        // Add product
        // ----------------------------------------------------
        if ($action === 'add_product') {
            $listingId = (int)($_POST['listing_id'] ?? 0);
            if (!$listingId) solar_admin_redirect('products', 'error', 'Please choose a listing.');
            $chk = $db->prepare("SELECT id, status FROM solar_listings WHERE id = ?");
            $chk->execute([$listingId]);
            $listing = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$listing) solar_admin_redirect('products', 'error', 'Listing not found.');
            if ($listing['status'] !== 'active') solar_admin_redirect('products', 'error', 'Only active listings can be added.');
            $dup = $db->prepare("SELECT id FROM solar_calculator_products WHERE listing_id = ?");
            $dup->execute([$listingId]);
            if ($dup->fetch()) solar_admin_redirect('products', 'error', 'This listing is already in the calculator.');

            $db->prepare("
                INSERT INTO solar_calculator_products
                (listing_id, product_type, panel_wattage_w, inverter_capacity_kva, continuous_kw,
                 battery_capacity_kwh, usable_battery_kwh, battery_voltage_v, expandable, priority, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ")->execute([
                $listingId,
                in_array($_POST['product_type'] ?? '', ['generator', 'panel', 'inverter', 'battery'], true) ? $_POST['product_type'] : 'generator',
                (int)max(0, (float)($_POST['panel_wattage_w'] ?? 0)),
                round(max(0, (float)($_POST['inverter_capacity_kva'] ?? 0)), 2),
                round(max(0, (float)($_POST['continuous_kw'] ?? 0)), 2),
                round(max(0, (float)($_POST['battery_capacity_kwh'] ?? 0)), 2),
                round(max(0, (float)($_POST['usable_battery_kwh'] ?? 0)), 2),
                (int)max(0, (float)($_POST['battery_voltage_v'] ?? 0)),
                isset($_POST['expandable']) ? 1 : 0,
                (int)($_POST['priority'] ?? 100),
            ]);
            Security::logActivity($adminId, 'solar_product_added', "Added listing #$listingId to the solar calculator");
            solar_admin_redirect('products', 'success', 'Product added to the calculator.');
        }

        // ----------------------------------------------------
        // Toggle active
        // ----------------------------------------------------
        if ($action === 'toggle_product') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $db->prepare("UPDATE solar_calculator_products SET active = 1 - active WHERE id = ?")->execute([$productId]);
            solar_admin_redirect('products', 'success', 'Product status updated.');
        }

        // ----------------------------------------------------
        // Remove product
        // ----------------------------------------------------
        if ($action === 'delete_product') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $db->prepare("DELETE FROM solar_calculator_products WHERE id = ?")->execute([$productId]);
            Security::logActivity($adminId, 'solar_product_removed', "Removed solar calculator product #$productId");
            solar_admin_redirect('products', 'success', 'Product removed from the calculator. The listing itself is untouched.');
        }

        // ----------------------------------------------------
        // Proposal status
        // ----------------------------------------------------
        if ($action === 'set_proposal_status') {
            $proposalId = (int)($_POST['proposal_id'] ?? 0);
            $status = (string)($_POST['status'] ?? '');
            if (!in_array($status, ['new', 'contacted', 'converted'], true)) {
                solar_admin_redirect('proposals', 'error', 'Invalid status.');
            }
            $db->prepare("UPDATE solar_proposals SET status = ? WHERE id = ?")->execute([$status, $proposalId]);
            solar_admin_redirect('proposals', 'success', 'Proposal status updated.');
        }

        solar_admin_redirect('settings', 'error', 'Unknown action.');
    } catch (Throwable $e) {
        error_log('admin/solar-calculator.php error: ' . $e->getMessage());
        solar_admin_redirect('settings', 'error', 'Something went wrong while processing that action.');
    }
}

// ============================================================
// GET DATA
// ============================================================
$tab = (string)($_GET['tab'] ?? 'settings');
if (!in_array($tab, ['settings', 'products', 'proposals'], true)) $tab = 'settings';
$csrf = Security::generateCSRFToken();
$flashSuccess = SessionManager::getFlash('solar_admin_success');
$flashError = SessionManager::getFlash('solar_admin_error');

// Settings (defaults overlaid with DB values)
$defaults = [
    'sun_hours_default' => 5.0, 'load_margin_pct' => 10.0, 'pv_performance_ratio' => 0.80,
    'battery_dod_pct' => 90.0, 'battery_efficiency_pct' => 95.0, 'inverter_safety_factor' => 1.25,
    'default_panel_wattage' => 550.0, 'co2_kg_per_kwh' => 0.85,
    'default_panel_price' => 450000.0, 'electricity_tariff_ngn' => 225.0,
];
try {
    $rows = $db->query("SELECT setting_key, setting_value FROM solar_calculator_settings")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        if (array_key_exists($row['setting_key'], $defaults)) $defaults[$row['setting_key']] = (float)$row['setting_value'];
    }
} catch (Throwable $e) { /* keep defaults */ }

// Products joined with listings
$products = [];
try {
    $products = $db->query("
        SELECT p.*, l.title, l.price, l.status AS listing_status
        FROM solar_calculator_products p
        JOIN solar_listings l ON l.id = p.listing_id
        ORDER BY p.priority ASC, p.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { }

// Available listings (active, not yet added)
$available = [];
try {
    $available = $db->query("
        SELECT id, title, price FROM solar_listings
        WHERE status = 'active'
          AND id NOT IN (SELECT listing_id FROM solar_calculator_products)
        ORDER BY title LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { }

// Proposals
$proposals = [];
try {
    $proposals = $db->query("SELECT * FROM solar_proposals ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { }

$viewProposal = null;
$proposalItems = [];
$viewId = (int)($_GET['view'] ?? 0);
if ($viewId > 0) {
    try {
        $st = $db->prepare("SELECT * FROM solar_proposals WHERE id = ?");
        $st->execute([$viewId]);
        $viewProposal = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($viewProposal) {
            $st2 = $db->prepare("SELECT * FROM solar_proposal_items WHERE proposal_id = ? ORDER BY id ASC");
            $st2->execute([$viewId]);
            $proposalItems = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) { }
}

$pageTitle = 'Solar Calculator — Admin';
require_once __DIR__ . '/../templates/header.php';
?>
<style>
.sc-wrap{max-width:100%;overflow-x:hidden}
.sc-header h1{font-family:'Prata',serif;font-size:26px;color:#0A0A0A;margin:0}
.sc-header p{color:#666;font-size:14px;margin-top:6px}
.sc-flash{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px}
.sc-flash.success{background:#E8F5E9;color:#2E7D32;border:1px solid #A5D6A7}
.sc-flash.error{background:#FFEBEE;color:#C62828;border:1px solid #EF9A9A}
.sc-tabs{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px}
.sc-tabs a{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:30px;background:#fff;border:1px solid #E0E0E0;color:#444;text-decoration:none;font-size:13px;font-weight:700}
.sc-tabs a.active{background:#C6A43F;border-color:#C6A43F;color:#0A0A0A}
.sc-card{background:#fff;border:1px solid #E0E0E0;border-radius:14px;overflow:hidden;margin-bottom:20px}
.sc-card-pad{padding:18px}
.sc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
.sc-field label{display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:6px}
.sc-field input,.sc-field select{width:100%;padding:10px 12px;border:1px solid #DDD;border-radius:8px;font-size:13px;box-sizing:border-box;background:#fff}
.sc-btn{border:none;border-radius:30px;padding:10px 18px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.sc-btn.gold{background:#C6A43F;color:#0A0A0A}
.sc-btn.secondary{background:#F1F1F1;color:#333}
.sc-btn-sm{border:none;border-radius:20px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer;margin-right:6px;margin-bottom:6px}
.sc-btn-sm.success{background:#E8F5E9;color:#2E7D32}
.sc-btn-sm.danger{background:#FFEBEE;color:#C62828}
.sc-btn-sm.dark{background:#222;color:#fff}
.sc-table-wrap{overflow-x:auto}
table.sc-table{width:100%;min-width:1000px;border-collapse:collapse}
table.sc-table th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#888;padding:12px 14px;border-bottom:1px solid #E0E0E0;background:#FAFAFA}
table.sc-table td{padding:12px 14px;border-bottom:1px solid #F0F0F0;font-size:13px;vertical-align:top}
table.sc-table td input,table.sc-table td select{padding:7px 9px;border:1px solid #DDD;border-radius:6px;font-size:12px;background:#fff}
.sc-status{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:800;text-transform:uppercase}
.sc-status.new{background:#FFF8E1;color:#8D6E00}
.sc-status.contacted{background:#E3F2FD;color:#1565C0}
.sc-status.converted{background:#E8F5E9;color:#2E7D32}
.sc-muted{color:#888;font-size:12px}
.sc-note{margin-top:8px;font-size:12px;color:#8D6E00;background:#FFF8E1;border-radius:8px;padding:6px 8px}
.sc-empty{padding:50px 20px;text-align:center;color:#999}
.sc-inline-form{display:inline-block}
@media (prefers-color-scheme: dark){
.sc-header h1,.sc-header p,.sc-card,table.sc-table th,table.sc-table td,.sc-field input,.sc-field select{background:#fff !important;color:#111 !important}
}
</style>
<div class="je-dash-shell sc-wrap">
<?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>
<main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">
<div class="sc-header">
    <h1><i class="fas fa-solar-panel" style="color:#C6A43F;margin-right:10px;"></i>Solar Calculator</h1>
    <p>Control the bundle-aware quotation engine: settings, approved products, and proposals.</p>
</div>

<?php if (!empty($flashSuccess)): ?><div class="sc-flash success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
<?php if (!empty($flashError)): ?><div class="sc-flash error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div><?php endif; ?>

<div class="sc-tabs">
    <a href="?tab=settings" class="<?= $tab === 'settings' ? 'active' : '' ?>"><i class="fas fa-sliders-h"></i> Settings</a>
    <a href="?tab=products" class="<?= $tab === 'products' ? 'active' : '' ?>"><i class="fas fa-boxes"></i> Products (<?= count($products) ?>)</a>
    <a href="?tab=proposals" class="<?= $tab === 'proposals' ? 'active' : '' ?>"><i class="fas fa-file-invoice"></i> Proposals (<?= count($proposals) ?>)</a>
</div>

<?php if ($tab === 'settings'): ?>
<form method="POST" class="sc-card">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="admin_action" value="save_settings">
    <div class="sc-card-pad">
        <h3 style="margin:0 0 12px;"><i class="fas fa-cog" style="color:#C6A43F;"></i> Technical Assumptions</h3>
        <div class="sc-grid">
            <?php foreach ($settingsCatalog['technical'] as $key => $label): ?>
            <div class="sc-field">
                <label><?= htmlspecialchars($label) ?></label>
                <input type="number" step="0.01" name="settings[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars((string)$defaults[$key]) ?>">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="sc-card-pad" style="border-top:1px solid #F0F0F0;">
        <h3 style="margin:0 0 12px;"><i class="fas fa-tags" style="color:#C6A43F;"></i> Pricing</h3>
        <div class="sc-grid">
            <?php foreach ($settingsCatalog['pricing'] as $key => $label): ?>
            <div class="sc-field">
                <label><?= htmlspecialchars($label) ?></label>
                <input type="number" step="1" min="0" name="settings[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars((string)$defaults[$key]) ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <div class="sc-note">Service costs (installation, cabling, mounting, transport) are intentionally NOT part of the automatic quotation — the company does not currently offer them.</div>
    </div>
    <div class="sc-card-pad" style="border-top:1px solid #F0F0F0;">
        <button type="submit" class="sc-btn gold"><i class="fas fa-save"></i> Save Settings</button>
    </div>
</form>

<?php elseif ($tab === 'products'): ?>
<div class="sc-card">
    <div class="sc-card-pad">
        <h3 style="margin:0 0 12px;"><i class="fas fa-plus-circle" style="color:#C6A43F;"></i> Add a KINAS VOLT product</h3>
        <?php if (empty($available)): ?>
        <p class="sc-muted">No additional active solar listings are available to add.</p>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="admin_action" value="add_product">
            <div class="sc-grid">
                <div class="sc-field" style="min-width:260px;">
                    <label>Listing</label>
                    <select name="listing_id" required>
                        <option value="">Select a listing…</option>
                        <?php foreach ($available as $l): ?>
                        <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['title']) ?> (₦<?= number_format((float)$l['price']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-field"><label>Type</label>
                    <select name="product_type">
                        <option value="generator">Generator (inverter+battery)</option>
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
                <div class="sc-field"><label>Voltage (V)</label><input type="number" name="battery_voltage_v" value="48" min="0"></div>
                <div class="sc-field"><label>Priority (lower = preferred)</label><input type="number" name="priority" value="100"></div>
                <div class="sc-field" style="align-self:end;"><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="expandable" checked style="width:auto;"> Expandable</label></div>
            </div>
            <div style="margin-top:14px;"><button type="submit" class="sc-btn gold"><i class="fas fa-plus"></i> Add Product</button></div>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="sc-card">
    <div class="sc-card-pad"><h3 style="margin:0 0 6px;"><i class="fas fa-boxes" style="color:#C6A43F;"></i> Calculator products</h3>
    <div class="sc-note">Prices come LIVE from the listing. Confirm the continuous kW / usable kWh values against the real datasheets — they were inferred from the model names.</div></div>
    <?php if (empty($products)): ?>
    <div class="sc-empty"><i class="fas fa-box-open" style="font-size:36px;display:block;margin-bottom:10px;color:#DDD;"></i>No products configured yet.</div>
    <?php else: ?>
    <div class="sc-table-wrap">
    <table class="sc-table">
        <thead><tr><th>Product</th><th>Type</th><th>Panel W</th><th>kVA</th><th>kW</th><th>Batt kWh</th><th>Usable kWh</th><th>V</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
            <td><strong><?= htmlspecialchars($p['title']) ?></strong><div class="sc-muted">Listing #<?= (int)$p['listing_id'] ?> · ₦<?= number_format((float)$p['price']) ?></div></td>
            <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="admin_action" value="save_product">
            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <td><select name="product_type">
                <?php foreach (['generator','panel','inverter','battery'] as $t): ?>
                <option value="<?= $t ?>" <?= $p['product_type'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select></td>
            <td><input type="number" name="panel_wattage_w" value="<?= (int)$p['panel_wattage_w'] ?>" style="width:70px;"></td>
            <td><input type="number" step="0.01" name="inverter_capacity_kva" value="<?= htmlspecialchars((string)$p['inverter_capacity_kva']) ?>" style="width:70px;"></td>
            <td><input type="number" step="0.01" name="continuous_kw" value="<?= htmlspecialchars((string)$p['continuous_kw']) ?>" style="width:70px;"></td>
            <td><input type="number" step="0.01" name="battery_capacity_kwh" value="<?= htmlspecialchars((string)$p['battery_capacity_kwh']) ?>" style="width:70px;"></td>
            <td><input type="number" step="0.01" name="usable_battery_kwh" value="<?= htmlspecialchars((string)$p['usable_battery_kwh']) ?>" style="width:70px;"></td>
            <td><input type="number" name="battery_voltage_v" value="<?= (int)$p['battery_voltage_v'] ?>" style="width:60px;"></td>
            <td><input type="number" name="priority" value="<?= (int)$p['priority'] ?>" style="width:60px;"></td>
            <td><input type="checkbox" name="active" <?= $p['active'] ? 'checked' : '' ?>></td>
            <td><button type="submit" class="sc-btn-sm success"><i class="fas fa-save"></i> Save</button></td>
            </form>
            <td style="white-space:nowrap;">
                <form method="POST" class="sc-inline-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="admin_action" value="toggle_product"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><button type="submit" class="sc-btn-sm dark"><i class="fas fa-power-off"></i> <?= $p['active'] ? 'Disable' : 'Enable' ?></button></form>
                <form method="POST" class="sc-inline-form" data-confirm="Remove this product from the calculator? The listing itself stays."><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="admin_action" value="delete_product"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><button type="submit" class="sc-btn-sm danger"><i class="fas fa-trash"></i> Remove</button></form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'proposals'): ?>
<?php if ($viewProposal): ?>
<div class="sc-card">
    <div class="sc-card-pad">
        <h3 style="margin:0 0 8px;"><i class="fas fa-file-invoice" style="color:#C6A43F;"></i> Proposal <?= htmlspecialchars($viewProposal['reference']) ?></h3>
        <p class="sc-muted" style="margin-top:0;">
            <?= htmlspecialchars($viewProposal['full_name']) ?> · <?= htmlspecialchars($viewProposal['email']) ?> · <?= htmlspecialchars($viewProposal['phone']) ?> ·
            <?= htmlspecialchars($viewProposal['city_state']) ?> · Backup <?= (int)$viewProposal['backup_hours'] ?>h ·
            <?= date('M j, Y g:i A', strtotime((string)$viewProposal['created_at'])) ?>
        </p>
        <div class="sc-grid" style="margin-bottom:14px;">
            <div class="sc-field"><label>Total Load (W)</label><input readonly value="<?= number_format((float)$viewProposal['total_load_w']) ?>"></div>
            <div class="sc-field"><label>Daily (kWh)</label><input readonly value="<?= htmlspecialchars((string)$viewProposal['daily_kwh']) ?>"></div>
            <div class="sc-field"><label>Required PV (kW)</label><input readonly value="<?= htmlspecialchars((string)$viewProposal['required_pv_kw']) ?>"></div>
            <div class="sc-field"><label>Panels</label><input readonly value="<?= (int)$viewProposal['panels_recommended'] ?>"></div>
            <div class="sc-field"><label>Required Battery (kWh)</label><input readonly value="<?= htmlspecialchars((string)$viewProposal['required_battery_kwh']) ?>"></div>
            <div class="sc-field"><label>Total (₦)</label><input readonly value="<?= number_format((float)$viewProposal['total_cost']) ?>"></div>
            <div class="sc-field"><label>Monthly Savings (₦)</label><input readonly value="<?= number_format((float)$viewProposal['monthly_savings']) ?>"></div>
            <div class="sc-field"><label>Payback (yrs)</label><input readonly value="<?= htmlspecialchars((string)$viewProposal['payback_years']) ?>"></div>
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
    <div class="sc-card-pad"><h3 style="margin:0 0 6px;"><i class="fas fa-file-invoice" style="color:#C6A43F;"></i> Recent proposals</h3></div>
    <?php if (empty($proposals)): ?>
    <div class="sc-empty"><i class="fas fa-inbox" style="font-size:36px;display:block;margin-bottom:10px;color:#DDD;"></i>No proposals yet.</div>
    <?php else: ?>
    <div class="sc-table-wrap">
    <table class="sc-table">
        <thead><tr><th>Reference</th><th>Customer</th><th>Location</th><th>System</th><th>Total (₦)</th><th>Savings (₦/mo)</th><th>Status</th><th>Created</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($proposals as $pr): ?>
        <tr>
            <td><strong><?= htmlspecialchars($pr['reference']) ?></strong></td>
            <td><?= htmlspecialchars($pr['full_name']) ?><div class="sc-muted"><?= htmlspecialchars($pr['email']) ?></div></td>
            <td><?= htmlspecialchars($pr['city_state']) ?></td>
            <td><?= htmlspecialchars((string)$pr['required_pv_kw']) ?> kW · <?= (int)$pr['panels_recommended'] ?> panels</td>
            <td><strong><?= number_format((float)$pr['total_cost']) ?></strong></td>
            <td><?= number_format((float)$pr['monthly_savings']) ?></td>
            <td>
                <form method="POST" class="sc-inline-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="admin_action" value="set_proposal_status">
                    <input type="hidden" name="proposal_id" value="<?= (int)$pr['id'] ?>">
                    <select name="status" onchange="this.form.submit()" style="padding:4px 8px;border:1px solid #DDD;border-radius:6px;font-size:12px;">
                        <?php foreach (['new','contacted','converted'] as $s): ?>
                        <option value="<?= $s ?>" <?= $pr['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </td>
            <td><?= date('M j, Y', strtotime((string)$pr['created_at'])) ?></td>
            <td><a href="?tab=proposals&view=<?= (int)$pr['id'] ?>" class="sc-btn-sm dark"><i class="fas fa-eye"></i> View</a></td>
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
<script>
document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('form[data-confirm]')) return;
    if (!confirm(form.getAttribute('data-confirm') || 'Are you sure?')) e.preventDefault();
});
</script>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
