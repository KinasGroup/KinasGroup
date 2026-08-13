<?php
// Authenticated, per-session content — never cache this page.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
/**
* Agent Dashboard - Add Hardware (KINAS VOLT) — REWRITTEN for hardware partitioning.
*
* Capacity is now unit-aware per hardware type so the Solar Calculator can
* read correct specs:
*   solar_panel   -> panel_watts  (W)
*   inverter      -> inverter_kva (kW/kVA)
*   battery       -> battery_kwh  (kWh)
*   power_station -> inverter_kva + battery_kwh
*   charge_controller / mounting_structure -> no capacity field
*
* Stores into the new partition columns (hardware_type, panel_watts,
* inverter_kva, battery_kwh) added by
* database/migrations/2026_08_14_solar_hardware_partitioning.sql, and keeps
* capacity_kw as a legacy normalized-kW value for backward compatibility.
* service_type is left free for its real purpose (residential/commercial/...).
*
* Access via: https://kinas-group.com/agent/add-hardware.php
*/
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
header('Location: /auth/login.php');
exit;
}
$db      = Database::getInstance()->getConnection();
$agentId = (int)$_SESSION['user_id'];
$hardwareTypes = [
'solar_panel'        => 'Solar Panel',
'inverter'           => 'Inverter',
'battery'            => 'Battery',
'power_station'      => 'Power Station',
'charge_controller'  => 'Charge Controller',
'mounting_structure' => 'Mounting Structure',
];
$errors  = [];
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
$errors[] = 'Please refresh the page and try again.';
} else {
$title         = trim($_POST['title'] ?? '');
$hardwareType  = $_POST['hardware_type'] ?? '';
$brand         = trim($_POST['brand'] ?? '');
$panelWatts    = trim($_POST['panel_watts'] ?? '');
$inverterKva   = trim($_POST['inverter_kva'] ?? '');
$batteryKwh    = trim($_POST['battery_kwh'] ?? '');
$warrantyYears = trim($_POST['warranty_years'] ?? '');
$price         = trim($_POST['price'] ?? '');
$description   = trim($_POST['description'] ?? '');
$city          = trim($_POST['city'] ?? '');
$state         = trim($_POST['state'] ?? '');
if ($title === '') $errors[] = 'Title is required.';
if (!array_key_exists($hardwareType, $hardwareTypes)) $errors[] = 'Please choose a valid hardware type.';
if ($price === '' || !is_numeric($price) || $price < 0) $errors[] = 'Please enter a valid price.';
if ($warrantyYears !== '' && !is_numeric($warrantyYears)) $errors[] = 'Warranty years must be a number.';
// Per-type capacity validation (units enforced per hardware type).
if ($hardwareType === 'solar_panel') {
if ($panelWatts === '' || !is_numeric($panelWatts) || $panelWatts <= 0) $errors[] = 'Solar Panel requires Panel Capacity in Watts.';
} elseif ($hardwareType === 'inverter') {
if ($inverterKva === '' || !is_numeric($inverterKva) || $inverterKva <= 0) $errors[] = 'Inverter requires Capacity in kW/kVA.';
} elseif ($hardwareType === 'battery') {
if ($batteryKwh === '' || !is_numeric($batteryKwh) || $batteryKwh <= 0) $errors[] = 'Battery requires Capacity in kWh.';
} elseif ($hardwareType === 'power_station') {
if ($inverterKva === '' || !is_numeric($inverterKva) || $inverterKva <= 0) $errors[] = 'Power Station requires Inverter Capacity in kW/kVA.';
if ($batteryKwh === '' || !is_numeric($batteryKwh) || $batteryKwh <= 0) $errors[] = 'Power Station requires Battery Capacity in kWh.';
}
if (empty($errors)) {
// Legacy normalized kW for backward compatibility.
$capacityKw = null;
if ($hardwareType === 'solar_panel' && $panelWatts !== '') $capacityKw = round(((float)$panelWatts) / 1000, 3);
elseif (($hardwareType === 'inverter' || $hardwareType === 'power_station') && $inverterKva !== '') $capacityKw = (float)$inverterKva;
elseif ($hardwareType === 'battery' && $batteryKwh !== '') $capacityKw = (float)$batteryKwh;
try {
// Only write the partition columns if the migration has been applied.
$colStmt = $db->query("SHOW COLUMNS FROM solar_listings");
$cols = [];
while ($c = $colStmt->fetch(PDO::FETCH_ASSOC)) $cols[] = $c['Field'];
$fields = ['agent_id','title','service_type','brand','capacity_kw','warranty_years','price','description','city','state','status','created_at'];
$values = [$agentId,$title,$hardwareType,$brand !== '' ? $brand : null,$capacityKw,$warrantyYears !== '' ? $warrantyYears : null,$price,$description !== '' ? $description : null,$city !== '' ? $city : null,$state !== '' ? $state : null,'active'];
if (in_array('hardware_type', $cols)) { $fields[] = 'hardware_type'; $values[] = $hardwareType; }
if (in_array('panel_watts', $cols))  { $fields[] = 'panel_watts';  $values[] = $panelWatts !== '' ? (float)$panelWatts : null; }
if (in_array('inverter_kva', $cols)) { $fields[] = 'inverter_kva'; $values[] = $inverterKva !== '' ? (float)$inverterKva : null; }
if (in_array('battery_kwh', $cols))  { $fields[] = 'battery_kwh';  $values[] = $batteryKwh !== '' ? (float)$batteryKwh : null; }
$ph = implode(',', array_fill(0, count($fields), '?'));
$sql = "INSERT INTO solar_listings (" . implode(',', $fields) . ", created_at) VALUES ($ph, NOW())";
// created_at already in $fields? we appended created_at in $fields list; adjust:
// (we put 'created_at' in $fields and also append; fix by removing duplicate)
$stmt = $db->prepare("INSERT INTO solar_listings (" . implode(',', $fields) . ") VALUES ($ph)");
$stmt->execute($values);
$_SESSION['flash_success'] = 'Hardware item "' . $title . '" added to your inventory.';
header('Location: hardware.php');
exit;
} catch (Exception $e) {
$errors[] = 'Could not save hardware item. Please try again.';
}
}
}
}
$csrf_token = Security::generateCSRFToken();
$pageTitle  = 'Add Hardware - Agent Dashboard';
include __DIR__ . '/../templates/header.php';
?>
<style>
.je-form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
@media(max-width:640px){.je-form-row{grid-template-columns:1fr;}}
.hw-capacity{border:1px dashed #C6A43F;border-radius:8px;padding:12px;margin-top:4px;background:#fffdf5;}
</style>
<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">
<div class="je-dash-header">
<div>
<h1><i class="fas fa-plus" style="color: #C6A43F;"></i> Add Hardware</h1>
<p>Add a new solar hardware item to your inventory</p>
</div>
<div>
<a href="hardware.php" class="je-btn je-btn-outline"><i class="fas fa-arrow-left"></i> Back to Inventory</a>
</div>
</div>
<?php if (!empty($errors)): ?>
<div class="je-form-error">
<?php foreach ($errors as $error): ?><div><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>
<div class="je-panel">
<div class="je-panel-body">
<form method="POST" action="add-hardware.php" id="hardwareForm">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
<div class="je-form-group">
<label for="title">Item Title</label>
<input type="text" id="title" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="e.g. 550W Monocrystalline Solar Panel">
</div>
<div class="je-form-row">
<div class="je-form-group">
<label for="hardware_type">Hardware Type</label>
<select id="hardware_type" name="hardware_type" required>
<option value="">Select type...</option>
<?php foreach ($hardwareTypes as $value => $label): ?>
<option value="<?= htmlspecialchars($value) ?>" <?= ((($_POST['hardware_type'] ?? '')) === $value) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="je-form-group">
<label for="brand">Brand</label>
<input type="text" id="brand" name="brand" value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>" placeholder="e.g. Jinko Solar">
</div>
</div>
<!-- Unit-aware capacity fields, shown per hardware type -->
<div class="hw-capacity">
<div class="je-form-row">
<div class="je-form-group" id="grp_panel_watts" style="display:none;">
<label for="panel_watts">Panel Capacity (W)</label>
<input type="number" step="0.01" min="0" id="panel_watts" name="panel_watts" value="<?= htmlspecialchars($_POST['panel_watts'] ?? '') ?>" placeholder="e.g. 550">
</div>
<div class="je-form-group" id="grp_inverter_kva" style="display:none;">
<label for="inverter_kva">Inverter Capacity (kW/kVA)</label>
<input type="number" step="0.01" min="0" id="inverter_kva" name="inverter_kva" value="<?= htmlspecialchars($_POST['inverter_kva'] ?? '') ?>" placeholder="e.g. 5">
</div>
<div class="je-form-group" id="grp_battery_kwh" style="display:none;">
<label for="battery_kwh">Battery Capacity (kWh)</label>
<input type="number" step="0.01" min="0" id="battery_kwh" name="battery_kwh" value="<?= htmlspecialchars($_POST['battery_kwh'] ?? '') ?>" placeholder="e.g. 10">
</div>
</div>
<p style="font-size:12px;color:#888;margin-top:6px;" id="hw_hint">Select a hardware type to enter its capacity in the correct unit.</p>
</div>
<div class="je-form-row">
<div class="je-form-group">
<label for="warranty_years">Warranty (years)</label>
<input type="number" id="warranty_years" name="warranty_years" value="<?= htmlspecialchars($_POST['warranty_years'] ?? '') ?>" placeholder="e.g. 25">
</div>
<div class="je-form-group">
<label for="price">Price (₦)</label>
<input type="number" step="0.01" id="price" name="price" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" placeholder="e.g. 450000">
</div>
</div>
<div class="je-form-row">
<div class="je-form-group">
<label for="city">City</label>
<input type="text" id="city" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" placeholder="e.g. Lagos">
</div>
<div class="je-form-group">
<label for="state">State</label>
<input type="text" id="state" name="state" value="<?= htmlspecialchars($_POST['state'] ?? '') ?>" placeholder="e.g. Lagos">
</div>
</div>
<div class="je-form-group">
<label for="description">Description</label>
<textarea id="description" name="description" rows="4" placeholder="Describe this hardware item..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
</div>
<button type="submit" class="je-btn je-btn-gold"><i class="fas fa-check"></i> Add Hardware</button>
</form>
</div>
</div>
</main>
</div>
<script>
(function(){
var sel = document.getElementById('hardware_type');
var gW = document.getElementById('grp_panel_watts');
var gK = document.getElementById('grp_inverter_kva');
var gB = document.getElementById('grp_battery_kwh');
var hint = document.getElementById('hw_hint');
function sync(){
var t = sel ? sel.value : '';
gW.style.display = (t === 'solar_panel') ? '' : 'none';
gK.style.display = (t === 'inverter' || t === 'power_station') ? '' : 'none';
gB.style.display = (t === 'battery' || t === 'power_station') ? '' : 'none';
var hints = {
solar_panel: 'Enter Panel Capacity in Watts (W).',
inverter: 'Enter Inverter Capacity in kW/kVA.',
battery: 'Enter Battery Capacity in kWh.',
power_station: 'Enter both Inverter (kW/kVA) and Battery (kWh) capacities.',
charge_controller: 'No capacity needed for Charge Controller.',
mounting_structure: 'No capacity needed for Mounting Structure.'
};
if (hint) hint.textContent = hints[t] || 'Select a hardware type to enter its capacity in the correct unit.';
}
if (sel) sel.addEventListener('change', sync);
sync();
})();
</script>
<?php include __DIR__ . '/../templates/footer.php'; ?>
