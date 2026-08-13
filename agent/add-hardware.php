<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') { header('Location: /auth/login.php'); exit; }
$db      = Database::getInstance()->getConnection();
$agentId = $_SESSION['user_id'];
$hardwareTypes = [
'solar_panel'   => 'Solar Panel',
'inverter'      => 'Inverter',
'battery'       => 'Battery',
'power_station' => 'Power Station',
'charge_controller' => 'Charge Controller',
'mounting_structure' => 'Mounting Structure',
];
$errors  = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Please refresh and try again.'; }
else {
$title   = trim($_POST['title'] ?? '');
$hwType  = $_POST['hardware_type'] ?? '';
$brand   = trim($_POST['brand'] ?? '');
$panelWatts = trim($_POST['panel_watts'] ?? '');
$inverterKva = trim($_POST['inverter_kva'] ?? '');
$batteryKwh = trim($_POST['battery_kwh'] ?? '');
$warranty = trim($_POST['warranty_years'] ?? '');
$price    = trim($_POST['price'] ?? '');
$description = trim($_POST['description'] ?? '');
$city = trim($_POST['city'] ?? ''); $state = trim($_POST['state'] ?? '');
if ($title === '') $errors[] = 'Title is required.';
if (!array_key_exists($hwType, $hardwareTypes)) $errors[] = 'Please choose a valid hardware type.';
if ($price === '' || !is_numeric($price) || $price < 0) $errors[] = 'Please enter a valid price.';
// Per-type capacity validation (units enforced per hardware type).
if ($hwType === 'solar_panel' && ($panelWatts === '' || !is_numeric($panelWatts) || $panelWatts <= 0)) $errors[] = 'Solar Panel requires Panel Capacity in Watts (W).';
if ($hwType === 'inverter' && ($inverterKva === '' || !is_numeric($inverterKva) || $inverterKva <= 0)) $errors[] = 'Inverter requires Capacity in kW/kVA.';
if ($hwType === 'battery' && ($batteryKwh === '' || !is_numeric($batteryKwh) || $batteryKwh <= 0)) $errors[] = 'Battery requires Capacity in kWh.';
if ($hwType === 'power_station') {
if ($inverterKva === '' || !is_numeric($inverterKva) || $inverterKva <= 0) $errors[] = 'Power Station requires Inverter Capacity in kW/kVA.';
if ($batteryKwh === '' || !is_numeric($batteryKwh) || $batteryKwh <= 0) $errors[] = 'Power Station requires Battery Capacity in kWh.';
}
if (empty($errors)) {
try {
$colStmt = $db->query("SHOW COLUMNS FROM solar_listings"); $cols = [];
while ($c = $colStmt->fetch(PDO::FETCH_ASSOC)) $cols[] = $c['Field'];
$fields = ['agent_id','title','service_type','brand','price','warranty_years','description','city','state','status','created_at'];
$values = [$agentId,$title,$hwType,$brand !== '' ? $brand : null,$price,$warranty !== '' ? $warranty : null,$description !== '' ? $description : null,$city !== '' ? $city : null,$state !== '' ? $state : null,'active'];
if (in_array('hardware_type',$cols)) { $fields[]='hardware_type'; $values[]=$hwType; }
if (in_array('panel_watts',$cols)) { $fields[]='panel_watts'; $values[]=$panelWatts !== '' ? (float)$panelWatts : null; }
if (in_array('inverter_kva',$cols)) { $fields[]='inverter_kva'; $values[]=$inverterKva !== '' ? (float)$inverterKva : null; }
if (in_array('battery_kwh',$cols)) { $fields[]='battery_kwh'; $values[]=$batteryKwh !== '' ? (float)$batteryKwh : null; }
$ph = implode(',', array_fill(0, count($fields), '?'));
$db->prepare("INSERT INTO solar_listings (".implode(',',$fields).") VALUES ($ph)")->execute($values);
$_SESSION['flash_success'] = 'Hardware item "' . $title . '" added to your inventory.';
header('Location: hardware.php'); exit;
} catch (Exception $e) { $errors[] = 'Could not save hardware item. Please try again.'; }
}
}
}
$csrf_token = Security::generateCSRFToken();
$pageTitle = 'Add Hardware - Agent Dashboard';
include __DIR__ . '/../templates/header.php';
?>
<style>
.hw-cap{border:1px dashed #C6A43F;border-radius:8px;padding:12px;margin-top:4px;background:#fffdf5;}
</style>
<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">
<div class="je-dash-header"><div><h1><i class="fas fa-plus" style="color:#C6A43F;"></i> Add Hardware</h1><p>Add a solar hardware item to your inventory</p></div><a href="hardware.php" class="je-btn je-btn-outline"><i class="fas fa-arrow-left"></i> Back to Inventory</a></div>
<?php if (!empty($errors)): ?><div class="je-form-error"><?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>
<div class="je-panel"><div class="je-panel-body">
<form method="POST" action="add-hardware.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
<div class="je-form-group"><label>Item Title</label><input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="e.g. 550W Monocrystalline Solar Panel"></div>
<div class="je-form-row">
<div class="je-form-group"><label>Hardware Type</label>
<select name="hardware_type" id="hardwareType" required>
<option value="">Select type...</option>
<?php foreach ($hardwareTypes as $v => $l): ?>
<option value="<?= $v ?>" <?= ((($_POST['hardware_type'] ?? '')) === $v) ? 'selected' : '' ?>><?= $l ?></option>
<?php endforeach; ?>
</select></div>
<div class="je-form-group"><label>Brand</label><input type="text" name="brand" value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>" placeholder="e.g. Jinko Solar"></div>
</div>
<div class="hw-cap">
<div class="je-form-row">
<div class="je-form-group" id="grp_panel_watts" style="display:none;"><label>Panel Capacity (W)</label><input type="number" step="0.01" min="0" name="panel_watts" value="<?= htmlspecialchars($_POST['panel_watts'] ?? '') ?>" placeholder="e.g. 550"></div>
<div class="je-form-group" id="grp_inverter_kva" style="display:none;"><label>Inverter Capacity (kW/kVA)</label><input type="number" step="0.01" min="0" name="inverter_kva" value="<?= htmlspecialchars($_POST['inverter_kva'] ?? '') ?>" placeholder="e.g. 5"></div>
<div class="je-form-group" id="grp_battery_kwh" style="display:none;"><label>Battery Capacity (kWh)</label><input type="number" step="0.01" min="0" name="battery_kwh" value="<?= htmlspecialchars($_POST['battery_kwh'] ?? '') ?>" placeholder="e.g. 10"></div>
</div>
<p style="font-size:12px;color:#888;margin-top:6px;" id="hw_hint">Select a hardware type to enter its capacity in the correct unit.</p>
</div>
<div class="je-form-row">
<div class="je-form-group"><label>Warranty (years)</label><input type="number" name="warranty_years" value="<?= htmlspecialchars($_POST['warranty_years'] ?? '') ?>" placeholder="e.g. 25"></div>
<div class="je-form-group"><label>Price (₦)</label><input type="number" step="0.01" name="price" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" placeholder="e.g. 450000"></div>
</div>
<div class="je-form-row">
<div class="je-form-group"><label>City</label><input type="text" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"></div>
<div class="je-form-group"><label>State</label><input type="text" name="state" value="<?= htmlspecialchars($_POST['state'] ?? '') ?>"></div>
</div>
<div class="je-form-group"><label>Description</label><textarea name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></div>
<button type="submit" class="je-btn je-btn-gold"><i class="fas fa-check"></i> Add Hardware</button>
</form>
</div></div>
</main>
</div>
<script>
(function(){
var sel=document.getElementById('hardwareType');
function sync(){
var t=sel?sel.value:'';
var show=function(id,on){var elx=document.getElementById(id);if(elx)elx.style.display=on?'':'none';};
show('grp_panel_watts', t==='solar_panel');
show('grp_inverter_kva', t==='inverter'||t==='power_station');
show('grp_battery_kwh', t==='battery'||t==='power_station');
var hints={solar_panel:'Enter Panel Capacity in Watts (W).',inverter:'Enter Inverter Capacity in kW/kVA.',battery:'Enter Battery Capacity in kWh.',power_station:'Enter both Inverter (kW/kVA) and Battery (kWh) capacities.',charge_controller:'No capacity needed for Charge Controller.',mounting_structure:'No capacity needed for Mounting Structure.'};
var h=document.getElementById('hw_hint'); if(h)h.textContent=hints[t]||'Select a hardware type to enter its capacity in the correct unit.';
}
if(sel){sel.addEventListener('change',sync);sync();}
})();
</script>
<?php include __DIR__ . '/../templates/footer.php'; ?>
