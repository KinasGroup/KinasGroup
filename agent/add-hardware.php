<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
/**
* Agent Dashboard - Add Hardware (KINAS VOLT only)
* Hardware partitioning: capacity inputs change per hardware type so the
* Solar Calculator reads correct units:
*   solar_panel   -> panel_watts  (W)
*   inverter      -> inverter_kva (kW/kVA)
*   battery       -> battery_kwh  (kWh)
*   power_station -> inverter_kva + battery_kwh
*   charge_controller / mounting_structure -> no capacity field
*/
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') { header('Location: /auth/login.php'); exit; }
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
$errors[] = 'Please refresh the page and try again.';
} else {
$title         = trim($_POST['title'] ?? '');
$hardwareType  = $_POST['hardware_type'] ?? '';
$serviceType   = $_POST['service_type'] ?? '';
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
try {
// Legacy normalized kW for backward compatibility.
$capacityKw = null;
if ($hardwareType === 'solar_panel' && $panelWatts !== '') $capacityKw = round(((float)$panelWatts) / 1000, 3);
elseif (($hardwareType === 'inverter' || $hardwareType === 'power_station') && $inverterKva !== '') $capacityKw = (float)$inverterKva;
elseif ($hardwareType === 'battery' && $batteryKwh !== '') $capacityKw = (float)$batteryKwh;
// Insert with partitioned columns when present (guarded).
$colStmt = $db->query("SHOW COLUMNS FROM solar_listings");
$cols = []; while ($c = $colStmt->fetch(PDO::FETCH_ASSOC)) $cols[] = $c['Field'];
$fields = ['agent_id','title','service_type','brand','capacity_kw','warranty_years','price','description','city','state','status','created_at'];
$values = [$agentId,$title,$serviceType !== '' ? $serviceType : ($hardwareType),$brand !== '' ? $brand : null,$capacityKw,$warrantyYears !== '' ? $warrantyYears : null,$price,$description !== '' ? $description : null,$city !== '' ? $city : null,$state !== '' ? $state : null,'active'];
if (in_array('hardware_type', $cols)) { $fields[] = 'hardware_type'; $values[] = $hardwareType; }
if (in_array('panel_watts', $cols))  { $fields[] = 'panel_watts';  $values[] = $panelWatts !== '' ? (float)$panelWatts : null; }
if (in_array('inverter_kva', $cols)) { $fields[] = 'inverter_kva'; $values[] = $inverterKva !== '' ? (float)$inverterKva : null; }
if (in_array('battery_kwh', $cols))  { $fields[] = 'battery_kwh';  $values[] = $batteryKwh !== '' ? (float)$batteryKwh : null; }
$ph = implode(',', array_fill(0, count($fields), '?'));
$db->prepare("INSERT INTO solar_listings (".implode(',',$fields).") VALUES ($ph)")->execute($values);
$_SESSION['flash_success'] = 'Hardware item "' . $title . '" added to your inventory.';
header('Location: hardware.php'); exit;
} catch (Exception $e) { $errors[] = 'Could not save hardware item. Please try again.'; }
}
}
}
$csrf_token = Security::generateCSRFToken();
$pageTitle  = 'Add Hardware - Agent Dashboard';
include __DIR__ . '/../templates/header.php';
?>
<style>
.hw-capacity{border:1px dashed #C6A43F;border-radius:8px;padding:12px;margin-top:4px;background:#fffdf5;}
</style>
<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">
<div class="agent-container" style="max-width:900px;margin:0 auto;padding:30px;">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
<h1 style="font-family:'Prata',serif;font-size:24px;"><i class="fas fa-solar-panel" style="color:#C6A43F;"></i> Add Hardware</h1>
<a href="hardware.php" class="btn-secondary" style="background:#F5F5F5;color:#666;padding:10px 20px;border-radius:40px;text-decoration:none;">Back to Inventory</a>
</div>
<?php if (!empty($errors)): ?><div style="background:#FFEBEE;border:1px solid #EF9A9A;color:#B71C1C;border-radius:8px;padding:14px 20px;margin-bottom:20px;"><?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>
<div style="background:white;border-radius:16px;border:1px solid #E0E0E0;padding:24px;">
<form method="POST" action="add-hardware.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
<div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Item Title *</label>
<input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="e.g. 550W Mono Solar Panel" style="width:100%;padding:10px;border:1px solid #E0E0E0;border-radius:8px;"></div>
<div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Hardware Type *</label>
<select name="hardware_type" id="hardwareType" required style="width:100%;padding:10px;border:1px solid #E0E0E0;border-radius:8px;">
<option value="">Select type...</option>
<?php foreach ($hardwareTypes as $val => $label): ?>
<option value="<?= htmlspecialchars($val) ?>" <?= ((($_POST['hardware_type'] ?? '')) === $val) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
<?php endforeach; ?>
</select></div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
<div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">System Type</label>
<select name="service_type" style="width:100%;padding:10px;border:1px solid #E0E0E0;border-radius:8px;">
<option value="">Select...</option>
<option value="Residential" <?= ((($_POST['service_type'] ?? '')) === 'Residential') ? 'selected' : '' ?>>Residential</option>
<option value="Commercial" <?= ((($_POST['service_type'] ?? '')) === 'Commercial') ? 'selected' : '' ?>>Commercial</option>
<option value="Industrial" <?= ((($_POST['service_type'] ?? '')) === 'Industrial') ? 'selected' : '' ?>>Industrial</option>
</select></div>
<div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Brand</label>
<input type="text" name="brand" value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>" placeholder="e.g. Jinko Solar" style="width:100%;padding:10px;border:1px solid #E0E0E0;border-radius:8px;"></div>
</div>
<!-- Unit-aware capacity fields (shown per hardware type) -->
<div class="hw-capacity">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
<div id="grp_panel_watts" style="display:none;"><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Panel Capacity (W)</label>
<input type="number" step="0.01" min="0" name="panel_watts" value="<?= htmlspecialchars($_POST['panel_watts'] ?? '') ?>" placeholder="e.g. 550" style="width:100%;padding:10px;border:1px solid #E0E0E0;border-radius:8px;"></div>
<div id="grp_inverter_kva" style="display:none;"><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Inverter Capacity (kW/kVA)</label>
<input type="number" step="0.01" min="0" name="inverter_kva" value="<?= htmlspecialchars($_POST['inverter_kva'] ?? '') ?>" placeholder="e.g. 5" style="width:100%;padding:10px;border:1px solid #E0E0E0;border-radius:8px;"></div>
<div id="grp_battery_kwh" style="display:none;"><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Battery Capacity (kWh)</label>
<input type="number" step="0.01" min="0" name="battery_kwh" value="<?= htmlspecialchars($_POST['battery_kwh'] ?? '') ?>" placeholder="e.g. 10" style="width:100%;padding:10px;border:1px solid #E0E0E0;border-radius:8px;"></div>
</div>
<p style="font-size:12px;color:#888;margin-top:8px;" id="hw_hint">Select a hardware type to enter its capacity in the correct unit.</p>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
<div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Warranty (years)</label>
<input type="number" name="warranty_years" value="<?= htmlspecialchars($_POST['warranty_years'] ?? '') ?>" placeholder="e.g. 25" style="width:100%;padding:10px;border:1px solid #E0E0E0;border-radius:8px;"></div>
<div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Price (₦) *</label>
<input type="number" step="0.01" name="price" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" placeholder="e.g. 450000" style="width:100%;padding:10px;border:1px solid #E0E0E0;border-radius:8px;"></div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
<div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">City</label>
<input type="text" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" style="width:100%;padding:10px;border:1px solid #E0E0E0;border-radius:8px;"></div>
<div><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">State</label>
<input type="text" name="state" value="<?= htmlspecialchars($_POST['state'] ?? '') ?>" style="width:100%;padding:10px;border:1px solid #E0E0E0;border-radius:8px;"></div>
</div>
<div style="margin-top:16px;"><label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Description</label>
<textarea name="description" rows="4" style="width:100%;padding:10px;border:1px solid #E0E0E0;border-radius:8px;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></div>
<button type="submit" style="margin-top:20px;background:#C6A43F;color:#0A0A0A;border:none;padding:12px 28px;border-radius:40px;font-weight:600;cursor:pointer;"><i class="fas fa-check"></i> Add Hardware</button>
</form>
</div>
</div>
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
var hints={
solar_panel:'Enter Panel Capacity in Watts (W).',
inverter:'Enter Inverter Capacity in kW/kVA.',
battery:'Enter Battery Capacity in kWh.',
power_station:'Enter both Inverter (kW/kVA) and Battery (kWh) capacities.',
charge_controller:'No capacity needed for Charge Controller.',
mounting_structure:'No capacity needed for Mounting Structure.'
};
var h=document.getElementById('hw_hint'); if(h)h.textContent=hints[t]||'Select a hardware type to enter its capacity in the correct unit.';
}
if(sel)sel.addEventListener('change',sync);
sync();
})();
</script>
<?php include __DIR__ . '/../templates/footer.php'; ?>
