<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
$kycStatus = 'pending'; $isBusinessAgent = false; $kybApprovedAgent = false;
try {
$st = Database::getInstance()->getConnection()->prepare("SELECT verification_status, company_name, kyb_status FROM agent_profiles WHERE user_id = ?");
$st->execute([(int)$_SESSION['user_id']]);
$agentRow = $st->fetch(PDO::FETCH_ASSOC) ?: [];
$kycStatus = $agentRow['verification_status'] ?? 'pending';
$isBusinessAgent = trim((string)($agentRow['company_name'] ?? '')) !== '';
$kybApprovedAgent = ($agentRow['kyb_status'] ?? '') === 'approved';
} catch (Exception $e) {}
$canOfferRental = $isBusinessAgent && $kybApprovedAgent;
$agentDivision = $_SESSION['user_division'] ?? null;
$isSuperAgent  = !empty($_SESSION['is_super_agent']);
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$divisionMap = [
'kinas-automobile'    => ['type' => 'car',         'label' => 'Kinas Automobile',      'opt' => 'automobile'],
'williams-connect-home'=> ['type' => 'property',   'label' => 'Williams Connect Home', 'opt' => 'realestate'],
'kinas-volt'          => ['type' => 'solar',       'label' => 'Kinas Volt',            'opt' => 'solar'],
'kinas-marketplace'   => ['type' => 'marketplace', 'label' => 'Kinas Marketplace',     'opt' => 'marketplace'],
];
$csrf_token = Security::generateCSRFToken();
$pageTitle = 'Add Listing - Agent Dashboard';
require_once __DIR__ . '/../templates/header.php';
?>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.agent-container { max-width: 1200px; margin: 0 auto; padding: 30px; }
.agent-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 32px; }
.agent-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; }
.agent-header h1 i { color: #C6A43F; margin-right: 12px; }
.btn-secondary { background: #F5F5F5; color: #666; padding: 10px 20px; border-radius: 40px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; border: 1px solid #E0E0E0; }
.btn-secondary:hover { background: #E0E0E0; }
.listing-form { background: white; border-radius: 24px; border: 1px solid #E0E0E0; overflow: hidden; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.form-section { padding: 32px; }
.form-section:first-child { border-right: 1px solid #E0E0E0; }
.form-section h3 { font-size: 18px; font-weight: 600; color: #C6A43F; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 2px solid #C6A43F; display: inline-block; }
.form-group { margin-bottom: 24px; }
.form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #333; }
.form-group label i { color: #C6A43F; margin-right: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 12px; font-family: 'Inter', sans-serif; font-size: 14px; transition: all 0.3s; background: #fff; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #C6A43F; box-shadow: 0 0 0 3px rgba(198,164,63,0.1); }
.form-group select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; padding-right: 40px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.input-prefix { position: relative; display: flex; align-items: center; }
.prefix { position: absolute; left: 16px; color: #C6A43F; font-weight: 600; }
.input-prefix input { padding-left: 32px; }
.image-upload-area { border: 2px dashed #E0E0E0; border-radius: 16px; padding: 20px; text-align: center; transition: all 0.3s; position: relative; }
.image-upload-area:hover { border-color: #C6A43F; background: rgba(198,164,63,0.02); }
.upload-placeholder { cursor: pointer; }
.upload-placeholder i { font-size: 48px; color: #C6A43F; margin-bottom: 12px; }
.upload-placeholder p { margin-bottom: 8px; color: #666; }
.upload-placeholder span { font-size: 12px; color: #999; }
.image-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px; margin-top: 20px; }
.preview-item { position: relative; border-radius: 12px; overflow: hidden; aspect-ratio: 1; background: #F5F5F5; }
.preview-item img { width: 100%; height: 100%; object-fit: cover; }
.preview-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; background: rgba(0,0,0,0.7); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; font-size: 14px; border: none; }
.checkbox-group { display: flex; align-items: center; gap: 12px; margin-top: 8px; }
.checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.checkbox-label input { width: auto; accent-color: #C6A43F; }
.form-actions { display: flex; justify-content: flex-end; gap: 16px; margin-top: 32px; padding-top: 24px; border-top: 1px solid #E0E0E0; }
.btn-cancel { padding: 12px 28px; background: #F5F5F5; border: none; border-radius: 40px; color: #666; cursor: pointer; font-weight: 600; }
.btn-submit { padding: 12px 32px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; color: #0A0A0A; cursor: pointer; transition: all 0.3s; }
.btn-submit:hover { background: #A8882E; transform: translateY(-2px); }
.automobile-fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.automobile-fields-grid .form-group { margin-bottom: 0; }
.full-width { grid-column: 1 / -1; }
@media (max-width: 968px) { .form-grid { grid-template-columns: 1fr; } .form-section:first-child { border-right: none; border-bottom: 1px solid #E0E0E0; } .form-row { grid-template-columns: 1fr; gap: 0; } .automobile-fields-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) { .agent-container { padding: 20px; } .form-section { padding: 24px; } }
.upload-loading-overlay { position: absolute; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); color:white; display:flex; flex-direction:column; align-items:center; justify-content:center; font-family:'Inter',sans-serif; border-radius:16px; z-index:10; }
@media (prefers-color-scheme: dark) {
body { background: #F5F7FA !important; }
.agent-header h1 { color: #0A0A0A !important; } .agent-header h1 i { color: #C6A43F !important; }
.btn-secondary { background: #F5F5F5 !important; color: #666 !important; }
.listing-form { background: white !important; }
.form-section h3 { color: #C6A43F !important; }
.form-group label { color: #333 !important; } .form-group label i { color: #C6A43F !important; }
.form-group input, .form-group select, .form-group textarea { background: #fff !important; }
.prefix { color: #C6A43F !important; }
.btn-cancel { background: #F5F5F5 !important; color: #666 !important; }
.btn-submit { background: #C6A43F !important; color: #0A0A0A !important; }
}
</style>
<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">
<div class="agent-container">
<div class="agent-header"><div><h1><i class="fas fa-plus-circle"></i> Add New Listing</h1><p>Create a new listing in any division</p></div><a href="/agent/listings.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Listings</a></div>
<?php if (!in_array($kycStatus, ['approved'], true)): ?>
<div style="background:linear-gradient(135deg,#FFF8E1,#FFF3E0); border:1px solid #FFE0B2; border-radius:16px; padding:18px 24px; margin-bottom:24px; display:flex; align-items:center; gap:16px;">
<i class="fas fa-shield-alt" style="color:#E65100; font-size:24px;"></i>
<div style="flex:1;"><strong style="color:#BF360C;">Verification recommended.</strong>
<span style="color:#5D4037; font-size:13px;"> Listings from verified agents rank higher and get the verified badge. <a href="/agent/verification.php" style="color:#BF360C; font-weight:600;">Complete KYC →</a></span></div>
</div>
<?php endif; ?>
<?php if ($flashSuccess): ?><div style="background:#E8F5E9; border:1px solid #A5D6A7; color:#1B5E20; border-radius:8px; padding:14px 20px; margin-bottom:24px;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div style="background:#FFEBEE; border:1px solid #EF9A9A; color:#B71C1C; border-radius:8px; padding:14px 20px; margin-bottom:24px;"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div><?php endif; ?>
<form class="listing-form" method="POST" action="/api/listings/create.php" enctype="multipart/form-data" id="listingForm">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
<div class="form-grid">
<div class="form-section"><h3>Basic Information</h3>
<div class="form-group"><label><i class="fas fa-layer-group"></i> Division *</label>
<select name="division" id="division" required>
<option value="">Select Division</option>
<?php foreach ($divisionMap as $dbDiv => $info):
$sel = (!$isSuperAgent && $dbDiv === $agentDivision) ? 'selected' : '';
?>
<option value="<?= htmlspecialchars($info['opt']) ?>" <?= $sel ?>><?= htmlspecialchars($info['label']) ?></option>
<?php endforeach; ?>
</select>
<?php if (!$isSuperAgent && $agentDivision): ?>
<small style="color:#666; font-size:12px;">Your account is registered for <strong><?= htmlspecialchars($divisionMap[$agentDivision]['label'] ?? $agentDivision) ?></strong> only. Contact an admin to be promoted to a Super Agent.</small>
<?php endif; ?>
<input type="hidden" name="listing_type" id="listing_type" value="">
</div>
<div class="form-group"><label><i class="fas fa-heading"></i> Listing Title *</label><input type="text" name="title" placeholder="e.g., 2024 Mercedes-Benz S-Class" required></div>
<div class="form-group"><label><i class="fas fa-align-left"></i> Description *</label><textarea name="description" rows="6" placeholder="Provide a detailed description of your listing..." required></textarea></div>
<div class="form-row"><div class="form-group"><label><i class="fas fa-tag"></i> Price *</label><div class="input-prefix"><span class="prefix">₦</span><input type="number" name="price" placeholder="0" step="0.01" required></div></div><div class="form-group"><label>Price Type</label><select name="price_type"><option value="fixed">Fixed Price</option><option value="negotiable">Negotiable</option><option value="contact">Contact for Price</option></select></div></div>
<h3>Location</h3>
<div class="form-row"><div class="form-group"><label><i class="fas fa-map-marker-alt"></i> City</label><input type="text" name="city" placeholder="e.g., Bellevue"></div><div class="form-group"><label>State/Province</label><input type="text" name="state" placeholder="e.g., WA"></div></div>
<div class="form-group"><label><i class="fas fa-location-dot"></i> Address</label><input type="text" name="address" placeholder="Full address, e.g., 1880 136th Place NE"></div>
<div class="form-group"><label><i class="fas fa-globe"></i> Country</label><input type="text" name="country" placeholder="e.g., United States" value="Nigeria"></div>
</div>
<div class="form-section"><h3>Images</h3>
<div class="image-upload-area"><input type="file" name="images[]" id="imageUpload" multiple accept="image/*" style="display: none;"><div class="upload-placeholder" onclick="if (document.getElementById('imageUpload').dataset.kinasProcessing === '1') return; document.getElementById('imageUpload').click()"><i class="fas fa-cloud-upload-alt"></i><p>Click or drag images here</p><span>Upload up to 10 images (Max 5MB each)</span></div><div class="image-preview-grid" id="imagePreviewGrid"></div></div>
<!-- AUTOMOBILE -->
<div id="automobileFields" style="display:none; margin-top:24px;">
<h3 style="margin-bottom:16px;"><i class="fas fa-car"></i> Automobile Details</h3>
<div class="automobile-fields-grid">
<div class="form-group"><label><i class="fas fa-key"></i> Listing Purpose *</label>
<select name="car_listing_type" id="carListingType" required>
<option value="sale">For Sale</option>
<?php if ($canOfferRental): ?><option value="rental">For Rental</option><?php endif; ?>
</select>
<?php if (!$canOfferRental): ?><span style="display:block;font-size:12px;color:#888;margin-top:4px;">Rental listings are only available to verified registered businesses. <?= $isBusinessAgent ? 'Your business KYB verification is still pending.' : 'Add a business name and complete KYB verification in your profile to unlock this.' ?></span><?php endif; ?>
</div>
<div class="form-group"><label><i class="fas fa-clipboard-check"></i> Inspection Fee (₦) <span style="font-weight:400;color:#888;">(optional)</span></label><input type="number" name="inspection_fee" min="0" step="0.01" placeholder="Leave blank for free inspections"></div>
<div class="form-group"><label><i class="fas fa-tag"></i> Make *</label>
<select name="brand" required><option value="">Select Make</option><option>Acura</option><option>Alfa Romeo</option><option>Aston Martin</option><option>Audi</option><option>Bentley</option><option>BMW</option><option>Bugatti</option><option>Buick</option><option>Cadillac</option><option>Chevrolet</option><option>Chrysler</option><option>Citroen</option><option>Dodge</option><option>Ferrari</option><option>Fiat</option><option>Ford</option><option>Genesis</option><option>GMC</option><option>Honda</option><option>Hyundai</option><option>Infiniti</option><option>Jaguar</option><option>Jeep</option><option>Kia</option><option>Lamborghini</option><option>Land Rover</option><option>Lexus</option><option>Maserati</option><option>Mazda</option><option>McLaren</option><option>Mercedes-Benz</option><option>Mini</option><option>Mitsubishi</option><option>Nissan</option><option>Porsche</option><option>Ram</option><option>Rolls-Royce</option><option>Subaru</option><option>Tesla</option><option>Toyota</option><option>Volkswagen</option><option>Volvo</option><option>Other</option></select>
</div>
<div class="form-group"><label><i class="fas fa-car"></i> Model *</label><input type="text" name="model" placeholder="e.g., S-Class" required></div>
<div class="form-group"><label><i class="fas fa-calendar"></i> Year *</label><input type="number" name="year" placeholder="e.g., 2018" min="1900" max="2099" required></div>
<div class="form-group"><label><i class="fas fa-tachometer-alt"></i> Mileage *</label><input type="text" name="mileage" placeholder="e.g., 19592 mi (31530 km)" required></div>
<div class="form-group"><label><i class="fas fa-cog"></i> Engine</label><input type="text" name="engine" placeholder="e.g., 6 Cylinder"></div>
<div class="form-group"><label><i class="fas fa-cogs"></i> Gearbox / Transmission</label><select name="gearbox"><option value="">Select Gearbox</option><option>Automatic</option><option>Manual</option><option>Semi-Automatic</option><option>CVT</option></select></div>
<div class="form-group"><label><i class="fas fa-car-side"></i> Car Type</label><select name="car_type"><option value="">Select Car Type</option><option>Coupe</option><option>Sedan</option><option>SUV</option><option>Convertible</option><option>Hatchback</option><option>Wagon</option><option>Truck</option><option>Van</option></select></div>
<div class="form-group"><label><i class="fas fa-steering-wheel"></i> Drive</label><select name="drive"><option value="">Select Drive</option><option value="LHD">LHD (Left-Hand Drive)</option><option value="RHD">RHD (Right-Hand Drive)</option></select></div>
<div class="form-group"><label><i class="fas fa-road"></i> Drive Train</label><select name="drive_train"><option value="">Select Drive Train</option><option value="AWD">AWD</option><option value="FWD">FWD</option><option value="RWD">RWD</option><option value="4WD">4WD</option></select></div>
<div class="form-group"><label><i class="fas fa-gas-pump"></i> Fuel Type</label><select name="fuel_type"><option value="">Select Fuel Type</option><option>Petrol</option><option>Diesel</option><option>Electric</option><option>Hybrid</option><option>Plugin-Hybrid</option></select></div>
<div class="form-group"><label><i class="fas fa-clipboard-check"></i> Condition</label><select name="condition"><option value="">Select Condition</option><option>Brand New</option><option>Like New</option><option>Excellent</option><option>Very Good</option><option>Good</option><option>Fair</option></select></div>
<div class="form-group"><label><i class="fas fa-barcode"></i> VIN</label><input type="text" name="vin" placeholder="e.g., 19UNC1B01JY000027"></div>
<div class="form-group"><label><i class="fas fa-palette"></i> Color</label><input type="text" name="color" placeholder="e.g., Silver"></div>
<div class="form-group"><label><i class="fas fa-palette"></i> Interior Color</label><input type="text" name="interior_color" placeholder="e.g., Grey"></div>
<div class="form-group"><label><i class="fas fa-door-open"></i> Doors</label><select name="doors"><option value="">Select Doors</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option></select></div>
<div class="form-group"><label><i class="fas fa-users"></i> Seats</label><select name="seats"><option value="">Select Seats</option><option value="2">2</option><option value="4">4</option><option value="5">5</option><option value="7">7</option><option value="8">8</option></select></div>
<div class="form-group full-width"><label><i class="fas fa-check-circle"></i> Features (comma separated)</label><input type="text" name="features" placeholder="e.g., Leather seats, Sunroof, Navigation, Backup camera"></div>
</div>
</div>
<!-- PROPERTY -->
<div id="realestateFields" style="display:none; margin-top:24px;">
<h3 style="margin-bottom:16px;"><i class="fas fa-home"></i> Property Details</h3>
<div class="automobile-fields-grid">
<div class="form-group"><label>Listing Type</label><select name="listing_type_purpose" id="listing_type_purpose"><option value="sale">For Sale</option><option value="rent">For Rent</option></select></div>
<div class="form-group"><label>Bedrooms</label><input type="number" name="bedrooms" placeholder="e.g., 3"></div>
<div class="form-group"><label>Bathrooms</label><input type="number" name="bathrooms" placeholder="e.g., 2"></div>
<div class="form-group"><label>Area (sq ft)</label><input type="text" name="area" placeholder="e.g., 2500"></div>
<div class="form-group"><label>Property Type</label><select name="property_type"><option value="">Select Type</option><option>Villa</option><option>Apartment</option><option>Land</option><option>House</option><option>Condo</option><option>Townhouse</option></select></div>
<div class="form-group"><label><i class="fas fa-clipboard-check"></i> Inspection Fee (₦) <span style="font-weight:400;color:#888;">(optional)</span></label><input type="number" name="inspection_fee" min="0" step="0.01" placeholder="Leave blank for free inspections"></div>
</div>
<div style="margin-top:20px;">
<label style="display:block;font-weight:600;margin-bottom:8px;"><i class="fas fa-street-view"></i> Virtual Tour <span style="font-weight:400;color:#888;">(optional)</span></label>
<div style="display:flex;gap:20px;margin-bottom:12px;">
<label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;"><input type="radio" name="virtual_tour_type" value="link" id="vtTypeLink" checked> Paste a link (YouTube, Vimeo, Matterport)</label>
<label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;"><input type="radio" name="virtual_tour_type" value="video" id="vtTypeVideo"> Upload a video file</label>
</div>
<div id="vtLinkField" class="form-group"><input type="url" name="virtual_tour_url" id="vtUrlInput" placeholder="https://youtube.com/watch?v=..."></div>
<div id="vtVideoField" class="form-group" style="display:none;"><input type="file" name="virtual_tour_video" id="vtVideoInput" accept="video/mp4,video/quicktime,video/webm"><span style="display:block;font-size:12px;color:#888;margin-top:4px;">MP4, MOV, or WebM — up to 150MB.</span></div>
</div>
</div>
<!-- SOLAR (HARDWARE PARTITIONING) -->
<div id="solarFields" style="display:none; margin-top:24px;">
<h3 style="margin-bottom:16px;"><i class="fas fa-sun"></i> Solar Details</h3>
<div class="automobile-fields-grid">
<div class="form-group"><label><i class="fas fa-microchip"></i> Hardware Type *</label>
<select name="hardware_type" id="hardwareType" required>
<option value="solar_panel">Solar Panel</option>
<option value="inverter">Inverter</option>
<option value="battery">Battery</option>
<option value="power_station">Power Station</option>
</select>
</div>
<div class="form-group"><label>System Type</label>
<select name="solar_type"><option value="">Select Type</option><option value="Residential">Residential</option><option value="Commercial">Commercial</option><option value="Industrial">Industrial</option></select>
</div>
<div class="form-group" id="panelWattsGroup"><label>Panel Capacity (W)</label><input type="number" name="panel_watts" id="panelWatts" min="1" step="1" placeholder="e.g., 550"></div>
<div class="form-group" id="inverterKvaGroup" style="display:none;"><label>Inverter Rating (kW/kVA)</label><input type="number" name="inverter_kva" id="inverterKva" min="0.1" step="0.1" placeholder="e.g., 5"></div>
<div class="form-group" id="batteryKwhGroup" style="display:none;"><label>Battery Capacity (kWh)</label><input type="number" name="battery_kwh" id="batteryKwh" min="0.1" step="0.1" placeholder="e.g., 10"></div>
</div>
</div>
<div class="checkbox-group" style="margin-top:24px;"><label class="checkbox-label"><input type="checkbox" name="featured" value="1"><span>Feature this listing for premium visibility</span></label></div>
<div class="form-actions"><button type="button" class="btn-cancel" onclick="window.location.href='/agent/listings.php'">Cancel</button><button type="submit" class="btn-submit" id="submitBtn">Publish Listing</button></div>
</div>
</div>
</form>
</div>
<script>
const divisionToType = { automobile: 'car', realestate: 'property', solar: 'solar', marketplace: 'marketplace' };
// Show/hide the correct capacity inputs for the chosen hardware type.
function syncSolarFields() {
const t = document.getElementById('hardwareType')?.value || 'solar_panel';
const show = (id, on) => { const elx = document.getElementById(id); if (elx) elx.style.display = on ? '' : 'none'; };
show('panelWattsGroup',  t === 'solar_panel');
show('inverterKvaGroup', t === 'inverter' || t === 'power_station');
show('batteryKwhGroup',  t === 'battery'  || t === 'power_station');
}
function syncListingType() {
const d = document.getElementById('division')?.value || '';
const listingType = divisionToType[d] || '';
document.getElementById('listing_type').value = listingType;
document.getElementById('automobileFields').style.display = d === 'automobile' ? 'block' : 'none';
document.getElementById('realestateFields').style.display = d === 'realestate' ? 'block' : 'none';
document.getElementById('solarFields').style.display = d === 'solar' ? 'block' : 'none';
if (d === 'solar') syncSolarFields();
}
document.addEventListener('DOMContentLoaded', function() {
syncListingType();
const divisionSelect = document.getElementById('division');
if (divisionSelect) divisionSelect.addEventListener('change', syncListingType);
const hw = document.getElementById('hardwareType');
if (hw) hw.addEventListener('change', syncSolarFields);
const vtTypeLink = document.getElementById('vtTypeLink');
const vtTypeVideo = document.getElementById('vtTypeVideo');
const vtLinkField = document.getElementById('vtLinkField');
const vtVideoField = document.getElementById('vtVideoField');
const vtUrlInput = document.getElementById('vtUrlInput');
const vtVideoInput = document.getElementById('vtVideoInput');
function syncVirtualTourMode() {
if (!vtTypeLink || !vtTypeVideo) return;
const isLink = vtTypeLink.checked;
vtLinkField.style.display = isLink ? '' : 'none';
vtVideoField.style.display = isLink ? 'none' : '';
if (isLink && vtVideoInput) vtVideoInput.value = '';
if (!isLink && vtUrlInput) vtUrlInput.value = '';
}
if (vtTypeLink && vtTypeVideo) { vtTypeLink.addEventListener('change', syncVirtualTourMode); vtTypeVideo.addEventListener('change', syncVirtualTourMode); syncVirtualTourMode(); }
});
document.addEventListener('DOMContentLoaded', function() {
const form = document.getElementById('listingForm');
const submitBtn = document.getElementById('submitBtn');
let isSubmitting = false;
if (form && submitBtn) {
form.addEventListener('submit', function(e) {
e.preventDefault();
if (isSubmitting) return;
const division = document.getElementById('division').value;
const title = form.querySelector('input[name="title"]')?.value?.trim() || '';
const price = form.querySelector('input[name="price"]')?.value?.trim() || '';
const description = form.querySelector('textarea[name="description"]')?.value?.trim() || '';
if (!division) { showSuccessBanner('Please select a division.', true); return; }
if (!title) { showSuccessBanner('Please enter a listing title.', true); return; }
if (!price || parseFloat(price) <= 0) { showSuccessBanner('Please enter a valid price greater than zero.', true); return; }
if (!description) { showSuccessBanner('Please enter a description.', true); return; }
syncListingType();
const listingType = document.getElementById('listing_type')?.value || '';
if (!listingType) { showSuccessBanner('Please select a valid division.', true); return; }
isSubmitting = true;
submitBtn.disabled = true;
submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing...';
form.submit();
});
}
});
const imageUpload = document.getElementById('imageUpload');
const previewGrid = document.getElementById('imagePreviewGrid');
let selectedFiles = [];
const filePreviewUrls = new Map();
function getPreviewUrl(file) { let url = filePreviewUrls.get(file); if (!url) { url = URL.createObjectURL(file); filePreviewUrls.set(file, url); } return url; }
window.__kinasPreviewImgError = function(imgEl, index) {
const file = selectedFiles[index]; if (!file) return;
const oldUrl = filePreviewUrls.get(file); if (oldUrl) { URL.revokeObjectURL(oldUrl); filePreviewUrls.delete(file); }
const freshUrl = getPreviewUrl(file);
if (imgEl.dataset.kinasRetried === '1') return;
imgEl.dataset.kinasRetried = '1';
imgEl.onerror = function() { window.__kinasPreviewImgError(imgEl, index); };
imgEl.src = freshUrl;
};
function syncInputFiles() { const dt = new DataTransfer(); selectedFiles.forEach(f => dt.items.add(f)); if (imageUpload) imageUpload.files = dt.files; }
function updatePreview() {
if (!previewGrid) return;
previewGrid.innerHTML = '';
const currentFiles = new Set(selectedFiles);
for (const [file, url] of filePreviewUrls) { if (!currentFiles.has(file)) { URL.revokeObjectURL(url); filePreviewUrls.delete(file); } }
selectedFiles.forEach((file, index) => {
const div = document.createElement('div'); div.className = 'preview-item';
div.innerHTML = `<img src="${getPreviewUrl(file)}" onerror="window.__kinasPreviewImgError && window.__kinasPreviewImgError(this, ${index})"><button type="button" class="preview-remove" onclick="removeImage(${index})">&times;</button>`;
previewGrid.appendChild(div);
});
}
function removeImage(index) { selectedFiles.splice(index, 1); updatePreview(); syncInputFiles(); }
if (imageUpload) {
imageUpload.addEventListener('kinas:images-ready', function(e) {
const newFiles = e.detail && Array.isArray(e.detail.files) ? e.detail.files : Array.from(imageUpload.files || []);
selectedFiles = [...selectedFiles, ...newFiles];
syncInputFiles(); updatePreview();
});
}
</script>
</main>
</div>
<?php $__imgUploadJsV = @filemtime(__DIR__ . '/../assets/js/image-upload.js') ?: time(); ?>
<script src="/assets/js/image-upload.js?v=<?= $__imgUploadJsV ?>"></script>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
